<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing API Response for Encrypted ID\n";
echo str_repeat("=", 60) . "\n\n";

// Get a movie with showtimes
$movie = App\Models\Movie::whereHas('showtimes')->first();

if (!$movie) {
    echo "❌ No movies with showtimes found\n";
    exit(1);
}

echo "Testing movie: {$movie->title} (slug: {$movie->slug})\n\n";

// Use the ShowtimeService to get the formatted response
$service = new App\Services\ShowtimeService();

try {
    $result = $service->getMovieShowtimes($movie->slug);
    
    echo "✓ API Service executed successfully\n\n";
    
    // Check if showtimes_grouped exists
    if (isset($result['showtimes_grouped']) && count($result['showtimes_grouped']) > 0) {
        echo "✓ Showtimes grouped found\n";
        
        $firstTheater = $result['showtimes_grouped'][0];
        echo "  Theater: {$firstTheater['theater']['name']}\n";
        
        if (isset($firstTheater['formats']) && count($firstTheater['formats']) > 0) {
            $firstFormat = $firstTheater['formats'][0];
            echo "  Format: {$firstFormat['format']['name']}\n";
            
            if (isset($firstFormat['showtimes']) && count($firstFormat['showtimes']) > 0) {
                $firstShowtime = $firstFormat['showtimes'][0];
                
                echo "\n  First Showtime Details:\n";
                echo "  - ID: " . ($firstShowtime['id'] ?? 'NOT FOUND') . "\n";
                
                if (isset($firstShowtime['encrypted_id'])) {
                    echo "  ✓ encrypted_id: " . substr($firstShowtime['encrypted_id'], 0, 50) . "...\n";
                    
                    // Try to decrypt it
                    try {
                        $decrypted = Illuminate\Support\Facades\Crypt::decryptString($firstShowtime['encrypted_id']);
                        if ($decrypted == $firstShowtime['id']) {
                            echo "  ✓ Decryption verified: ID matches ($decrypted)\n";
                        } else {
                            echo "  ❌ Decryption mismatch\n";
                        }
                    } catch (Exception $e) {
                        echo "  ❌ Decryption failed: " . $e->getMessage() . "\n";
                    }
                } else {
                    echo "  ❌ encrypted_id NOT FOUND in showtime data\n";
                    echo "  Available keys: " . implode(', ', array_keys($firstShowtime)) . "\n";
                }
                
                echo "  - Time: " . ($firstShowtime['time'] ?? 'NOT FOUND') . "\n";
                echo "  - Date: " . ($firstShowtime['scheduled_date'] ?? 'NOT FOUND') . "\n";
            }
        }
    } else {
        echo "❌ No showtimes grouped found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Test completed!\n";
echo "\nNext steps:\n";
echo "1. Clear browser cache (Ctrl+Shift+R)\n";
echo "2. Navigate to movie detail page: http://localhost/movies/{$movie->slug}\n";
echo "3. Click on any showtime\n";
echo "4. URL should show encrypted ID like: /booking/eyJpdiI6...\n";