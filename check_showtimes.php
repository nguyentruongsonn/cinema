<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Showtime;
use Carbon\Carbon;

echo "Checking showtimes for movie ID 1...\n\n";

$movieId = 1;
$now = Carbon::now();
$cutoffTime = $now->copy()->subMinutes(20);
$endDate = $now->copy()->addDays(5)->endOfDay();

echo "Time filters:\n";
echo "  Now: {$now}\n";
echo "  Cutoff (20min ago): {$cutoffTime}\n";
echo "  End date (+5 days): {$endDate}\n\n";

$showtimes = Showtime::where('movie_id', $movieId)
    ->where('status', 1)
    ->where('scheduled_at', '>', $cutoffTime)
    ->where('scheduled_at', '<=', $endDate)
    ->get();

echo "Found " . $showtimes->count() . " showtimes\n\n";

if ($showtimes->isEmpty()) {
    echo "No showtimes found with current filters.\n";
    echo "Let's check all showtimes for this movie:\n\n";

    $allShowtimes = Showtime::where('movie_id', $movieId)->get();
    echo "Total showtimes for movie: " . $allShowtimes->count() . "\n";

    if ($allShowtimes->isNotEmpty()) {
        foreach ($allShowtimes->take(3) as $st) {
            echo "  - ID {$st->id}: {$st->scheduled_at}, status: {$st->status}\n";
        }
    }
} else {
    echo "Testing relationship loading...\n";
    foreach ($showtimes->take(2) as $showtime) {
        echo "\nShowtime ID {$showtime->id}:\n";

        try {
            $showtime->load('screen');
            $screenName = $showtime->screen->name ?? 'N/A';
            echo "  ✓ Screen loaded: {$screenName}\n";
        } catch (\Exception $e) {
            echo "  ✗ Screen load failed: {$e->getMessage()}\n";
        }

        try {
            $showtime->load('screen.theater');
            $theaterName = $showtime->screen->theater->name ?? 'N/A';
            echo "  ✓ Theater loaded: {$theaterName}\n";
        } catch (\Exception $e) {
            echo "  ✗ Theater load failed: {$e->getMessage()}\n";
        }

        try {
            $showtime->load('format');
            $formatName = $showtime->format->name ?? 'N/A';
            echo "  ✓ Format loaded: {$formatName}\n";
        } catch (\Exception $e) {
            echo "  ✗ Format load failed: {$e->getMessage()}\n";
        }

        try {
            $showtime->load('sound');
            $soundName = $showtime->sound->name ?? 'N/A';
            echo "  ✓ Sound loaded: {$soundName}\n";
        } catch (\Exception $e) {
            echo "  ✗ Sound load failed: {$e->getMessage()}\n";
        }

        try {
            $showtime->load('subtitle');
            $subtitleName = $showtime->subtitle->name ?? 'N/A';
            echo "  ✓ Subtitle loaded: {$subtitleName}\n";
        } catch (\Exception $e) {
            echo "  ✗ Subtitle load failed: {$e->getMessage()}\n";
        }
    }
}
