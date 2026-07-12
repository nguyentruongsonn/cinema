<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Order;
use App\Services\OrderService;

try {
    $order = Order::first();
    if (!$order) {
        echo "No orders found in DB\n";
        exit;
    }
    $user = User::find($order->user_id);
    echo "Running getUserOrders for user ID: " . $user->id . "\n";
    $orderService = app(OrderService::class);
    $orders = $orderService->getUserOrders($user, 10);
    echo "Success! Found " . $orders->total() . " orders.\n";
    echo json_encode($orders->items(), JSON_PRETTY_PRINT) . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
