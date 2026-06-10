<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Debug Auth Login Issue ===\n\n";

// Test 1: Check JWT config
echo "1. JWT Configuration:\n";
echo "   JWT_TTL: " . config('jwt.ttl') . " minutes\n";
echo "   JWT_REFRESH_TTL: " . config('jwt.refresh_ttl') . " minutes\n";
echo "   JWT_SECRET: " . (config('jwt.secret') ? 'Set (' . strlen(config('jwt.secret')) . ' chars)' : 'NOT SET') . "\n";
echo "   SESSION_SECURE: " . (config('session.secure') ? 'true' : 'false') . "\n";
echo "   SESSION_SAME_SITE: " . config('session.same_site') . "\n\n";

// Test 2: Try to login
echo "2. Testing Login Process:\n";
$email = 'admin@cinema.com'; // Change to your test email
$password = 'password123'; // Change to your test password

try {
    $user = \App\Models\User::where('email', $email)->first();
    
    if (!$user) {
        echo "   ERROR: User not found\n";
        exit(1);
    }
    
    echo "   User found: {$user->name} (ID: {$user->id})\n";
    
    // Check password
    if (!\Hash::check($password, $user->password)) {
        echo "   ERROR: Password incorrect\n";
        exit(1);
    }
    
    echo "   Password correct\n";
    
    // Generate tokens like AuthController does
    $accessToken = auth()->claims([
        'type' => 'access',
        'user_id' => $user->id,
    ])->setTTL(config('jwt.ttl', 15))->tokenById($user->id);
    
    echo "   Access token generated: " . substr($accessToken, 0, 50) . "...\n";
    echo "   Access token length: " . strlen($accessToken) . " chars\n";
    
    $refreshToken = auth()->claims([
        'type' => 'refresh',
        'user_id' => $user->id,
    ])->setTTL(config('jwt.refresh_ttl', 20160))->tokenById($user->id);
    
    echo "   Refresh token generated: " . substr($refreshToken, 0, 50) . "...\n";
    echo "   Refresh token length: " . strlen($refreshToken) . " chars\n\n";
    
    // Test 3: Try to validate the access token immediately
    echo "3. Testing Token Validation:\n";
    
    try {
        auth()->setToken($accessToken);
        $payload = auth()->getPayload();
        
        echo "   Token payload:\n";
        echo "   - type: " . $payload->get('type') . "\n";
        echo "   - user_id: " . $payload->get('user_id') . "\n";
        echo "   - sub: " . $payload->get('sub') . "\n";
        echo "   - iat: " . date('Y-m-d H:i:s', $payload->get('iat')) . "\n";
        echo "   - exp: " . date('Y-m-d H:i:s', $payload->get('exp')) . "\n";
        echo "   - now: " . date('Y-m-d H:i:s', time()) . "\n";
        
        $authenticated = auth()->authenticate();
        
        if ($authenticated) {
            echo "   ✓ Token validation SUCCESS\n";
            echo "   Authenticated user: {$authenticated->name}\n\n";
        } else {
            echo "   ✗ Token validation FAILED - authenticate() returned null\n\n";
        }
        
    } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
        echo "   ✗ Token EXPIRED immediately: " . $e->getMessage() . "\n\n";
    } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
        echo "   ✗ Token INVALID: " . $e->getMessage() . "\n\n";
    } catch (\Exception $e) {
        echo "   ✗ Token validation ERROR: " . $e->getMessage() . "\n\n";
    }
    
    // Test 4: Simulate middleware behavior
    echo "4. Testing Middleware Cookie Validation:\n";
    
    // Create a mock request with cookie
    $request = \Illuminate\Http\Request::create('/test', 'GET');
    $request->cookies->set('access_token', $accessToken);
    
    echo "   Cookie set in request\n";
    
    $middleware = new \App\Http\Middleware\AuthenticateFromCookie();
    
    try {
        $response = $middleware->handle($request, function($req) {
            echo "   ✓ Middleware passed request through\n";
            echo "   Auth user: " . (auth()->user() ? auth()->user()->name : 'NULL') . "\n";
            return response()->json(['success' => true]);
        });
        
        echo "   Response status: " . $response->getStatusCode() . "\n\n";
        
    } catch (\Exception $e) {
        echo "   ✗ Middleware ERROR: " . $e->getMessage() . "\n\n";
    }
    
    // Test 5: Check for clock skew
    echo "5. Checking for Clock Skew Issues:\n";
    $iat = $payload->get('iat');
    $exp = $payload->get('exp');
    $now = time();
    
    echo "   Issued at (iat): " . $iat . " (" . date('Y-m-d H:i:s', $iat) . ")\n";
    echo "   Expires at (exp): " . $exp . " (" . date('Y-m-d H:i:s', $exp) . ")\n";
    echo "   Current time: " . $now . " (" . date('Y-m-d H:i:s', $now) . ")\n";
    echo "   Time to expiry: " . ($exp - $now) . " seconds\n";
    
    if ($iat > $now) {
        echo "   ⚠ WARNING: Token issued in the future! Clock skew detected.\n";
    } elseif ($exp < $now) {
        echo "   ✗ ERROR: Token already expired!\n";
    } else {
        echo "   ✓ Token timing looks correct\n";
    }
    
} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== Debug Complete ===\n";