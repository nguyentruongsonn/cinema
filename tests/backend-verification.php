<?php

/**
 * Backend Verification Script
 * Tests critical model relationships and features after migration consolidation
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 BACKEND VERIFICATION AFTER MIGRATION CONSOLIDATION\n";
echo str_repeat("=", 70) . "\n\n";

$results = [
    'passed' => 0,
    'failed' => 0,
    'errors' => []
];

// Helper function to test and report
function test($description, $callback, &$results) {
    echo "Testing: $description... ";
    try {
        $result = $callback();
        if ($result) {
            echo "✅ PASS\n";
            $results['passed']++;
            return true;
        } else {
            echo "❌ FAIL\n";
            $results['failed']++;
            $results['errors'][] = $description . ": Test returned false";
            return false;
        }
    } catch (\Exception $e) {
        echo "❌ ERROR\n";
        $results['failed']++;
        $results['errors'][] = $description . ": " . $e->getMessage();
        return false;
    }
}

echo "📊 Phase 1: NEW FEATURES\n";
echo str_repeat("-", 70) . "\n";

// Test 1: VersionType model exists and has data
test("VersionType model exists and has data", function() {
    $versionTypes = \App\Models\VersionType::all();
    return $versionTypes->count() >= 4; // Should have 4 types from seeder
}, $results);

// Test 2: VersionType has correct data
test("VersionType has expected data (Phụ đề, Lồng tiếng, etc)", function() {
    $slugs = \App\Models\VersionType::pluck('slug')->toArray();
    $expected = ['phu-de', 'long-tieng', 'thuyet-minh', 'nguyen-ban'];
    return count(array_intersect($expected, $slugs)) >= 4;
}, $results);

echo "\n📊 Phase 2: MODIFIED RELATIONSHIPS\n";
echo str_repeat("-", 70) . "\n";

// Test 3: Theater has branch_id instead of city
test("Theater has branch_id column", function() {
    $theater = \App\Models\Theater::first();
    return isset($theater->branch_id);
}, $results);

// Test 4: Theater->branch relationship works
test("Theater->branch relationship works", function() {
    $theater = \App\Models\Theater::with('branch')->first();
    return $theater && $theater->branch !== null;
}, $results);

// Test 5: Payment has user_id column
test("Payment has user_id column", function() {
    $payment = \App\Models\Payment::first();
    return $payment === null || isset($payment->user_id); // May be null if no payments yet
}, $results);

echo "\n📊 Phase 3: CORE MODEL RELATIONSHIPS\n";
echo str_repeat("-", 70) . "\n";

// Test 6: User->orders relationship
test("User->orders relationship", function() {
    $user = \App\Models\User::with('orders')->first();
    return $user !== null && is_object($user->orders);
}, $results);

// Test 7: User->roles relationship
test("User->roles relationship", function() {
    $user = \App\Models\User::with('roles')->first();
    return $user !== null && is_object($user->roles);
}, $results);

// Test 8: Order->user relationship
test("Order->user relationship", function() {
    $order = \App\Models\Order::with('user')->first();
    return $order !== null && $order->user !== null;
}, $results);

// Test 9: Order->showtime relationship
test("Order->showtime relationship", function() {
    $order = \App\Models\Order::with('showtime')->first();
    return $order !== null && $order->showtime !== null;
}, $results);

// Test 10: Order->items relationship
test("Order->items relationship", function() {
    $order = \App\Models\Order::with('items')->first();
    return $order !== null && is_object($order->items);
}, $results);

// Test 11: Showtime->movie relationship
test("Showtime->movie relationship", function() {
    $showtime = \App\Models\Showtime::with('movie')->first();
    return $showtime !== null && $showtime->movie !== null;
}, $results);

// Test 12: Showtime->screen relationship
test("Showtime->screen relationship", function() {
    $showtime = \App\Models\Showtime::with('screen')->first();
    return $showtime !== null && $showtime->screen !== null;
}, $results);

// Test 13: Showtime->versionType relationship (NEW)
test("Showtime->versionType relationship (NEW)", function() {
    $showtime = \App\Models\Showtime::with('versionType')->first();
    return $showtime !== null; // versionType may be null (nullable)
}, $results);

// Test 14: Movie->showtimes relationship
test("Movie->showtimes relationship", function() {
    $movie = \App\Models\Movie::with('showtimes')->first();
    return $movie !== null && is_object($movie->showtimes);
}, $results);

// Test 15: Movie->categories relationship
test("Movie->categories relationship", function() {
    $movie = \App\Models\Movie::with('categories')->first();
    return $movie !== null && is_object($movie->categories);
}, $results);

// Test 16: Screen->theater relationship
test("Screen->theater relationship", function() {
    $screen = \App\Models\Screen::with('theater')->first();
    return $screen !== null && $screen->theater !== null;
}, $results);

// Test 17: Screen->seats relationship
test("Screen->seats relationship", function() {
    $screen = \App\Models\Screen::with('seats')->first();
    return $screen !== null && is_object($screen->seats) && $screen->seats->count() > 0;
}, $results);

// Test 18: Seat->screen relationship
test("Seat->screen relationship", function() {
    $seat = \App\Models\Seat::with('screen')->first();
    return $seat !== null && $seat->screen !== null;
}, $results);

// Test 19: Seat->seatType relationship
test("Seat->seatType relationship", function() {
    $seat = \App\Models\Seat::with('seatType')->first();
    return $seat !== null && $seat->seatType !== null;
}, $results);

echo "\n📊 Phase 4: DATA INTEGRITY\n";
echo str_repeat("-", 70) . "\n";

// Test 20: Showtimes have version_type_id populated
test("Showtimes have version_type_id values", function() {
    $showtimesWithVersion = \App\Models\Showtime::whereNotNull('version_type_id')->count();
    return $showtimesWithVersion > 0;
}, $results);

// Test 21: All theaters have valid branch_id
test("All theaters have valid branch_id", function() {
    $theatersWithBranch = \App\Models\Theater::whereNotNull('branch_id')->count();
    $totalTheaters = \App\Models\Theater::count();
    return $theatersWithBranch === $totalTheaters;
}, $results);

// Test 22: Seats have seat_type_id
test("Seats have seat_type_id values", function() {
    $seatsWithType = \App\Models\Seat::whereNotNull('seat_type_id')->count();
    return $seatsWithType > 0;
}, $results);

// Test 23: Orders have user_id
test("Orders have user_id values", function() {
    $ordersWithUser = \App\Models\Order::whereNotNull('user_id')->count();
    $totalOrders = \App\Models\Order::count();
    return $totalOrders === 0 || $ordersWithUser === $totalOrders;
}, $results);

// Test 24: Orders have showtime_id
test("Orders have showtime_id values", function() {
    $ordersWithShowtime = \App\Models\Order::whereNotNull('showtime_id')->count();
    $totalOrders = \App\Models\Order::count();
    return $totalOrders === 0 || $ordersWithShowtime === $totalOrders;
}, $results);

echo "\n" . str_repeat("=", 70) . "\n";
echo "📊 VERIFICATION SUMMARY\n";
echo str_repeat("=", 70) . "\n";
echo "✅ Passed: " . $results['passed'] . "\n";
echo "❌ Failed: " . $results['failed'] . "\n";
echo "📈 Success Rate: " . round(($results['passed'] / ($results['passed'] + $results['failed'])) * 100, 2) . "%\n";

if ($results['failed'] > 0) {
    echo "\n🚨 ERRORS FOUND:\n";
    foreach ($results['errors'] as $error) {
        echo "  - " . $error . "\n";
    }
    exit(1);
} else {
    echo "\n🎉 ALL TESTS PASSED! Backend is working correctly.\n";
    exit(0);
}
