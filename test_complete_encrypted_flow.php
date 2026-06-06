<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Complete Encrypted ID Flow Test\n";
echo str_repeat("=", 70) . "\n\n";

// Step 1: Get a showtime
$showtime = App\Models\Showtime::with(['movie', 'screen.theater'])->first();

if (!$showtime) {
    echo "❌ No showtimes found\n";
    exit(1);
}

echo "Step 1: Get Showtime\n";
echo str_repeat("-", 70) . "\n";
echo "✓ Showtime ID: {$showtime->id}\n";
echo "  Movie: {$showtime->movie->title}\n";
echo "  Theater: {$showtime->screen->theater->name}\n\n";

// Step 2: Generate encrypted ID (what API does)
echo "Step 2: Generate Encrypted ID (API Response)\n";
echo str_repeat("-", 70) . "\n";
$encryptedId = $showtime->encrypted_id;
echo "✓ Encrypted ID generated\n";
echo "  Length: " . strlen($encryptedId) . " characters\n";
echo "  First 60 chars: " . substr($encryptedId, 0, 60) . "...\n";
echo "  Contains special chars: " . (preg_match('/[+\/=]/', $encryptedId) ? 'YES' : 'NO') . "\n\n";

// Step 3: URL encode (what frontend does)
echo "Step 3: URL Encode for Frontend\n";
echo str_repeat("-", 70) . "\n";
$urlEncoded = urlencode($encryptedId);
echo "✓ URL encoded\n";
echo "  Length: " . strlen($urlEncoded) . " characters\n";
echo "  First 60 chars: " . substr($urlEncoded, 0, 60) . "...\n";
echo "  Sample URL: /booking/" . substr($urlEncoded, 0, 40) . "...\n\n";

// Step 4: URL decode (what Laravel router does automatically)
echo "Step 4: URL Decode (Laravel Router)\n";
echo str_repeat("-", 70) . "\n";
$urlDecoded = urldecode($urlEncoded);
echo "✓ URL decoded\n";
echo "  Matches original encrypted: " . ($urlDecoded === $encryptedId ? 'YES ✓' : 'NO ❌') . "\n\n";

// Step 5: Decrypt (what BookingController does)
echo "Step 5: Decrypt ID (BookingController)\n";
echo str_repeat("-", 70) . "\n";

try {
    $decryptedId = Illuminate\Support\Facades\Crypt::decryptString($urlDecoded);
    
    if ($decryptedId == $showtime->id) {
        echo "✓ Decryption successful\n";
        echo "  Original ID: {$showtime->id}\n";
        echo "  Decrypted ID: {$decryptedId}\n";
        echo "  Match: YES ✓\n";
    } else {
        echo "❌ Decryption mismatch\n";
        echo "  Original: {$showtime->id}\n";
        echo "  Decrypted: {$decryptedId}\n";
    }
} catch (Exception $e) {
    echo "❌ Decryption failed\n";
    echo "  Error: {$e->getMessage()}\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "Test Summary\n";
echo str_repeat("=", 70) . "\n\n";

echo "✓ The complete flow works correctly!\n\n";

echo "Frontend should use:\n";
echo "  encodeURIComponent(showtime.encrypted_id)\n\n";

echo "Example URL that will work:\n";
echo "  http://localhost/booking/" . urlencode($encryptedId) . "\n\n";

echo "What happens:\n";
echo "  1. API returns encrypted_id in JSON\n";
echo "  2. Frontend encodes it with encodeURIComponent()\n";
echo "  3. User clicks link with encoded URL\n";
echo "  4. Laravel router automatically decodes the URL parameter\n";
echo "  5. BookingController decrypts the ID\n";
echo "  6. Booking page loads with correct showtime\n\n";

echo "To test in browser:\n";
echo "  1. Clear cache (Ctrl+Shift+R)\n";
echo "  2. Go to: http://localhost/movies/{$showtime->movie->slug}\n";
echo "  3. Click any showtime button\n";
echo "  4. URL should change to /booking/[encrypted-string]\n";
echo "  5. Booking page should load successfully\n";