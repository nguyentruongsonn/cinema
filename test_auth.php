<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
$app = app();
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::first();
$token = auth('api')->login($user);

// Simulate the request
request()->merge(['token' => $token]);
auth('api')->setToken(request()->token);
$auth_user = auth('api')->user();

echo $auth_user ? "User ID: " . $auth_user->id : "Failed";
