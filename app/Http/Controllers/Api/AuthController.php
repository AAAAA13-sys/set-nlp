<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Services\DecoupledEvaluationService;
use App\Services\SecurityAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PDO;

class AuthController extends Controller
{
    public const TOKEN_TTL_SECONDS = 604800; // 7 days
    public const RATE_LIMIT_MAX_ATTEMPTS = 8;
    public const RATE_LIMIT_WINDOW_SECONDS = 60;

    /**
     * Precomputed bcrypt hash used for constant-time verification when an identifier does not exist (#13).
     */
    private const DUMMY_BCRYPT_HASH = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

    private PDO $pdo;
    private SecurityAuditService $securityAudit;

    public function __construct(
        ?PDO $pdo = null,
        private ?string $appSecret = null
    ) {
        $this->pdo = (new DecoupledEvaluationService($pdo))->getPdo();
        $this->securityAudit = new SecurityAuditService();
        $envSecret = getenv('EVALUATECH_SIGNING_SECRET');
        $this->appSecret = ($this->appSecret !== null && $this->appSecret !== '')
            ? $this->appSecret
            : ((is_string($envSecret) && trim($envSecret) !== '') ? trim($envSecret) : 'evaluatech-hmac-signing-secret-2026');
    }

    public function login(Request $request): JsonResponse
    {
        $rawIdentifier = $request->input('identifier') ?? $request->input('email') ?? $request->input('institutional_id') ?? '';
        $rawPassword = $request->input('password', '');

        // Reject NoSQL/JSON array or object injection on credential fields (#4, #8)
        if (is_array($rawIdentifier) || is_object($rawIdentifier) || is_array($rawPassword) || is_object($rawPassword)) {
            $this->securityAudit->logEvent(
                $this->pdo,
                'AUTH_OPERATOR_INJECTION_BLOCKED',
                'WARN',
                ['reason' => 'non_scalar_credential_payload'],
                null,
                '/api/auth/login'
            );
            return new JsonResponse([
                'authenticated' => false,
                'code' => 'OPERATOR_INJECTION_REJECTED',
                'message' => 'Scalar string credentials are required.',
            ], 422);
        }

        $identifier = trim((string) $rawIdentifier);
        $password = (string) $rawPassword;

        if ($identifier === '' || $password === '') {
            return new JsonResponse([
                'authenticated' => false,
                'code' => 'MISSING_CREDENTIALS',
                'message' => 'Institutional ID or university email and password are required.',
            ], 422);
        }

        $bucketKey = $this->computeRateLimitBucket($identifier);
        $rateState = $this->checkRateLimit($bucketKey);
        if ($rateState['blocked']) {
            $this->securityAudit->logEvent(
                $this->pdo,
                'AUTH_RATE_LIMITED',
                'WARN',
                ['retry_after_seconds' => $rateState['retry_after']],
                null,
                '/api/auth/login'
            );
            return new JsonResponse([
                'authenticated' => false,
                'code' => 'RATE_LIMITED',
                'retry_after' => $rateState['retry_after'],
                'message' => 'Too many failed sign-in attempts. Please wait 60 seconds before retrying.',
            ], 429, ['Retry-After' => (string) $rateState['retry_after']]);
        }

        $user = $this->findUserByIdentifier($identifier);
        $storedHash = is_array($user) ? (string) ($user['password'] ?? $user['password_hash'] ?? '') : '';

        // Constant-time verification even when user does not exist (#13 Account Enumeration Defense)
        $hashToVerify = $storedHash !== '' ? $storedHash : self::DUMMY_BCRYPT_HASH;
        $passwordVerified = password_verify($password, $hashToVerify);

        if (!is_array($user) || $storedHash === '' || !$passwordVerified) {
            $postFail = $this->recordFailedAttempt($bucketKey);
            if ($postFail['blocked']) {
                $this->securityAudit->logEvent(
                    $this->pdo,
                    'AUTH_RATE_LIMITED',
                    'WARN',
                    ['retry_after_seconds' => $postFail['retry_after']],
                    null,
                    '/api/auth/login'
                );
                return new JsonResponse([
                    'authenticated' => false,
                    'code' => 'RATE_LIMITED',
                    'retry_after' => $postFail['retry_after'],
                    'message' => 'Too many failed sign-in attempts. Please wait 60 seconds before retrying.',
                ], 429, ['Retry-After' => (string) $postFail['retry_after']]);
            }

            $this->securityAudit->logEvent(
                $this->pdo,
                'AUTH_LOGIN_FAILED',
                'WARN',
                ['outcome' => 'invalid_credentials'],
                null,
                '/api/auth/login'
            );

            return new JsonResponse([
                'authenticated' => false,
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Invalid institutional ID/email or password.',
            ], 401);
        }

        if (isset($user['is_active']) && (int) $user['is_active'] === 0) {
            return new JsonResponse([
                'authenticated' => false,
                'code' => 'ACCOUNT_INACTIVE',
                'message' => 'This institutional account is currently inactive. Contact the Office of the University Registrar.',
            ], 403);
        }

        $this->resetRateLimit($bucketKey);

        $roleEnum = UserRole::from((string) $user['role']);
        $token = $this->issueAuthToken($user);

        $this->securityAudit->logEvent(
            $this->pdo,
            'AUTH_LOGIN_SUCCESS',
            'INFO',
            ['workspace' => $this->resolveWorkspaceRoute($roleEnum)],
            $roleEnum->value,
            '/api/auth/login'
        );

        return new JsonResponse([
            'authenticated' => true,
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => self::TOKEN_TTL_SECONDS,
            'user' => $this->formatUserPayload($user, $roleEnum),
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
                'message' => 'Authentication required.',
            ], 401);
        }

        $roleValue = is_string($user->role ?? null)
            ? $user->role
            : ($user->role instanceof UserRole ? $user->role->value : 'student');
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
                'department_id' => isset($user->department_id) && $user->department_id !== null ? (int) $user->department_id : null,
                'department_name' => isset($user->department_name) ? (string) $user->department_name : null,
                'department_code' => isset($user->department_code) ? (string) $user->department_code : null,
            ],
            'workspace_route' => $this->resolveWorkspaceRoute($roleEnum),
        ], 200);
    }

    public function logout(?Request $request = null): JsonResponse
    {
        if ($request !== null) {
            $header = (string) ($request->header('Authorization') ?? $request->header('authorization') ?? '');
            if (preg_match('/^\s*Bearer\s+(.+?)\s*$/i', $header, $matches) === 1) {
                $this->revokeAuthToken($matches[1]);
            }
        }

        $this->securityAudit->logEvent(
            $this->pdo,
            'AUTH_LOGOUT_REVOKED',
            'INFO',
            ['revoked' => true],
            null,
            '/api/auth/logout'
        );

        return new JsonResponse([
            'authenticated' => false,
            'revoked' => true,
            'message' => 'Session terminated cleanly.',
        ], 200);
    }

    public function revokeAuthToken(string $token): void
    {
        $trimmed = trim($token);
        if ($trimmed === '') {
            return;
        }
        try {
            $this->pdo->exec('
                CREATE TABLE IF NOT EXISTS revoked_auth_tokens (
                    token_hash VARCHAR(64) PRIMARY KEY,
                    revoked_at VARCHAR(32) NOT NULL
                )
            ');
            $stmt = $this->pdo->prepare('
                INSERT OR IGNORE INTO revoked_auth_tokens (token_hash, revoked_at)
                VALUES (:hash, :revoked_at)
            ');
            $stmt->execute([
                ':hash' => hash('sha256', $trimmed),
                ':revoked_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Non-sqlite or read-only fallback
        }
    }

    /**
     * Issue an HMAC-SHA256 signed institutional session token (`EVT.<payload>.<signature>`).
     *
     * @param array<string, mixed> $user
     */
    public function issueAuthToken(array $user, ?int $issuedAt = null): string
    {
        $now = $issuedAt ?? time();
        $claims = [
            'alg' => 'HS256',
            'iss' => 'EvaluaTech-PLP-Auth',
            'jti' => bin2hex(random_bytes(8)),
            'sub' => (int) $user['id'],
            'inst' => (string) $user['institutional_id'],
            'role' => (string) $user['role'],
            'dept' => $user['department_id'] !== null ? (int) $user['department_id'] : null,
            'iat' => $now,
            'exp' => $now + self::TOKEN_TTL_SECONDS,
        ];

        $payloadB64 = rtrim(strtr(base64_encode((string) json_encode($claims, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
        $sigB64 = rtrim(strtr(base64_encode(hash_hmac('sha256', 'EVT.' . $payloadB64, (string) $this->appSecret, true)), '+/', '-_'), '=');

        return 'EVT.' . $payloadB64 . '.' . $sigB64;
    }

    /**
     * Verify a raw `EVT.<payload>.<signature>` token and hydrate the live DB user record.
     * Explicitly rejects unsigned or `alg:none` JWT tokens (#15).
     */
    public function verifyAuthToken(string $token, ?int $nowUnix = null): ?object
    {
        $trimmed = trim($token);
        if ($trimmed === '' || !str_starts_with($trimmed, 'EVT.')) {
            return null;
        }

        $parts = explode('.', $trimmed);
        if (count($parts) !== 3 || $parts[1] === '' || $parts[2] === '') {
            return null;
        }

        [, $payloadB64, $sigB64] = $parts;
        $expectedSig = rtrim(strtr(base64_encode(hash_hmac('sha256', 'EVT.' . $payloadB64, (string) $this->appSecret, true)), '+/', '-_'), '=');
        if (!hash_equals($expectedSig, $sigB64)) {
            return null;
        }

        $padded = strtr($payloadB64, '-_', '+/');
        $mod = strlen($padded) % 4;
        if ($mod > 0) {
            $padded .= str_repeat('=', 4 - $mod);
        }

        $claims = json_decode((string) base64_decode($padded, true), true);
        if (!is_array($claims)) {
            return null;
        }

        if (($claims['iss'] ?? '') !== 'EvaluaTech-PLP-Auth') {
            return null;
        }
        if (isset($claims['alg']) && strtoupper((string) $claims['alg']) !== 'HS256') {
            return null;
        }

        $now = $nowUnix ?? time();
        $exp = (int) ($claims['exp'] ?? 0);
        $userId = (int) ($claims['sub'] ?? 0);
        if ($userId <= 0 || $exp <= $now) {
            return null;
        }

        try {
            $revStmt = $this->pdo->prepare('SELECT 1 FROM revoked_auth_tokens WHERE token_hash = :hash LIMIT 1');
            $revStmt->execute([':hash' => hash('sha256', $trimmed)]);
            if ($revStmt->fetchColumn() !== false) {
                return null;
            }
        } catch (\Throwable) {
            // Table not initialized yet
        }

        $stmt = $this->pdo->prepare('
            SELECT u.*, d.name AS department_name, d.code AS department_code
            FROM users u
            LEFT JOIN departments d ON d.id = u.department_id
            WHERE u.id = :id
            LIMIT 1
        ');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        if (isset($row['is_active']) && (int) $row['is_active'] === 0) {
            return null;
        }

        return (object) [
            'id' => (int) $row['id'],
            'institutional_id' => (string) $row['institutional_id'],
            'name' => (string) $row['name'],
            'email' => (string) $row['email'],
            'role' => (string) $row['role'],
            'department_id' => $row['department_id'] !== null ? (int) $row['department_id'] : null,
            'department_name' => isset($row['department_name']) ? (string) $row['department_name'] : null,
            'department_code' => isset($row['department_code']) ? (string) $row['department_code'] : null,
        ];
    }

    /**
     * Extract and verify an `Authorization: Bearer <token>` header string.
     */
    public function verifyBearerHeader(?string $authorizationHeader): ?object
    {
        if ($authorizationHeader === null || trim($authorizationHeader) === '') {
            return null;
        }

        if (preg_match('/^\s*Bearer\s+(.+?)\s*$/i', $authorizationHeader, $matches) !== 1) {
            return null;
        }

        return $this->verifyAuthToken($matches[1]);
    }

    private function computeRateLimitBucket(string $identifier): string
    {
        return hash('sha256', 'auth_rl|' . strtolower(trim($identifier)));
    }

    /**
     * @return array{blocked: bool, retry_after: int}
     */
    private function checkRateLimit(string $bucketKey, ?int $nowUnix = null): array
    {
        $now = $nowUnix ?? time();
        try {
            $stmt = $this->pdo->prepare('SELECT attempts, window_started_at, blocked_until FROM auth_rate_limits WHERE bucket_key = :key LIMIT 1');
            $stmt->execute([':key' => $bucketKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return ['blocked' => false, 'retry_after' => 0];
            }

            $blockedUntil = (int) ($row['blocked_until'] ?? 0);
            if ($blockedUntil > $now) {
                return ['blocked' => true, 'retry_after' => max(1, $blockedUntil - $now)];
            }

            $windowStart = (int) ($row['window_started_at'] ?? 0);
            if (($now - $windowStart) >= self::RATE_LIMIT_WINDOW_SECONDS) {
                $this->resetRateLimit($bucketKey);
                return ['blocked' => false, 'retry_after' => 0];
            }

            if ((int) ($row['attempts'] ?? 0) >= self::RATE_LIMIT_MAX_ATTEMPTS) {
                return ['blocked' => true, 'retry_after' => max(1, self::RATE_LIMIT_WINDOW_SECONDS - ($now - $windowStart))];
            }
        } catch (\Throwable) {
            return ['blocked' => false, 'retry_after' => 0];
        }

        return ['blocked' => false, 'retry_after' => 0];
    }

    /**
     * @return array{blocked: bool, retry_after: int}
     */
    private function recordFailedAttempt(string $bucketKey, ?int $nowUnix = null): array
    {
        $now = $nowUnix ?? time();
        $nowStr = date('Y-m-d H:i:s', $now);

        try {
            $stmt = $this->pdo->prepare('SELECT attempts, window_started_at FROM auth_rate_limits WHERE bucket_key = :key LIMIT 1');
            $stmt->execute([':key' => $bucketKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row) || ($now - (int) $row['window_started_at']) >= self::RATE_LIMIT_WINDOW_SECONDS) {
                $del = $this->pdo->prepare('DELETE FROM auth_rate_limits WHERE bucket_key = :key');
                $del->execute([':key' => $bucketKey]);

                $ins = $this->pdo->prepare('
                    INSERT INTO auth_rate_limits (bucket_key, attempts, window_started_at, blocked_until, updated_at)
                    VALUES (:key, 1, :started, 0, :updated)
                ');
                $ins->execute([
                    ':key' => $bucketKey,
                    ':started' => $now,
                    ':updated' => $nowStr,
                ]);
                return ['blocked' => false, 'retry_after' => 0];
            }

            $newAttempts = ((int) $row['attempts']) + 1;
            $blockedUntil = $newAttempts >= self::RATE_LIMIT_MAX_ATTEMPTS
                ? ($now + self::RATE_LIMIT_WINDOW_SECONDS)
                : 0;

            $upd = $this->pdo->prepare('
                UPDATE auth_rate_limits
                SET attempts = :attempts, blocked_until = :blocked, updated_at = :updated
                WHERE bucket_key = :key
            ');
            $upd->execute([
                ':attempts' => $newAttempts,
                ':blocked' => $blockedUntil,
                ':updated' => $nowStr,
                ':key' => $bucketKey,
            ]);

            if ($blockedUntil > $now) {
                return ['blocked' => true, 'retry_after' => self::RATE_LIMIT_WINDOW_SECONDS];
            }
        } catch (\Throwable) {
            return ['blocked' => false, 'retry_after' => 0];
        }

        return ['blocked' => false, 'retry_after' => 0];
    }

    private function resetRateLimit(string $bucketKey): void
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM auth_rate_limits WHERE bucket_key = :key');
            $stmt->execute([':key' => $bucketKey]);
        } catch (\Throwable) {
            // Non-blocking cleanup
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findUserByIdentifier(string $identifier): ?array
    {
        $candidates = [$identifier];
        if (str_contains($identifier, '@')) {
            [$local, $domain] = explode('@', strtolower($identifier), 2);
            if ($domain === 'evaluatech.edu' || $domain === 'student.evaluatech.edu' || $domain === 'evaluatech.edu.ph') {
                $candidates[] = $local . '@plpasig.edu.ph';
            } elseif ($domain === 'plpasig.edu.ph') {
                $candidates[] = $local . '@evaluatech.edu';
                $candidates[] = $local . '@student.evaluatech.edu';
            }
        }

        $stmt = $this->pdo->prepare('
            SELECT u.*, d.name AS department_name, d.code AS department_code
            FROM users u
            LEFT JOIN departments d ON d.id = u.department_id
            WHERE LOWER(u.email) = LOWER(:id) OR LOWER(u.institutional_id) = LOWER(:id)
            LIMIT 1
        ');

        foreach ($candidates as $candidate) {
            $stmt->execute([':id' => $candidate]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function formatUserPayload(array $user, UserRole $roleEnum): array
    {
        return [
            'id' => (int) $user['id'],
            'institutional_id' => (string) $user['institutional_id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'role' => $roleEnum->value,
            'role_label' => $roleEnum->label(),
            'department_id' => $user['department_id'] !== null ? (int) $user['department_id'] : null,
            'department_name' => isset($user['department_name']) ? (string) $user['department_name'] : null,
            'department_code' => isset($user['department_code']) ? (string) $user['department_code'] : null,
        ];
    }

    private function resolveWorkspaceRoute(UserRole $role): string
    {
        return match ($role) {
            UserRole::ADMIN => '/admin',
            UserRole::DEAN => '/dean',
            UserRole::CHAIR => '/chair',
            UserRole::FACULTY => '/faculty',
            UserRole::STUDENT => '/queue',
        };
    }
}
