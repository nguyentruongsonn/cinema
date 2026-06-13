<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
$request = Illuminate\Http\Request::create('/api/v1/admin/revenue/stats', 'GET', ['start_date' => '2026-06-08', 'end_date' => '2026-06-13']);
$request->headers->set('Accept', 'application/json');
$user = App\Models\User::first();
$token = Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);
$request->headers->set('Authorization', 'Bearer '.$token);
$response = $kernel->handle($request);
echo $response->getContent();