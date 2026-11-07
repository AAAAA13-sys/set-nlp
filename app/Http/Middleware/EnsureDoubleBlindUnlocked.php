<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\DecoupledEvaluationService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnsureDoubleBlindUnlocked
{
    public function __construct(
        private ?DecoupledEvaluationService $evaluationService = null
    ) {
    }

    /**
     * Enforce Double-Blind Faculty Visibility Lock:
     * CanViewEvaluation = (term_grades_submitted == true) AND (registrar_lock == true).
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $service = $this->evaluationService ?? new DecoupledEvaluationService();
        $sectionId = (int) ($request->route('sectionId') ?? $request->input('course_section_id', 0));

        if ($sectionId > 0) {
            $user = $request->user();
            $facultyId = ($user !== null && ($user->role ?? '') === 'faculty') ? (int) ($user->id ?? 0) : null;
            $report = $service->getFacultySectionReport($sectionId, $facultyId ?: null);
            if (($report['locked'] ?? false) === true) {
                return new JsonResponse($report, (int) ($report['http_status'] ?? 403));
            }
        } else {
            $gradesSubmitted = $request->boolean('term_grades_submitted', false);
            $registrarLock = $request->boolean('registrar_lock', false);
            if (!$service->canFacultyViewSection($gradesSubmitted, $registrarLock)) {
                return new JsonResponse([
                    'locked' => true,
                    'code' => 'DOUBLE_BLIND_LOCKED',
                    'term_grades_submitted' => $gradesSubmitted,
                    'registrar_lock' => $registrarLock,
                    'message' => 'Double-blind protection active: evaluation results remain locked until both instructor term grades are submitted and Registrar lock is enabled.',
                ], 403);
            }
        }

        return $next($request);
    }
}
