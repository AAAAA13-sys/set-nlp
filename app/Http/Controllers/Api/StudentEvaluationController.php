<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\DecoupledEvaluationService;
use App\Services\IntegrityValidatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class StudentEvaluationController extends Controller
{
    private DecoupledEvaluationService $evaluationService;
    private IntegrityValidatorService $validator;

    public function __construct(
        ?DecoupledEvaluationService $evaluationService = null,
        ?IntegrityValidatorService $validator = null
    ) {
        $this->validator = $validator ?? new IntegrityValidatorService();
        $this->evaluationService = $evaluationService ?? new DecoupledEvaluationService(null, $this->validator);
    }

    public function queue(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentId = (int) ($user->id ?? $request->input('student_id', 0));
        $termId = $request->input('academic_term_id') !== null ? (int) $request->input('academic_term_id') : null;

        $queueData = $this->evaluationService->getStudentQueue($studentId, $termId);
        return new JsonResponse($queueData, 200);
    }

    public function startSession(Request $request, int $courseSectionId = 0): JsonResponse
    {
        $user = $request->user();
        $studentId = (int) ($user->id ?? $request->input('student_id', 0));
        $sectionId = $courseSectionId > 0 ? $courseSectionId : (int) $request->input('course_section_id', 0);

        $token = $this->validator->issueStartToken($studentId, $sectionId);

        return new JsonResponse([
            'student_id' => $studentId,
            'course_section_id' => $sectionId,
            'start_token' => $token,
            'min_dwell_seconds' => IntegrityValidatorService::MIN_DWELL_SECONDS_20_ITEMS,
        ], 200);
    }

    public function submit(Request $request): JsonResponse
    {
        $user = $request->user();
        $payload = $request->all();
        if ($user !== null && !isset($payload['student_id'])) {
            $payload['student_id'] = (int) ($user->id ?? 0);
        }

        $result = $this->evaluationService->submitEvaluation($payload);
        $status = (int) ($result['http_status'] ?? 200);

        return new JsonResponse($result, $status);
    }

    public function permit(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentId = (int) ($user->id ?? $request->input('student_id', 0));
        $queue = $this->evaluationService->getStudentQueue($studentId);

        return new JsonResponse([
            'student_id' => $studentId,
            'overall_clearance_status' => $queue['overall_clearance_status'],
            'exam_permit_unlocked' => $queue['exam_permit_unlocked'],
            'exam_permit_token' => $queue['exam_permit_token'],
            'completed_count' => $queue['completed_count'],
            'total_count' => $queue['total_count'],
        ], 200);
    }
}
