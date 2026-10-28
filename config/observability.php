<?php

return [
    'metrics_enabled' => (bool) env('METRICS_ENABLED', false),
    'metrics_token' => env('METRICS_TOKEN'),
    'metrics_prefix' => env('METRICS_PREFIX', 'cinema:metrics:'),
    'duration_buckets_ms' => [50, 100, 250, 500, 1000, 2500, 5000],
    'server_timing_header' => (bool) env('SERVER_TIMING_HEADER', false),
    'operations' => [
        'lookback_hours' => (int) env('OPERATIONS_LOOKBACK_HOURS', 24),
        'email_max_age_seconds' => (int) env('OPERATIONS_EMAIL_MAX_AGE_SECONDS', 600),
        'max_overdue_payments' => (int) env('OPERATIONS_MAX_OVERDUE_PAYMENTS', 0),
        'max_unsent_ticket_emails' => (int) env('OPERATIONS_MAX_UNSENT_TICKET_EMAILS', 5),
    ],
    'alerts' => [
        'enabled' => (bool) env('OPERATIONS_ALERTS_ENABLED', false),
        'webhook_url' => env('OPERATIONS_ALERT_WEBHOOK_URL'),
        'cooldown_seconds' => (int) env('OPERATIONS_ALERT_COOLDOWN_SECONDS', 300),
        'timeout_seconds' => (int) env('OPERATIONS_ALERT_TIMEOUT_SECONDS', 5),
    ],
];
