<?php

declare(strict_types=1);

namespace App\Services\Observability;

use Illuminate\Contracts\Cache\Repository;

class MetricsService
{
    public function __construct(private readonly Repository $cache) {}

    public function recordRequest(float $durationMs, int $status): void
    {
        if (! config('observability.metrics_enabled')) {
            return;
        }

        $this->increment('http_requests_total');
        $this->increment('http_responses_'.$this->statusClass($status).'_total');
        $this->increment('http_duration_count');
        $this->increment('http_duration_sum_ms', max(0, (int) round($durationMs)));

        foreach (config('observability.duration_buckets_ms', []) as $bucket) {
            if ($durationMs <= $bucket) {
                $this->increment('http_duration_bucket_'.$bucket);
            }
        }
    }

    public function toPrometheus(): string
    {
        $requests = $this->value('http_requests_total');
        $lines = [
            '# HELP cinema_http_requests_total Total HTTP requests processed.',
            '# TYPE cinema_http_requests_total counter',
            "cinema_http_requests_total {$requests}",
            '# HELP cinema_http_responses_total HTTP responses grouped by status class.',
            '# TYPE cinema_http_responses_total counter',
        ];

        foreach (['2xx', '3xx', '4xx', '5xx'] as $statusClass) {
            $value = $this->value('http_responses_'.$statusClass.'_total');
            $lines[] = "cinema_http_responses_total{status_class=\"{$statusClass}\"} {$value}";
        }

        $lines[] = '# HELP cinema_http_request_duration_milliseconds Request duration summary.';
        $lines[] = '# TYPE cinema_http_request_duration_milliseconds histogram';

        foreach (config('observability.duration_buckets_ms', []) as $bucket) {
            $value = $this->value('http_duration_bucket_'.$bucket);
            $lines[] = "cinema_http_request_duration_milliseconds_bucket{le=\"{$bucket}\"} {$value}";
        }

        $lines[] = "cinema_http_request_duration_milliseconds_bucket{le=\"+Inf\"} {$requests}";
        $lines[] = 'cinema_http_request_duration_milliseconds_sum '.$this->value('http_duration_sum_ms');
        $lines[] = 'cinema_http_request_duration_milliseconds_count '.$this->value('http_duration_count');

        return implode("\n", $lines)."\n";
    }

    private function increment(string $metric, int $amount = 1): void
    {
        $key = $this->key($metric);
        $this->cache->add($key, 0, now()->addDays(30));
        $this->cache->increment($key, $amount);
    }

    private function value(string $metric): int
    {
        return (int) $this->cache->get($this->key($metric), 0);
    }

    private function key(string $metric): string
    {
        return config('observability.metrics_prefix', 'cinema:metrics:').$metric;
    }

    private function statusClass(int $status): string
    {
        return match (true) {
            $status >= 500 => '5xx',
            $status >= 400 => '4xx',
            $status >= 300 => '3xx',
            default => '2xx',
        };
    }
}
