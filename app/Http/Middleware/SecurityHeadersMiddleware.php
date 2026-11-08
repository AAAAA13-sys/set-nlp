<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityHeadersMiddleware
{
    public const MAX_BODY_BYTES = 2_097_152; // 2 MB strict upload/request ceiling

    /**
     * @var list<string>
     */
    public const FORBIDDEN_OPERATORS = [
        '$gt',
        '$gte',
        '$lt',
        '$lte',
        '$ne',
        '$in',
        '$nin',
        '$where',
        '$regex',
        '$or',
        '$and',
        '__proto__',
        'constructor',
        'prototype',
    ];

    /**
     * Return standard OWASP & institutional HTTP security response headers.
     *
     * @return array<string, string>
     */
    public static function getSecurityHeaders(bool $isApiRoute = false): array
    {
        $headers = [
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'",
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'X-Robots-Tag' => 'noindex, nofollow',
        ];

        if ($isApiRoute) {
            $headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0';
            $headers['Pragma'] = 'no-cache';
        }

        return $headers;
    }

    /**
     * Inspect raw request URI, headers, and decoded payload for common web exploit vectors.
     *
     * @param array<string, mixed> $serverVars
     * @param mixed $decodedPayload
     * @return array{allowed: bool, status: int, code: string, message: string}
     */
    public static function inspectRawRequest(
        string $rawUri,
        string $method,
        array $serverVars = [],
        string $rawBody = '',
        mixed $decodedPayload = null
    ): array {
        $lowerUri = strtolower($rawUri);

        // 1. Path traversal & null-byte defense (#6)
        if (
            str_contains($rawUri, "\0")
            || str_contains($lowerUri, '..')
            || str_contains($lowerUri, '%2e%2e')
            || str_contains($lowerUri, '%252e')
            || str_contains($lowerUri, '..\\')
        ) {
            return [
                'allowed' => false,
                'status' => 403,
                'code' => 'PATH_TRAVERSAL_BLOCKED',
                'message' => 'Directory traversal sequences are strictly prohibited.',
            ];
        }

        $uriPath = strtolower((string) (parse_url($rawUri, PHP_URL_PATH) ?: '/'));

        // 2. Public DB, backup, source-map & environment file exposure block (#41, #49, #61)
        if (
            preg_match('#(^|/)\.(env|git|htaccess|htpasswd)($|/|\.)#i', $uriPath) === 1
            || preg_match('#\.(sqlite|sqlite3|sql|bak|backup|old|swp|map)$#i', $uriPath) === 1
        ) {
            return [
                'allowed' => false,
                'status' => 403,
                'code' => 'SENSITIVE_ASSET_BLOCKED',
                'message' => 'Direct access to environment, database, backup, or source-map files is denied.',
            ];
        }

        // 3. Unsecured GraphQL endpoint block (#30)
        if ($uriPath === '/graphql' || $uriPath === '/api/graphql') {
            return [
                'allowed' => false,
                'status' => 404,
                'code' => 'GRAPHQL_DISABLED',
                'message' => 'GraphQL introspection and query endpoints are disabled.',
            ];
        }

        // 4. Unsecured WebSocket upgrade block (#31)
        $upgradeHeader = strtolower(trim((string) ($serverVars['HTTP_UPGRADE'] ?? '')));
        if ($upgradeHeader === 'websocket') {
            return [
                'allowed' => false,
                'status' => 426,
                'code' => 'WEBSOCKET_UPGRADE_REJECTED',
                'message' => 'Unauthenticated WebSocket upgrades are disabled.',
            ];
        }

        // 5. Strict Same-Origin CORS & Cross-Origin Mutation Guard (#2, #9)
        $originHeader = trim((string) ($serverVars['HTTP_ORIGIN'] ?? ''));
        $hostHeader = strtolower(trim((string) ($serverVars['HTTP_HOST'] ?? '')));
        if ($originHeader !== '' && $hostHeader !== '') {
            $originHost = strtolower((string) (parse_url($originHeader, PHP_URL_HOST) ?? ''));
            $originPort = parse_url($originHeader, PHP_URL_PORT);
            $originAuthority = $originPort !== null ? "{$originHost}:{$originPort}" : $originHost;
            $hostWithoutPort = explode(':', $hostHeader)[0];

            $sameHost = ($originAuthority === $hostHeader) || ($originHost !== '' && $originHost === $hostWithoutPort);
            if (!$sameHost) {
                return [
                    'allowed' => false,
                    'status' => 403,
                    'code' => 'CROSS_ORIGIN_BLOCKED',
                    'message' => 'Cross-origin API requests from untrusted origins are denied.',
                ];
            }
        }

        // 6. Request body ceiling (#5, #37)
        $contentLength = (int) ($serverVars['CONTENT_LENGTH'] ?? strlen($rawBody));
        if ($contentLength > self::MAX_BODY_BYTES || strlen($rawBody) > self::MAX_BODY_BYTES) {
            return [
                'allowed' => false,
                'status' => 413,
                'code' => 'PAYLOAD_TOO_LARGE',
                'message' => 'Request payload exceeds the 2 MB institutional security ceiling.',
            ];
        }

        // 7. NoSQL / JSON Operator & Prototype Pollution Injection Guard (#4, #8)
        if (is_array($decodedPayload) && self::containsForbiddenOperator($decodedPayload)) {
            return [
                'allowed' => false,
                'status' => 422,
                'code' => 'OPERATOR_INJECTION_REJECTED',
                'message' => 'Nested query operators and prototype pollution keys are prohibited.',
            ];
        }

        return [
            'allowed' => true,
            'status' => 200,
            'code' => 'OK',
            'message' => 'OK',
        ];
    }

    /**
     * Recursively detect MongoDB/NoSQL operators ($gt, $ne, $where) or prototype pollution keys.
     *
     * @param array<int|string, mixed> $input
     */
    public static function containsForbiddenOperator(array $input, int $depth = 0): bool
    {
        if ($depth > 12) {
            return true;
        }

        foreach ($input as $key => $value) {
            if (is_string($key)) {
                $trimmedKey = trim($key);
                if (
                    str_starts_with($trimmedKey, '$')
                    || in_array(strtolower($trimmedKey), self::FORBIDDEN_OPERATORS, true)
                ) {
                    return true;
                }
            }

            if (is_array($value) && self::containsForbiddenOperator($value, $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $uri = method_exists($request, 'getRequestUri') ? (string) $request->getRequestUri() : '/';
        $method = method_exists($request, 'method') ? (string) $request->method() : 'GET';
        $payload = method_exists($request, 'all') ? $request->all() : [];

        $inspection = self::inspectRawRequest($uri, $method, $_SERVER, '', $payload);
        if (!$inspection['allowed']) {
            return new JsonResponse([
                'error' => $inspection['message'],
                'code' => $inspection['code'],
            ], $inspection['status'], self::getSecurityHeaders(true));
        }

        $response = $next($request);
        $headers = self::getSecurityHeaders(str_starts_with($uri, '/api'));
        if (is_object($response) && method_exists($response, 'header')) {
            foreach ($headers as $k => $v) {
                $response->header($k, $v);
            }
        }

        return $response;
    }
}

