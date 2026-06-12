<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = Illuminate\Http\Request::create('/api/v1/tickets?page=1&per_page=10', 'GET');
$request->headers->set('Accept', 'application/json');
$user = App\Models\User::find(3);
$request->setUserResolver(function() use ($user) { return $user; });

$controller = app()->make(App\Http\Controllers\Api\V1\TicketController::class);
try {
    $response = $controller->index($request);
    echo $response->getContent();
} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
