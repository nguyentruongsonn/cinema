<?php

namespace App\Jobs;

use App\Services\SeatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupExpiredSeatHolds implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times job can be attempted.
     */
    public $tries = 3;

    /**
     * Maximum execution time (5 minutes).
     */
    public $timeout = 300;

    /**
     * Backoff delays between retries (1 minute, 3 minutes).
     */
    public $backoff = [60, 180];

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        // Set dedicated cleanup queue
        $this->onQueue('cleanup');
    }

    /**
     * Prevent overlapping job execution.
     *
     * @return array
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('cleanup-seat-holds'))
                ->expireAfter(300) // 5 minutes
                ->releaseAfter(60), // 1 minute
        ];
    }

    /**
     * Execute the job with metrics and error handling.
     *
     * @param SeatService $seatService
     * @return void
     */
    public function handle(SeatService $seatService): void
    {
        $startTime = microtime(true);

        try {
            $cleanedCount = $seatService->cleanupExpiredSeatHolds();

            $duration = microtime(true) - $startTime;

            Log::info('Seat hold cleanup completed', [
                'cleaned_count' => $cleanedCount,
                'duration_seconds' => round($duration, 2),
                'timestamp' => now()->toIso8601String(),
                'job_id' => $this->job?->getJobId(),
            ]);
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            Log::error('Seat hold cleanup failed', [
                'exception' => $e->getMessage(),
                'duration_seconds' => round($duration, 2),
                'timestamp' => now()->toIso8601String(),
                'job_id' => $this->job?->getJobId(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Seat hold cleanup job failed after all retries', [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => now()->toIso8601String(),
            'job_id' => $this->job?->getJobId(),
        ]);

        // Could integrate with monitoring/alerting system here
    }
}