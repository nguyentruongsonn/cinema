<?php

$url = 'http://127.0.0.1:8000/api/auth/register';
$data = [
    'name' => 'Test User',
    'email' => 'testuser' . time() . '@example.com',
    'phone' => '0912345678',
    'password' => 'Password123',
    'password_confirmation' => 'Password123',
    'terms' => true
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "CURL Error: $error\n";
    exit(1);
}

echo "HTTP Status: $httpCode\n";
echo "Response:\n";
echo json_encode(json_decode($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n";

if ($httpCode === 201) {
    echo "\n✓ Registration successful!\n";
} elseif ($httpCode === 422) {
    echo "\n✗ Validation error\n";
} elseif ($httpCode === 500) {
    echo "\n✗ Server error\n";
} else {
    echo "\n✗ Unexpected status code\n";
}
