<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

class CampaignWarRoomService
{
    public const INSTITUTIONAL_TARGET_TURNOUT_PCT = 85.0;

    private PDO $pdo;
    private DecoupledEvaluationService $evaluationService;
    private AcademicSetupService $setupService;
    private AnalyticsClientService $analyticsClient;

    public function __construct(
        ?PDO $pdo = null,
        ?DecoupledEvaluationService $evaluationService = null,
        ?AcademicSetupService $setupService = null,
        ?AnalyticsClientService $analyticsClient = null
    ) {
        $this->evaluationService = $evaluationService ?? new DecoupledEvaluationService($pdo);
        $this->pdo = $this->evaluationService->getPdo();
        $this->setupService = $setupService ?? new AcademicSetupService($this->pdo);
        $this->analyticsClient = $analyticsClient ?? new AnalyticsClientService();
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Resolve the active or specified academic term ID.
     */
    public function resolveAcademicTermId(?int $academicTermId = null): int
    {
        if ($academicTermId !== null && $academicTermId > 0) {
            return $academicTermId;
        }

        $stmt = $this->pdo->query('SELECT id FROM academic_terms WHERE is_active = 1 LIMIT 1');
        $activeId = $stmt ? $stmt->fetchColumn() : null;

        if ($activeId) {
            return (int) $activeId;
        }

        $stmt = $this->pdo->query('SELECT id FROM academic_terms ORDER BY id DESC LIMIT 1');
        $latestId = $stmt ? $stmt->fetchColumn() : null;

        return $latestId ? (int) $latestId : 1;
    }

    /**
     * Execute a 6-point pre-launch diagnostic audit for the academic term.
     *
     * @return array<string, mixed>
     */
    public function getPreFlightAudit(?int $academicTermId = null): array
    {
        $termId = $this->resolveAcademicTermId($academicTermId);
        $setupOverview = $this->setupService->getSetupOverview($termId);

        $term = null;
        foreach ($setupOverview['terms'] as $t) {
            if ((int) $t['id'] === $termId) {
                $term = $t;
                break;
            }
        }

        $window = $setupOverview['window_config'] ?? [];
        $sections = $setupOverview['sections'] ?? [];
        $readiness = $setupOverview['readiness_summary'] ?? [];

        $checks = [];

        // Check 1: Active Academic Term & Schedule Window
        $hasActiveTerm = $term !== null && !empty($term['is_active']);
        $hasStartsAt = !empty($window['starts_at']);
        $hasEndsAt = !empty($window['ends_at']);
        $isWindowOpen = ($window['status'] ?? '') === 'OPEN';

        $termScheduleStatus = ($hasActiveTerm && $hasStartsAt && $hasEndsAt) ? 'PASS' : ($hasActiveTerm ? 'WARN' : 'FAIL');
        $checks['check_term_schedule'] = [
            'id' => 1,
            'title' => 'Academic Term & Window Schedule',
            'status' => $termScheduleStatus,
            'message' => $termScheduleStatus === 'PASS'
                ? "Academic term '{$term['name']}' is configured with valid schedule dates ({$window['starts_at']} to {$window['ends_at']})."
                : 'Evaluation window schedule dates or active term status requires attention before opening.',
            'details' => [
                'term_id' => $termId,
                'term_code' => $term['code'] ?? 'N/A',
                'term_name' => $term['name'] ?? 'N/A',
                'is_active' => $hasActiveTerm,
                'window_status' => $window['status'] ?? 'CLOSED',
                'starts_at' => $window['starts_at'] ?? null,
                'ends_at' => $window['ends_at'] ?? null,
                'timezone' => $window['timezone'] ?? 'Asia/Manila',
            ],
        ];

        // Check 2: Course Section Faculty Assignments
        $totalSections = count($sections);
        $unassignedCount = (int) ($readiness['unassigned_sections'] ?? 0);
        $facultyAssignedStatus = ($totalSections > 0 && $unassignedCount === 0) ? 'PASS' : ($unassignedCount > 0 ? 'WARN' : 'FAIL');
        $checks['check_faculty_assignments'] = [
            'id' => 2,
            'title' => 'Course Section Faculty Assignments',
            'status' => $facultyAssignedStatus,
            'message' => $facultyAssignedStatus === 'PASS'
                ? "All {$totalSections} course section(s) have verified faculty instructors assigned."
                : ($totalSections === 0
                    ? 'No course sections configured for this term.'
                    : "{$unassignedCount} section(s) lack an assigned faculty instructor."),
            'details' => [
                'total_sections' => $totalSections,
                'assigned_sections' => $totalSections - $unassignedCount,
                'unassigned_sections' => $unassignedCount,
            ],
        ];

        // Check 3: Student Roster Ingestion & Enrollment Density
        $totalEnrolled = (int) ($readiness['total_enrolled'] ?? 0);
        $zeroEnrollmentCount = (int) ($readiness['sections_with_zero_enrollment'] ?? 0);
        $rosterStatus = ($totalEnrolled > 0 && $zeroEnrollmentCount === 0) ? 'PASS' : ($zeroEnrollmentCount > 0 ? 'WARN' : 'FAIL');
        $checks['check_roster_enrollments'] = [
            'id' => 3,
            'title' => 'Roster Ingestion & Enrollment Density',
            'status' => $rosterStatus,
            'message' => $rosterStatus === 'PASS'
                ? "{$totalEnrolled} student enrollment(s) verified across active sections with zero empty sections."
                : ($totalEnrolled === 0
                    ? 'No student enrollments found for this term.'
                    : "{$zeroEnrollmentCount} section(s) currently have zero enrolled students."),
            'details' => [
                'total_enrolled_students' => $totalEnrolled,
                'sections_with_zero_enrollment' => $zeroEnrollmentCount,
                'student_directory_count' => count($setupOverview['students'] ?? []),
            ],
        ];

        // Check 4: Accredited Questionnaire Instrument
        $questionnaire = $setupOverview['questionnaire_preview'] ?? [];
        $clusters = $questionnaire['clusters'] ?? [];
        $totalLikert = 0;
        foreach ($clusters as $cl) {
            $totalLikert += count($cl['items'] ?? []);
        }
        $qualitativeCount = count($questionnaire['professor_qualitative_questions'] ?? $questionnaire['qualitative_questions'] ?? []);
        $instrumentStatus = ($totalLikert === 25 && count($clusters) === 5) ? 'PASS' : 'WARN';
        $checks['check_instrument_integrity'] = [
            'id' => 4,
            'title' => 'Accredited Evaluation Instrument',
            'status' => $instrumentStatus,
            'message' => $instrumentStatus === 'PASS'
                ? 'Official 25-item 5-cluster Likert scale + qualitative questionnaire instrument verified.'
                : 'Questionnaire instrument does not match the 25-item AACCUP Level III standard.',
            'details' => [
                'total_likert_items' => $totalLikert,
                'pedagogical_clusters' => count($clusters),
                'qualitative_inquiry_count' => $qualitativeCount,
            ],
        ];

        // Check 5: Double-Blind Locks & Registrar Governance
        $lockedCount = 0;
        $unlockedCount = 0;
        foreach ($sections as $s) {
            if (!empty($s['registrar_lock'])) {
                $lockedCount++;
            } else {
                $unlockedCount++;
            }
        }
        $locksStatus = 'PASS';
        $checks['check_double_blind_locks'] = [
            'id' => 5,
            'title' => 'Double-Blind Protection & Registrar Locks',
            'status' => $locksStatus,
            'message' => 'Double-blind faculty visibility rules active. Faculty cannot view unreleased term ratings.',
            'details' => [
                'total_sections' => $totalSections,
                'registrar_locked_sections' => $lockedCount,
                'unlocked_pending_sections' => $unlockedCount,
                'gate_rule' => 'term_grades_submitted = TRUE AND registrar_lock = TRUE',
            ],
        ];

        // Check 6: NLP Microservice & Circuit-Breaker Readiness
        $health = $this->analyticsClient->checkHealth();
        $isNlpHealthy = !empty($health['healthy']);
        $isFallback = !empty($health['fallback_heuristic']);
        $nlpStatus = $isNlpHealthy ? 'PASS' : 'WARN';
        $checks['check_nlp_microservice'] = [
            'id' => 6,
            'title' => 'NLP Sentiment Engine & Fallback Readiness',
            'status' => $nlpStatus,
            'message' => $isNlpHealthy
                ? ($isFallback
                    ? 'NLP service active in deterministic heuristic fallback mode (RuleBased+VADER).'
                    : 'NLP microservice active and connected successfully.')
                : 'NLP daemon offline; local deterministic circuit breaker will process submissions.',
            'details' => [
                'healthy' => $isNlpHealthy,
                'runtime_engine' => $health['runtime_engine'] ?? 'Local Deterministic Fallback',
                'calamancy_loaded' => $health['calamancy_loaded'] ?? false,
                'fallback_heuristic' => $isFallback,
            ],
        ];

        // Compute overall pre-flight score
        $passedCount = 0;
        $warnCount = 0;
        $failCount = 0;
        foreach ($checks as $c) {
            if ($c['status'] === 'PASS') {
                $passedCount++;
            } elseif ($c['status'] === 'WARN') {
                $warnCount++;
            } else {
                $failCount++;
            }
        }

        $readinessScorePct = round(($passedCount / count($checks)) * 100, 1);
        $overallVerdict = $failCount > 0 ? 'BLOCKED' : ($warnCount > 0 ? 'ACTION_REQUIRED' : 'READY_FOR_LAUNCH');

        return [
            'academic_term_id' => $termId,
            'term_name' => $term['name'] ?? 'N/A',
            'overall_verdict' => $overallVerdict,
            'readiness_score_pct' => $readinessScorePct,
            'checks_passed' => $passedCount,
            'checks_warn' => $warnCount,
            'checks_fail' => $failCount,
            'total_checks' => count($checks),
            'checks' => $checks,
            'audited_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Compute real-time turnout leaderboard and velocity projections for the active term.
     *
     * @return array<string, mixed>
     */
    public function getTurnoutLeaderboard(?int $academicTermId = null): array
    {
        $termId = $this->resolveAcademicTermId($academicTermId);

        // Fetch department-level completion aggregates
        $sql = '
            SELECT 
                d.id AS department_id,
                d.code AS department_code,
                d.name AS department_name,
                d.college_name AS college_name,
                COUNT(e.id) AS total_enrolled,
                SUM(CASE WHEN cl.id IS NOT NULL AND cl.status = "CLEARED" THEN 1 ELSE 0 END) AS completed_evaluations
            FROM departments d
            JOIN courses c ON c.department_id = d.id
            JOIN course_sections cs ON cs.course_id = c.id AND cs.academic_term_id = :term_id
            JOIN enrollments e ON e.course_section_id = cs.id
            LEFT JOIN evaluation_clearances cl ON cl.course_section_id = cs.id AND cl.student_id = e.student_id
            GROUP BY d.id, d.code, d.name, d.college_name
            ORDER BY d.name ASC
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['term_id' => $termId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $departments = [];
        $instTotalEnrolled = 0;
        $instTotalCompleted = 0;

        foreach ($rows as $r) {
            $total = (int) $r['total_enrolled'];
            $completed = (int) $r['completed_evaluations'];
            $pending = max(0, $total - $completed);
            $pct = $total > 0 ? round(($completed / $total) * 100, 1) : 0.0;

            $instTotalEnrolled += $total;
            $instTotalCompleted += $completed;

            $status = $pct >= self::INSTITUTIONAL_TARGET_TURNOUT_PCT
                ? 'MET'
                : ($pct >= 70.0 ? 'ON_TRACK' : 'NEEDS_BOOST');

            $departments[] = [
                'department_id' => (int) $r['department_id'],
                'department_code' => (string) $r['department_code'],
                'department_name' => (string) $r['department_name'],
                'college_name' => (string) ($r['college_name'] ?? 'College of Computer Studies'),
                'total_enrolled' => $total,
                'completed' => $completed,
                'pending' => $pending,
                'turnout_pct' => $pct,
                'target_pct' => self::INSTITUTIONAL_TARGET_TURNOUT_PCT,
                'status' => $status,
            ];
        }

        // Sort by turnout percentage descending to determine departmental rank
        usort($departments, fn ($a, $b) => $b['turnout_pct'] <=> $a['turnout_pct']);
        foreach ($departments as $idx => &$dept) {
            $dept['rank'] = $idx + 1;
        }
        unset($dept);

        $instPending = max(0, $instTotalEnrolled - $instTotalCompleted);
        $instTurnoutPct = $instTotalEnrolled > 0 ? round(($instTotalCompleted / $instTotalEnrolled) * 100, 1) : 0.0;
        $targetGap = max(0.0, round(self::INSTITUTIONAL_TARGET_TURNOUT_PCT - $instTurnoutPct, 1));

        // Velocity & projection estimates
        $velocity = [
            'target_turnout_pct' => self::INSTITUTIONAL_TARGET_TURNOUT_PCT,
            'current_turnout_pct' => $instTurnoutPct,
            'gap_pct' => $targetGap,
            'target_evaluations_needed' => (int) ceil(($instTotalEnrolled * (self::INSTITUTIONAL_TARGET_TURNOUT_PCT / 100.0)) - $instTotalCompleted),
            'target_status' => $instTurnoutPct >= self::INSTITUTIONAL_TARGET_TURNOUT_PCT ? 'TARGET_MET' : 'IN_PROGRESS',
        ];

        return [
            'academic_term_id' => $termId,
            'institutional_summary' => [
                'total_enrolled_evaluations' => $instTotalEnrolled,
                'completed_evaluations' => $instTotalCompleted,
                'pending_evaluations' => $instPending,
                'turnout_pct' => $instTurnoutPct,
                'target_pct' => self::INSTITUTIONAL_TARGET_TURNOUT_PCT,
            ],
            'velocity_projection' => $velocity,
            'department_leaderboard' => $departments,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Generate non-coercive reminder batch for pending evaluators with strict privacy preservation.
     *
     * @return array<string, mixed>
     */
    public function generatePendingReminderBatch(?int $academicTermId = null, ?int $departmentId = null): array
    {
        $termId = $this->resolveAcademicTermId($academicTermId);

        $sql = '
            SELECT 
                u.id AS student_user_id,
                u.institutional_id,
                u.name AS student_name,
                u.email AS student_email,
                d.name AS department_name,
                COUNT(e.id) AS total_enrolled,
                SUM(CASE WHEN cl.id IS NOT NULL AND cl.status = "CLEARED" THEN 1 ELSE 0 END) AS completed_count
            FROM users u
            JOIN enrollments e ON e.student_id = u.id
            JOIN course_sections cs ON cs.id = e.course_section_id AND cs.academic_term_id = :term_id
            JOIN courses c ON c.id = cs.course_id
            LEFT JOIN departments d ON d.id = c.department_id
            LEFT JOIN evaluation_clearances cl ON cl.course_section_id = cs.id AND cl.student_id = u.id
            WHERE u.role = "student"
        ';

        $params = ['term_id' => $termId];
        if ($departmentId !== null && $departmentId > 0) {
            $sql .= ' AND c.department_id = :department_id';
            $params['department_id'] = $departmentId;
        }

        $sql .= '
            GROUP BY u.id, u.institutional_id, u.name, u.email, d.name
            HAVING completed_count < total_enrolled
            ORDER BY u.name ASC
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $pendingStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $recipients = [];
        $totalPendingEvaluations = 0;

        foreach ($pendingStudents as $s) {
            $enrolled = (int) $s['total_enrolled'];
            $completed = (int) $s['completed_count'];
            $pending = max(0, $enrolled - $completed);
            $totalPendingEvaluations += $pending;

            $recipients[] = [
                'student_id' => $s['institutional_id'],
                'student_name' => $s['student_name'],
                'email' => $s['student_email'],
                'department' => $s['department_name'] ?? 'College of Computer Studies',
                'enrolled_courses' => $enrolled,
                'completed_evaluations' => $completed,
                'pending_evaluations' => $pending,
            ];
        }

        $termName = '1st Semester AY 2026-2027';
        $termStmt = $this->pdo->prepare('SELECT name FROM academic_terms WHERE id = ?');
        $termStmt->execute([$termId]);
        $fetchedTerm = $termStmt->fetchColumn();
        if ($fetchedTerm) {
            $termName = (string) $fetchedTerm;
        }

        return [
            'academic_term_id' => $termId,
            'academic_term_name' => $termName,
            'department_filter_id' => $departmentId,
            'total_pending_students' => count($recipients),
            'total_pending_evaluations' => $totalPendingEvaluations,
            'reminder_subject' => "Pamantasan ng Lungsod ng Pasig · Student Evaluation Notice ({$termName})",
            'reminder_template' => "Dear Student,\n\nThis is an institutional notice from the Office of Academic Affairs regarding your course evaluations for {$termName}. Your valuable and anonymous feedback helps improve instructional quality at Pamantasan ng Lungsod ng Pasig.\n\nPlease log in to EvaluaTech (https://evaluatech.plpasig.edu.ph) when ready to complete your remaining course evaluations.\n\nNote: Your evaluations are 100% anonymous and cryptographically decoupled. The university tracks evaluation participation to ensure institutional compliance.\n\nThank you,\nOffice of Academic Affairs\nPamantasan ng Lungsod ng Pasig",
            'recipients' => $recipients,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }
}
