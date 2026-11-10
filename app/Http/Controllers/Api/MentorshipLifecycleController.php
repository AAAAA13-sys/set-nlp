<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\MentorshipLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class MentorshipLifecycleController extends Controller
{
    private MentorshipLifecycleService $service;

    public function __construct(?MentorshipLifecycleService $service = null)
    {
        $this->service = $service ?? new MentorshipLifecycleService();
    }

    /**
     * Get faculty's own active action plan.
     */
    public function getFacultyActionPlan(Request $request): JsonResponse
    {
        $user = $request->user();
        $facultyId = (int) ($user->id ?? $request->input('faculty_id', 0));

        if ($facultyId <= 0) {
            return new JsonResponse([
                'authenticated' => false,
                'code' => 'UNAUTHENTICATED',
                'message' => 'Faculty authentication required.',
            ], 401);
        }

        $plan = $this->service->getFacultyActionPlan($facultyId);
        if ($plan === null) {
            return new JsonResponse([
                'found' => false,
                'message' => 'No active pedagogical action plan assigned to this instructor.',
            ], 200);
        }

        return new JsonResponse([
            'found' => true,
            'action_plan' => $plan,
        ], 200);
    }

    /**
     * Submit faculty self-reflection and milestone commitments.
     */
    public function submitFacultyReflection(Request $request): JsonResponse
    {
        $user = $request->user();
        $facultyId = (int) ($user->id ?? $request->input('faculty_id', 0));

        if ($facultyId <= 0) {
            return new JsonResponse([
                'code' => 'UNAUTHENTICATED',
                'message' => 'Faculty authentication required.',
            ], 401);
        }

        $reflection = trim((string) $request->input('faculty_reflection', ''));
        if ($reflection === '') {
            return new JsonResponse([
                'code' => 'INVALID_REFLECTION',
                'message' => 'Self-reflection narrative cannot be empty.',
            ], 422);
        }

        $milestones = is_array($request->input('milestones')) ? $request->input('milestones') : [];

        try {
            $updated = $this->service->submitFacultyReflection($facultyId, $reflection, $milestones);

            return new JsonResponse([
                'success' => true,
                'action_plan' => $updated,
                'message' => 'Action plan reflection submitted successfully for Department Chair review.',
            ], 200);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Internal server error processing action plan submission: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Department Chair assigns senior faculty peer mentor and approves action plan.
     */
    public function assignMentorAndApprove(Request $request): JsonResponse
    {
        $user = $request->user();
        $chairUserId = (int) ($user->id ?? 1);

        $planId = trim((string) $request->input('plan_id', ''));
        $mentorFacultyId = (int) $request->input('mentor_faculty_id', 0);
        $chairNotes = $request->input('chair_notes') !== null ? (string) $request->input('chair_notes') : null;

        if ($planId === '' || $mentorFacultyId <= 0) {
            return new JsonResponse([
                'code' => 'INVALID_PAYLOAD',
                'message' => 'plan_id and a valid mentor_faculty_id are required.',
            ], 422);
        }

        try {
            $updated = $this->service->assignMentorAndApprove($planId, $mentorFacultyId, $chairNotes, $chairUserId);

            return new JsonResponse([
                'success' => true,
                'action_plan' => $updated,
                'message' => 'Peer mentor successfully assigned and action plan approved.',
            ], 200);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update progress of a specific milestone.
     */
    public function updateMilestoneProgress(Request $request): JsonResponse
    {
        $planId = trim((string) $request->input('plan_id', ''));
        $milestoneId = trim((string) $request->input('milestone_id', ''));
        $completed = (bool) $request->input('is_completed', false);
        $notes = $request->input('notes') !== null ? (string) $request->input('notes') : null;

        if ($planId === '' || $milestoneId === '') {
            return new JsonResponse([
                'code' => 'INVALID_PAYLOAD',
                'message' => 'plan_id and milestone_id are required.',
            ], 422);
        }

        try {
            $updated = $this->service->updateMilestoneProgress($planId, $milestoneId, $completed, $notes);

            return new JsonResponse([
                'success' => true,
                'action_plan' => $updated,
                'message' => 'Milestone progress updated.',
            ], 200);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Chair or Dean signs off and resolves an action plan.
     */
    public function signOffAndResolve(Request $request): JsonResponse
    {
        $user = $request->user();
        $signedOffByUserId = (int) ($user->id ?? 1);

        $planId = trim((string) $request->input('plan_id', ''));
        $resolutionNotes = trim((string) $request->input('resolution_notes', ''));

        if ($planId === '' || $resolutionNotes === '') {
            return new JsonResponse([
                'code' => 'INVALID_PAYLOAD',
                'message' => 'plan_id and resolution_notes are required for sign-off.',
            ], 422);
        }

        try {
            $updated = $this->service->signOffAndResolve($planId, $resolutionNotes, $signedOffByUserId);

            return new JsonResponse([
                'success' => true,
                'action_plan' => $updated,
                'message' => 'Action plan successfully resolved and certified.',
            ], 200);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Chair overview of departmental mentorship.
     */
    public function getDepartmentMentorshipDashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $deptId = (int) ($request->input('department_id') ?? ($user->department_id ?? 1));

        $data = $this->service->getDepartmentMentorshipDashboard($deptId);

        return new JsonResponse($data, 200);
    }
}
