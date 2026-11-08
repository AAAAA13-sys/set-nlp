<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AdminRosterController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeanAnalyticsController;
use App\Http\Controllers\Api\FacultyPortalController;
use App\Http\Controllers\Api\StudentEvaluationController;
use App\Services\AnalyticsClientService;
use App\Services\DecoupledEvaluationService;
use Database\Seeders\EvaluaTechDemoSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// 1. Serve static CSS/JS assets directly when running under PHP built-in server (php -S 127.0.0.1:8000 -t public public/index.php)
if (PHP_SAPI === 'cli-server') {
    if (preg_match('#^/build/[A-Za-z0-9_./-]+$#', $uriPath) === 1) {
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

if (file_exists($maintenance = __DIR__ . '/../storage/framework/maintenance.php')) {
    require $maintenance;
}

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

$app = require_once __DIR__ . '/../bootstrap/app.php';

if (is_object($app) && method_exists($app, 'handleRequest')) {
    $app->handleRequest(Request::capture());
    return;
}

// 2. Standalone JSON API Dispatcher backed by persistent SQLite PDO + EvaluaTechDemoSeeder
if (str_starts_with($uriPath, '/api/')) {
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

    $rawBody = (string) file_get_contents('php://input');
    $jsonPayload = json_decode($rawBody, true);
    $input = array_merge($_GET, $_POST, is_array($jsonPayload) ? $jsonPayload : []);
    $request = new Request($input);

    $response = null;

    if ($uriPath === '/api/health') {
        $response = new JsonResponse([
            'status' => 'ok',
            'institution' => 'PAMANTASAN NG LUNGSOD NG PASIG',
            'reference' => 'https://plpasig.edu.ph/',
            'platform' => 'EvaluaTech',
            'nlp_engine' => 'calamanCy+VADER',
            'calamancy_repo' => 'https://github.com/ljvmiranda921/calamanCy',
        ], 200);
    } elseif ($uriPath === '/api/nlp/analyze' && $method === 'POST') {
        $analytics = new AnalyticsClientService();
        $commentText = (string) ($input['comment_text'] ?? $input['text'] ?? '');
        $analysis = $analytics->analyzeComment($commentText);
        if (isset($input['professor_questions']) && is_array($input['professor_questions'])) {
            $analysis['professor_questionnaire'] = $analytics->analyzeProfessorQuestionnaire($input['professor_questions']);
        }
        $response = new JsonResponse($analysis, 200);
    } elseif ($uriPath === '/api/nlp/synthesis' && $method === 'POST') {
        $analytics = new AnalyticsClientService();
        $items = $input['questionnaires'] ?? (isset($input[0]) ? $input : [$input]);
        $synthesis = $analytics->synthesizeQuestionnaires(is_array($items) ? $items : []);
        $response = new JsonResponse($synthesis, 200);
    } elseif ($uriPath === '/api/security/vault-audit' && $method === 'GET') {
        $audit = $service->verifyVaultAnonymity();
        $recentVault = $pdo->query('SELECT id, course_section_id, mean_score, rating_variance, is_straight_lined, dwell_time_seconds, comment_hash, sentiment_polarity, sentiment_valence, pedagogical_tag, recorded_date FROM evaluation_vault ORDER BY rowid DESC LIMIT 5')->fetchAll(\PDO::FETCH_ASSOC);
        $audit['recent_anonymized_vault_entries'] = $recentVault ?: [];
        $audit['institution'] = 'PAMANTASAN NG LUNGSOD NG PASIG';
        $response = new JsonResponse($audit, 200);
    } elseif ($uriPath === '/api/security/audit' && $method === 'GET') {
        $auditService = new \App\Services\SecurityAuditService();
        $fullAudit = $auditService->runFullInstitutionalSecurityAudit($pdo);
        $response = new JsonResponse($fullAudit, 200);
    } elseif ($uriPath === '/api/admin/reset-demo' && $method === 'POST') {
        (new EvaluaTechDemoSeeder($pdo))->run();
        $response = new JsonResponse([
            'ok' => true,
            'message' => 'Pamantasan ng Lungsod ng Pasig (PLP) canonical evaluation queue and decoupled vault reset to initial state (3 of 6 Completed).',
        ], 200);
    } elseif ($uriPath === '/api/auth/login' && $method === 'POST') {
        $response = (new AuthController($pdo))->login($request);
    } elseif ($uriPath === '/api/auth/logout' && $method === 'POST') {
        $response = (new AuthController($pdo))->logout();
    } elseif ($uriPath === '/api/student/queue' && $method === 'GET') {
        if (!isset($input['student_id'])) {
            $studentId = (int) $pdo->query("SELECT id FROM users WHERE institutional_id = '2024-10892' LIMIT 1")->fetchColumn();
            $request = new Request(array_merge($input, ['student_id' => $studentId]), $_SERVER);
        }
        $response = (new StudentEvaluationController($service))->queue($request);
    } elseif (preg_match('#^/api/student/evaluations/(\d+)/start$#', $uriPath, $m) === 1 && $method === 'POST') {
        if (!isset($input['student_id'])) {
            $studentId = (int) $pdo->query("SELECT id FROM users WHERE institutional_id = '2024-10892' LIMIT 1")->fetchColumn();
            $request = new Request(array_merge($input, ['student_id' => $studentId]), $_SERVER);
        }
        $response = (new StudentEvaluationController($service))->startSession($request, (int) $m[1]);
    } elseif (($uriPath === '/api/evaluation/submit' || $uriPath === '/api/student/evaluations/submit' || $uriPath === '/api/student/evaluations') && $method === 'POST') {
        if (!isset($input['student_id'])) {
            $studentId = (int) $pdo->query("SELECT id FROM users WHERE institutional_id = '2024-10892' LIMIT 1")->fetchColumn();
            $request = new Request(array_merge($input, ['student_id' => $studentId]), $_SERVER);
        }
        $response = (new StudentEvaluationController($service))->submit($request);
    } elseif ($uriPath === '/api/student/permit' && $method === 'GET') {
        $response = (new StudentEvaluationController($service))->permit($request);
    } elseif ($uriPath === '/api/student/permit/verify') {
        $response = (new StudentEvaluationController($service))->verifyPermit($request);
    } elseif (preg_match('#^/api/faculty/sections/(\d+)/report$#', $uriPath, $m) === 1 && $method === 'GET') {
        $response = (new FacultyPortalController($service))->sectionReport($request, (int) $m[1]);
    } elseif (preg_match('#^/api/faculty/sections/(\d+)/submit-grades$#', $uriPath, $m) === 1 && $method === 'POST') {
        $response = (new FacultyPortalController($service))->submitTermGrades($request, (int) $m[1]);
    } elseif ($uriPath === '/api/faculty/action-plan/status' && $method === 'POST') {
        $response = (new FacultyPortalController($service))->updateActionPlanStatus($request);
    } elseif (($uriPath === '/api/dean/overview' || $uriPath === '/api/chair/overview') && $method === 'GET') {
        $response = (new DeanAnalyticsController($service))->overview($request);
    } elseif (($uriPath === '/api/dean/watchlist' || $uriPath === '/api/chair/watchlist') && $method === 'GET') {
        $response = (new DeanAnalyticsController($service))->watchlist($request);
    } elseif ($uriPath === '/api/dean/qualitative-synthesis' && $method === 'GET') {
        $response = (new DeanAnalyticsController($service))->qualitativeSynthesis($request);
    } elseif ($uriPath === '/api/dean/export-qualitative-csv' && $method === 'POST') {
        $response = (new DeanAnalyticsController($service))->exportQualitativeCsv($request);
    } elseif ($uriPath === '/api/dean/quality-metrics' && $method === 'GET') {
        $response = (new DeanAnalyticsController($service))->qualityMetrics();
    } elseif ($uriPath === '/api/dean/accreditation' && $method === 'GET') {
        $response = (new DeanAnalyticsController($service))->accreditationSummary();
    } elseif (($uriPath === '/api/dean/action-plan/status' || $uriPath === '/api/chair/action-plan/status') && $method === 'POST') {
        $response = (new DeanAnalyticsController($service))->updateActionPlanStatus($request);
    } elseif ($uriPath === '/api/dean/sus/evaluate' && $method === 'POST') {
        $response = (new DeanAnalyticsController($service))->evaluateLiveSus($request);
    } elseif ($uriPath === '/api/dean/intervention-plan' && $method === 'POST') {
        $analytics = new AnalyticsClientService();
        $fName = (string) ($input['faculty_name'] ?? 'Faculty Member');
        $mScore = (float) ($input['mean_score'] ?? 3.10);
        $negPct = (float) ($input['negative_sentiment_pct'] ?? 38.0);
        $pTag = (string) ($input['primary_tag'] ?? 'Pedagogical Delivery');
        $plan = $analytics->generateInterventionPlan($fName, $mScore, $negPct, $pTag);
        $response = new JsonResponse($plan, 200);
    } elseif ($uriPath === '/api/dean/export-csv' && $method === 'POST') {
        $response = (new DeanAnalyticsController($service))->exportCsv($request);
    } elseif ($uriPath === '/api/admin/rosters/import' && $method === 'POST') {
        $response = (new AdminRosterController(null, $service))->import($request);
    } elseif (preg_match('#^/api/admin/sections/(\d+)/registrar-lock$#', $uriPath, $m) === 1 && $method === 'POST') {
        $response = (new AdminRosterController(null, $service))->toggleRegistrarLock($request, (int) $m[1]);
    } elseif ($uriPath === '/api/admin/evaluation-window' && $method === 'GET') {
        $response = (new AdminRosterController(null, $service))->getEvaluationWindow($request);
    } elseif ($uriPath === '/api/admin/evaluation-window' && $method === 'POST') {
        $response = (new AdminRosterController(null, $service))->updateEvaluationWindow($request);
    } else {
        $response = new JsonResponse([
            'error' => 'NOT_FOUND',
            'path' => $uriPath,
        ], 404);
    }

    http_response_code($response->getStatusCode());
    header('Content-Type: application/json; charset=utf-8');
    echo $response->getContent();
    return;
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
