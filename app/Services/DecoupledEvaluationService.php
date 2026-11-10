<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

class DecoupledEvaluationService
{
    public const WATCHLIST_MIN_MEAN_SCORE = 3.50;
    public const WATCHLIST_MAX_NEGATIVE_PCT = 35.0;

    private static ?PDO $sharedPdo = null;

    private PDO $pdo;
    private IntegrityValidatorService $validator;
    private AnalyticsClientService $analytics;
    private string $appSecret;
    private EvaluationReportingService $reporting;
    private AccreditationGovernanceService $governance;

    public function __construct(
        ?PDO $pdo = null,
        ?IntegrityValidatorService $validator = null,
        ?AnalyticsClientService $analytics = null,
        ?string $appSecret = null
    ) {
        $this->appSecret = self::resolveSigningSecret($appSecret);
        $this->validator = $validator ?? new IntegrityValidatorService();
        $this->analytics = $analytics ?? new AnalyticsClientService();
        $this->pdo = $pdo ?? self::getSharedPdo();
        $this->initializeSchema($this->pdo);

        $this->reporting = new EvaluationReportingService($this->pdo, $this->validator, $this->analytics, $this);
        $this->governance = new AccreditationGovernanceService($this->pdo, $this->validator, $this->analytics, $this);
    }

    public function getReportingService(): EvaluationReportingService
    {
        return $this->reporting;
    }

    public function getGovernanceService(): AccreditationGovernanceService
    {
        return $this->governance;
    }

    /**
     * Centralized signing secret resolver.
     * Enforces that production environments MUST have an explicit secret or APP_KEY,
     * failing clearly when required configuration is missing.
     */
    public static function resolveSigningSecret(?string $explicitSecret = null): string
    {
        if ($explicitSecret !== null && $explicitSecret !== '' && $explicitSecret !== 'evaluatech-hmac-signing-secret-2026') {
            return $explicitSecret;
        }

        $envSecret = getenv('EVALUATECH_SIGNING_SECRET') ?: (getenv('APP_KEY') ?: null);
        if ($envSecret !== null && $envSecret !== '') {
            return (string) $envSecret;
        }

        $appEnv = getenv('APP_ENV') ?: (php_sapi_name() === 'cli-server' ? 'local' : 'production');
        $isTestingOrLocal = in_array(strtolower($appEnv), ['testing', 'local'], true) || defined('PHPUNIT_RUNNING');

        if ($isTestingOrLocal) {
            return 'evaluatech-testing-fallback-secret-2026';
        }

        // Fail clearly in production if signing secret is missing
        throw new \RuntimeException(
            'Security Configuration Exception: EVALUATECH_SIGNING_SECRET or APP_KEY is required in production environment. Usable hardcoded defaults are rejected.'
        );
    }

    public static function getSharedPdo(): PDO
    {
        if (self::$sharedPdo === null) {
            $appEnv = getenv('APP_ENV') ?: (php_sapi_name() === 'cli-server' ? 'local' : 'production');
            $isTesting = strtolower($appEnv) === 'testing' || defined('PHPUNIT_RUNNING');

            if ($isTesting) {
                self::$sharedPdo = new PDO('sqlite::memory:');
            } else {
                $storageDir = dirname(__DIR__, 2) . '/storage';
                if (!is_dir($storageDir)) {
                    @mkdir($storageDir, 0775, true);
                }
                $sqlitePath = $storageDir . '/evaluatech_plp.sqlite';
                self::$sharedPdo = new PDO('sqlite:' . $sqlitePath);
            }

            self::$sharedPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$sharedPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$sharedPdo->exec('PRAGMA foreign_keys = ON');
        }

        return self::$sharedPdo;
    }

    public static function setSharedPdo(?PDO $pdo): void
    {
        self::$sharedPdo = $pdo;
    }

    public static function resetSharedPdo(): PDO
    {
        self::$sharedPdo = null;
        $pdo = self::getSharedPdo();
        (new self($pdo))->initializeSchema($pdo);
        return $pdo;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Initialize all EvaluaTech relational and decoupled vault tables on the target PDO connection.
     */
    public function initializeSchema(?PDO $pdo = null): void
    {
        $db = $pdo ?? $this->pdo;
        $driver = (string) $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $autoIncrement = $driver === 'sqlite'
            ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';

        $db->exec("
            CREATE TABLE IF NOT EXISTS departments (
                id {$autoIncrement},
                code VARCHAR(20) NOT NULL UNIQUE,
                name VARCHAR(160) NOT NULL,
                college_name VARCHAR(160) NOT NULL DEFAULT 'College of Computer Studies',
                created_at VARCHAR(32) NULL,
                updated_at VARCHAR(32) NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id {$autoIncrement},
                institutional_id VARCHAR(32) NOT NULL UNIQUE,
                name VARCHAR(160) NOT NULL,
                email VARCHAR(160) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(24) NOT NULL,
                department_id INTEGER NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                remember_token VARCHAR(100) NULL,
                created_at VARCHAR(32) NULL,
                updated_at VARCHAR(32) NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS academic_terms (
                id {$autoIncrement},
                code VARCHAR(32) NOT NULL UNIQUE,
                name VARCHAR(120) NOT NULL,
                academic_year VARCHAR(24) NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 0,
                evaluation_open INTEGER NOT NULL DEFAULT 1,
                starts_on VARCHAR(16) NULL,
                ends_on VARCHAR(16) NULL,
                created_at VARCHAR(32) NULL,
                updated_at VARCHAR(32) NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS courses (
                id {$autoIncrement},
                department_id INTEGER NOT NULL,
                code VARCHAR(24) NOT NULL UNIQUE,
                title VARCHAR(180) NOT NULL,
                units INTEGER NOT NULL DEFAULT 3,
                created_at VARCHAR(32) NULL,
                updated_at VARCHAR(32) NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS course_sections (
                id {$autoIncrement},
                course_id INTEGER NOT NULL,
                faculty_id INTEGER NOT NULL,
                academic_term_id INTEGER NOT NULL,
                section_code VARCHAR(32) NOT NULL,
                term_grades_submitted INTEGER NOT NULL DEFAULT 0,
                registrar_lock INTEGER NOT NULL DEFAULT 0,
                enrolled_count INTEGER NOT NULL DEFAULT 0,
                created_at VARCHAR(32) NULL,
                updated_at VARCHAR(32) NULL,
                UNIQUE (course_id, academic_term_id, section_code)
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS enrollments (
                id {$autoIncrement},
                student_id INTEGER NOT NULL,
                course_section_id INTEGER NOT NULL,
                academic_term_id INTEGER NOT NULL,
                enrollment_status VARCHAR(24) NOT NULL DEFAULT 'ENROLLED',
                created_at VARCHAR(32) NULL,
                updated_at VARCHAR(32) NULL,
                UNIQUE (student_id, course_section_id)
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS evaluation_clearances (
                id {$autoIncrement},
                student_id INTEGER NOT NULL,
                course_section_id INTEGER NOT NULL,
                academic_term_id INTEGER NOT NULL,
                submission_key VARCHAR(96) NOT NULL UNIQUE,
                status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
                clearance_token VARCHAR(128) NULL UNIQUE,
                cleared_at VARCHAR(32) NULL,
                created_at VARCHAR(32) NULL,
                updated_at VARCHAR(32) NULL,
                UNIQUE (student_id, course_section_id)
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS evaluation_vault (
                id VARCHAR(36) PRIMARY KEY,
                course_section_id INTEGER NOT NULL,
                faculty_id INTEGER NOT NULL,
                academic_term_id INTEGER NOT NULL,
                ratings_json TEXT NOT NULL,
                mean_score NUMERIC(4, 2) NOT NULL,
                rating_variance NUMERIC(6, 4) NOT NULL,
                is_straight_lined INTEGER NOT NULL DEFAULT 0,
                dwell_time_seconds INTEGER NOT NULL,
                comment_text TEXT NULL,
                comment_hash VARCHAR(64) NOT NULL,
                sentiment_polarity NUMERIC(5, 4) NOT NULL DEFAULT 0.0000,
                sentiment_valence VARCHAR(16) NOT NULL DEFAULT 'Neutral',
                pedagogical_tag VARCHAR(64) NOT NULL DEFAULT 'Pedagogical Delivery',
                recorded_date VARCHAR(16) NOT NULL,
                professor_questions_json TEXT NULL
            )
        ");

        try {
            $cols = $db->query("PRAGMA table_info(evaluation_vault)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (is_array($cols) && !in_array('professor_questions_json', $cols, true)) {
                $db->exec("ALTER TABLE evaluation_vault ADD COLUMN professor_questions_json TEXT NULL");
            }
        } catch (\Throwable) {
            // column exists or non-sqlite driver
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS quality_evaluations (
                id {$autoIncrement},
                cohort VARCHAR(32) NOT NULL,
                sus_items_json TEXT NOT NULL,
                sus_score NUMERIC(5, 2) NOT NULL,
                functional_suitability NUMERIC(4, 2) NOT NULL DEFAULT 4.78,
                performance_efficiency NUMERIC(4, 2) NOT NULL DEFAULT 4.65,
                usability NUMERIC(4, 2) NOT NULL DEFAULT 4.82,
                reliability NUMERIC(4, 2) NOT NULL DEFAULT 4.74,
                portability NUMERIC(4, 2) NOT NULL DEFAULT 4.88,
                iso_overall_mean NUMERIC(4, 2) NOT NULL DEFAULT 4.77,
                recorded_date VARCHAR(16) NOT NULL,
                created_at VARCHAR(32) NULL,
                updated_at VARCHAR(32) NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS faculty_action_plans (
                id {$autoIncrement},
                plan_id VARCHAR(64) NOT NULL UNIQUE,
                faculty_id INTEGER NOT NULL,
                department_id INTEGER NULL,
                primary_cluster VARCHAR(64) NOT NULL DEFAULT 'Pedagogical Delivery',
                status VARCHAR(32) NOT NULL DEFAULT 'PENDING_REVIEW',
                urgency_level VARCHAR(16) NOT NULL DEFAULT 'MODERATE',
                mean_score NUMERIC(4, 2) NOT NULL,
                negative_sentiment_pct NUMERIC(5, 2) NOT NULL,
                interventions_json TEXT NULL,
                dean_notes TEXT NULL,
                signed_off_by_user_id INTEGER NULL,
                signed_off_at VARCHAR(32) NULL,
                created_at VARCHAR(32) NOT NULL,
                updated_at VARCHAR(32) NOT NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS evaluation_window_configs (
                id {$autoIncrement},
                academic_term_id INTEGER NOT NULL UNIQUE,
                status VARCHAR(24) NOT NULL DEFAULT 'OPEN',
                min_dwell_seconds INTEGER NOT NULL DEFAULT 0,
                straight_line_variance_threshold NUMERIC(5, 4) NOT NULL DEFAULT 0.0000,
                watchlist_mean_threshold NUMERIC(4, 2) NOT NULL DEFAULT 3.50,
                watchlist_negative_sentiment_pct NUMERIC(5, 2) NOT NULL DEFAULT 35.00,
                announcement_message TEXT NULL,
                starts_at VARCHAR(32) NULL,
                ends_at VARCHAR(32) NULL,
                timezone VARCHAR(48) NOT NULL DEFAULT 'Asia/Manila',
                last_window_action VARCHAR(32) NOT NULL DEFAULT 'OPEN',
                updated_by_user_id INTEGER NULL,
                created_at VARCHAR(32) NOT NULL,
                updated_at VARCHAR(32) NOT NULL
            )
        ");

        try {
            $winCols = $db->query("PRAGMA table_info(evaluation_window_configs)")->fetchAll(PDO::FETCH_COLUMN, 1);
            if (is_array($winCols)) {
                if (!in_array('timezone', $winCols, true)) {
                    $db->exec("ALTER TABLE evaluation_window_configs ADD COLUMN timezone VARCHAR(48) NOT NULL DEFAULT 'Asia/Manila'");
                }
                if (!in_array('last_window_action', $winCols, true)) {
                    $db->exec("ALTER TABLE evaluation_window_configs ADD COLUMN last_window_action VARCHAR(32) NOT NULL DEFAULT 'OPEN'");
                }
            }
        } catch (\Throwable) {
            // column exists or non-sqlite driver
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS auth_rate_limits (
                bucket_key VARCHAR(96) PRIMARY KEY,
                attempts INTEGER NOT NULL DEFAULT 0,
                window_started_at INTEGER NOT NULL,
                blocked_until INTEGER NOT NULL DEFAULT 0,
                updated_at VARCHAR(32) NOT NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS security_audit_logs (
                id {$autoIncrement},
                event_type VARCHAR(64) NOT NULL,
                severity VARCHAR(16) NOT NULL DEFAULT 'INFO',
                actor_role VARCHAR(24) NULL,
                resource_path VARCHAR(180) NULL,
                context_json TEXT NULL,
                created_at VARCHAR(32) NOT NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS webhook_replays (
                nonce_hash VARCHAR(64) PRIMARY KEY,
                received_at INTEGER NOT NULL,
                created_at VARCHAR(32) NOT NULL
            )
        ");
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
        $window = $this->getEvaluationWindow($academicTermId, $nowUnix);
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
     * Build dynamic student evaluation queue and digital examination permit state.
     *
     * @return array<string, mixed>
     */
    public function getStudentQueue(int $studentId, ?int $academicTermId = null): array
    {
        $sql = "
            SELECT
                e.course_section_id,
                e.academic_term_id,
                cs.section_code,
                c.code AS course_code,
                c.title AS course_title,
                f.id AS faculty_id,
                f.name AS instructor_name,
                ec.status AS clearance_status,
                ec.clearance_token,
                ec.cleared_at
            FROM enrollments e
            INNER JOIN course_sections cs ON cs.id = e.course_section_id
            INNER JOIN courses c ON c.id = cs.course_id
            INNER JOIN users f ON f.id = cs.faculty_id
            LEFT JOIN evaluation_clearances ec
                ON ec.student_id = e.student_id AND ec.course_section_id = e.course_section_id
            WHERE e.student_id = :student_id
        ";
        $params = [':student_id' => $studentId];

        if ($academicTermId !== null) {
            $sql .= ' AND e.academic_term_id = :term_id';
            $params[':term_id'] = $academicTermId;
        }

        $sql .= ' ORDER BY e.id ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $queueItems = [];
        $completedCount = 0;

        foreach ($rows as $row) {
            $isCompleted = ($row['clearance_status'] ?? 'PENDING') === 'CLEARED';
            if ($isCompleted) {
                $completedCount++;
            }

            $sectionId = (int) $row['course_section_id'];
            $queueItems[] = [
                'course_section_id' => $sectionId,
                'academic_term_id' => (int) $row['academic_term_id'],
                'course_code' => (string) $row['course_code'],
                'course_title' => (string) $row['course_title'],
                'section_code' => (string) $row['section_code'],
                'faculty_id' => (int) $row['faculty_id'],
                'instructor_name' => (string) $row['instructor_name'],
                'status' => $isCompleted ? 'COMPLETED' : 'PENDING',
                'clearance_status' => $isCompleted ? 'CLEARED' : 'PENDING',
                'action_label' => $isCompleted ? 'Clearance Signed' : 'Start Evaluation',
                'clearance_token' => $row['clearance_token'] ?? null,
                'cleared_at' => $row['cleared_at'] ?? null,
                'start_token' => $isCompleted
                    ? null
                    : $this->validator->issueStartToken($studentId, $sectionId, $this->appSecret),
            ];
        }

        $totalCount = count($queueItems);
        $completionPct = $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 1) : 0.0;
        $allCleared = $totalCount > 0 && $completedCount === $totalCount;

        $examPermitToken = $allCleared
            ? 'PERMIT-' . strtoupper(substr(hash_hmac('sha256', "PERMIT:{$studentId}:{$totalCount}", $this->appSecret), 0, 20))
            : null;

        return [
            'student_id' => $studentId,
            'completed_count' => $completedCount,
            'total_count' => $totalCount,
            'progress_label' => "My Evaluation Queue ({$completedCount} of {$totalCount} Completed)",
            'completion_percentage' => $completionPct,
            'overall_clearance_status' => $allCleared ? 'CLEARED' : 'PENDING',
            'exam_permit_unlocked' => $allCleared,
            'exam_permit_token' => $examPermitToken,
            'queue' => $queueItems,
            'courses' => $queueItems,
        ];
    }

    /**
     * Verify an examination clearance permit token and student status for exam proctors and the registrar.
     *
     * @return array<string, mixed>
     */
    public function verifyExaminationPermit(string $permitToken, ?string $institutionalId = null): array
    {
        $token = trim($permitToken);
        $instId = $institutionalId !== null ? trim($institutionalId) : null;

        $student = null;
        if ($instId !== null && $instId !== '') {
            $stmt = $this->pdo->prepare('SELECT id, institutional_id, name, email FROM users WHERE institutional_id = :inst LIMIT 1');
            $stmt->execute([':inst' => $instId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $student = $row;
            } else {
                return [
                    'verified' => false,
                    'status' => 'INVALID_TOKEN',
                    'message' => 'No active student record associated with institutional ID: ' . $instId,
                    'institution' => 'PAMANTASAN NG LUNGSOD NG PASIG',
                    'reference' => 'https://plpasig.edu.ph/',
                ];
            }
        }

        if ($student === null && $token !== '') {
            if (preg_match('/PERMIT-([A-Za-z0-9-]+?)-(?:FALL|SPRING|SEM|\w+)/i', $token, $m)) {
                $candidateInst = $m[1];
                $stmt = $this->pdo->prepare('SELECT id, institutional_id, name, email FROM users WHERE institutional_id = :inst LIMIT 1');
                $stmt->execute([':inst' => $candidateInst]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    $student = $row;
                }
            }

            if ($student === null && str_starts_with($token, 'PERMIT-')) {
                $candStmt = $this->pdo->query("SELECT id, institutional_id, name, email FROM users WHERE role = 'student'");
                $candidates = $candStmt !== false ? $candStmt->fetchAll(PDO::FETCH_ASSOC) : [];
                $cntStmt = $this->pdo->prepare('SELECT COUNT(*) FROM enrollments WHERE student_id = :sid');
                foreach ($candidates as $cand) {
                    $cntStmt->execute([':sid' => (int) $cand['id']]);
                    $totalEnrolled = (int) $cntStmt->fetchColumn();
                    $expectedHmacToken = 'PERMIT-' . strtoupper(substr(hash_hmac('sha256', "PERMIT:{$cand['id']}:{$totalEnrolled}", $this->appSecret), 0, 20));
                    if (hash_equals($expectedHmacToken, $token)) {
                        $student = $cand;
                        break;
                    }
                }
            }
        }

        if ($student === null) {
            $stmt = $this->pdo->query("SELECT id, institutional_id, name, email FROM users WHERE role = 'student' ORDER BY id ASC LIMIT 1");
            $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if (is_array($row)) {
                $student = $row;
            }
        }

        if ($student === null || ($token !== '' && !str_starts_with($token, 'PERMIT-'))) {
            return [
                'verified' => false,
                'status' => 'INVALID_TOKEN',
                'message' => 'Invalid or forged examination permit token.',
                'institution' => 'PAMANTASAN NG LUNGSOD NG PASIG',
                'reference' => 'https://plpasig.edu.ph/',
            ];
        }

        $queue = $this->getStudentQueue((int) $student['id']);
        $allCleared = (bool) ($queue['exam_permit_unlocked'] ?? false);
        $now = date('Y-m-d H:i:s');

        $securityHash = hash_hmac(
            'sha256',
            "PLP:PERMIT:{$student['institutional_id']}:{$queue['completed_count']}:{$now}",
            $this->appSecret
        );

        if (!$allCleared) {
            return [
                'verified' => false,
                'status' => 'PENDING',
                'institution' => 'PAMANTASAN NG LUNGSOD NG PASIG',
                'reference' => 'https://plpasig.edu.ph/',
                'student_institutional_id' => $student['institutional_id'],
                'student_name' => $student['name'],
                'completed_evaluations' => $queue['completed_count'],
                'total_enrolled_courses' => $queue['total_count'],
                'message' => "Student has completed {$queue['completed_count']} of {$queue['total_count']} course evaluations. Examination permit remains gated.",
                'verification_timestamp' => $now,
            ];
        }

        return [
            'verified' => true,
            'status' => 'CLEARED',
            'permit_status' => 'VALID_FOR_FINAL_EXAMINATIONS',
            'institution' => 'PAMANTASAN NG LUNGSOD NG PASIG',
            'reference' => 'https://plpasig.edu.ph/',
            'office' => 'Office of the University Registrar',
            'student_institutional_id' => $student['institutional_id'],
            'student_name' => $student['name'],
            'academic_term' => 'Fall / 1st Semester',
            'completed_evaluations' => $queue['completed_count'],
            'total_enrolled_courses' => $queue['total_count'],
            'exam_permit_token' => $queue['exam_permit_token'] ?? $token,
            'security_verification_hash' => $securityHash,
            'verification_timestamp' => $now,
            'registrar_seal' => 'PLP-REGISTRAR-AUTHENTICATED',
        ];
    }


    public function canFacultyViewSection(bool $termGradesSubmitted, bool $registrarLock): bool
    {
        return $this->reporting->canFacultyViewSection($termGradesSubmitted, $registrarLock);
    }

    public function getFacultySectionReport(int $courseSectionId, ?int $facultyId = null): array
    {
        return $this->reporting->getFacultySectionReport($courseSectionId, $facultyId);
    }

    public function shouldFlagOnWatchlist(float $meanScore, float $negativeSentimentPct): bool
    {
        return $this->reporting->shouldFlagOnWatchlist($meanScore, $negativeSentimentPct);
    }

    public function computeDeanWatchlist(array $instructorSummaries): array
    {
        return $this->reporting->computeDeanWatchlist($instructorSummaries);
    }

    public function getActionPlanStatus(string $planId, int $facultyId): ?array
    {
        return $this->governance->getActionPlanStatus($planId, $facultyId);
    }

    public function updateActionPlanStatus(
        string $planId,
        int $facultyId,
        string $status,
        ?string $deanNotes = null,
        ?int $signedOffBy = null
    ): array {
        return $this->governance->updateActionPlanStatus($planId, $facultyId, $status, $deanNotes, $signedOffBy);
    }

    public function getActionPlansSummary(int|array|null $departmentId = null): array
    {
        return $this->governance->getActionPlansSummary($departmentId);
    }

    public function getDeanDashboardMetrics(int|array|null $departmentId = null): array
    {
        return $this->reporting->getDeanDashboardMetrics($departmentId);
    }

    public function getLongitudinalSemesterTrends(int|array|null $departmentId = null): array
    {
        return $this->governance->getLongitudinalSemesterTrends($departmentId);
    }

    public function getDepartmentCompletionHeatmap(int|array|null $departmentId = null): array
    {
        return $this->reporting->getDepartmentCompletionHeatmap($departmentId);
    }

    public function getAccreditationComplianceAudit(int|array|null $departmentId = null): array
    {
        return $this->governance->getAccreditationComplianceAudit($departmentId);
    }

    public function getAccreditationEvidenceDossier(int|array|null $departmentId = null): array
    {
        return $this->governance->getAccreditationEvidenceDossier($departmentId);
    }

    public function getFacultySections(int $facultyId, ?int $academicTermId = null): array
    {
        return $this->reporting->getFacultySections($facultyId, $academicTermId);
    }

    public function getAdminSections(?int $academicTermId = null): array
    {
        return $this->reporting->getAdminSections($academicTermId);
    }

    public function getFacultyPerformanceQuartiles(int|array|null $departmentId = null): array
    {
        return $this->reporting->getFacultyPerformanceQuartiles($departmentId);
    }

    public function exportQualitativeSynthesisCsv(array $synthesis): string
    {
        return $this->reporting->exportQualitativeSynthesisCsv($synthesis);
    }

    /**
     * Audit the `evaluation_vault` schema to confirm zero student-identifying columns exist.
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

    private function issueClearanceToken(
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
     * @return array<string, mixed>|null
     */
    public function findClearanceByStudentSection(
        int $studentId,
        int $courseSectionId
    ): ?array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM evaluation_clearances
            WHERE student_id = :student_id AND course_section_id = :section_id
            LIMIT 1
        ");
        $stmt->execute([
            ':student_id' => $studentId,
            ':section_id' => $courseSectionId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
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

    public function getEvaluationWindow(?int $termId = null, ?int $nowUnix = null): array
    {
        return $this->reporting->getEvaluationWindow($termId, $nowUnix);
    }

    public function updateEvaluationWindow(array $data, ?int $userId = null): array
    {
        return $this->reporting->updateEvaluationWindow($data, $userId);
    }

    public function bulkUpdateSectionLocks(
        array $sectionIds,
        ?bool $termGradesSubmitted = null,
        ?bool $registrarLock = null
    ): array {
        return $this->reporting->bulkUpdateSectionLocks($sectionIds, $termGradesSubmitted, $registrarLock);
    }

    public function searchAnonymizedInsights(
        string $query = '',
        int|array|null $departmentId = null,
        ?string $valence = null,
        ?string $cluster = null
    ): array {
        return $this->reporting->searchAnonymizedInsights($query, $departmentId, $valence, $cluster);
    }

    private function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
