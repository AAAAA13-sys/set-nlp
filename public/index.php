<?php

declare(strict_types=1);

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Serve static CSS/JS assets directly when running under PHP built-in server (php -S 127.0.0.1:8000 -t public)
if (PHP_SAPI === 'cli-server') {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
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

if (is_object($app) && method_exists($app, 'handleRequest') && class_exists(Request::class)) {
    $app->handleRequest(Request::capture());
    return;
}

// Standalone fallback when composer vendor directory is not yet installed
$bladeFile = __DIR__ . '/../resources/views/app.blade.php';
if (is_file($bladeFile)) {
    $html = (string) file_get_contents($bladeFile);
    $html = preg_replace('/@if\s*\([^\)]*\).*?@else(.*?)@endif/s', '$1', $html) ?? $html;
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
}
