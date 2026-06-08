<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TEST TICKETS API ===\n\n";

try {
    // 1. Check if we have any users
    $userCount = DB::table('users')->count();
    echo "1. Users in database: {$userCount}\n";

    if ($userCount > 0) {
        $user = DB::table('users')->first();
        echo "   Sample user ID: {$user->id}, Email: {$user->email}\n";
    }

    // 2. Check if we have any orders
    $orderCount = DB::table('orders')->count();
    echo "\n2. Orders in database: {$orderCount}\n";

    if ($orderCount > 0) {
        $orders = DB::table('orders')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->select('orders.*', 'users.email')
            ->limit(5)
            ->get();

        echo "   Sample orders:\n";
        foreach ($orders as $order) {
            echo "   - Order #{$order->code} (User: {$order->email}, Status: {$order->status})\n";
        }

        // 3. Test eager loading for first order
        $testOrder = App\Models\Order::with([
            'showtime.movie',
            'showtime.format',
            'showtime.sound',
            'showtime.subtitle',
            'showtime.screen.theater.branch',
            'orderItems.item',
            'payment',
        ])->first();

        if ($testOrder) {
            echo "\n3. Testing eager loading for Order #{$testOrder->code}:\n";
            echo "   - Movie: " . ($testOrder->showtime->movie->title ?? 'NULL') . "\n";
            echo "   - Format: " . ($testOrder->showtime->format->name ?? 'NULL') . "\n";
            echo "   - Sound: " . ($testOrder->showtime->sound->name ?? 'NULL') . "\n";
            echo "   - Subtitle: " . ($testOrder->showtime->subtitle->name ?? 'NULL') . "\n";
            echo "   - Theater: " . ($testOrder->showtime->screen->theater->name ?? 'NULL') . "\n";
            echo "   - Branch: " . ($testOrder->showtime->screen->theater->branch->name ?? 'NULL') . "\n";
            echo "   - Order Items: " . $testOrder->orderItems->count() . "\n";

            if ($testOrder->orderItems->count() > 0) {
                $firstItem = $testOrder->orderItems->first();
                if ($firstItem->item_type === 'App\Models\Seat') {
                    echo "   - First Seat: " . ($firstItem->item->label ?? ($firstItem->item->row . $firstItem->item->number)) . "\n";
                }
            }
        }
    } else {
        echo "   ⚠️  No orders found! You need to create some test orders first.\n";
        echo "   Run: php artisan db:seed --class=OrderSeeder (if exists)\n";
        echo "   Or create orders through the booking flow on the website.\n";
    }

    // 4. Check showtime relationships
    echo "\n4. Checking showtime data:\n";
    $showtimeCount = DB::table('showtimes')->count();
    echo "   - Showtimes: {$showtimeCount}\n";

    if ($showtimeCount > 0) {
        $showtime = App\Models\Showtime::with(['format', 'sound', 'subtitle'])->first();
        echo "   - Sample showtime has format: " . ($showtime->format ? 'YES' : 'NO') . "\n";
        echo "   - Sample showtime has sound: " . ($showtime->sound ? 'YES' : 'NO') . "\n";
        echo "   - Sample showtime has subtitle: " . ($showtime->subtitle ? 'YES' : 'NO') . "\n";
    }

    echo "\n✅ Test completed!\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
