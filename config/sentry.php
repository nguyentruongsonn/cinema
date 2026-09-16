<?php

return [
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),
    'release' => env('SENTRY_RELEASE'),
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV')),
    'sample_rate' => (float) env('SENTRY_SAMPLE_RATE', 1.0),
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.0),
    'send_default_pii' => false,
    'enable_logs' => (bool) env('SENTRY_ENABLE_LOGS', true),
    'enable_metrics' => (bool) env('SENTRY_ENABLE_METRICS', true),
    'ignore_transactions' => ['/up', '/api/v1/health/live'],
    'breadcrumbs' => [
        'logs' => true,
        'cache' => true,
        'sql_queries' => true,
        'sql_bindings' => false,
        'queue_info' => true,
        'http_client_requests' => true,
    ],
    'tracing' => [
        'queue_job_transactions' => true,
        'sql_queries' => true,
        'sql_bindings' => false,
        'views' => true,
        'http_client_requests' => true,
        'cache' => true,
        'redis_commands' => true,
        'default_integrations' => true,
    ],
];
