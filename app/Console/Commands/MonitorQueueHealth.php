<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Throwable;

class MonitorQueueHealth extends Command
{
    protected $signature = 'queue:monitor-health {--json : Print machine-readable output}';

    protected $description = 'Check queue depth and failed jobs against configured alert thresholds';

    public function handle(): int
    {
        $depths = [];
        $healthy = true;
        $maxDepth = config('queue.monitoring.max_depth', 100);

        foreach (config('queue.monitoring.queues', ['default']) as $queue) {
            $depths[$queue] = Queue::size($queue);
            $healthy = $healthy && $depths[$queue] <= $maxDepth;
        }

        $failedJobs = $this->failedJobCount();
        $healthy = $healthy && $failedJobs <= config('queue.monitoring.max_failed_jobs', 0);
        $context = [
            'healthy' => $healthy,
            'connection' => config('queue.default'),
            'depths' => $depths,
            'failed_jobs' => $failedJobs,
        ];

        if (! $healthy) {
            Log::critical('Queue health threshold exceeded', $context);
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($context, JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Queue', 'Depth'], collect($depths)->map(fn ($depth, $queue) => [$queue, $depth]));
            $this->line("Failed jobs: {$failedJobs}");
        }

        return $healthy ? self::SUCCESS : self::FAILURE;
    }

    private function failedJobCount(): int
    {
        try {
            return (int) DB::table(config('queue.failed.table', 'failed_jobs'))->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
