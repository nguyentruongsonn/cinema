<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Movie;

echo "Testing showtime query logic...\n\n";

$movieId = 1;

echo "Step 1: Check if movie ID {$movieId} exists:\n";
$movie1 = Movie::find($movieId);
if ($movie1) {
    echo "✓ Movie found: {$movie1->title}\n";
    echo "  Status: {$movie1->status}\n";
    echo "  Status type: " . gettype($movie1->status) . "\n";
} else {
    echo "✗ Movie not found\n";
}

echo "\nStep 2: Query with status = 1:\n";
$movie2 = Movie::where('status', 1)->where('id', $movieId)->first();
if ($movie2) {
    echo "✓ Movie found with status filter\n";
} else {
    echo "✗ Movie not found with status filter\n";
}

echo "\nStep 3: Query with status = '1' (string):\n";
$movie3 = Movie::where('status', '1')->where('id', $movieId)->first();
if ($movie3) {
    echo "✓ Movie found with status = '1' (string)\n";
} else {
    echo "✗ Movie not found with status = '1' (string)\n";
}

echo "\nStep 4: Query with status = true:\n";
$movie4 = Movie::where('status', true)->where('id', $movieId)->first();
if ($movie4) {
    echo "✓ Movie found with status = true\n";
} else {
    echo "✗ Movie not found with status = true\n";
}

echo "\nStep 5: Exact query from ShowtimeService:\n";
$movieQuery = Movie::query()->where('status', 1);
$movieQuery->where('id', (int) $movieId);
echo "SQL: " . $movieQuery->toSql() . "\n";
echo "Bindings: " . json_encode($movieQuery->getBindings()) . "\n";

try {
    $movie5 = $movieQuery->firstOrFail();
    echo "✓ Movie found\n";
} catch (\Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
}
