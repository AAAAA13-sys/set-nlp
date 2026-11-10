<?php

declare(strict_types=1);

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');

use App\Http\Controllers\Api\AdminRosterController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeanAnalyticsController;
use App\Http\Controllers\Api\FacultyPortalController;
use App\Http\Controllers\Api\StudentEvaluationController;
use App\Http\Middleware\EnsureDoubleBlindUnlocked;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Services\AnalyticsClientService;
use App\Services\DecoupledEvaluationService;
use Database\Seeders\EvaluaTechDemoSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

if (file_exists($maintenance = __DIR__ . '/../storage/framework/maintenance.php')) {
    require $maintenance;
}

$app = require_once __DIR__ . '/../bootstrap/app.php';

$rawRequestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$uriPath = parse_url($rawRequestUri, PHP_URL_PATH) ?: '/';
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$isApiRoute = str_starts_with($uriPath, '/api/');

foreach (SecurityHeadersMiddleware::getSecurityHeaders($isApiRoute) as $secHeader => $secVal) {
    header($secHeader . ': ' . $secVal);
}

$precheck = SecurityHeadersMiddleware::inspectRawRequest($rawRequestUri, $method, $_SERVER);
if (!$precheck['allowed']) {
    http_response_code($precheck['status']);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => $precheck['message'],
        'code' => $precheck['code'],
    ], JSON_UNESCAPED_SLASHES);
    return;
}

// 1. Serve static CSS/JS assets directly when running under PHP built-in server (php -S 127.0.0.1:8000 -t public public/index.php)
if (PHP_SAPI === 'cli-server') {
    if (preg_match('#^/build/[A-Za-z0-9_./-]+$#', $uriPath) === 1 && !str_ends_with(strtolower($uriPath), '.map')) {
        $candidate = realpath(__DIR__ . $uriPath);
        $buildDir = realpath(__DIR__ . '/build');
        if ($candidate !== false && $buildDir !== false && str_starts_with($candidate, $buildDir) && is_file($candidate)) {
            $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'css' => 'text/css; charset=utf-8',
                'js', 'mjs' => 'application/javascript; charset=utf-8',
                'json' => 'application/json; charset=utf-8',
                default => 'application/octet-stream',
            };
            header('Content-Type: ' . $mime);
            readfile($candidate);
            return;
        }
    }
    if (preg_match('#^/resources/(css|js)/[A-Za-z0-9_./-]+$#', $uriPath) === 1) {
        $candidate = realpath(__DIR__ . '/..' . $uriPath);
        $rootResources = realpath(__DIR__ . '/../resources');
        if ($candidate !== false && $rootResources !== false && str_starts_with($candidate, $rootResources) && is_file($candidate)) {
            $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'css' => 'text/css; charset=utf-8',
                'js', 'mjs' => 'application/javascript; charset=utf-8',
                'vue' => 'text/plain; charset=utf-8',
                default => 'application/octet-stream',
            };
            header('Content-Type: ' . $mime);
            readfile($candidate);
            return;
        }
    }
}

if (is_object($app) && method_exists($app, 'handleRequest')) {
    $app->handleRequest(Request::capture());
    return;
}

// 2. Standalone JSON API Dispatcher backed by persistent SQLite PDO + EvaluaTechDemoSeeder (Fail-Closed Error Boundary #28, #57)
if ($isApiRoute) {
    try {
        $rawBody = (string) file_get_contents('php://input');
        $jsonPayload = json_decode($rawBody, true);
        $combinedPayload = array_merge($_GET, $_POST, is_array($jsonPayload) ? $jsonPayload : []);

        $bodyInspection = SecurityHeadersMiddleware::inspectRawRequest($rawRequestUri, $method, $_SERVER, $rawBody, $combinedPayload);
        if (!$bodyInspection['allowed']) {
            http_response_code($bodyInspection['status']);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => $bodyInspection['message'],
                'code' => $bodyInspection['code'],
            ], JSON_UNESCAPED_SLASHES);
            return;
        }

        $storageDir = __DIR__ . '/../storage/framework';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0775, true);
        }
        $sqlitePath = $storageDir . '/evaluatech_plp.sqlite';
        $pdo = new \PDO('sqlite:' . $sqlitePath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');

        DecoupledEvaluationService::setSharedPdo($pdo);
        $service = new DecoupledEvaluationService($pdo);

        $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($userCount === 0) {
            (new EvaluaTechDemoSeeder($pdo))->run();
        }

        $input = $combinedPayload;

        $authHeader = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;
        $sigHeader = $_SERVER['HTTP_X_EVALUATECH_SIGNATURE'] ?? null;
        $tsHeader = $_SERVER['HTTP_X_EVALUATECH_TIMESTAMP'] ?? null;
        $nonceHeader = $_SERVER['HTTP_X_EVALUATECH_NONCE'] ?? null;

        if (function_exists('getallheaders')) {
            $allHeaders = getallheaders();
            if (is_array($allHeaders)) {
                $authHeader = $authHeader ?? ($allHeaders['Authorization'] ?? $allHeaders['authorization'] ?? null);
                $sigHeader = $sigHeader ?? ($allHeaders['X-EvaluaTech-Signature'] ?? $allHeaders['x-evaluatech-signature'] ?? null);
                $tsHeader = $tsHeader ?? ($allHeaders['X-EvaluaTech-Timestamp'] ?? $allHeaders['x-evaluatech-timestamp'] ?? null);
                $nonceHeader = $nonceHeader ?? ($allHeaders['X-EvaluaTech-Nonce'] ?? $allHeaders['x-evaluatech-nonce'] ?? null);
            }
        }

        $reqHeaders = [];
        if (is_string($authHeader) && $authHeader !== '') {
            $reqHeaders['Authorization'] = $authHeader;
        }
        if (is_string($sigHeader) && $sigHeader !== '') {
            $reqHeaders['X-EvaluaTech-Signature'] = $sigHeader;
        }
        if ($tsHeader !== null && (string) $tsHeader !== '') {
            $reqHeaders['X-EvaluaTech-Timestamp'] = (string) $tsHeader;
        }
        if (is_string($nonceHeader) && $nonceHeader !== '') {
            $reqHeaders['X-EvaluaTech-Nonce'] = $nonceHeader;
        }

        $authController = new AuthController($pdo);
        $authUser = $authController->verifyBearerHeader(is_string($authHeader) ? $authHeader : null);

        $request = new Request($input, $reqHeaders);
        if ($authUser !== null) {
            $request->setUserResolver(static fn () => $authUser);
        }

        $roleGuard = new EnsureRole();
        $doubleBlindGuard = new EnsureDoubleBlindUnlocked($service);
        $withRoles = static function (string $roles, callable $action) use ($roleGuard, $request): mixed {
            return $roleGuard->handle($request, static fn (Request $req) => $action($req), $roles);
        };

        $response = null;

        // Public endpoints (Health, Institutional Login, Proctor Cryptographic Permit Verification, Signed Registrar Webhook)
        if ($uriPath === '/api/health' && $method === 'GET') {
            $response = new JsonResponse([
                'status' => 'ok',
                'institution' => 'PAMANTASAN NG LUNGSOD NG PASIG',
                'reference' => 'https://plpasig.edu.ph/',
                'platform' => 'EvaluaTech',
                'nlp_engine' => 'calamanCy+VADER',
                'calamancy_repo' => 'https://github.com/ljvmiranda921/calamanCy',
            ], 200);
        } elseif ($uriPath === '/api/auth/login' && $method === 'POST') {
            $response = $authController->login($request);
        } elseif ($uriPath === '/api/auth/me' && $method === 'GET') {
            $response = $authController->me($request);
        } elseif ($uriPath === '/api/auth/logout' && $method === 'POST') {
            $response = $authController->logout($request);
        } elseif ($uriPath === '/api/permit/verify' || $uriPath === '/api/student/permit/verify') {
            $response = (new StudentEvaluationController($service))->verifyPermit($request);
        } elseif ($uriPath === '/api/webhooks/registrar-sync' && $method === 'POST') {
            $response = (new AdminRosterController(null, $service))->registrarSyncWebhook($request, $rawBody);
        } elseif ($uriPath === '/api/evaluation-window' && $method === 'GET') {
            $response = $withRoles('student,faculty,chair,dean,admin', static fn (Request $req) => (new AdminRosterController(null, $service))->getEvaluationWindow($req));
        }
        // Student Queue & Evaluation Endpoints (Role: student)
        elseif ($uriPath === '/api/student/queue' && $method === 'GET') {
            $response = $withRoles('student', static fn (Request $req) => (new StudentEvaluationController($service))->queue($req));
        } elseif (preg_match('#^/api/student/evaluations/(\d+)/start$#', $uriPath, $m) === 1 && $method === 'POST') {
            $sectionId = (int) $m[1];
            $response = $withRoles('student', static fn (Request $req) => (new StudentEvaluationController($service))->startSession($req, $sectionId));
        } elseif (($uriPath === '/api/evaluation/submit' || $uriPath === '/api/student/evaluations/submit' || $uriPath === '/api/student/evaluations') && $method === 'POST') {
            $response = $withRoles('student', static fn (Request $req) => (new StudentEvaluationController($service))->submit($req));
        } elseif ($uriPath === '/api/student/permit' && $method === 'GET') {
            $response = $withRoles('student', static fn (Request $req) => (new StudentEvaluationController($service))->permit($req));
        }
        // Faculty Double-Blind Portal Endpoints (Role: faculty, admin)
        elseif ($uriPath === '/api/faculty/sections' && $method === 'GET') {
            $response = $withRoles('faculty,admin', static fn (Request $req) => (new FacultyPortalController($service))->sections($req));
        } elseif (preg_match('#^/api/faculty/sections/(\d+)/report$#', $uriPath, $m) === 1 && $method === 'GET') {
            $sectionId = (int) $m[1];
            $routeReq = new Request($input, $reqHeaders, ['sectionId' => $sectionId]);
            if ($authUser !== null) {
                $routeReq->setUserResolver(static fn () => $authUser);
            }
            $response = $roleGuard->handle(
                $routeReq,
                static fn (Request $req) => $doubleBlindGuard->handle(
                    $req,
                    static fn (Request $innerReq) => (new FacultyPortalController($service))->sectionReport($innerReq, $sectionId)
                ),
                'faculty,admin'
            );
        } elseif (preg_match('#^/api/faculty/sections/(\d+)/submit-grades$#', $uriPath, $m) === 1 && $method === 'POST') {
            $sectionId = (int) $m[1];
            $response = $withRoles('faculty,admin', static fn (Request $req) => (new FacultyPortalController($service))->submitTermGrades($req, $sectionId));
        } elseif ($uriPath === '/api/faculty/action-plan/status' && $method === 'POST') {
            $response = $withRoles('faculty,dean,chair,admin', static fn (Request $req) => (new FacultyPortalController($service))->updateActionPlanStatus($req));
        }
        // Dean & Department Chair Analytical Workspace Endpoints (Role: dean, chair, admin)
        elseif (($uriPath === '/api/dean/overview' || $uriPath === '/api/chair/overview') && $method === 'GET') {
            $response = $withRoles('dean,chair,admin', static fn (Request $req) => (new DeanAnalyticsController($service))->overview($req));
        } elseif (($uriPath === '/api/dean/watchlist' || $uriPath === '/api/chair/watchlist') && $method === 'GET') {
            $response = $withRoles('dean,chair,admin', static fn (Request $req) => (new DeanAnalyticsController($service))->watchlist($req));
        } elseif (($uriPath === '/api/dean/completion-heatmap' || $uriPath === '/api/chair/completion-heatmap') && $method === 'GET') {
            $response = $withRoles('dean,chair,admin', static fn (Request $req) => (new DeanAnalyticsController($service))->completionHeatmap($req));
        } elseif (($uriPath === '/api/dean/longitudinal-trends' || $uriPath === '/api/chair/longitudinal-trends') && $method === 'GET') {
            $response = $withRoles('dean,chair,admin', static fn (Request $req) => (new DeanAnalyticsController($service))->longitudinalTrends($req));
        } elseif (($uriPath === '/api/dean/compliance-audit' || $uriPath === '/api/chair/compliance-audit') && $method === 'GET') {
            $response = $withRoles('dean,chair,admin', static fn (Request $req) => (new DeanAnalyticsController($service))->complianceAudit($req));
        } elseif (($uriPath === '/api/dean/evidence-dossier' || $uriPath === '/api/chair/evidence-dossier') && $method === 'GET') {
            $response = $withRoles('dean,chair,admin', static fn (Request $req) => (new DeanAnalyticsController($service))->evidenceDossier($req));
        } elseif (($uriPath === '/api/dean/performance-quartiles' || $uriPath === '/api/chair/performance-quartiles') && $method === 'GET') {
            $response = $withRoles('dean,chair,admin', static fn (Request $req) => (new DeanAnalyticsController($service))->performanceQuartiles($req));
        } elseif (($uriPath === '/api/dean/qualitative-synthesis' || $uriPath === '/api/chair/qualitative-synthesis') && $method === 'GET') {
            $response = $withRoles('dean,chair,admin', static fn (Request $req) => (new DeanAnalyticsController($service))->qualitativeSynthesis($req));
        } elseif (($uriPath === '/api/dean/insights-search' || $uriPath === '/api/chair/insights-search') && $method === 'GET') {
            $response = $withRoles('dean,chair,admin', static fn (Request $req) => (new DeanAnalyticsController($service))->insightsSearch($req));
        } elseif ($uriPath === '/api/dean/export-qualitative-csv' && $method === 'POST') {
            $response = $withRoles('dean,chair,admin', static fn (Request $req) => (new DeanAnalyticsController($service))->exportQualitativeCsv($req));
        } elseif ($uriPath === '/api/dean/export-dossier-bundle' && ($method === 'POST' || $method === 'GET')) {
            $response = $withRoles('dean,chair,admin', static fn (Request $req) => (new DeanAnalyticsController($service))->exportAccreditationDossier($req));
        } elseif ($uriPath === '/api/dean/quality-metrics' && $method === 'GET') {
            $response = $withRoles('dean,chair,admin', static fn () => (new DeanAnalyticsController($service))->qualityMetrics());
        } elseif ($uriPath === '/api/dean/accreditation' && $method === 'GET') {
            $response = $withRoles('dean,chair,admin', static fn () => (new DeanAnalyticsController($service))->accreditationSummary());
        } elseif (($uriPath === '/api/dean/action-plan/status' || $uriPath === '/api/chair/action-plan/status') && $method === 'POST') {
            $response = $withRoles('dean,chair,admin', static fn (Request $req) => (new DeanAnalyticsController($service))->updateActionPlanStatus($req));
        } elseif ($uriPath === '/api/dean/sus/evaluate' && $method === 'POST') {
            $response = $withRoles('dean,chair,admin', static fn (Request $req) => (new DeanAnalyticsController($service))->evaluateLiveSus($req));
        } elseif ($uriPath === '/api/dean/intervention-plan' && $method === 'POST') {
            $response = $withRoles('dean,chair,admin', static function () use ($input): JsonResponse {
                $analytics = new AnalyticsClientService();
                $fName = (string) ($input['faculty_name'] ?? 'Faculty Member');
                $mScore = (float) ($input['mean_score'] ?? 3.10);
                $negPct = (float) ($input['negative_sentiment_pct'] ?? 38.0);
                $pTag = (string) ($input['primary_tag'] ?? 'Pedagogical Delivery');
                return new JsonResponse($analytics->generateInterventionPlan($fName, $mScore, $negPct, $pTag), 200);
            });
        } elseif ($uriPath === '/api/dean/export-csv' && $method === 'POST') {
            $response = $withRoles('dean,chair,admin', static fn (Request $req) => (new DeanAnalyticsController($service))->exportCsv($req));
        } elseif ($uriPath === '/api/nlp/analyze' && $method === 'POST') {
            $response = $withRoles('dean,chair,admin', static function () use ($input): JsonResponse {
                $analytics = new AnalyticsClientService();
                $commentText = (string) ($input['comment_text'] ?? $input['text'] ?? '');
                $analysis = $analytics->analyzeComment($commentText);
                if (isset($input['professor_questions']) && is_array($input['professor_questions'])) {
                    $analysis['professor_questionnaire'] = $analytics->analyzeProfessorQuestionnaire($input['professor_questions']);
                }
                return new JsonResponse($analysis, 200);
            });
        } elseif ($uriPath === '/api/nlp/synthesis' && $method === 'POST') {
            $response = $withRoles('dean,chair,admin', static function () use ($input): JsonResponse {
                $analytics = new AnalyticsClientService();
                $items = $input['questionnaires'] ?? (isset($input[0]) ? $input : [$input]);
                return new JsonResponse($analytics->synthesizeQuestionnaires(is_array($items) ? $items : []), 200);
            });
        }
        // System Administrator / Registrar Endpoints (Role: admin)
        elseif ($uriPath === '/api/admin/sections' && $method === 'GET') {
            $response = $withRoles('admin', static fn (Request $req) => (new AdminRosterController(null, $service))->sections($req));
        } elseif ($uriPath === '/api/admin/rosters/import' && $method === 'POST') {
            $response = $withRoles('admin', static fn (Request $req) => (new AdminRosterController(null, $service))->import($req));
        } elseif ($uriPath === '/api/admin/sections/bulk-lock' && $method === 'POST') {
            $response = $withRoles('admin', static fn (Request $req) => (new AdminRosterController(null, $service))->bulkToggleLocks($req));
        } elseif ($uriPath === '/api/admin/security-posture' && $method === 'GET') {
            $response = $withRoles('admin,dean', static fn (Request $req) => (new AdminRosterController(null, $service))->securityPosture($req));
        } elseif (preg_match('#^/api/admin/sections/(\d+)/registrar-lock$#', $uriPath, $m) === 1 && $method === 'POST') {
            $sectionId = (int) $m[1];
            $response = $withRoles('admin', static fn (Request $req) => (new AdminRosterController(null, $service))->toggleRegistrarLock($req, $sectionId));
        } elseif ($uriPath === '/api/admin/evaluation-window' && $method === 'GET') {
            $response = $withRoles('student,faculty,chair,dean,admin', static fn (Request $req) => (new AdminRosterController(null, $service))->getEvaluationWindow($req));
        } elseif ($uriPath === '/api/admin/evaluation-window' && $method === 'POST') {
            $response = $withRoles('admin', static fn (Request $req) => (new AdminRosterController(null, $service))->updateEvaluationWindow($req));
        } elseif ($uriPath === '/api/admin/setup/overview' && $method === 'GET') {
            $response = $withRoles('admin', static fn (Request $req) => (new \App\Http\Controllers\Api\AdminSetupController())->overview($req));
        } elseif ($uriPath === '/api/admin/setup/terms' && $method === 'POST') {
            $response = $withRoles('admin', static fn (Request $req) => (new \App\Http\Controllers\Api\AdminSetupController())->createOrUpdateTerm($req));
        } elseif (preg_match('#^/api/admin/setup/terms/(\d+)/set-active$#', $uriPath, $m) === 1 && $method === 'POST') {
            $tId = (int) $m[1];
            $response = $withRoles('admin', static fn (Request $req) => (new \App\Http\Controllers\Api\AdminSetupController())->setActiveTerm($req, $tId));
        } elseif (preg_match('#^/api/admin/setup/terms/(\d+)/delete$#', $uriPath, $m) === 1 && in_array($method, ['POST', 'DELETE'], true)) {
            $tId = (int) $m[1];
            $response = $withRoles('admin', static fn (Request $req) => (new \App\Http\Controllers\Api\AdminSetupController())->deleteTerm($req, $tId));
        } elseif ($uriPath === '/api/admin/setup/courses' && $method === 'POST') {
            $response = $withRoles('admin', static fn (Request $req) => (new \App\Http\Controllers\Api\AdminSetupController())->createCourse($req));
        } elseif (preg_match('#^/api/admin/setup/courses/(\d+)$#', $uriPath, $m) === 1 && in_array($method, ['PUT', 'POST'], true)) {
            $courseId = (int) $m[1];
            $response = $withRoles('admin', static fn (Request $req) => (new \App\Http\Controllers\Api\AdminSetupController())->updateCourse($req, $courseId));
        } elseif (preg_match('#^/api/admin/setup/courses/(\d+)/delete$#', $uriPath, $m) === 1 && in_array($method, ['POST', 'DELETE'], true)) {
            $courseId = (int) $m[1];
            $response = $withRoles('admin', static fn (Request $req) => (new \App\Http\Controllers\Api\AdminSetupController())->deleteCourse($req, $courseId));
        } elseif ($uriPath === '/api/admin/setup/sections' && $method === 'POST') {
            $response = $withRoles('admin', static fn (Request $req) => (new \App\Http\Controllers\Api\AdminSetupController())->createSection($req));
        } elseif ($uriPath === '/api/admin/setup/assign-faculty' && $method === 'POST') {
            $response = $withRoles('admin', static fn (Request $req) => (new \App\Http\Controllers\Api\AdminSetupController())->assignFaculty($req));
        } elseif ($uriPath === '/api/admin/setup/users' && $method === 'POST') {
            $response = $withRoles('admin', static fn (Request $req) => (new \App\Http\Controllers\Api\AdminSetupController())->createUser($req));
        } elseif (preg_match('#^/api/admin/setup/sections/(\d+)/delete$#', $uriPath, $m) === 1 && in_array($method, ['POST', 'DELETE'], true)) {
            $secId = (int) $m[1];
            $response = $withRoles('admin', static fn (Request $req) => (new \App\Http\Controllers\Api\AdminSetupController())->deleteSection($req, $secId));
        } elseif ($uriPath === '/api/admin/setup/questionnaire' && $method === 'GET') {
            $response = $withRoles('admin', static fn (Request $req) => (new \App\Http\Controllers\Api\AdminSetupController())->questionnaire($req));
        } elseif (preg_match('#^/api/admin/setup/students/(\d+)/enrollment$#', $uriPath, $m) === 1 && $method === 'GET') {
            $stdId = (int) $m[1];
            $response = $withRoles('admin', static fn (Request $req) => (new \App\Http\Controllers\Api\AdminSetupController())->studentEnrollment($req, $stdId));
        } elseif ($uriPath === '/api/admin/setup/students/pending' && $method === 'GET') {
            $response = $withRoles('admin', static fn (Request $req) => (new \App\Http\Controllers\Api\AdminSetupController())->pendingStudentsTracker($req));
        } elseif (preg_match('#^/api/admin/setup/sections/(\d+)/roster$#', $uriPath, $m) === 1 && $method === 'GET') {
            $secId = (int) $m[1];
            $response = $withRoles('admin', static fn (Request $req) => (new \App\Http\Controllers\Api\AdminSetupController())->sectionRoster($req, $secId));
        } elseif (preg_match('#^/api/admin/setup/faculty/(\d+)/assignments$#', $uriPath, $m) === 1 && $method === 'GET') {
            $facId = (int) $m[1];
            $response = $withRoles('admin', static fn (Request $req) => (new \App\Http\Controllers\Api\AdminSetupController())->facultyAssignments($req, $facId));
        } elseif (preg_match('#^/api/admin/setup/users/(\d+)$#', $uriPath, $m) === 1 && in_array($method, ['PUT', 'POST'], true)) {
            $uId = (int) $m[1];
            $response = $withRoles('admin', static fn (Request $req) => (new \App\Http\Controllers\Api\AdminSetupController())->updateUser($req, $uId));
        } elseif (preg_match('#^/api/admin/setup/users/(\d+)/toggle-status$#', $uriPath, $m) === 1 && $method === 'POST') {
            $uId = (int) $m[1];
            $response = $withRoles('admin', static fn (Request $req) => (new \App\Http\Controllers\Api\AdminSetupController())->toggleUserStatus($req, $uId));
        }
        // Security & Zero-Knowledge Vault Audit Endpoints (Role: admin, dean)
        elseif ($uriPath === '/api/security/vault-audit' && $method === 'GET') {
            $response = $withRoles('admin,dean', static function () use ($service, $pdo): JsonResponse {
                $audit = $service->verifyVaultAnonymity();
                $recentVault = $pdo->query('SELECT id, course_section_id, mean_score, rating_variance, is_straight_lined, dwell_time_seconds, comment_hash, sentiment_polarity, sentiment_valence, pedagogical_tag, recorded_date FROM evaluation_vault ORDER BY rowid DESC LIMIT 5')->fetchAll(\PDO::FETCH_ASSOC);
                $audit['recent_anonymized_vault_entries'] = $recentVault ?: [];
                $audit['institution'] = 'PAMANTASAN NG LUNGSOD NG PASIG';
                return new JsonResponse($audit, 200);
            });
        } elseif ($uriPath === '/api/security/audit' && $method === 'GET') {
            $response = $withRoles('admin,dean', static function () use ($pdo): JsonResponse {
                $auditService = new \App\Services\SecurityAuditService();
                return new JsonResponse($auditService->runFullInstitutionalSecurityAudit($pdo), 200);
            });
        } else {
            $response = new JsonResponse([
                'error' => 'NOT_FOUND',
                'code' => 'ENDPOINT_NOT_FOUND',
                'path' => $uriPath,
            ], 404);
        }

        http_response_code($response->getStatusCode());
        $customHeaders = method_exists($response, 'getHeaders') ? $response->getHeaders() : [];
        $hasContentType = false;
        foreach ($customHeaders as $hName => $hVal) {
            header($hName . ': ' . $hVal);
            if (strtolower((string) $hName) === 'content-type') {
                $hasContentType = true;
            }
        }
        if (!$hasContentType) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo $response->getContent();
        return;
    } catch (\Throwable) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => 'An unexpected server error occurred. Please retry or contact the University Registrar.',
            'code' => 'INTERNAL_SERVER_ERROR',
            'request_id' => bin2hex(random_bytes(8)),
        ], JSON_UNESCAPED_SLASHES);
        return;
    }
}

// 3. Render the Pamantasan ng Lungsod ng Pasig (PLP) EvaluaTech SPA shell
$bladeFile = __DIR__ . '/../resources/views/app.blade.php';
if (is_file($bladeFile)) {
    $html = (string) file_get_contents($bladeFile);
    $manifestPath = __DIR__ . '/build/manifest.json';
    if (is_file($manifestPath)) {
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $tags = '';
        if (is_array($manifest)) {
            if (isset($manifest['resources/css/app.css']['file'])) {
                $tags .= '<link rel="stylesheet" href="/build/' . $manifest['resources/css/app.css']['file'] . "\">\n";
            }
            if (isset($manifest['resources/js/app.js']['css'])) {
                foreach ($manifest['resources/js/app.js']['css'] as $c) {
                    $tags .= '<link rel="stylesheet" href="/build/' . $c . "\">\n";
                }
            }
            if (isset($manifest['resources/js/app.js']['file'])) {
                $tags .= '<script type="module" src="/build/' . $manifest['resources/js/app.js']['file'] . "\"></script>\n";
            }
        }
        $html = preg_replace('/@if\s*\(function_exists\(\'vite\'\)\).*?@endif/s', $tags, $html) ?? $html;
    } else {
        $html = preg_replace('/@if\s*\([^\)]*\).*?@else(.*?)@endif/s', '$1', $html) ?? $html;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
}
