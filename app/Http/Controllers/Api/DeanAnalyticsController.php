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
        $this->qualityService = $qualityService ?? new QualityMetricsService();
        $this->rosterService = $rosterService ?? new RosterImportService($this->evaluationService->getPdo());
    }

    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        $requestedDeptId = $request->input('department_id') !== null ? (int) $request->input('department_id') : null;

        if ($user !== null) {
            $role = is_string($user->role ?? null)
                ? $user->role
                : ($user->role instanceof UserRole ? $user->role->value : '');

            if ($role === UserRole::CHAIR->value) {
                $chairDeptId = isset($user->department_id) ? (int) $user->department_id : null;
                if ($requestedDeptId !== null && $requestedDeptId !== $chairDeptId) {
                    return new JsonResponse([
                        'authorized' => false,
                        'code' => 'FORBIDDEN_DEPARTMENT_SCOPE',
                        'message' => 'Department Chairs are strictly scoped to their assigned department.',
                    ], 403);
                }
                $requestedDeptId = $chairDeptId;
            }
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
        $requestedDeptId = $request->input('department_id') !== null ? (int) $request->input('department_id') : null;
        $overview = $this->evaluationService->getDeanDashboardMetrics($requestedDeptId);
        $synthesis = $overview['qualitative_synthesis'] ?? [];

        return new JsonResponse($synthesis, 200);
    }

    public function exportQualitativeCsv(Request $request): JsonResponse
    {
        $requestedDeptId = $request->input('department_id') !== null ? (int) $request->input('department_id') : null;
        $overview = $this->evaluationService->getDeanDashboardMetrics($requestedDeptId);
        $synthesis = $overview['qualitative_synthesis'] ?? [];
        $csv = $this->rosterService->exportQualitativeSynthesisCsv($synthesis);

        return new JsonResponse([
            'format' => 'csv',
            'department_id' => $requestedDeptId,
            'csv_content' => $csv,
        ], 200);
    }

    public function accreditationSummary(): JsonResponse
    {
        $actionPlanSummary = $this->evaluationService->getActionPlansSummary();
        return new JsonResponse($this->qualityService->getPlpAccreditationSummary($actionPlanSummary), 200);
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
        $user = $request->user();
        $requestedDeptId = $request->input('department_id') !== null ? (int) $request->input('department_id') : null;

        if ($user !== null) {
            $role = is_string($user->role ?? null)
                ? $user->role
                : ($user->role instanceof UserRole ? $user->role->value : '');

            if ($role === UserRole::CHAIR->value) {
                $chairDeptId = isset($user->department_id) ? (int) $user->department_id : null;
                if ($requestedDeptId !== null && $requestedDeptId !== $chairDeptId) {
                    return new JsonResponse([
                        'authorized' => false,
                        'code' => 'FORBIDDEN_DEPARTMENT_SCOPE',
                        'message' => 'Department Chairs are strictly scoped to their assigned department.',
                    ], 403);
                }
                $requestedDeptId = $chairDeptId;
            }
        }

        $trends = $this->evaluationService->getLongitudinalSemesterTrends($requestedDeptId);
        return new JsonResponse($trends, 200);
    }

    public function exportAccreditationDossier(Request $request): JsonResponse
    {
        $requestedDeptId = $request->input('department_id') !== null ? (int) $request->input('department_id') : null;
        $trends = $this->evaluationService->getLongitudinalSemesterTrends($requestedDeptId);

        $csvRows = [];
        $csvRows[] = ['ACCREDITATION MATRIX', 'AACCUP Area III: Faculty Development & Instructional Competence'];
        $csvRows[] = ['Institution', 'PAMANTASAN NG LUNGSOD NG PASIG (PLP)'];
        $csvRows[] = ['Reference', 'https://plpasig.edu.ph/'];
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
            'department_id' => $requestedDeptId,
            'summary' => $trends,
            'csv_content' => $csvContent,
            'filename' => 'PLP-AACCUP-Area-III-Dossier-AY2026-2027.csv',
        ], 200);
    }

    public function completionHeatmap(Request $request): JsonResponse
    {
        $user = $request->user();
        $requestedDeptId = $request->input('department_id') !== null ? (int) $request->input('department_id') : null;

        if ($user !== null) {
            $role = is_string($user->role ?? null)
                ? $user->role
                : ($user->role instanceof UserRole ? $user->role->value : '');

            if ($role === UserRole::CHAIR->value) {
                $chairDeptId = isset($user->department_id) ? (int) $user->department_id : null;
                if ($requestedDeptId !== null && $requestedDeptId !== $chairDeptId) {
                    return new JsonResponse([
                        'authorized' => false,
                        'code' => 'FORBIDDEN_DEPARTMENT_SCOPE',
                        'message' => 'Department Chairs are strictly scoped to their assigned department.',
                    ], 403);
                }
                $requestedDeptId = $chairDeptId;
            }
        }

        $heatmap = $this->evaluationService->getDepartmentCompletionHeatmap($requestedDeptId);
        return new JsonResponse($heatmap, 200);
    }

    public function complianceAudit(Request $request): JsonResponse
    {
        $requestedDeptId = $request->input('department_id') !== null ? (int) $request->input('department_id') : null;
        $audit = $this->evaluationService->getAccreditationComplianceAudit($requestedDeptId);
        return new JsonResponse($audit, 200);
    }
}
