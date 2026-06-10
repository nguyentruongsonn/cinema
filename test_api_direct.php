<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

echo "=== TEST API DIRECT ===\n\n";

try {
    $response = Http::get('http://localhost:8000/api/v1/movies/avengers-endgame/showtimes');
    $data = $response->json();
    
    echo "Status: " . $response->status() . "\n";
    echo "Success: " . ($data['success'] ? 'true' : 'false') . "\n";
    echo "Has data: " . (isset($data['data']) ? 'yes' : 'no') . "\n";
    echo "Has showtimes_grouped: " . (isset($data['data']['showtimes_grouped']) ? 'yes' : 'no') . "\n";
    echo "Groups count: " . (isset($data['data']['showtimes_grouped']) ? count($data['data']['showtimes_grouped']) : 0) . "\n";
    
    if (isset($data['data']['showtimes_grouped']) && count($data['data']['showtimes_grouped']) > 0) {
        echo "First theater: " . $data['data']['showtimes_grouped'][0]['theater']['name'] . "\n";
        echo "First theater formats: " . count($data['data']['showtimes_grouped'][0]['formats']) . "\n";
        if (count($data['data']['showtimes_grouped'][0]['formats']) > 0) {
            echo "First format showtimes: " . count($data['data']['showtimes_grouped'][0]['formats'][0]['showtimes']) . "\n";
        }
    }
    
    echo "\nResponse keys: " . implode(', ', array_keys($data)) . "\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}