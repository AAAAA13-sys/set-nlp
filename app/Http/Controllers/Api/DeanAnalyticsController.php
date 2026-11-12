<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Services\DecoupledEvaluationService;
use App\Services\QualityMetricsService;
use App\Services\RosterImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DeanAnalyticsController extends Controller
{
    private DecoupledEvaluationService $evaluationService;
    private QualityMetricsService $qualityService;
    private RosterImportService $rosterService;

    public function __construct(
        ?DecoupledEvaluationService $evaluationService = null,
        ?QualityMetricsService $qualityService = null,
        ?RosterImportService $rosterService = null
    ) {
        $this->evaluationService = $evaluationService ?? new DecoupledEvaluationService();
        $this->qualityService = $qualityService ?? new QualityMetricsService($this->evaluationService->getPdo());
        $this->rosterService = $rosterService ?? new RosterImportService($this->evaluationService->getPdo());
    }

    /**
     * Resolves and enforces departmental and college scoping for the authenticated user.
     *
     * @param Request $request
     * @return array{0: ?int, 1: ?JsonResponse} [resolvedDepartmentId, errorResponse]
     */
    private function resolveScopedDepartmentId(Request $request): array
    {
        $user = $request->user();
        $rawDept = $request->input('department_id');
        $requestedDeptId = ($rawDept !== null && trim((string) $rawDept) !== '') ? (int) $rawDept : null;

        if ($user === null) {
            return [$requestedDeptId, null];
        }

        $role = is_string($user->role ?? null)
            ? $user->role
            : ($user->role instanceof UserRole ? $user->role->value : '');

        // Administrator has unrestricted institutional access
        if ($role === UserRole::ADMIN->value) {
            return [$requestedDeptId, null];
        }

        // Department Chair is strictly scoped to their assigned department_id
        if ($role === UserRole::CHAIR->value) {
            $chairDeptId = isset($user->department_id) && $user->department_id !== null ? (int) $user->department_id : null;
            if ($chairDeptId === null || $chairDeptId <= 0) {
                return [
                    null,
                    new JsonResponse([
                        'authorized' => false,
                        'code' => 'UNASSIGNED_DEPARTMENT_SCOPE',
                        'message' => 'Department Chair has no assigned department; access restricted.',
                    ], 403),
                ];
            }

            if ($requestedDeptId !== null && $requestedDeptId !== $chairDeptId) {
                return [
                    null,
                    new JsonResponse([
                        'authorized' => false,
                        'code' => 'FORBIDDEN_DEPARTMENT_SCOPE',
                        'message' => 'Department Chairs are strictly scoped to their assigned department.',
                    ], 403),
                ];
            }

            return [$chairDeptId, null];
        }

        // College Dean is scoped to their assigned college
        if ($role === UserRole::DEAN->value) {
            $deanCollege = trim((string) ($user->college_name ?? ''));
            if ($deanCollege === '' && !empty($user->department_id)) {
                $stmt = $this->evaluationService->getPdo()->prepare('SELECT college_name FROM departments WHERE id = :id');
                $stmt->execute([':id' => (int) $user->department_id]);
                $deanCollege = trim((string) ($stmt->fetchColumn() ?: ''));
            }
            if ($deanCollege === '' && !empty($user->id)) {
                $stmt = $this->evaluationService->getPdo()->prepare('
                    SELECT d.college_name 
                    FROM departments d 
                    INNER JOIN users u ON u.department_id = d.id 
                    WHERE u.id = :uid
                ');
                $stmt->execute([':uid' => (int) $user->id]);
                $deanCollege = trim((string) ($stmt->fetchColumn() ?: ''));
            }
            if ($deanCollege === '') {
                return [
                    null,
                    new JsonResponse([
                        'authorized' => false,
                        'code' => 'UNASSIGNED_COLLEGE_SCOPE',
                        'message' => 'College Dean has no assigned college.',
                    ], 403),
                ];
            }

            $collegeDepts = $this->getDepartmentIdsForCollege($deanCollege);
            if (empty($collegeDepts)) {
                return [
                    null,
                    new JsonResponse([
                        'authorized' => false,
                        'code' => 'UNASSIGNED_COLLEGE_SCOPE',
                        'message' => 'College Dean has no active departments in their assigned college.',
                    ], 403),
                ];
            }

            if ($requestedDeptId !== null && !in_array($requestedDeptId, $collegeDepts, true)) {
                return [
                    null,
                    new JsonResponse([
                        'authorized' => false,
                        'code' => 'FORBIDDEN_COLLEGE_SCOPE',
                        'message' => 'College Deans are strictly scoped to departments within their assigned college.',
                    ], 403),
                ];
            }

            // When requestedDeptId is specified, query that department.
            // When omitted (null), default strictly to all departments in their assigned college ($collegeDepts).
            $effectiveDept = $requestedDeptId !== null ? $requestedDeptId : $collegeDepts;
            return [$effectiveDept, null];
        }

        return [
            null,
            new JsonResponse([
                'authorized' => false,
                'code' => 'FORBIDDEN_ROLE',
                'message' => 'Access restricted to authorized academic supervisors and administrators.',
            ], 403),
        ];
    }

    /**
     * @return int[]
     */
    private function getDepartmentIdsForCollege(string $collegeName): array
    {
        $stmt = $this->evaluationService->getPdo()->prepare(
            'SELECT id FROM departments WHERE college_name = :college'
        );
        $stmt->execute([':college' => $collegeName]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function overview(Request $request): JsonResponse
    {
        [$requestedDeptId, $scopeError] = $this->resolveScopedDepartmentId($request);
        if ($scopeError !== null) {
            return $scopeError;
        }

        $metrics = $this->evaluationService->getDeanDashboardMetrics($requestedDeptId);
        return new JsonResponse($metrics, 200);
    }

    public function watchlist(Request $request): JsonResponse
    {
        $overviewResponse = $this->overview($request);
        if ($overviewResponse->getStatusCode() !== 200) {
            return $overviewResponse;
        }

        $data = (array) $overviewResponse->getData(true);
        return new JsonResponse([
            'department_id' => $data['department_id'] ?? null,
            'watchlist' => $data['watchlist'] ?? [],
        ], 200);
    }

    public function qualityMetrics(): JsonResponse
    {
        return new JsonResponse([
            'iso_25010' => $this->qualityService->getIso25010EvaluationResults(),
            'sus' => $this->qualityService->getSusCohortBreakdown(),
        ], 200);
    }

    public function exportCsv(Request $request): JsonResponse
    {
        $rows = is_array($request->input('rows')) ? $request->input('rows') : [];
        $csv = $this->rosterService->exportAccreditationCsv($rows);

        return new JsonResponse([
            'format' => 'csv',
            'csv_content' => $csv,
        ], 200);
    }

    public function qualitativeSynthesis(Request $request): JsonResponse
    {
        [$requestedDeptId, $scopeError] = $this->resolveScopedDepartmentId($request);
        if ($scopeError !== null) {
            return $scopeError;
        }

        $overview = $this->evaluationService->getDeanDashboardMetrics($requestedDeptId);
        $synthesis = $overview['qualitative_synthesis'] ?? [];

        return new JsonResponse($synthesis, 200);
    }

    public function exportQualitativeCsv(Request $request): JsonResponse
    {
        [$requestedDeptId, $scopeError] = $this->resolveScopedDepartmentId($request);
        if ($scopeError !== null) {
            return $scopeError;
        }

        $overview = $this->evaluationService->getDeanDashboardMetrics($requestedDeptId);
        $synthesis = $overview['qualitative_synthesis'] ?? [];
        $csv = $this->rosterService->exportQualitativeSynthesisCsv($synthesis);

        return new JsonResponse([
            'format' => 'csv',
            'department_id' => is_array($requestedDeptId) ? null : $requestedDeptId,
            'csv_content' => $csv,
        ], 200);
    }

    public function accreditationSummary(): JsonResponse
    {
        $actionPlanSummary = $this->evaluationService->getActionPlansSummary();
        return new JsonResponse($this->qualityService->getInstitutionalAccreditationSummary($actionPlanSummary), 200);
    }

    public function updateActionPlanStatus(Request $request): JsonResponse
    {
        $planId = trim((string) $request->input('plan_id', ''));
        $facultyId = (int) $request->input('faculty_id', 0);
        $status = trim((string) $request->input('status', 'PENDING_REVIEW'));
        $deanNotes = $request->input('dean_notes') !== null ? (string) $request->input('dean_notes') : null;
        $user = $request->user();
        $signedOffBy = $user ? (int) ($user->id ?? 0) : null;

        if ($planId === '' || $facultyId <= 0) {
            return new JsonResponse([
                'updated' => false,
                'code' => 'INVALID_PLAN_PAYLOAD',
                'message' => 'plan_id and faculty_id are required fields.',
            ], 422);
        }

        if ($user !== null) {
            $role = is_string($user->role ?? null)
                ? $user->role
                : ($user->role instanceof UserRole ? $user->role->value : '');

            if ($role === UserRole::CHAIR->value) {
                $chairDeptId = isset($user->department_id) && $user->department_id !== null ? (int) $user->department_id : null;
                if ($chairDeptId === null || $chairDeptId <= 0) {
                    return new JsonResponse([
                        'updated' => false,
                        'code' => 'UNASSIGNED_DEPARTMENT_SCOPE',
                        'message' => 'Department Chair has no assigned department; access restricted.',
                    ], 403);
                }

                $existingPlan = $this->evaluationService->getActionPlanStatus($planId, $facultyId);
                if ($existingPlan !== null && !empty($existingPlan['department_id']) && (int) $existingPlan['department_id'] !== $chairDeptId) {
                    return new JsonResponse([
                        'updated' => false,
                        'code' => 'FORBIDDEN_DEPARTMENT_SCOPE',
                        'message' => 'Department Chairs can only manage action plans for faculty in their assigned department.',
                    ], 403);
                }
            } elseif ($role === UserRole::DEAN->value) {
                $deanCollege = trim((string) ($user->college_name ?? ''));
                if ($deanCollege === '' && !empty($user->department_id)) {
                    $stmt = $this->evaluationService->getPdo()->prepare('SELECT college_name FROM departments WHERE id = :id');
                    $stmt->execute([':id' => (int) $user->department_id]);
                    $deanCollege = trim((string) ($stmt->fetchColumn() ?: ''));
                }

                if ($deanCollege !== '') {
                    $collegeDepts = $this->getDepartmentIdsForCollege($deanCollege);
                    if (!empty($collegeDepts)) {
                        $existingPlan = $this->evaluationService->getActionPlanStatus($planId, $facultyId);
                        if ($existingPlan !== null && !empty($existingPlan['department_id']) && !in_array((int) $existingPlan['department_id'], $collegeDepts, true)) {
                            return new JsonResponse([
                                'updated' => false,
                                'code' => 'FORBIDDEN_COLLEGE_SCOPE',
                                'message' => 'College Deans can only manage action plans for faculty within their assigned college.',
                            ], 403);
                        }
                    }
                }
            }
        }

        $result = $this->evaluationService->updateActionPlanStatus(
            $planId,
            $facultyId,
            $status,
            $deanNotes,
            $signedOffBy
        );

        return new JsonResponse($result, 200);
    }

    public function evaluateLiveSus(Request $request): JsonResponse
    {
        $rawItems = $request->input('items') ?? $request->input('sus_items') ?? [];
        $items = is_array($rawItems) ? $rawItems : [];
        $cohort = (string) $request->input('cohort', 'Students');

        try {
            $result = $this->qualityService->evaluateLiveSusSubmission($items, $cohort);
            return new JsonResponse($result, 200);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'accepted' => false,
                'code' => 'INVALID_SUS_SUBMISSION',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function longitudinalTrends(Request $request): JsonResponse
    {
        [$requestedDeptId, $scopeError] = $this->resolveScopedDepartmentId($request);
        if ($scopeError !== null) {
            return $scopeError;
        }

        $trends = $this->evaluationService->getLongitudinalSemesterTrends($requestedDeptId);
        return new JsonResponse($trends, 200);
    }

    public function exportAccreditationDossier(Request $request): JsonResponse
    {
        [$requestedDeptId, $scopeError] = $this->resolveScopedDepartmentId($request);
        if ($scopeError !== null) {
            return $scopeError;
        }

        $trends = $this->evaluationService->getLongitudinalSemesterTrends($requestedDeptId);

        $csvRows = [];
        $csvRows[] = ['ACCREDITATION MATRIX', 'AACCUP Area III: Faculty Development & Instructional Competence'];
        $csvRows[] = ['Institution', 'SCHOOL AAAAA13'];
        $csvRows[] = ['Reference', 'https://aaaaa13.edu/'];
        $csvRows[] = ['Active Term', $trends['active_term']['term_name'] ?? 'Fall 2026'];
        $csvRows[] = ['Baseline Term', $trends['prior_term']['term_name'] ?? 'Spring 2026'];
        $csvRows[] = ['Mean Score Delta', ($trends['deltas']['mean_score_delta'] ?? 0) >= 0 ? '+' . ($trends['deltas']['mean_score_delta'] ?? 0) : (string) ($trends['deltas']['mean_score_delta'] ?? 0)];
        $csvRows[] = ['Negative Sentiment Shift', ($trends['deltas']['negative_sentiment_shift_pct'] ?? 0) . '%'];
        $csvRows[] = ['Action Plan Resolution Rate', ($trends['deltas']['action_plan_resolution_rate_pct'] ?? 100) . '%'];
        $csvRows[] = [''];
        $csvRows[] = ['PEDAGOGICAL CLUSTER', 'ACTIVE SCORE', 'PRIOR BASELINE', 'DELTA', 'STATUS'];

        foreach ($trends['cluster_comparisons'] ?? [] as $cc) {
            $csvRows[] = [
                $cc['cluster'],
                $cc['active_score'],
                $cc['prior_score'],
                $cc['delta'],
                $cc['status'],
            ];
        }

        $csvRows[] = [''];
        $csvRows[] = ['HISTORICAL SEMESTER', 'MEAN RATING', 'NEGATIVE SENTIMENT %', 'ACTION PLAN RESOLUTION %'];
        foreach ($trends['historical_semesters'] ?? [] as $hs) {
            $csvRows[] = [
                $hs['term_name'] ?? $hs['term_code'],
                $hs['mean_score'],
                $hs['negative_pct'] . '%',
                $hs['action_plan_resolution_pct'] . '%',
            ];
        }

        $csvContent = $this->rosterService->exportAccreditationCsv($csvRows);

        return new JsonResponse([
            'format' => 'aaccup_dossier_bundle',
            'department_id' => is_array($requestedDeptId) ? null : $requestedDeptId,
            'summary' => $trends,
            'csv_content' => $csvContent,
            'filename' => 'AAAAA13-AACCUP-Area-III-Dossier-AY2026-2027.csv',
        ], 200);
    }

    public function completionHeatmap(Request $request): JsonResponse
    {
        [$requestedDeptId, $scopeError] = $this->resolveScopedDepartmentId($request);
        if ($scopeError !== null) {
            return $scopeError;
        }

        $heatmap = $this->evaluationService->getDepartmentCompletionHeatmap($requestedDeptId);
        return new JsonResponse($heatmap, 200);
    }

    public function complianceAudit(Request $request): JsonResponse
    {
        [$requestedDeptId, $scopeError] = $this->resolveScopedDepartmentId($request);
        if ($scopeError !== null) {
            return $scopeError;
        }

        $audit = $this->evaluationService->getAccreditationComplianceAudit($requestedDeptId);
        return new JsonResponse($audit, 200);
    }

    public function evidenceDossier(Request $request): JsonResponse
    {
        [$requestedDeptId, $scopeError] = $this->resolveScopedDepartmentId($request);
        if ($scopeError !== null) {
            return $scopeError;
        }

        $dossier = $this->evaluationService->getAccreditationEvidenceDossier($requestedDeptId);
        return new JsonResponse($dossier, 200);
    }

    public function compileAccreditationDossier(Request $request): JsonResponse
    {
        [$requestedDeptId, $scopeError] = $this->resolveScopedDepartmentId($request);
        if ($scopeError !== null) {
            return $scopeError;
        }

        $bundle = $this->evaluationService->compileExecutiveAccreditationDossierBundle($requestedDeptId);
        return new JsonResponse($bundle, 200);
    }

    public function performanceQuartiles(Request $request): JsonResponse
    {
        [$requestedDeptId, $scopeError] = $this->resolveScopedDepartmentId($request);
        if ($scopeError !== null) {
            return $scopeError;
        }

        $quartiles = $this->evaluationService->getFacultyPerformanceQuartiles($requestedDeptId);
        return new JsonResponse($quartiles, 200);
    }

    /**
     * Evaluation Participation Tracker for supervisors: which students in scope are done vs. pending.
     * Chairs are contained to their department and deans to their college via resolveScopedDepartmentId.
     * Returns completion status only; never links a student to any submitted response.
     */
    public function participationTracker(Request $request): JsonResponse
    {
        [$requestedDeptId, $scopeError] = $this->resolveScopedDepartmentId($request);
        if ($scopeError !== null) {
            return $scopeError;
        }

        $termId = $request->input('academic_term_id') !== null ? (int) $request->input('academic_term_id') : null;

        try {
            $data = (new \App\Services\AcademicSetupService($this->evaluationService->getPdo()))
                ->getPendingStudentsTracker($termId, $requestedDeptId);
            return new JsonResponse($data, 200);
        } catch (\RuntimeException $e) {
            return new JsonResponse([
                'success' => false,
                'code' => 'ACADEMIC_TERM_NOT_FOUND',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function insightsSearch(Request $request): JsonResponse
    {
        [$requestedDeptId, $scopeError] = $this->resolveScopedDepartmentId($request);
        if ($scopeError !== null) {
            return $scopeError;
        }

        $query = (string) ($request->input('q') ?? $request->input('query') ?? '');
        $valence = $request->input('valence') !== null ? (string) $request->input('valence') : null;
        $cluster = $request->input('cluster') !== null ? (string) $request->input('cluster') : null;

        $results = $this->evaluationService->searchAnonymizedInsights($query, $requestedDeptId, $valence, $cluster);
        return new JsonResponse($results, 200);
    }
}
