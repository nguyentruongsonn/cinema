<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Load Laravel bootstrap
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$config = [
    'status' => 'OK',
    'timestamp' => date('Y-m-d H:i:s'),
    'environment' => [
        'APP_ENV' => env('APP_ENV'),
        'APP_URL' => env('APP_URL'),
        'APP_DEBUG' => env('APP_DEBUG'),
    ],
    'session' => [
        'driver' => config('session.driver'),
        'secure' => config('session.secure'),
        'same_site' => config('session.same_site'),
        'http_only' => config('session.http_only'),
        'path' => config('session.path'),
        'domain' => config('session.domain'),
    ],
    'jwt' => [
        'ttl' => env('JWT_TTL'),
        'refresh_ttl' => env('JWT_REFRESH_TTL'),
        'secret_set' => !empty(env('JWT_SECRET')),
    ],
    'cookies_analysis' => [
        'access_token_present' => isset($_COOKIE['access_token']),
        'refresh_token_present' => isset($_COOKIE['refresh_token']),
    ],
    'recommendations' => []
];

// Add recommendations based on config
if (config('session.secure') === true && env('APP_ENV') === 'local') {
    $config['recommendations'][] = '⚠️ session.secure is TRUE in local environment - cookies will not work over HTTP!';
}

if (config('session.secure') === false && env('APP_ENV') === 'production') {
    $config['recommendations'][] = '⚠️ session.secure is FALSE in production - security risk!';
}

if (!isset($_COOKIE['access_token']) && !isset($_COOKIE['refresh_token'])) {
    $config['recommendations'][] = '💡 No auth cookies found - you need to login first';
}

if (env('APP_URL') !== 'http://' . $_SERVER['HTTP_HOST']) {
    $config['recommendations'][] = '⚠️ APP_URL (' . env('APP_URL') . ') does not match current host (' . $_SERVER['HTTP_HOST'] . ')';
}

echo json_encode($config, JSON_PRETTY_PRINT);