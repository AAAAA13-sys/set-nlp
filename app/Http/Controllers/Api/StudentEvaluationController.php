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
        $studentId = $user !== null ? (int) ($user->id ?? 0) : (int) $request->input('student_id', 0);
        $termId = $request->input('academic_term_id') !== null ? (int) $request->input('academic_term_id') : null;

        $queueData = $this->evaluationService->getStudentQueue($studentId, $termId);
        return new JsonResponse($queueData, 200);
    }

    public function startSession(Request $request, int $courseSectionId = 0): JsonResponse
    {
        $user = $request->user();
        $studentId = $user !== null ? (int) ($user->id ?? 0) : (int) $request->input('student_id', 0);
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
        $studentId = $user !== null ? (int) ($user->id ?? 0) : (int) $request->input('student_id', 0);

        if ($studentId <= 0) {
            return new JsonResponse([
                'accepted' => false,
                'status' => 'REJECTED',
                'code' => 'UNAUTHENTICATED',
                'http_status' => 401,
                'message' => 'Valid authenticated student identity is required to submit evaluations.',
            ], 401);
        }

        // Explicit allowlist: sanitize and construct safe domain payload.
        // Strips any client-supplied spoofing attempts (e.g. now_unix, faculty_id, academic_term_id).
        $sectionId = (int) $request->input('course_section_id', 0);
        $submissionKey = trim((string) $request->input('submission_key', ''));
        $rawRatings = $request->input('ratings');
        $ratings = is_array($rawRatings) ? array_values($rawRatings) : [];
        $dwellSeconds = (int) $request->input('dwell_time_seconds', 0);
        $confirmUniform = filter_var($request->input('confirm_uniform', false), FILTER_VALIDATE_BOOLEAN);
        $commentText = (string) $request->input('comment_text', '');
        $profQuestions = $request->input('professor_questions');
        $startToken = $request->input('start_token') !== null ? (string) $request->input('start_token') : null;

        $safePayload = [
            'student_id' => $studentId,
            'course_section_id' => $sectionId,
            'submission_key' => $submissionKey,
            'ratings' => $ratings,
            'dwell_time_seconds' => $dwellSeconds,
            'confirm_uniform' => $confirmUniform,
            'comment_text' => $commentText,
            'professor_questions' => is_array($profQuestions) ? $profQuestions : null,
            'start_token' => $startToken,
        ];

        $result = $this->evaluationService->submitEvaluation($safePayload);
        $status = (int) ($result['http_status'] ?? 200);

        return new JsonResponse($result, $status);
    }

    public function permit(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentId = $user !== null ? (int) ($user->id ?? 0) : (int) $request->input('student_id', 0);
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

    public function verifyPermit(Request $request): JsonResponse
    {
        $token = trim((string) ($request->input('token') ?? $request->input('exam_permit_token', '')));
        $rawInst = $request->input('institutional_id')
            ?? $request->input('student_id')
            ?? ($request->user()->institutional_id ?? null);
        $instId = $rawInst !== null && trim((string) $rawInst) !== '' ? trim((string) $rawInst) : null;

        $result = $this->evaluationService->verifyExaminationPermit($token, $instId);
        $status = $result['verified'] ? 200 : 422;

        return new JsonResponse($result, $status);
    }
}
