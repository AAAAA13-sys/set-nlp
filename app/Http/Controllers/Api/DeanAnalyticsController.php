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

    public function accreditationSummary(): JsonResponse
    {
        return new JsonResponse($this->qualityService->getPlpAccreditationSummary(), 200);
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
}
