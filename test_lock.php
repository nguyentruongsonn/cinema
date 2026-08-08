<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::role('admin')->first();
$showtime = \App\Models\Showtime::first();
$seatIds = \App\Models\Seat::where('screen_id', $showtime->screen_id)->take(2)->pluck('id')->toArray();

$seatService = app(\App\Services\SeatService::class);
try {
    $res = $seatService->lock([
        'showtime_id' => $showtime->id,
        'seat_ids' => $seatIds
    ], $user);

    echo json_encode($res, JSON_PRETTY_PRINT);
} catch (\Exception $e) {
    echo $e->getMessage();
}
