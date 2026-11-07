<?php

declare(strict_types=1);

return [
    'nlp' => [
        'url' => env('NLP_SERVICE_URL', 'http://127.0.0.1:5001'),
        'analyze_endpoint' => env('NLP_SERVICE_URL', 'http://127.0.0.1:5001') . '/analyze',
        'batch_endpoint' => env('NLP_SERVICE_URL', 'http://127.0.0.1:5001') . '/analyze/batch',
        'health_endpoint' => env('NLP_SERVICE_URL', 'http://127.0.0.1:5001') . '/health',
        'timeout_seconds' => (float) env('NLP_SERVICE_TIMEOUT_SECONDS', 1.5),
    ],
];
