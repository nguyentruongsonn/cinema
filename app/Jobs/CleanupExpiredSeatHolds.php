<?php

namespace App\Jobs;

use App\Services\SeatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CleanupExpiredSeatHolds implements ShouldQueue
{
    use Queueable;

    public function handle(SeatService $seatService): void
    {
        $seatService->cleanupExpiredSeatHolds();
    }
}
