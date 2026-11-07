<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'EvaluaTech'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost:8000'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Manila'),
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY', 'base64:RXZhbHVhVGVjaDualTokenDecouplingSecretKey2026='),
    'signing_secret' => env('EVALUATECH_SIGNING_SECRET', 'evaluatech-hmac-signing-secret-2026'),
    'min_dwell_seconds' => (int) env('EVALUATECH_MIN_DWELL_SECONDS', 45),
    'watchlist_min_mean' => (float) env('EVALUATECH_WATCHLIST_MIN_MEAN', 3.50),
    'watchlist_max_negative_pct' => (float) env('EVALUATECH_WATCHLIST_MAX_NEGATIVE_PCT', 35.0),
];
