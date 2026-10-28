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

    public $tries = 3;

    public $timeout = 300;

    public $backoff = [60, 180];

    public function __construct(
        public ?int $seatHoldId = null
    ) {
        $this->onQueue('cleanup');
    }

    // Dùng Redis lock chống hai worker cùng dọn dẹp một lượt giữ ghế
    public function middleware(): array
    {
        $key = $this->seatHoldId !== null
            ? 'cleanup-seat-hold-'.$this->seatHoldId
            : 'cleanup-seat-holds';

        return [
            (new WithoutOverlapping($key))
                ->expireAfter(300)
                ->releaseAfter(60),
        ];
    }

    // Job nền giải phóng các ghế giữ đã quá hạn
    public function handle(SeatService $seatService): void
    {
        $startTime = microtime(true);

        try {
            $cleanedCount = $seatService->cleanupExpiredSeatHolds(seatHoldId: $this->seatHoldId);

            $duration = microtime(true) - $startTime;

            Log::info('Seat hold cleanup completed', [
                'seat_hold_id' => $this->seatHoldId,
                'cleaned_count' => $cleanedCount,
                'duration_seconds' => round($duration, 2),
                'timestamp' => now()->toIso8601String(),
                'job_id' => $this->job?->getJobId(),
            ]);
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            Log::error('Seat hold cleanup failed', [
                'seat_hold_id' => $this->seatHoldId,
                'exception' => $e->getMessage(),
                'duration_seconds' => round($duration, 2),
                'timestamp' => now()->toIso8601String(),
                'job_id' => $this->job?->getJobId(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Seat hold cleanup job failed after all retries', [
            'seat_hold_id' => $this->seatHoldId,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => now()->toIso8601String(),
            'job_id' => $this->job?->getJobId(),
        ]);

    }
}
