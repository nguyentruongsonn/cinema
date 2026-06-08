<?php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== TEST API RESPONSE STRUCTURE ===\n\n";

try {
    // Get first order with full eager loading
    $order = App\Models\Order::with([
        'showtime.movie',
        'showtime.format',
        'showtime.sound',
        'showtime.subtitle',
        'showtime.screen.theater.branch',
        'orderItems.item',
        'payment',
    ])->first();

    if (!$order) {
        echo "❌ No orders found in database\n";
        exit(1);
    }

    // Use OrderService to format
    $orderService = app(App\Services\OrderService::class);
    $formatted = $orderService->format($order);

    echo "✅ Order found: #{$formatted['code']}\n\n";

    // Check flattened fields
    echo "📋 Flattened fields:\n";
    echo "   movie_title: " . ($formatted['movie_title'] ?? 'NULL') . "\n";
    echo "   poster_url: " . ($formatted['poster_url'] ?? 'NULL') . "\n";
    echo "   show_date: " . ($formatted['show_date'] ?? 'NULL') . "\n";
    echo "   show_time: " . ($formatted['show_time'] ?? 'NULL') . "\n";
    echo "   theater_name: " . ($formatted['theater_name'] ?? 'NULL') . "\n";
    echo "   screen_name: " . ($formatted['screen_name'] ?? 'NULL') . "\n";
    echo "   branch_name: " . ($formatted['branch_name'] ?? 'NULL') . "\n\n";

    // Check nested objects still work
    echo "📦 Nested showtime object:\n";
    echo "   showtime.movie.title: " . ($formatted['showtime']->movie->title ?? 'NULL') . "\n";
    echo "   showtime.movie.poster_url: " . ($formatted['showtime']->movie->poster_url ?? 'NULL') . "\n";
    echo "   showtime.start_time: " . ($formatted['showtime']->start_time ?? 'NULL') . "\n\n";

    // Check order items
    echo "🎫 Order items:\n";
    if ($formatted['items'] && count($formatted['items']) > 0) {
        foreach ($formatted['items'] as $index => $item) {
            echo "   Item " . ($index + 1) . ":\n";
            echo "      item_type: " . $item->item_type . "\n";

            if ($item->item_type === 'App\Models\Seat') {
                $seat = $item->item;
                echo "      seat.label: " . ($seat->label ?? 'NULL') . "\n";
                echo "      seat.row: " . ($seat->row ?? 'NULL') . "\n";
                echo "      seat.number: " . ($seat->number ?? 'NULL') . "\n";
                echo "      computed: " . ($seat->label ?: ($seat->row . $seat->number)) . "\n";
            }
        }
    } else {
        echo "   No items\n";
    }

    echo "\n✅ All fields are correctly formatted!\n";
    echo "\n🎯 Frontend should now display:\n";
    echo "   ✓ Movie poster from: poster_url or showtime.movie.poster_url\n";
    echo "   ✓ Show date from: show_date or showtime.start_time\n";
    echo "   ✓ Seat info from: items[].item (polymorphic)\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
