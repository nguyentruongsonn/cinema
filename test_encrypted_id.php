<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing Encrypted ID in Showtime API\n";
echo str_repeat("=", 60) . "\n\n";

// Test 1: Check Showtime model
echo "Test 1: Showtime Model Configuration\n";
echo str_repeat("-", 60) . "\n";

$showtime = App\Models\Showtime::first();

if (!$showtime) {
    echo "❌ No showtimes found in database\n";
    exit(1);
}

echo "✓ Found showtime ID: " . $showtime->id . "\n";

// Check if encrypted_id accessor works
try {
    $encryptedId = $showtime->encrypted_id;
    echo "✓ encrypted_id accessor works\n";
    echo "  Plain ID: " . $showtime->id . "\n";
    echo "  Encrypted ID: " . substr($encryptedId, 0, 50) . "...\n";
} catch (Exception $e) {
    echo "❌ encrypted_id accessor failed: " . $e->getMessage() . "\n";
}

// Check if it's in the appends array
$appends = $showtime->getAppends();
echo "Appends array: " . json_encode($appends) . "\n";

if (in_array('encrypted_id', $appends)) {
    echo "✓ encrypted_id is in appends array\n";
} else {
    echo "❌ encrypted_id is NOT in appends array\n";
}

// Test 2: Check JSON output
echo "\n\nTest 2: JSON Output\n";
echo str_repeat("-", 60) . "\n";

$json = $showtime->toArray();

if (isset($json['encrypted_id'])) {
    echo "✓ encrypted_id is present in JSON\n";
    echo "  Value: " . substr($json['encrypted_id'], 0, 50) . "...\n";
} else {
    echo "❌ encrypted_id is NOT present in JSON\n";
    echo "Available keys: " . implode(', ', array_keys($json)) . "\n";
}

// Test 3: Check API response with relationships
echo "\n\nTest 3: API Response with Relationships\n";
echo str_repeat("-", 60) . "\n";

$showtimeWithRelations = App\Models\Showtime::with([
    'movie:id,title',
    'screen:id,name',
    'format:id,name'
])->first();

$jsonWithRelations = $showtimeWithRelations->toArray();

if (isset($jsonWithRelations['encrypted_id'])) {
    echo "✓ encrypted_id is present with relationships\n";
} else {
    echo "❌ encrypted_id is NOT present with relationships\n";
}

// Test 4: Decryption test
echo "\n\nTest 4: Encryption/Decryption Test\n";
echo str_repeat("-", 60) . "\n";

try {
    $originalId = $showtime->id;
    $encrypted = $showtime->encrypted_id;
    $decrypted = Illuminate\Support\Facades\Crypt::decryptString($encrypted);
    
    if ($decrypted == $originalId) {
        echo "✓ Encryption and decryption work correctly\n";
        echo "  Original: $originalId\n";
        echo "  Decrypted: $decrypted\n";
    } else {
        echo "❌ Decryption mismatch\n";
        echo "  Original: $originalId\n";
        echo "  Decrypted: $decrypted\n";
    }
} catch (Exception $e) {
    echo "❌ Encryption/Decryption failed: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Test completed!\n";