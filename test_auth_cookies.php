<?php

/**
 * AUTH COOKIES DEBUG SCRIPT
 * 
 * This script tests the entire auth flow to identify where it's breaking
 * 
 * Run: php test_auth_cookies.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=================================================\n";
echo "AUTH COOKIES DEBUG TEST\n";
echo "=================================================\n\n";

// Test 1: Check .env configuration
echo "TEST 1: Configuration Check\n";
echo "----------------------------\n";
echo "SESSION_SECURE: " . config('session.secure', 'NOT SET') . "\n";
echo "SESSION_SAME_SITE: " . config('session.same_site', 'NOT SET') . "\n";
echo "SESSION_PATH: " . config('session.path', 'NOT SET') . "\n";
echo "SESSION_DOMAIN: " . config('session.domain', 'NOT SET') . "\n";
echo "APP_URL: " . config('app.url') . "\n";
echo "JWT_SECRET exists: " . (config('jwt.secret') ? 'YES' : 'NO') . "\n";
echo "JWT_TTL: " . config('jwt.ttl', 'NOT SET') . " minutes\n";
echo "\n";

// Test 2: Try to create a JWT token
echo "TEST 2: JWT Token Creation\n";
echo "----------------------------\n";
try {
    $user = \App\Models\User::first();
    
    if (!$user) {
        echo "❌ ERROR: No users in database!\n";
        echo "Run: php artisan db:seed --class=UserSeeder\n\n";
        exit(1);
    }
    
    echo "Found user: {$user->email} (ID: {$user->id})\n";
    
    // Create JWT token
    $token = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);
    echo "✅ JWT Token created successfully!\n";
    echo "Token (first 50 chars): " . substr($token, 0, 50) . "...\n";
    
    // Validate token
    $decoded = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->authenticate();
    echo "✅ Token validated successfully!\n";
    echo "Decoded user: {$decoded->email}\n";
    echo "\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR: {$e->getMessage()}\n\n";
    exit(1);
}

// Test 3: Simulate cookie-based authentication (what middleware does)
echo "TEST 3: Middleware Simulation\n";
echo "----------------------------\n";
try {
    // Simulate request with cookie
    $request = \Illuminate\Http\Request::create('/', 'GET');
    $request->cookies->set('access_token', $token);
    
    echo "Simulated cookie set: access_token\n";
    
    // Read cookie (what middleware does)
    $accessToken = $request->cookie('access_token');
    echo "Cookie read successfully: " . ($accessToken ? 'YES' : 'NO') . "\n";
    
    if ($accessToken) {
        echo "Cookie value (first 50 chars): " . substr($accessToken, 0, 50) . "...\n";
        
        // Validate and authenticate (what middleware does)
        $authenticatedUser = \Tymon\JWTAuth\Facades\JWTAuth::setToken($accessToken)->authenticate();
        
        if ($authenticatedUser) {
            echo "✅ User authenticated from cookie!\n";
            echo "Authenticated user: {$authenticatedUser->email}\n";
            
            // Simulate Auth::login() (what middleware does)
            \Illuminate\Support\Facades\Auth::login($authenticatedUser);
            
            // Check if Auth::check() returns true
            $isAuthenticated = \Illuminate\Support\Facades\Auth::check();
            echo "Auth::check() result: " . ($isAuthenticated ? '✅ TRUE' : '❌ FALSE') . "\n";
            
            if ($isAuthenticated) {
                $currentUser = \Illuminate\Support\Facades\Auth::user();
                echo "Auth::user() email: {$currentUser->email}\n";
                echo "✅ ALL TESTS PASSED! Middleware should work.\n";
            } else {
                echo "❌ ERROR: Auth::check() returned false after Auth::login()!\n";
            }
        } else {
            echo "❌ ERROR: Could not authenticate user from token!\n";
        }
    } else {
        echo "❌ ERROR: Cookie was not read from request!\n";
    }
    echo "\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n\n";
    exit(1);
}

// Test 4: Check middleware registration
echo "TEST 4: Middleware Registration Check\n";
echo "----------------------------\n";
try {
    $middlewareGroups = $app->make('router')->getMiddlewareGroups();
    
    if (isset($middlewareGroups['web'])) {
        echo "Web middleware group exists: YES\n";
        echo "Middleware in 'web' group:\n";
        foreach ($middlewareGroups['web'] as $middleware) {
            echo "  - " . (is_string($middleware) ? $middleware : get_class($middleware)) . "\n";
            if (strpos($middleware, 'AuthenticateFromCookie') !== false || 
                (is_object($middleware) && get_class($middleware) === 'App\Http\Middleware\AuthenticateFromCookie')) {
                echo "    ✅ FOUND: AuthenticateFromCookie middleware!\n";
            }
        }
    } else {
        echo "❌ Web middleware group not found!\n";
    }
    echo "\n";
    
} catch (\Exception $e) {
    echo "⚠️  Could not check middleware: {$e->getMessage()}\n\n";
}

// Test 5: Test actual HTTP request (with cookies)
echo "TEST 5: HTTP Request Test\n";
echo "----------------------------\n";
echo "Testing actual HTTP request with cookies...\n";
try {
    // Create a real HTTP request
    $response = $app->handle(
        \Illuminate\Http\Request::create('/', 'GET', [], ['access_token' => $token])
    );
    
    echo "Response status: {$response->getStatusCode()}\n";
    echo "✅ HTTP request handled successfully\n";
    echo "\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR: {$e->getMessage()}\n\n";
}

// Summary
echo "=================================================\n";
echo "SUMMARY\n";
echo "=================================================\n";
echo "If all tests passed above, the issue is likely:\n";
echo "1. Browser not sending cookies (check DevTools)\n";
echo "2. Cookie domain/path mismatch\n";
echo "3. CORS issue blocking cookies\n";
echo "4. Browser cache - try incognito mode\n";
echo "\n";
echo "Next steps:\n";
echo "1. Clear browser cookies completely\n";
echo "2. Open DevTools → Application → Cookies\n";
echo "3. Login and check if cookies are set\n";
echo "4. Check if 'Secure' flag is UNCHECKED\n";
echo "5. Refresh page and check if cookies are sent\n";
echo "\n";
echo "=================================================\n";