<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnsureRole
{
    /**
     * Enforce five-role RBAC isolation on API endpoints.
     */
    public function handle(Request $request, Closure $next, string ...$allowedRoles): mixed
    {
        $user = $request->user();
        if ($user === null) {
            return new JsonResponse([
                'authenticated' => false,
                'code' => 'UNAUTHENTICATED',
                'message' => 'Authentication is required to access this EvaluaTech resource.',
            ], 401);
        }

        $userRole = is_string($user->role ?? null)
            ? $user->role
            : ($user->role instanceof UserRole ? $user->role->value : '');

        $normalizedAllowed = [];
        foreach ($allowedRoles as $roleArg) {
            foreach (explode(',', $roleArg) as $part) {
                $trimmed = trim($part);
                if ($trimmed !== '') {
                    $normalizedAllowed[] = $trimmed;
                }
            }
        }

        if (!in_array($userRole, $normalizedAllowed, true)) {
            return new JsonResponse([
                'authorized' => false,
                'code' => 'FORBIDDEN_ROLE',
                'role' => $userRole,
                'allowed_roles' => $normalizedAllowed,
                'message' => 'Your institutional role is not authorized to perform this action.',
            ], 403);
        }

        return $next($request);
    }

    /**
     * Verify departmental scope: Deans and Admins have college-wide visibility; Chairs are scoped to their department_id.
     */
    public function canAccessDepartment(object $user, ?int $targetDepartmentId): bool
    {
        $role = is_string($user->role ?? null)
            ? $user->role
            : ($user->role instanceof UserRole ? $user->role->value : '');

        if ($role === UserRole::ADMIN->value || $role === UserRole::DEAN->value) {
            return true;
        }

        if ($role === UserRole::CHAIR->value) {
            $userDept = isset($user->department_id) ? (int) $user->department_id : null;
            return $userDept !== null && $targetDepartmentId !== null && $userDept === $targetDepartmentId;
        }

        return false;
    }
}
