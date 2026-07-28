<?php

use App\Jobs\CleanupExpiredSeatHolds;
use App\Jobs\ExpirePendingOrders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new CleanupExpiredSeatHolds)->everyMinute();
Schedule::job(new ExpirePendingOrders)->everyMinute();
Schedule::command('queue:monitor-health')->everyMinute()->withoutOverlapping();
