<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Services\DecoupledEvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PDO;

class AuthController extends Controller
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = (new DecoupledEvaluationService($pdo))->getPdo();
    }

    public function login(Request $request): JsonResponse
    {
        $identifier = trim((string) ($request->input('identifier') ?? $request->input('email') ?? $request->input('institutional_id') ?? ''));
        $password = (string) $request->input('password', '');

        if ($identifier === '' || $password === '') {
            return new JsonResponse([
                'authenticated' => false,
                'code' => 'MISSING_CREDENTIALS',
                'message' => 'Institutional identifier/email and password are required.',
            ], 422);
        }

        $stmt = $this->pdo->prepare('
            SELECT id, institutional_id, name, email, password, role, department_id, is_active
            FROM users
            WHERE email = :id OR institutional_id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($user) || !password_verify($password, (string) $user['password'])) {
            return new JsonResponse([
                'authenticated' => false,
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Invalid institutional credentials.',
            ], 401);
        }

        $roleEnum = UserRole::from((string) $user['role']);

        return new JsonResponse([
            'authenticated' => true,
            'user' => [
                'id' => (int) $user['id'],
                'institutional_id' => (string) $user['institutional_id'],
                'name' => (string) $user['name'],
                'email' => (string) $user['email'],
                'role' => $roleEnum->value,
                'role_label' => $roleEnum->label(),
                'department_id' => $user['department_id'] !== null ? (int) $user['department_id'] : null,
            ],
            'workspace_route' => $this->resolveWorkspaceRoute($roleEnum),
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return new JsonResponse([
                'authenticated' => false,
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        $roleValue = is_string($user->role ?? null) ? $user->role : 'student';
        $roleEnum = UserRole::from($roleValue);

        return new JsonResponse([
            'authenticated' => true,
            'user' => [
                'id' => (int) ($user->id ?? 0),
                'institutional_id' => (string) ($user->institutional_id ?? ''),
                'name' => (string) ($user->name ?? ''),
                'email' => (string) ($user->email ?? ''),
                'role' => $roleEnum->value,
                'role_label' => $roleEnum->label(),
                'department_id' => isset($user->department_id) ? (int) $user->department_id : null,
            ],
            'workspace_route' => $this->resolveWorkspaceRoute($roleEnum),
        ], 200);
    }

    public function logout(): JsonResponse
    {
        return new JsonResponse([
            'authenticated' => false,
            'message' => 'Session terminated cleanly.',
        ], 200);
    }

    private function resolveWorkspaceRoute(UserRole $role): string
    {
        return match ($role) {
            UserRole::ADMIN => '/admin/rosters',
            UserRole::DEAN, UserRole::CHAIR => '/dean/dashboard',
            UserRole::FACULTY => '/faculty/dashboard',
            UserRole::STUDENT => '/student/queue',
        };
    }
}
