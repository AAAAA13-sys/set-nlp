<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Services\CampaignWarRoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class CampaignWarRoomController extends Controller
{
    private CampaignWarRoomService $warRoomService;

    public function __construct(?CampaignWarRoomService $warRoomService = null)
    {
        $this->warRoomService = $warRoomService ?? new CampaignWarRoomService();
    }

    private function verifyAdminOrSupervisorRole(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if ($user !== null) {
            $role = is_string($user->role ?? null)
                ? $user->role
                : ($user->role instanceof UserRole ? $user->role->value : '');
            if (!in_array($role, [UserRole::ADMIN->value, UserRole::DEAN->value, UserRole::CHAIR->value], true)) {
                return new JsonResponse([
                    'authorized' => false,
                    'code' => 'FORBIDDEN_ROLE',
                    'message' => 'Only Academic Administrators and Supervisors may access Campaign War Room operations.',
                ], 403);
            }
        }
        return null;
    }

    public function preflight(Request $request): JsonResponse
    {
        if ($authError = $this->verifyAdminOrSupervisorRole($request)) {
            return $authError;
        }

        try {
            $termId = $request->input('academic_term_id') !== null ? (int) $request->input('academic_term_id') : null;
            $audit = $this->warRoomService->getPreFlightAudit($termId);
            return new JsonResponse($audit, 200);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 'PREFLIGHT_AUDIT_FAILED',
            ], 500);
        }
    }

    public function preFlightAudit(Request $request): JsonResponse
    {
        return $this->preflight($request);
    }

    public function leaderboard(Request $request): JsonResponse
    {
        if ($authError = $this->verifyAdminOrSupervisorRole($request)) {
            return $authError;
        }

        try {
            $termId = $request->input('academic_term_id') !== null ? (int) $request->input('academic_term_id') : null;
            $data = $this->warRoomService->getTurnoutLeaderboard($termId);
            return new JsonResponse($data, 200);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 'LEADERBOARD_FAILED',
            ], 500);
        }
    }

    public function turnoutLeaderboard(Request $request): JsonResponse
    {
        return $this->leaderboard($request);
    }

    public function reminderBatch(Request $request): JsonResponse
    {
        if ($authError = $this->verifyAdminOrSupervisorRole($request)) {
            return $authError;
        }

        try {
            $termId = $request->input('academic_term_id') !== null ? (int) $request->input('academic_term_id') : null;
            $departmentId = $request->input('department_id') !== null ? (int) $request->input('department_id') : null;

            // If user is Department Chair, strictly constrain to their department
            $user = $request->user();
            if ($user !== null) {
                $role = is_string($user->role ?? null) ? $user->role : ($user->role instanceof UserRole ? $user->role->value : '');
                if ($role === UserRole::CHAIR->value && !empty($user->department_id)) {
                    $departmentId = (int) $user->department_id;
                }
            }

            $batch = $this->warRoomService->generatePendingReminderBatch($termId, $departmentId);
            return new JsonResponse($batch, 200);
        } catch (Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 'REMINDER_BATCH_FAILED',
            ], 500);
        }
    }

    public function pendingReminderBatch(Request $request): JsonResponse
    {
        return $this->reminderBatch($request);
    }
}
