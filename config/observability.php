<?php

return [
    'metrics_enabled' => (bool) env('METRICS_ENABLED', false),
    'metrics_token' => env('METRICS_TOKEN'),
    'metrics_prefix' => env('METRICS_PREFIX', 'cinema:metrics:'),
    'duration_buckets_ms' => [50, 100, 250, 500, 1000, 2500, 5000],
    'server_timing_header' => (bool) env('SERVER_TIMING_HEADER', false),
];
