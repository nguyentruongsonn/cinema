<?php

/**
 * Test Booking Flow
 *
 * Tests the complete booking flow:
 * 1. Login to get JWT token
 * 2. Get showtimes for a movie
 * 3. Get seats for a showtime
 * 4. Lock seats
 * 5. Create an order
 * 6. Create a payment
 * 7. Verify payment
 * 8. Verify seat status
 */

// Configuration
$baseUrl = 'http://127.0.0.1:8000/api';
$email = 'test_user_' . time() . '@test.com';
$password = 'Test@123456';
$name = 'Test Booking User';

// Colors for output
function colorOutput($message, $color = 'green') {
    $colors = [
        'green' => "\033[0;32m",
        'red' => "\033[0;31m",
        'yellow' => "\033[1;33m",
        'blue' => "\033[0;34m",
        'reset' => "\033[0m",
    ];

    return $colors[$color] . $message . $colors['reset'];
}

function makeRequest($method, $url, $data = null, $token = null) {
    $ch = curl_init();

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true),
        'raw' => $response,
    ];
}

echo colorOutput("\n========================================\n", 'blue');
echo colorOutput("  BOOKING FLOW API TEST\n", 'blue');
echo colorOutput("========================================\n\n", 'blue');

// Step 1: Register test user
echo colorOutput("[STEP 1] Registering test user...\n", 'yellow');
echo "  Email: {$email}\n";
$registerResponse = makeRequest('POST', "$baseUrl/auth/register", [
    'name' => $name,
    'email' => $email,
    'password' => $password,
    'password_confirmation' => $password,
    'terms' => true,
]);

if ($registerResponse['code'] !== 201 && $registerResponse['code'] !== 200) {
    // Maybe user already exists or other issue - try login anyway
    echo colorOutput("  Register failed: " . ($registerResponse['body']['message'] ?? 'Unknown'), 'yellow');
}

// Step 2: Login
echo colorOutput("[STEP 2] Login...\n", 'yellow');
$loginResponse = makeRequest('POST', "$baseUrl/auth/login", [
    'email' => $email,
    'password' => $password,
]);

if ($loginResponse['code'] !== 200 || !isset($loginResponse['body']['data']['access_token'])) {
    echo colorOutput("✗ Login failed!\n", 'red');
    echo "Response: " . print_r($loginResponse['body'], true) . "\n";
    exit(1);
}

$token = $loginResponse['body']['data']['access_token'];
echo colorOutput("✓ Login successful\n", 'green');
echo "  Token: " . substr($token, 0, 20) . "...\n\n";

// Step 3: Get movies and showtimes
echo colorOutput("[STEP 3] Getting movies...\n", 'yellow');
$moviesResponse = makeRequest('GET', "$baseUrl/movies");

if ($moviesResponse['code'] !== 200 || empty($moviesResponse['body']['data'])) {
    echo colorOutput("✗ No movies found!\n", 'red');
    echo "Response: " . print_r($moviesResponse['body'], true) . "\n";
    exit(1);
}

$movie = $moviesResponse['body']['data'][0];
echo colorOutput("✓ Found movies\n", 'green');
echo "  Testing with movie: {$movie['title']} (ID: {$movie['id']})\n\n";

// Get showtimes for this movie - use ID instead of slug
echo colorOutput("[STEP 4] Getting showtimes...\n", 'yellow');
$showtimesResponse = makeRequest('GET', "$baseUrl/movies/{$movie['id']}/showtimes");

if ($showtimesResponse['code'] !== 200 || empty($showtimesResponse['body']['data'])) {
    echo colorOutput("✗ No showtimes found!\n", 'red');
    echo "Response: " . print_r($showtimesResponse['body'], true) . "\n";
    exit(1);
}

$showtime = null;
$showtimesGrouped = $showtimesResponse['body']['data']['showtimes_grouped'] ?? [];

// Navigate through theater -> format -> showtimes structure
foreach ($showtimesGrouped as $theaterGroup) {
    if (!empty($theaterGroup['formats'])) {
        foreach ($theaterGroup['formats'] as $formatGroup) {
            if (!empty($formatGroup['showtimes'])) {
                $showtime = $formatGroup['showtimes'][0];
                break 2;
            }
        }
    }
}

if (!$showtime) {
    echo colorOutput("✗ No valid showtime found!\n", 'red');
    echo "Response structure: " . print_r($showtimesResponse['body']['data'], true) . "\n";
    exit(1);
}

echo colorOutput("✓ Found showtimes\n", 'green');
echo "  Testing with showtime ID: {$showtime['id']}\n";
echo "  Time: {$showtime['scheduled_date']} {$showtime['time']}\n\n";

// Step 5: Get seats for showtime
echo colorOutput("[STEP 5] Getting seats for showtime...\n", 'yellow');
$seatsResponse = makeRequest('GET', "$baseUrl/seats/showtime/{$showtime['id']}", null, $token);

if ($seatsResponse['code'] !== 200 || empty($seatsResponse['body']['data']['seats'])) {
    echo colorOutput("✗ Failed to get seats!\n", 'red');
    echo "Response: " . print_r($seatsResponse['body'], true) . "\n";
    exit(1);
}

// Find available seats
$availableSeats = array_filter($seatsResponse['body']['data']['seats'], function($seat) {
    return $seat['status'] === 'available';
});

if (empty($availableSeats)) {
    echo colorOutput("✗ No available seats found!\n", 'red');
    exit(1);
}

// Pick 2 seats to test
$seatsToBook = array_slice($availableSeats, 0, 2);
$seatIds = array_column($seatsToBook, 'id');

echo colorOutput("✓ Found available seats\n", 'green');
echo "  Selected seats: " . implode(', ', array_column($seatsToBook, 'seat_number')) . "\n";
echo "  Seat IDs: " . implode(', ', $seatIds) . "\n\n";

// Step 6: Lock seats
echo colorOutput("[STEP 6] Locking seats...\n", 'yellow');
$lockResponse = makeRequest('POST', "$baseUrl/seats/lock", [
    'showtime_id' => $showtime['id'],
    'seat_ids' => $seatIds,
], $token);

if ($lockResponse['code'] !== 200) {
    echo colorOutput("✗ Failed to lock seats!\n", 'red');
    echo "Response: " . print_r($lockResponse['body'], true) . "\n";
    exit(1);
}

$holdId = $lockResponse['body']['data']['hold_id'];
echo colorOutput("✓ Seats locked successfully\n", 'green');
echo "  Hold ID: {$holdId}\n";
echo "  Expires at: {$lockResponse['body']['data']['expires_at']}\n\n";

// Step 7: Create order
echo colorOutput("[STEP 7] Creating order...\n", 'yellow');
$orderData = [
    'showtime_id' => $showtime['id'],
    'seat_ids' => $seatIds,
    'items' => [],
];

// Add seat items
foreach ($seatsToBook as $seat) {
    $orderData['items'][] = [
        'item_type' => 'seat',
        'item_id' => $seat['id'],
        'quantity' => 1,
        'price' => $seat['price'],
    ];
}

$orderResponse = makeRequest('POST', "$baseUrl/orders", $orderData, $token);

if ($orderResponse['code'] !== 201) {
    echo colorOutput("✗ Failed to create order!\n", 'red');
    echo "Response: " . print_r($orderResponse['body'], true) . "\n";
    exit(1);
}

$orderId = $orderResponse['body']['data']['id'];
$orderTotal = $orderResponse['body']['data']['total_amount'];
echo colorOutput("✓ Order created successfully\n", 'green');
echo "  Order ID: {$orderId}\n";
echo "  Total amount: {$orderTotal}\n";
echo "  Status: {$orderResponse['body']['data']['status']}\n\n";

// Step 8: Create payment
echo colorOutput("[STEP 8] Creating payment...\n", 'yellow');
$paymentResponse = makeRequest('POST', "$baseUrl/payments", [
    'order_id' => $orderId,
    'payment_method' => 'e_wallet',
    'amount' => $orderTotal,
], $token);

if ($paymentResponse['code'] !== 201) {
    echo colorOutput("✗ Failed to create payment!\n", 'red');
    echo "Response: " . print_r($paymentResponse['body'], true) . "\n";
    exit(1);
}

$paymentId = $paymentResponse['body']['data']['id'];
echo colorOutput("✓ Payment created successfully\n", 'green');
echo "  Payment ID: {$paymentId}\n";
echo "  Method: {$paymentResponse['body']['data']['payment_method']}\n";
echo "  Status: {$paymentResponse['body']['data']['status']}\n\n";

// Step 9: Verify payment (mock success)
echo colorOutput("[STEP 9] Verifying payment...\n", 'yellow');
$verifyResponse = makeRequest('POST', "$baseUrl/payments/{$paymentId}/verify", [
    'status' => 'completed',
], $token);

if ($verifyResponse['code'] !== 200) {
    echo colorOutput("✗ Failed to verify payment!\n", 'red');
    echo "Response: " . print_r($verifyResponse['body'], true) . "\n";
    exit(1);
}

echo colorOutput("✓ Payment verified successfully\n", 'green');
echo "  Payment status: {$verifyResponse['body']['data']['payment']['status']}\n";
echo "  Order status: {$verifyResponse['body']['data']['order']['status']}\n\n";

// Step 10: Verify seat status changed
echo colorOutput("[STEP 10] Verifying seat status...\n", 'yellow');
$seatsCheckResponse = makeRequest('GET', "$baseUrl/seats/showtime/{$showtime['id']}", null, $token);

if ($seatsCheckResponse['code'] !== 200) {
    echo colorOutput("✗ Failed to get seats!\n", 'red');
    exit(1);
}

$bookedSeats = array_filter($seatsCheckResponse['body']['data']['seats'], function($seat) use ($seatIds) {
    return in_array($seat['id'], $seatIds);
});

$allBooked = true;
foreach ($bookedSeats as $seat) {
    if ($seat['status'] !== 'booked') {
        $allBooked = false;
        echo colorOutput("  ✗ Seat {$seat['seat_number']} status: {$seat['status']} (expected: booked)\n", 'red');
    } else {
        echo colorOutput("  ✓ Seat {$seat['seat_number']} status: booked\n", 'green');
    }
}

if (!$allBooked) {
    echo colorOutput("\n✗ Not all seats are booked!\n", 'red');
    exit(1);
}

echo colorOutput("\n✓ All seats are booked correctly\n", 'green');

// Final summary
echo colorOutput("\n========================================\n", 'blue');
echo colorOutput("  TEST SUMMARY\n", 'blue');
echo colorOutput("========================================\n", 'blue');
echo colorOutput("✓ All tests passed!\n\n", 'green');
echo "Order ID: {$orderId}\n";
echo "Payment ID: {$paymentId}\n";
