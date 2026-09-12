<?php

namespace App\Console\Commands;

use App\Services\Observability\QueueHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorQueueHealth extends Command
{
    protected $signature = 'queue:monitor-health {--json : Print machine-readable output}';

    protected $description = 'Check queue depth, pending age, and failed jobs against configured alert thresholds';

    public function handle(QueueHealthService $queueHealth): int
    {
        $context = $queueHealth->snapshot();

        if (! $context['healthy']) {
            Log::critical('Queue health threshold exceeded', $context);
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($context, JSON_THROW_ON_ERROR));
        } else {
            $this->table(
                ['Queue', 'Depth', 'Oldest pending', 'Status'],
                collect($context['queues'])->map(fn (array $status, string $queue): array => [
                    $queue,
                    $status['depth'],
                    $status['oldest_pending_age_seconds'] === null
                        ? 'N/A'
                        : $status['oldest_pending_age_seconds'].'s',
                    $status['healthy'] ? 'OK' : 'ALERT',
                ])
            );
            $this->line("Failed jobs: {$context['failed_jobs']}");
        }

        return $context['healthy'] ? self::SUCCESS : self::FAILURE;
    }
}
