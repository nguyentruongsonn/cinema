<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== PROFILE AUTH DEBUG ===\n\n";

// Test 1: Check if access_token cookie exists
echo "1. Simulating request with access_token cookie...\n";

// Get a valid JWT token from database
$user = \App\Models\User::first();
if (!$user) {
    echo "❌ No users found in database\n";
    exit(1);
}

echo "✓ Found user: {$user->email}\n";

// Generate JWT token
try {
    $token = auth('api')->login($user);
    echo "✓ Generated JWT token: " . substr($token, 0, 20) . "...\n\n";
} catch (\Exception $e) {
    echo "❌ Error generating token: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Test CookieToBearerToken middleware
echo "2. Testing CookieToBearerToken middleware...\n";

$request = \Illuminate\Http\Request::create('/profile', 'GET');
$request->cookies->set('access_token', $token);

$middleware = new \App\Http\Middleware\CookieToBearerToken();
$response = $middleware->handle($request, function ($req) {
    return $req;
});

if ($response->bearerToken()) {
    echo "✓ CookieToBearerToken working: " . substr($response->bearerToken(), 0, 20) . "...\n\n";
} else {
    echo "❌ CookieToBearerToken failed\n\n";
}

// Test 3: Test auth:api middleware
echo "3. Testing auth:api middleware...\n";

try {
    $authUser = auth('api')->setToken($token)->user();
    if ($authUser) {
        echo "✓ JWT token valid for user: {$authUser->email}\n\n";
    } else {
        echo "❌ JWT token invalid or expired\n\n";
    }
} catch (\Exception $e) {
    echo "❌ Auth error: " . $e->getMessage() . "\n\n";
}

// Test 4: Check route definition
echo "4. Checking route definitions...\n";

$routes = \Illuminate\Support\Facades\Route::getRoutes();

$profileRoute = $routes->getByName('profile.index');
if ($profileRoute) {
    echo "✓ Route 'profile.index' exists: " . $profileRoute->uri() . "\n";
    echo "  Middleware: " . implode(', ', $profileRoute->middleware()) . "\n";
} else {
    echo "❌ Route 'profile.index' not found\n";
}

$loginRoute = $routes->getByName('login');
if ($loginRoute) {
    echo "✓ Route 'login' exists: " . $loginRoute->uri() . "\n\n";
} else {
    echo "❌ Route 'login' not found\n\n";
}

// Test 5: Test full request flow
echo "5. Testing full request flow...\n";

try {
    $testRequest = \Illuminate\Http\Request::create('/profile', 'GET');
    $testRequest->cookies->set('access_token', $token);
    
    // Apply CookieToBearerToken
    $middleware = new \App\Http\Middleware\CookieToBearerToken();
    $testRequest = $middleware->handle($testRequest, function ($req) {
        return $req;
    });
    
    // Set request for auth
    app('request')->headers->set('Authorization', $testRequest->header('Authorization'));
    
    // Try to get authenticated user
    $authUser = auth('api')->user();
    
    if ($authUser) {
        echo "✓ Full auth flow successful!\n";
        echo "  Authenticated as: {$authUser->email}\n";
    } else {
        echo "❌ Auth flow failed - no authenticated user\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Request flow error: " . $e->getMessage() . "\n";
    echo "  " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== DEBUG COMPLETE ===\n";