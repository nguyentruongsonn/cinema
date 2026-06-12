<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/api/v1/tickets', 'GET');
$request->setUserResolver(function() { return App\Models\User::find(3); });
$response = $kernel->handle($request);
echo $response->getContent();
