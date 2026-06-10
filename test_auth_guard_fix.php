<?php
/**
 * Test Auth Guard Fix - Verify login không bị logout ngay
 * 
 * Root cause đã fix: 
 * - .env có AUTH_GUARD=api (JWT) làm default
 * - Blade dùng auth()->check() check api guard → fail
 * - Đã fix: Blade dùng Auth::guard('web')->check() thay vì auth()->check()
 */

$baseUrl = 'http://127.0.0.1:8000';
$apiUrl = $baseUrl . '/api/v1';

echo "=== TEST AUTH GUARD FIX ===\n\n";

// 1. Register test user
echo "1. Đăng ký user test...\n";
$email = 'test_guard_' . time() . '@test.com';
$password = 'Test123456';

$ch = curl_init("$apiUrl/auth/register");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'name' => 'Test Guard User',
        'email' => $email,
        'password' => $password,
        'password_confirmation' => $password,
        'terms' => true
    ]),
    CURLOPT_HEADER => true,
    CURLOPT_COOKIEJAR => 'cookies.txt',
    CURLOPT_COOKIEFILE => 'cookies.txt',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 201) {
    echo "✗ FAILED: Register failed with code $httpCode\n";
    echo "Response: $response\n";
    exit(1);
}

echo "✓ User registered successfully\n";
echo "Email: $email\n\n";

// 2. Test home page SSR (should show logged in state)
echo "2. Kiểm tra trang home SSR...\n";
$ch = curl_init($baseUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => 'cookies.txt',
    CURLOPT_FOLLOWLOCATION => true,
]);

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "✗ FAILED: Home page returned code $httpCode\n";
    exit(1);
}

// Check if user dropdown is shown (authenticated state)
$hasUserDropdown = strpos($html, 'id="userDropdown"') !== false;
$hasLoginButton = strpos($html, 'cinema-login-btn') !== false;

if ($hasUserDropdown && !$hasLoginButton) {
    echo "✓ SSR auth state: AUTHENTICATED (user dropdown shown)\n";
} else if (!$hasUserDropdown && $hasLoginButton) {
    echo "✗ FAILED: SSR auth state: GUEST (login button shown)\n";
    echo "User bị logout ngay sau login!\n";
    exit(1);
} else {
    echo "⚠ WARNING: Both login button and user dropdown present\n";
}

// Check APP_CONFIG.auth
if (preg_match('/authenticated:\s*(true|false)/', $html, $matches)) {
    $isAuth = $matches[1] === 'true';
    echo "APP_CONFIG.auth.authenticated: " . ($isAuth ? 'true ✓' : 'false ✗') . "\n";
    
    if (!$isAuth) {
        echo "✗ FAILED: APP_CONFIG shows not authenticated\n";
        exit(1);
    }
} else {
    echo "⚠ WARNING: Could not find APP_CONFIG.auth.authenticated\n";
}

echo "\n";

// 3. Test profile page
echo "3. Kiểm tra trang profile...\n";
$ch = curl_init("$baseUrl/profile");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => 'cookies.txt',
    CURLOPT_FOLLOWLOCATION => true,
]);

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo "✗ FAILED: Profile page returned code $httpCode\n";
    exit(1);
}

$hasAuthRequired = strpos($html, 'profile-auth-required') !== false;
$hasProfileContent = strpos($html, 'profileContent') !== false;

if (!$hasAuthRequired && $hasProfileContent) {
    echo "✓ Profile page: AUTHENTICATED (profile content shown)\n";
} else if ($hasAuthRequired && !$hasProfileContent) {
    echo "✗ FAILED: Profile page: GUEST (auth required shown)\n";
    echo "User bị logout ngay!\n";
    exit(1);
} else {
    echo "⚠ WARNING: Profile page state unclear\n";
}

echo "\n";

// 4. Test API profile endpoint
echo "4. Kiểm tra API /auth/profile...\n";
$ch = curl_init("$apiUrl/auth/profile");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
    CURLOPT_COOKIEFILE => 'cookies.txt',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($httpCode === 200 && isset($data['success']) && $data['success']) {
    echo "✓ API profile: SUCCESS\n";
    echo "User: {$data['data']['user']['name']}\n";
    echo "Email: {$data['data']['user']['email']}\n";
} else {
    echo "✗ FAILED: API profile returned code $httpCode\n";
    echo "Response: $response\n";
    exit(1);
}

echo "\n";

// Cleanup
@unlink('cookies.txt');

echo "=================================\n";
echo "✓ ALL TESTS PASSED\n";
echo "Auth guard fix hoạt động OK!\n";
echo "User không bị logout ngay sau login.\n";
echo "=================================\n";