<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

/**
 * DecoupledEvaluationService
 *
 * Master Domain Orchestrator and Facade for the EvaluaTech evaluation engine.
 * Composes specialized single-responsibility domain services:
 *  - DatabaseSchemaManager: Relational schema DDL and table setup
 *  - EvaluationSubmissionService: Dual-token cryptographic decoupled submissions
 *  - StudentQueueService: Evaluation queue generation and start token issuance
 *  - ExaminationPermitService: Clearance verification for exam proctors & registrar
 *  - EvaluationReportingService: Aggregates, grade locks, watchlist, and search
 *  - AccreditationGovernanceService: AACCUP Level III compliance, dossiers & seals
 */
class DecoupledEvaluationService
{
    public const WATCHLIST_MIN_MEAN_SCORE = 3.50;
    public const WATCHLIST_MAX_NEGATIVE_PCT = 35.0;

    private static ?PDO $sharedPdo = null;

    private PDO $pdo;
    private IntegrityValidatorService $validator;
    private AnalyticsClientService $analytics;
    private string $appSecret;

    private DatabaseSchemaManager $schemaManager;
    private EvaluationSubmissionService $submissionService;
    private StudentQueueService $queueService;
    private ExaminationPermitService $permitService;
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

        $this->schemaManager = new DatabaseSchemaManager();
        $this->schemaManager->initializeSchema($this->pdo);

        $this->reporting = new EvaluationReportingService($this->pdo, $this->validator, $this->analytics, $this);
        $this->governance = new AccreditationGovernanceService($this->pdo, $this->validator, $this->analytics, $this);
        $this->queueService = new StudentQueueService($this->pdo, $this->validator, $this->appSecret);
        $this->permitService = new ExaminationPermitService($this->pdo, $this->queueService, $this->appSecret);
        $this->submissionService = new EvaluationSubmissionService(
            $this->pdo,
            $this->validator,
            $this->analytics,
            $this->appSecret,
            $this->reporting
        );
    }

    public function getReportingService(): EvaluationReportingService
    {
        return $this->reporting;
    }

    public function getGovernanceService(): AccreditationGovernanceService
    {
        return $this->governance;
    }

    public function getSubmissionService(): EvaluationSubmissionService
    {
        return $this->submissionService;
    }

    public function getQueueService(): StudentQueueService
    {
        return $this->queueService;
    }

    public function getPermitService(): ExaminationPermitService
    {
        return $this->permitService;
    }

    public function getSchemaManager(): DatabaseSchemaManager
    {
        return $this->schemaManager;
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
        throw new RuntimeException(
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

        return self::getSharedPdo();
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function initializeSchema(?PDO $pdo = null): void
    {
        $this->schemaManager->initializeSchema($pdo ?? $this->pdo);
    }

    /**
     * Execute an atomic, integrity-guarded dual-token evaluation submission.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function submitEvaluation(array $payload): array
    {
        return $this->submissionService->submitEvaluation($payload);
    }

    /**
     * Build dynamic student evaluation queue and digital examination permit state.
     *
     * @return array<string, mixed>
     */
    public function getStudentQueue(int $studentId, ?int $academicTermId = null): array
    {
        return $this->queueService->getStudentQueue($studentId, $academicTermId);
    }

    /**
     * Verify an examination clearance permit token and student status for exam proctors and the registrar.
     *
     * @return array<string, mixed>
     */
    public function verifyExaminationPermit(string $permitToken, ?string $institutionalId = null): array
    {
        return $this->permitService->verifyExaminationPermit($permitToken, $institutionalId);
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
        ?int $signedOffByUserId = null
    ): array {
        return $this->governance->updateActionPlanStatus($planId, $facultyId, $status, $deanNotes, $signedOffByUserId);
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

    public function compileExecutiveAccreditationDossierBundle(int|array|null $departmentId = null): array
    {
        return $this->governance->compileExecutiveAccreditationDossierBundle($departmentId);
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

    public function verifyVaultAnonymity(): array
    {
        return $this->submissionService->verifyVaultAnonymity();
    }

    public function findClearanceByStudentSection(int $studentId, int $courseSectionId): ?array
    {
        return $this->submissionService->findClearanceByStudentSection($studentId, $courseSectionId);
    }

    public function findSectionById(int $courseSectionId): ?array
    {
        return $this->submissionService->findSectionById($courseSectionId);
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
        ?string $tag = null,
        int $limit = 25
    ): array {
        return $this->reporting->searchAnonymizedInsights($query, $departmentId, $valence, $tag, $limit);
    }
}
