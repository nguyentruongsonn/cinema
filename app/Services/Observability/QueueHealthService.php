<?php

declare(strict_types=1);

namespace App\Services\Observability;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

final class QueueHealthService
{
    /**
     * @return array{
     *     healthy: bool,
     *     connection: string,
     *     driver: string,
     *     max_depth: int,
     *     max_age_seconds: int,
     *     queues: array<string, array{depth: int, oldest_pending_age_seconds: int|null, healthy: bool}>,
     *     depths: array<string, int>,
     *     failed_jobs: int,
     *     violations: array<int, array{queue: string, reason: string, value: int, threshold: int}>
     * }
     */
    public function snapshot(): array
    {
        $connection = (string) config('queue.default', 'sync');
        $connectionConfig = (array) config("queue.connections.{$connection}", []);
        $driver = (string) ($connectionConfig['driver'] ?? $connection);
        $maxDepth = max(0, (int) config('queue.monitoring.max_depth', 100));
        $maxAgeSeconds = max(0, (int) config('queue.monitoring.max_age_seconds', 300));
        $queues = [];
        $depths = [];
        $violations = [];

        foreach ((array) config('queue.monitoring.queues', ['default']) as $queueName) {
            $queue = trim((string) $queueName);
            if ($queue === '') {
                continue;
            }

            try {
                $depth = (int) Queue::connection($connection)->size($queue);
                $oldestAge = $this->oldestPendingAgeSeconds($driver, $connectionConfig, $queue);
            } catch (\Throwable) {
                $depths[$queue] = -1;
                $queues[$queue] = [
                    'depth' => -1,
                    'oldest_pending_age_seconds' => null,
                    'healthy' => false,
                ];
                $violations[] = [
                    'queue' => $queue,
                    'reason' => 'unavailable',
                    'value' => 1,
                    'threshold' => 0,
                ];

                continue;
            }

            $queueHealthy = $depth <= $maxDepth
                && ($oldestAge === null || $maxAgeSeconds === 0 || $oldestAge <= $maxAgeSeconds);

            if ($depth > $maxDepth) {
                $violations[] = [
                    'queue' => $queue,
                    'reason' => 'depth',
                    'value' => $depth,
                    'threshold' => $maxDepth,
                ];
            }

            if ($oldestAge !== null && $maxAgeSeconds > 0 && $oldestAge > $maxAgeSeconds) {
                $violations[] = [
                    'queue' => $queue,
                    'reason' => 'age',
                    'value' => $oldestAge,
                    'threshold' => $maxAgeSeconds,
                ];
            }

            $depths[$queue] = $depth;
            $queues[$queue] = [
                'depth' => $depth,
                'oldest_pending_age_seconds' => $oldestAge,
                'healthy' => $queueHealthy,
            ];
        }

        try {
            $failedJobs = $this->failedJobCount();
        } catch (\Throwable) {
            $failedJobs = -1;
            $violations[] = [
                'queue' => 'failed_jobs',
                'reason' => 'unavailable',
                'value' => 1,
                'threshold' => 0,
            ];
        }

        $maxFailedJobs = max(0, (int) config('queue.monitoring.max_failed_jobs', 0));
        if ($failedJobs >= 0 && $failedJobs > $maxFailedJobs) {
            $violations[] = [
                'queue' => 'failed_jobs',
                'reason' => 'failed',
                'value' => $failedJobs,
                'threshold' => $maxFailedJobs,
            ];
        }

        return [
            'healthy' => $violations === [],
            'connection' => $connection,
            'driver' => $driver,
            'max_depth' => $maxDepth,
            'max_age_seconds' => $maxAgeSeconds,
            'queues' => $queues,
            'depths' => $depths,
            'failed_jobs' => $failedJobs,
            'violations' => $violations,
        ];
    }

    /** @param array<string, mixed> $connectionConfig */
    private function oldestPendingAgeSeconds(string $driver, array $connectionConfig, string $queue): ?int
    {
        return match ($driver) {
            'database' => $this->oldestDatabaseJobAge($connectionConfig, $queue),
            'redis' => $this->oldestRedisJobAge($connectionConfig, $queue),
            default => null,
        };
    }

    /** @param array<string, mixed> $connectionConfig */
    private function oldestDatabaseJobAge(array $connectionConfig, string $queue): ?int
    {
        $databaseConnection = $connectionConfig['connection'] ?? null;
        $table = (string) ($connectionConfig['table'] ?? 'jobs');
        $now = now()->getTimestamp();
        $retryAfter = max(1, (int) ($connectionConfig['retry_after'] ?? 90));
        $oldestCreatedAt = DB::connection(is_string($databaseConnection) ? $databaseConnection : null)
            ->table($table)
            ->where('queue', $queue)
            ->where(function ($query) use ($now, $retryAfter): void {
                $query->where(function ($ready) use ($now): void {
                    $ready->whereNull('reserved_at')
                        ->where('available_at', '<=', $now);
                })->orWhere('reserved_at', '<=', $now - $retryAfter);
            })
            ->min('created_at');

        return is_numeric($oldestCreatedAt)
            ? max(0, $now - (int) $oldestCreatedAt)
            : null;
    }

    /** @param array<string, mixed> $connectionConfig */
    private function oldestRedisJobAge(array $connectionConfig, string $queue): ?int
    {
        $redisConnection = (string) ($connectionConfig['connection'] ?? 'default');
        $redis = Redis::connection($redisConnection);
        $queueKey = "queues:{$queue}";
        $payloads = [];

        $readyPayload = $redis->command('lindex', [$queueKey, 0]);
        if (is_string($readyPayload)) {
            $payloads[] = $readyPayload;
        }

        foreach (["{$queueKey}:delayed", "{$queueKey}:reserved"] as $sortedQueueKey) {
            $duePayloads = $redis->command('zrangebyscore', [
                $sortedQueueKey,
                '-inf',
                (string) now()->getTimestamp(),
                'LIMIT',
                0,
                1,
            ]);

            if (is_array($duePayloads) && isset($duePayloads[0]) && is_string($duePayloads[0])) {
                $payloads[] = $duePayloads[0];
            }
        }

        $timestamps = array_values(array_filter(array_map(
            fn (string $payload): ?int => $this->payloadPushedAt($payload),
            $payloads
        )));

        return $timestamps === []
            ? null
            : max(0, now()->getTimestamp() - min($timestamps));
    }

    private function payloadPushedAt(string $payload): ?int
    {
        $decoded = json_decode($payload, true);
        $pushedAt = is_array($decoded) ? ($decoded['pushedAt'] ?? null) : null;

        return is_numeric($pushedAt) ? (int) $pushedAt : null;
    }

    private function failedJobCount(): int
    {
        return (int) DB::table((string) config('queue.failed.table', 'failed_jobs'))->count();
    }
}
