<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== DATABASE CHECK ===\n\n";

// Check Movies
echo "MOVIES:\n";
$movies = DB::table('movies')->select('id', 'slug', 'status', 'title')->limit(5)->get();
echo "Total movies: " . DB::table('movies')->count() . "\n";
foreach ($movies as $movie) {
    echo "  - ID: {$movie->id}, Slug: {$movie->slug}, Status: {$movie->status}, Title: {$movie->title}\n";
}

echo "\n\nSHOWTIMES:\n";
$showtimes = DB::table('showtimes')->select('id', 'movie_id', 'status', 'scheduled_at')->limit(5)->get();
echo "Total showtimes: " . DB::table('showtimes')->count() . "\n";
foreach ($showtimes as $st) {
    echo "  - ID: {$st->id}, Movie: {$st->movie_id}, Status: {$st->status}, Scheduled: {$st->scheduled_at}\n";
}

echo "\n\nSHOWTIMES FOR EACH MOVIE:\n";
$moviesWithShowtimes = DB::table('showtimes')
    ->select('movie_id', DB::raw('COUNT(*) as count'))
    ->groupBy('movie_id')
    ->limit(5)
    ->get();

foreach ($moviesWithShowtimes as $item) {
    echo "  - Movie ID {$item->movie_id}: {$item->count} showtimes\n";
}

echo "\n\nFUTURE SHOWTIMES (next 5 days, status=1):\n";
$now = \Carbon\Carbon::now();
$future = DB::table('showtimes')
    ->where('status', 1)
    ->where('scheduled_at', '>', $now->copy()->subMinutes(20))
    ->where('scheduled_at', '<=', $now->copy()->addDays(5)->endOfDay())
    ->select('id', 'movie_id', 'scheduled_at')
    ->limit(5)
    ->get();

echo "Count: " . count($future) . "\n";
foreach ($future as $st) {
    echo "  - Showtime ID: {$st->id}, Movie: {$st->movie_id}, At: {$st->scheduled_at}\n";
}

echo "\n\nDATABASE CONNECTION TEST:\n";
try {
    $result = DB::select("SELECT 1");
    echo "✓ Database connection OK\n";
} catch (\Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}