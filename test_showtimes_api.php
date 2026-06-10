<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Route;

echo "=== TESTING SHOWTIMES API ===\n\n";

// Test 1: Check routes
echo "TEST 1: Check registered routes\n";
$routes = Route::getRoutes();
$showtimeRoutes = [];
foreach ($routes as $route) {
    if (strpos($route->uri, 'showtimes') !== false || strpos($route->uri, 'movies') !== false) {
        $showtimeRoutes[] = [
            'method' => $route->methods,
            'uri' => $route->uri,
            'name' => $route->getName(),
        ];
    }
}

foreach ($showtimeRoutes as $route) {
    echo "  - " . implode(',', $route['method']) . " {$route['uri']} (name: {$route['name']})\n";
}

// Test 2: Direct controller call
echo "\n\nTEST 2: Direct Controller Call\n";
try {
    $controller = app(\App\Http\Controllers\ShowtimeController::class);
    
    // Test getMovieShowtimes with movie slug
    $result = $controller->getMovieShowtimes('avengers-endgame');
    $decoded = json_decode($result->getContent(), true);
    
    echo "✓ getMovieShowtimes('avengers-endgame') executed\n";
    echo "  Response status: " . $result->getStatusCode() . "\n";
    echo "  Response success: " . ($decoded['success'] ? 'true' : 'false') . "\n";
    echo "  Has showtimes_grouped: " . (isset($decoded['data']['showtimes_grouped']) ? 'yes' : 'no') . "\n";
    echo "  Number of groups: " . (isset($decoded['data']['showtimes_grouped']) ? count($decoded['data']['showtimes_grouped']) : 0) . "\n";
    
    if (isset($decoded['data']['showtimes_grouped']) && count($decoded['data']['showtimes_grouped']) > 0) {
        $firstGroup = $decoded['data']['showtimes_grouped'][0];
        echo "  First theater: " . $firstGroup['theater']['name'] . "\n";
        echo "  First theater formats: " . count($firstGroup['formats']) . "\n";
    }
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Test 3: Simulate HTTP request
echo "\n\nTEST 3: Simulate HTTP Request\n";
try {
    $request = \Illuminate\Http\Request::create('/api/v1/movies/avengers-endgame/showtimes', 'GET');
    $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    
    echo "✓ HTTP request executed\n";
    echo "  Status: " . $response->getStatusCode() . "\n";
    
    $decoded = json_decode($response->getContent(), true);
    if ($decoded && isset($decoded['success'])) {
        echo "  API Success: " . ($decoded['success'] ? 'true' : 'false') . "\n";
        if (isset($decoded['data']['showtimes_grouped'])) {
            echo "  Showtimes groups: " . count($decoded['data']['showtimes_grouped']) . "\n";
        }
    }
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

// Test 4: Direct Service Call
echo "\n\nTEST 4: Direct Service Call\n";
try {
    $service = app(\App\Services\ShowtimeService::class);
    $result = $service->getMovieShowtimes('avengers-endgame');
    
    echo "✓ Service getMovieShowtimes executed\n";
    echo "  Has movie data: " . (isset($result['movie']) ? 'yes' : 'no') . "\n";
    echo "  Has showtimes_grouped: " . (isset($result['showtimes_grouped']) ? 'yes' : 'no') . "\n";
    echo "  Number of groups: " . (isset($result['showtimes_grouped']) ? count($result['showtimes_grouped']) : 0) . "\n";
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "  Stack: " . $e->getTraceAsString() . "\n";
}

echo "\n=== TEST COMPLETE ===\n";