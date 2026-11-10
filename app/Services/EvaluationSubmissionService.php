<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

/**
 * EvaluationSubmissionService
 *
 * Dedicated domain service responsible for executing atomic, integrity-guarded
 * dual-token student evaluation submissions, enforcing FT-02 idempotency,
 * straight-line confirmation challenges, PII narrative scrubbing, resilient
 * NLP sentiment classification, and zero-knowledge vault persistence.
 */
class EvaluationSubmissionService
{
    private PDO $pdo;
    private IntegrityValidatorService $validator;
    private AnalyticsClientService $analytics;
    private string $appSecret;
    private ?EvaluationReportingService $reporting = null;

    public function __construct(
        PDO $pdo,
        ?IntegrityValidatorService $validator = null,
        ?AnalyticsClientService $analytics = null,
        ?string $appSecret = null,
        ?EvaluationReportingService $reporting = null
    ) {
        $this->pdo = $pdo;
        $this->validator = $validator ?? new IntegrityValidatorService();
        $this->analytics = $analytics ?? new AnalyticsClientService();
        $this->appSecret = DecoupledEvaluationService::resolveSigningSecret($appSecret);
        $this->reporting = $reporting;
    }

    public function setReportingService(EvaluationReportingService $reporting): void
    {
        $this->reporting = $reporting;
    }

    /**
     * Execute an atomic, integrity-guarded dual-token evaluation submission.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function submitEvaluation(array $payload): array
    {
        $studentId = (int) ($payload['student_id'] ?? 0);
        $courseSectionId = (int) ($payload['course_section_id'] ?? 0);
        $submissionKey = trim((string) ($payload['submission_key'] ?? ''));
        $ratings = is_array($payload['ratings'] ?? null) ? array_values($payload['ratings']) : [];
        $dwellSeconds = (int) ($payload['dwell_time_seconds'] ?? 0);
        $confirmUniform = filter_var($payload['confirm_uniform'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $rawCommentText = trim((string) ($payload['comment_text'] ?? ''));
        $commentText = $this->validator->scrubPiiFromNarrative($rawCommentText);
        $startToken = isset($payload['start_token']) ? (string) $payload['start_token'] : null;

        if ($studentId <= 0 || $courseSectionId <= 0 || $submissionKey === '') {
            return [
                'accepted' => false,
                'status' => 'REJECTED',
                'code' => 'INVALID_SUBMISSION_PARAMETERS',
                'http_status' => 422,
                'message' => 'student_id, course_section_id, and submission_key are required.',
            ];
        }

        // 1. Authoritative Catalog & Enrollment Resolution
        $section = $this->findSectionById($courseSectionId);
        if (!is_array($section)) {
            return [
                'accepted' => false,
                'status' => 'REJECTED',
                'code' => 'COURSE_SECTION_NOT_FOUND',
                'http_status' => 404,
                'message' => 'The specified course section does not exist in the institutional catalog.',
            ];
        }

        // Authoritatively derive faculty_id and academic_term_id from database catalog
        $facultyId = (int) ($section['faculty_id'] ?? 1);
        $academicTermId = (int) ($section['academic_term_id'] ?? 1);

        // Verify active enrollment for this student in this course section
        $stmtEnr = $this->pdo->prepare('
            SELECT *
            FROM enrollments
            WHERE student_id = :student_id AND course_section_id = :section_id
            LIMIT 1
        ');
        $stmtEnr->execute([':student_id' => $studentId, ':section_id' => $courseSectionId]);
        $enrollment = $stmtEnr->fetch(PDO::FETCH_ASSOC);

        if (!is_array($enrollment) || (isset($enrollment['enrollment_status']) && strtoupper((string) $enrollment['enrollment_status']) === 'DROPPED')) {
            return [
                'accepted' => false,
                'status' => 'REJECTED',
                'code' => 'STUDENT_NOT_ENROLLED',
                'http_status' => 403,
                'message' => 'Student is not actively enrolled in the specified course section.',
            ];
        }

        // 1a. Verify submission_key is not already registered to another student or section
        $stmtKeyCheck = $this->pdo->prepare('
            SELECT id, student_id, course_section_id, status, clearance_token, submission_key
            FROM evaluation_clearances
            WHERE submission_key = :sub_key
            LIMIT 1
        ');
        $stmtKeyCheck->execute([':sub_key' => $submissionKey]);
        $existingKeyRow = $stmtKeyCheck->fetch(PDO::FETCH_ASSOC);

        if (is_array($existingKeyRow)) {
            if ((int) $existingKeyRow['student_id'] !== $studentId || (int) $existingKeyRow['course_section_id'] !== $courseSectionId) {
                return [
                    'accepted' => false,
                    'status' => 'REJECTED',
                    'code' => 'SUBMISSION_KEY_CONFLICT',
                    'http_status' => 409,
                    'message' => 'The provided submission key is already registered to another student or course section.',
                ];
            }
        }

        // 1b. FT-02 Idempotency fast-path check by student and section
        $existingClearance = $this->findClearanceByStudentSection($studentId, $courseSectionId);
        if ($existingClearance !== null && ($existingClearance['status'] ?? '') === 'CLEARED') {
            return [
                'accepted' => true,
                'status' => 'CLEARED',
                'code' => 'IDEMPOTENT_REPLAY',
                'http_status' => 200,
                'idempotent_replay' => true,
                'clearance_token' => (string) $existingClearance['clearance_token'],
                'submission_key' => (string) $existingClearance['submission_key'],
                'message' => 'Submission already processed; returning original signed clearance token.',
            ];
        }

        // 2. Validate 20 (4 clusters x 5) or 25 (5 clusters x 5) Likert ratings in [1..5]
        if (!$this->validator->validateRatingsArray($ratings, IntegrityValidatorService::DEFAULT_QUESTION_COUNT)) {
            return [
                'accepted' => false,
                'status' => 'REJECTED',
                'code' => 'INVALID_LIKERT_RATINGS',
                'http_status' => 422,
                'message' => '5-cluster 5-point Likert ratings (20 or 25 items) with integer values between 1 and 5 are required.',
            ];
        }

        // 2b. Validate structured Professor Qualitative Questions (4 default, max 5 questions)
        $rawProfQuestions = is_array($payload['professor_questions'] ?? null) ? $payload['professor_questions'] : [];
        $profQuestionsValidation = $this->validator->validateProfessorQuestions($rawProfQuestions);
        if (!$profQuestionsValidation['valid']) {
            return [
                'accepted' => false,
                'status' => 'REJECTED',
                'code' => $profQuestionsValidation['code'],
                'http_status' => 422,
                'max_questions' => IntegrityValidatorService::MAX_PROFESSOR_QUESTIONS,
                'message' => 'Professor qualitative questionnaire exceeds the maximum of 5 questions.',
            ];
        }
        $clusterScores = $this->validator->calculateClusterScores($ratings);

        // Window status and schedule boundary check (SOP 1 / Administrator-Controlled Evaluation Period)
        $nowUnix = isset($payload['now_unix']) ? (int) $payload['now_unix'] : time();
        $window = $this->reporting !== null
            ? $this->reporting->getEvaluationWindow($academicTermId, $nowUnix)
            : ['status' => 'OPEN', 'evaluation_open' => true, 'window_state' => 'OPEN', 'straight_line_variance_threshold' => 0.0];

        $windowState = (string) ($window['window_state'] ?? $window['status'] ?? 'OPEN');

        if ($windowState === 'NOT_YET_OPEN') {
            return [
                'accepted' => false,
                'status' => 'REJECTED',
                'code' => 'EVALUATION_WINDOW_NOT_YET_OPEN',
                'window_code' => 'EVALUATION_WINDOW_NOT_YET_OPEN',
                'http_status' => 422,
                'window_status' => 'NOT_YET_OPEN',
                'starts_at' => $window['starts_at'] ?? null,
                'timezone' => $window['timezone'] ?? 'Asia/Manila',
                'academic_term_id' => $academicTermId,
                'message' => 'The student evaluation period has not opened yet for this academic term.',
            ];
        }

        if ($windowState === 'EXPIRED') {
            return [
                'accepted' => false,
                'status' => 'REJECTED',
                'code' => 'EVALUATION_WINDOW_CLOSED',
                'window_code' => 'EVALUATION_WINDOW_EXPIRED',
                'http_status' => 422,
                'window_status' => 'EXPIRED',
                'ends_at' => $window['ends_at'] ?? null,
                'timezone' => $window['timezone'] ?? 'Asia/Manila',
                'academic_term_id' => $academicTermId,
                'message' => 'The student evaluation submission deadline has passed.',
            ];
        }

        if ($window['status'] !== 'OPEN' || !$window['evaluation_open']) {
            return [
                'accepted' => false,
                'status' => 'REJECTED',
                'code' => 'EVALUATION_WINDOW_CLOSED',
                'window_code' => $window['status'] === 'SUSPENDED' ? 'EVALUATION_WINDOW_SUSPENDED' : 'EVALUATION_WINDOW_CLOSED',
                'http_status' => 422,
                'window_status' => $window['status'],
                'academic_term_id' => $academicTermId,
                'message' => 'The student evaluation window is currently closed or suspended for this academic term.',
            ];
        }

        // 3. Mandatory Timing Policy: Zero minimum wait and zero maximum answering duration.
        // Only negative durations or forged/tampered start tokens are rejected.
        if (!$this->validator->validateDwellTime($dwellSeconds, count($ratings))) {
            return [
                'accepted' => false,
                'status' => 'REJECTED',
                'code' => 'INVALID_ELAPSED_DURATION',
                'http_status' => 422,
                'dwell_time_seconds' => $dwellSeconds,
                'message' => 'Elapsed answering duration cannot be negative.',
            ];
        }

        if ($startToken !== null && $startToken !== '') {
            $tokenValid = $this->validator->verifyStartToken(
                $startToken,
                $studentId,
                $courseSectionId,
                $this->appSecret,
                0,
                $nowUnix
            );
            if (!$tokenValid) {
                return [
                    'accepted' => false,
                    'status' => 'REJECTED',
                    'code' => 'INVALID_START_TOKEN',
                    'http_status' => 422,
                    'message' => 'Server-signed evaluation session start token verification failed.',
                ];
            }
        }

        // 4. Straight-Line / Zero-Variance Guard (\sigma^2 <= straight_line_variance_threshold)
        $varianceThreshold = (float) ($window['straight_line_variance_threshold'] ?? 0.0);
        $meanScore = $this->validator->calculateMean($ratings);
        $ratingVariance = $this->validator->calculateVariance($ratings);
        $isStraightLined = ($ratingVariance <= $varianceThreshold);

        if ($isStraightLined && !$confirmUniform) {
            return [
                'accepted' => false,
                'status' => 'CONFIRMATION_REQUIRED',
                'code' => 'ZERO_VARIANCE_CONFIRMATION_REQUIRED',
                'http_status' => 422,
                'mean_score' => $meanScore,
                'rating_variance' => $ratingVariance,
                'is_straight_lined' => true,
                'message' => 'Uniform Likert ratings detected (variance = 0.0). Please confirm if this uniform rating is intentional.',
            ];
        }

        // 5. NLP Sentiment & Pedagogical Classification (with bounded circuit-breaker fallback)
        $nlp = $this->analytics->analyzeComment($commentText);

        // 6. Execute Atomic Dual-Token Transaction
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $lockClause = $driver === 'mysql' ? ' FOR UPDATE' : '';

        $this->pdo->beginTransaction();
        try {
            $lockStmt = $this->pdo->prepare(
                "SELECT * FROM evaluation_clearances WHERE student_id = :student_id AND course_section_id = :section_id{$lockClause}"
            );
            $lockStmt->execute([
                ':student_id' => $studentId,
                ':section_id' => $courseSectionId,
            ]);
            $lockedRow = $lockStmt->fetch(PDO::FETCH_ASSOC);

            if (is_array($lockedRow) && ($lockedRow['status'] ?? '') === 'CLEARED') {
                $this->pdo->commit();
                return [
                    'accepted' => true,
                    'status' => 'CLEARED',
                    'code' => 'IDEMPOTENT_REPLAY',
                    'http_status' => 200,
                    'idempotent_replay' => true,
                    'clearance_token' => (string) $lockedRow['clearance_token'],
                    'submission_key' => (string) $lockedRow['submission_key'],
                    'message' => 'Submission already processed; returning original signed clearance token.',
                ];
            }

            $nowTimestamp = date('Y-m-d H:i:s');
            $recordedDate = date('Y-m-d');
            $clearanceToken = $this->issueClearanceToken($studentId, $courseSectionId, $academicTermId, $submissionKey);

            if (is_array($lockedRow)) {
                $updateClearance = $this->pdo->prepare("
                    UPDATE evaluation_clearances
                    SET submission_key = :sub_key,
                        status = 'CLEARED',
                        clearance_token = :token,
                        cleared_at = :cleared_at,
                        updated_at = :updated_at
                    WHERE id = :id
                ");
                $updateClearance->execute([
                    ':sub_key' => $submissionKey,
                    ':token' => $clearanceToken,
                    ':cleared_at' => $nowTimestamp,
                    ':updated_at' => $nowTimestamp,
                    ':id' => $lockedRow['id'],
                ]);
            } else {
                $insertClearance = $this->pdo->prepare("
                    INSERT INTO evaluation_clearances (
                        student_id, course_section_id, academic_term_id,
                        submission_key, status, clearance_token, cleared_at, created_at, updated_at
                    ) VALUES (
                        :student_id, :course_section_id, :academic_term_id,
                        :submission_key, 'CLEARED', :clearance_token, :cleared_at, :created_at, :updated_at
                    )
                ");
                $insertClearance->execute([
                    ':student_id' => $studentId,
                    ':course_section_id' => $courseSectionId,
                    ':academic_term_id' => $academicTermId,
                    ':submission_key' => $submissionKey,
                    ':clearance_token' => $clearanceToken,
                    ':cleared_at' => $nowTimestamp,
                    ':created_at' => $nowTimestamp,
                    ':updated_at' => $nowTimestamp,
                ]);
            }

            // Prepare stripped, salted payload for evaluation_vault (ZERO student identity fields)
            $profQuestions = $payload['professor_questions'] ?? null;
            $profQuestionsJson = null;
            if (is_array($profQuestions)) {
                $cleanWords = [];
                if (isset($profQuestions['three_words']) && is_array($profQuestions['three_words'])) {
                    foreach ($profQuestions['three_words'] as $w) {
                        $wStr = trim((string) $w);
                        if ($wStr !== '') {
                            $cleanWords[] = $this->validator->sanitizeCsvFormula($wStr);
                        }
                    }
                }
                $cleanTopics = [];
                if (isset($profQuestions['room_for_improvements']) && is_array($profQuestions['room_for_improvements'])) {
                    foreach ($profQuestions['room_for_improvements'] as $t) {
                        $tStr = trim((string) (is_array($t) ? ($t['topic'] ?? '') : $t));
                        if ($tStr !== '') {
                            $cleanTopics[] = $this->validator->sanitizeCsvFormula($tStr);
                        }
                    }
                }
                $cleanProfQuestions = [
                    'three_words' => array_slice($cleanWords, 0, 3),
                    'room_for_improvements' => array_map(
                        fn (string $t): string => $this->validator->scrubPiiFromNarrative($t),
                        $cleanTopics
                    ),
                    'teaching_strengths' => $this->validator->sanitizeCsvFormula(
                        $this->validator->scrubPiiFromNarrative((string) ($profQuestions['teaching_strengths'] ?? ''))
                    ),
                    'recommendations' => $this->validator->sanitizeCsvFormula(
                        $this->validator->scrubPiiFromNarrative((string) ($profQuestions['recommendations'] ?? ''))
                    ),
                    'additional_observation' => isset($profQuestions['additional_observation'])
                        ? $this->validator->sanitizeCsvFormula(
                            $this->validator->scrubPiiFromNarrative((string) $profQuestions['additional_observation'])
                        )
                        : null,
                ];
                $profQuestionsJson = json_encode($cleanProfQuestions);
            }

            $vaultPayload = $this->validator->stripIdentifyingMetadata([
                'id' => $this->generateUuidV4(),
                'course_section_id' => $courseSectionId,
                'faculty_id' => $facultyId,
                'academic_term_id' => $academicTermId,
                'ratings_json' => (string) json_encode(array_map('intval', $ratings)),
                'mean_score' => $meanScore,
                'rating_variance' => $ratingVariance,
                'is_straight_lined' => $isStraightLined ? 1 : 0,
                'dwell_time_seconds' => $dwellSeconds,
                'comment_text' => $commentText,
                'comment_hash' => $this->validator->computeCommentHash($commentText, $courseSectionId, $academicTermId, $this->appSecret),
                'sentiment_polarity' => $nlp['polarity'],
                'sentiment_valence' => $nlp['valence'],
                'pedagogical_tag' => $nlp['pedagogical_tag'],
                'recorded_date' => $recordedDate,
                'professor_questions_json' => $profQuestionsJson,
            ]);

            $insertVault = $this->pdo->prepare("
                INSERT INTO evaluation_vault (
                    id, course_section_id, faculty_id, academic_term_id,
                    ratings_json, mean_score, rating_variance, is_straight_lined,
                    dwell_time_seconds, comment_text, comment_hash,
                    sentiment_polarity, sentiment_valence, pedagogical_tag, recorded_date,
                    professor_questions_json
                ) VALUES (
                    :id, :course_section_id, :faculty_id, :academic_term_id,
                    :ratings_json, :mean_score, :rating_variance, :is_straight_lined,
                    :dwell_time_seconds, :comment_text, :comment_hash,
                    :sentiment_polarity, :sentiment_valence, :pedagogical_tag, :recorded_date,
                    :professor_questions_json
                )
            ");
            $insertVault->execute([
                ':id' => $vaultPayload['id'],
                ':course_section_id' => $vaultPayload['course_section_id'],
                ':faculty_id' => $vaultPayload['faculty_id'],
                ':academic_term_id' => $vaultPayload['academic_term_id'],
                ':ratings_json' => $vaultPayload['ratings_json'],
                ':mean_score' => $vaultPayload['mean_score'],
                ':rating_variance' => $vaultPayload['rating_variance'],
                ':is_straight_lined' => $vaultPayload['is_straight_lined'],
                ':dwell_time_seconds' => $vaultPayload['dwell_time_seconds'],
                ':comment_text' => $vaultPayload['comment_text'],
                ':comment_hash' => $vaultPayload['comment_hash'],
                ':sentiment_polarity' => $vaultPayload['sentiment_polarity'],
                ':sentiment_valence' => $vaultPayload['sentiment_valence'],
                ':pedagogical_tag' => $vaultPayload['pedagogical_tag'],
                ':recorded_date' => $vaultPayload['recorded_date'],
                ':professor_questions_json' => $vaultPayload['professor_questions_json'],
            ]);

            $this->pdo->commit();

            return [
                'accepted' => true,
                'status' => 'CLEARED',
                'code' => 'EVALUATION_SUBMITTED',
                'http_status' => 201,
                'idempotent_replay' => false,
                'clearance_token' => $clearanceToken,
                'submission_key' => $submissionKey,
                'vault_id' => $vaultPayload['id'],
                'mean_score' => $meanScore,
                'rating_variance' => $ratingVariance,
                'is_straight_lined' => $isStraightLined,
                'cluster_scores' => $clusterScores,
                'professor_questions' => $profQuestionsValidation['normalized'],
                'nlp_engine' => AnalyticsClientService::NLP_ENGINE_NAME,
                'sentiment' => $nlp,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // Handle concurrent race condition where another request committed the same clearance
            $raceExisting = $this->findClearanceByStudentSection($studentId, $courseSectionId);
            if ($raceExisting !== null && ($raceExisting['status'] ?? '') === 'CLEARED') {
                return [
                    'accepted' => true,
                    'status' => 'CLEARED',
                    'code' => 'IDEMPOTENT_REPLAY',
                    'http_status' => 200,
                    'idempotent_replay' => true,
                    'clearance_token' => (string) $raceExisting['clearance_token'],
                    'submission_key' => (string) $raceExisting['submission_key'],
                    'message' => 'Concurrent duplicate resolved idempotently.',
                ];
            }

            throw $e;
        }
    }

    /**
     * Issue a signed HMAC-SHA256 clearance token.
     */
    public function issueClearanceToken(
        int $studentId,
        int $courseSectionId,
        int $academicTermId,
        string $submissionKey
    ): string {
        $mac = hash_hmac(
            'sha256',
            "{$studentId}:{$courseSectionId}:{$academicTermId}:{$submissionKey}",
            $this->appSecret
        );

        return 'CLR-' . strtoupper(substr($mac, 0, 24));
    }

    /**
     * Find existing evaluation clearance by student and course section.
     *
     * @return array<string, mixed>|null
     */
    public function findClearanceByStudentSection(int $studentId, int $courseSectionId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT *
            FROM evaluation_clearances
            WHERE student_id = :student_id AND course_section_id = :section_id
            LIMIT 1
        ');
        $stmt->execute([
            ':student_id' => $studentId,
            ':section_id' => $courseSectionId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Find course section by ID.
     *
     * @return array<string, mixed>|null
     */
    public function findSectionById(int $courseSectionId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT cs.*, u.name AS instructor_name, c.code AS course_code, c.title AS course_title
            FROM course_sections cs
            LEFT JOIN users u ON u.id = cs.faculty_id
            LEFT JOIN courses c ON c.id = cs.course_id
            WHERE cs.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $courseSectionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Verify zero-knowledge anonymity invariant on the vault.
     *
     * @return array{decoupled: bool, vault_columns: list<string>, forbidden_found: list<string>}
     */
    public function verifyVaultAnonymity(): array
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $columns = [];

        if ($driver === 'sqlite') {
            $stmt = $this->pdo->query('PRAGMA table_info(evaluation_vault)');
            $info = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($info as $col) {
                $columns[] = (string) $col['name'];
            }
        } else {
            $stmt = $this->pdo->query('SHOW COLUMNS FROM evaluation_vault');
            $info = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($info as $col) {
                $columns[] = (string) $col['Field'];
            }
        }

        $forbidden = ['student_id', 'user_id', 'clearance_id', 'ip_address', 'session_id', 'user_agent'];
        $found = array_values(array_intersect($columns, $forbidden));

        return [
            'decoupled' => count($found) === 0,
            'vault_columns' => $columns,
            'forbidden_found' => $found,
        ];
    }

    private function generateUuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
