<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

class SecurityAuditService
{
    public const INSTITUTION_NAME = 'PAMANTASAN NG LUNGSOD NG PASIG';
    public const INSTITUTION_SHORT_NAME = 'PLP';
    public const INSTITUTION_URL = 'https://plpasig.edu.ph/';

    /**
     * @var list<string>
     */
    public const FORBIDDEN_VAULT_COLUMNS = [
        'student_id',
        'user_id',
        'clearance_id',
        'ip_address',
        'session_id',
        'user_agent',
    ];

    private IntegrityValidatorService $validator;

    public function __construct(?IntegrityValidatorService $validator = null)
    {
        $this->validator = $validator ?? new IntegrityValidatorService();
    }

    /**
     * Execute the full institutional security audit for Pamantasan ng Lungsod ng Pasig (PLP).
     *
     * Verifies:
     * 1. Cryptographic Anonymity Decoupling (zero student-identifying columns in evaluation_vault)
     * 2. Response Authenticity Guards (< 45s speed-run, sigma^2 = 0 straight-lining, > 5 professor questions cap)
     * 3. Double-Blind Retaliation Mitigation (4-state truth table for TermGradesSubmitted && RegistrarLock)
     * 4. CSV Formula Injection Neutralization (=, +, -, @ prefixes neutralized with leading apostrophe ')
     * 5. Metadata Stripping & Server-Signed HMAC Token Integrity
     *
     * @return array<string, mixed>
     */
    public function runFullInstitutionalSecurityAudit(?PDO $pdo = null): array
    {
        $evaluationService = new DecoupledEvaluationService($pdo, $this->validator);

        $anonymityCheck = $this->auditCryptographicAnonymity($evaluationService);
        $authenticityCheck = $this->auditResponseAuthenticityGuards();
        $doubleBlindCheck = $this->auditDoubleBlindRetaliationMitigation($evaluationService);
        $csvCheck = $this->auditCsvFormulaNeutralization();
        $metadataHmacCheck = $this->auditMetadataStrippingAndHmacSalting();

        $checks = [
            'cryptographic_anonymity_decoupling' => $anonymityCheck,
            'response_authenticity_guards' => $authenticityCheck,
            'double_blind_retaliation_mitigation' => $doubleBlindCheck,
            'csv_formula_injection_neutralization' => $csvCheck,
            'metadata_stripping_and_hmac_integrity' => $metadataHmacCheck,
        ];

        $passedCount = 0;
        foreach ($checks as $check) {
            if (($check['passed'] ?? false) === true) {
                $passedCount++;
            }
        }

        $allPassed = $passedCount === count($checks);

        return [
            'status' => $allPassed ? 'PASS' : 'FAIL',
            'institution' => self::INSTITUTION_NAME,
            'institution_short_name' => self::INSTITUTION_SHORT_NAME,
            'institution_url' => self::INSTITUTION_URL,
            'invariants_verified' => $passedCount,
            'checks' => $checks,
        ];
    }

    /**
     * Verify Cryptographic Anonymity Decoupling on `evaluation_vault`.
     *
     * @return array<string, mixed>
     */
    public function auditCryptographicAnonymity(?DecoupledEvaluationService $evaluationService = null): array
    {
        $service = $evaluationService ?? new DecoupledEvaluationService(null, $this->validator);
        $vaultAudit = $service->verifyVaultAnonymity();

        $vaultColumns = $vaultAudit['vault_columns'];
        $forbiddenFound = array_values(array_intersect($vaultColumns, self::FORBIDDEN_VAULT_COLUMNS));
        $passed = $vaultAudit['decoupled'] === true
            && count($forbiddenFound) === 0
            && count($vaultColumns) > 0;

        return [
            'passed' => $passed,
            'status' => $passed ? 'PASS' : 'FAIL',
            'decoupled' => $vaultAudit['decoupled'],
            'vault_columns' => $vaultColumns,
            'forbidden_columns_checked' => self::FORBIDDEN_VAULT_COLUMNS,
            'forbidden_found' => $forbiddenFound,
        ];
    }

    /**
     * Verify Response Authenticity Guards (< 45s speed-run, sigma^2 = 0 straight-line, > 5 professor questions).
     *
     * @return array<string, mixed>
     */
    public function auditResponseAuthenticityGuards(): array
    {
        // 1. Speed-Run Guard (< 45s rejected, >= 45s accepted)
        $speedRunRejected12s = !$this->validator->validateDwellTime(12, IntegrityValidatorService::DEFAULT_QUESTION_COUNT);
        $speedRunRejected44s = !$this->validator->validateDwellTime(44, IntegrityValidatorService::FIVE_CLUSTER_QUESTION_COUNT);
        $validDwellAccepted45s = $this->validator->validateDwellTime(45, IntegrityValidatorService::FIVE_CLUSTER_QUESTION_COUNT);
        $speedRunGuardPassed = $speedRunRejected12s && $speedRunRejected44s && $validDwellAccepted45s;

        // 2. Straight-Lining Guard (sigma^2 = 0 detected, non-zero variance accepted)
        $uniformRatings = array_fill(0, IntegrityValidatorService::FIVE_CLUSTER_QUESTION_COUNT, 5);
        $uniformVariance = $this->validator->calculateVariance($uniformRatings);
        $straightLineDetected = $this->validator->isStraightLined($uniformRatings) && $uniformVariance === 0.0;

        $variedRatings = [5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5];
        $variedVariance = $this->validator->calculateVariance($variedRatings);
        $variedNotFlagged = !$this->validator->isStraightLined($variedRatings) && $variedVariance > 0.0;
        $straightLineGuardPassed = $straightLineDetected && $variedNotFlagged;

        // 3. Professor Qualitative Questions Cap (> 5 rejected, <= 5 accepted)
        $exceededProfPayload = [
            'questions' => ['Q1', 'Q2', 'Q3', 'Q4', 'Q5', 'Q6'],
        ];
        $exceededResult = $this->validator->validateProfessorQuestions($exceededProfPayload);
        $exceededRejected = $exceededResult['valid'] === false
            && $exceededResult['code'] === 'MAX_PROFESSOR_QUESTIONS_EXCEEDED';

        $validProfPayload = [
            'three_words' => ['Dedicated', 'Structured', 'Supportive'],
            'room_for_improvements' => ['Pacing on lab exercises', 'More sample problems'],
            'teaching_strengths' => 'Clear explanation of data structures and algorithms.',
            'recommendations' => 'Continue providing practical coding walkthroughs.',
            'additional_observation' => 'Always starts and ends class on time.',
        ];
        $validProfResult = $this->validator->validateProfessorQuestions($validProfPayload);
        $validProfAccepted = $validProfResult['valid'] === true
            && $validProfResult['question_count'] === IntegrityValidatorService::MAX_PROFESSOR_QUESTIONS;

        $profQuestionsGuardPassed = $exceededRejected && $validProfAccepted;

        $passed = $speedRunGuardPassed && $straightLineGuardPassed && $profQuestionsGuardPassed;

        return [
            'passed' => $passed,
            'status' => $passed ? 'PASS' : 'FAIL',
            'speed_run_guard' => [
                'passed' => $speedRunGuardPassed,
                'minimum_dwell_seconds' => IntegrityValidatorService::MIN_DWELL_SECONDS_20_ITEMS,
                'rejected_12s' => $speedRunRejected12s,
                'rejected_44s' => $speedRunRejected44s,
                'accepted_45s' => $validDwellAccepted45s,
            ],
            'straight_line_guard' => [
                'passed' => $straightLineGuardPassed,
                'uniform_variance' => $uniformVariance,
                'straight_line_detected' => $straightLineDetected,
                'varied_variance' => $variedVariance,
                'varied_not_flagged' => $variedNotFlagged,
            ],
            'professor_questions_guard' => [
                'passed' => $profQuestionsGuardPassed,
                'max_questions' => IntegrityValidatorService::MAX_PROFESSOR_QUESTIONS,
                'exceeded_6_rejected' => $exceededRejected,
                'valid_5_accepted' => $validProfAccepted,
            ],
        ];
    }

    /**
     * Verify Double-Blind Retaliation Mitigation across all 4 truth-table states:
     * CanViewEvaluation = (TermGradesSubmitted == TRUE) && (RegistrarLock == TRUE).
     *
     * @return array<string, mixed>
     */
    public function auditDoubleBlindRetaliationMitigation(?DecoupledEvaluationService $evaluationService = null): array
    {
        $service = $evaluationService ?? new DecoupledEvaluationService(null, $this->validator);

        $truthTable = [
            ['term_grades_submitted' => false, 'registrar_lock' => false, 'expected' => false],
            ['term_grades_submitted' => true, 'registrar_lock' => false, 'expected' => false],
            ['term_grades_submitted' => false, 'registrar_lock' => true, 'expected' => false],
            ['term_grades_submitted' => true, 'registrar_lock' => true, 'expected' => true],
        ];

        $evaluatedStates = [];
        $allMatched = true;

        foreach ($truthTable as $row) {
            $actual = $service->canFacultyViewSection($row['term_grades_submitted'], $row['registrar_lock']);
            $matched = $actual === $row['expected'];
            if (!$matched) {
                $allMatched = false;
            }

            $stateKey = sprintf(
                '(%s,%s)',
                $row['term_grades_submitted'] ? 'true' : 'false',
                $row['registrar_lock'] ? 'true' : 'false'
            );

            $evaluatedStates[$stateKey] = [
                'term_grades_submitted' => $row['term_grades_submitted'],
                'registrar_lock' => $row['registrar_lock'],
                'can_view_evaluation' => $actual,
                'expected' => $row['expected'],
                'matched' => $matched,
            ];
        }

        return [
            'passed' => $allMatched,
            'status' => $allMatched ? 'PASS' : 'FAIL',
            'formula' => 'CanViewEvaluation = (TermGradesSubmitted == TRUE) && (RegistrarLock == TRUE)',
            'states_tested' => count($evaluatedStates),
            'truth_table' => $evaluatedStates,
        ];
    }

    /**
     * Verify CSV Formula Injection Neutralization for =, +, -, and @ prefixes.
     *
     * @return array<string, mixed>
     */
    public function auditCsvFormulaNeutralization(): array
    {
        $payloads = [
            '=' => '=HYPERLINK("http://evil.example/leak", "Click")',
            '+' => '+cmd|\' /C calc\'!A0',
            '-' => '-2+3+cmd|\' /C calc\'!A0',
            '@' => '@SUM(1+1)*cmd|\' /C calc\'!A0',
        ];

        $results = [];
        $allNeutralized = true;

        foreach ($payloads as $prefix => $rawCell) {
            $sanitized = $this->validator->sanitizeCsvCell($rawCell);
            $neutralized = str_starts_with($sanitized, "'" . $prefix) && $sanitized === "'" . $rawCell;
            if (!$neutralized) {
                $allNeutralized = false;
            }

            $results[$prefix] = [
                'raw' => $rawCell,
                'sanitized' => $sanitized,
                'neutralized' => $neutralized,
            ];
        }

        $safeSample = 'PAMANTASAN NG LUNGSOD NG PASIG - CCS';
        $safePreserved = $this->validator->sanitizeCsvCell($safeSample) === $safeSample;
        $passed = $allNeutralized && $safePreserved;

        return [
            'passed' => $passed,
            'status' => $passed ? 'PASS' : 'FAIL',
            'prefixes_verified' => array_keys($payloads),
            'safe_cell_preserved' => $safePreserved,
            'samples' => $results,
        ];
    }

    /**
     * Verify identifying metadata stripping and server-signed HMAC-SHA256 start/comment tokens.
     *
     * @return array<string, mixed>
     */
    public function auditMetadataStrippingAndHmacSalting(): array
    {
        $dirtyPayload = [
            'id' => 'vault-uuid-001',
            'course_section_id' => 101,
            'student_id' => 20260001,
            'user_id' => 42,
            'clearance_id' => 99,
            'ip_address' => '192.168.1.10',
            'session_id' => 'sess_abc123',
            'user_agent' => 'Mozilla/5.0',
            'mean_score' => 4.80,
        ];

        $stripped = $this->validator->stripIdentifyingMetadata($dirtyPayload);
        $remainingForbidden = array_values(array_intersect(array_keys($stripped), self::FORBIDDEN_VAULT_COLUMNS));
        $metadataStripped = count($remainingForbidden) === 0
            && isset($stripped['id'], $stripped['course_section_id'], $stripped['mean_score']);

        $commentHash = $this->validator->computeCommentHash('Mahusay magturo si Prof!', 101, 1);
        $validHmacHash = strlen($commentHash) === 64 && ctype_xdigit($commentHash);

        $issuedAt = 1700000000;
        $startToken = $this->validator->issueStartToken(10, 101, 'plp-audit-secret', $issuedAt);
        $under45Rejected = !$this->validator->verifyStartToken($startToken, 10, 101, 'plp-audit-secret', 45, $issuedAt + 30);
        $at45Accepted = $this->validator->verifyStartToken($startToken, 10, 101, 'plp-audit-secret', 45, $issuedAt + 45);

        $passed = $metadataStripped && $validHmacHash && $under45Rejected && $at45Accepted;

        return [
            'passed' => $passed,
            'status' => $passed ? 'PASS' : 'FAIL',
            'metadata_stripped' => $metadataStripped,
            'hmac_sha256_comment_hash_valid' => $validHmacHash,
            'signed_start_token_enforced' => $under45Rejected && $at45Accepted,
        ];
    }
}
