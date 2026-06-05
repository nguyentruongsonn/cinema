<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== COMPREHENSIVE SYSTEM CHECK ===\n\n";

// 1. Database Connection
echo "1. DATABASE CONNECTION\n";
try {
    DB::connection()->getPdo();
    echo "✓ Database connected\n";
    echo "   Database: " . config('database.connections.mysql.database') . "\n";
} catch (\Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
}

// 2. Check Critical Tables
echo "\n2. CRITICAL TABLES CHECK\n";
$tables = [
    'users', 'roles', 'permissions', 'movies', 'categories',
    'theaters', 'screens', 'seats', 'seat_types', 'showtimes',
    'orders', 'order_items', 'payments', 'seat_holds',
    'products', 'promotions', 'formats', 'sounds', 'subtitles'
];

foreach ($tables as $table) {
    try {
        $count = DB::table($table)->count();
        echo "✓ {$table}: {$count} records\n";
    } catch (\Exception $e) {
        echo "✗ {$table}: " . $e->getMessage() . "\n";
    }
}

// 3. Check Models
echo "\n3. MODEL CHECKS\n";
$models = [
    'User' => \App\Models\User::class,
    'Movie' => \App\Models\Movie::class,
    'Theater' => \App\Models\Theater::class,
    'Showtime' => \App\Models\Showtime::class,
    'Order' => \App\Models\Order::class,
    'Product' => \App\Models\Product::class,
    'Promotion' => \App\Models\Promotion::class,
];

foreach ($models as $name => $class) {
    try {
        $count = $class::count();
        echo "✓ {$name} model: {$count} records\n";
    } catch (\Exception $e) {
        echo "✗ {$name} model error: " . $e->getMessage() . "\n";
    }
}

// 4. Check Showtimes with Relations
echo "\n4. SHOWTIME RELATIONSHIP CHECK\n";
try {
    $showtime = \App\Models\Showtime::with(['movie', 'screen', 'screen.theater'])->first();
    if ($showtime) {
        echo "✓ Showtime found: " . $showtime->id . "\n";
        echo "  Movie: " . ($showtime->movie ? $showtime->movie->title : 'NULL') . "\n";
        echo "  Screen: " . ($showtime->screen ? $showtime->screen->name : 'NULL') . "\n";
        echo "  Theater: " . ($showtime->screen && $showtime->screen->theater ? $showtime->screen->theater->name : 'NULL') . "\n";
    } else {
        echo "⚠ No showtimes found\n";
    }
} catch (\Exception $e) {
    echo "✗ Showtime relationship error: " . $e->getMessage() . "\n";
}

// 5. Check Seats with Types
echo "\n5. SEAT CONFIGURATION CHECK\n";
try {
    $seat = \App\Models\Seat::with('seatType')->first();
    if ($seat) {
        echo "✓ Seat found: " . $seat->label . "\n";
        echo "  Type: " . ($seat->seatType ? $seat->seatType->name : 'NULL') . "\n";
        echo "  Surcharge: " . ($seat->seatType ? $seat->seatType->surcharge : '0') . "\n";
    } else {
        echo "⚠ No seats found\n";
    }
} catch (\Exception $e) {
    echo "✗ Seat configuration error: " . $e->getMessage() . "\n";
}

// 6. Check Products
echo "\n6. PRODUCT CHECK\n";
try {
    $products = \App\Models\Product::where('status', 1)->where('stock', '>', 0)->get();
    echo "✓ Active products: " . $products->count() . "\n";
    foreach ($products->take(3) as $product) {
        echo "  - {$product->name}: {$product->price}đ (stock: {$product->stock})\n";
    }
} catch (\Exception $e) {
    echo "✗ Product error: " . $e->getMessage() . "\n";
}

// 7. Check Promotions
echo "\n7. PROMOTION CHECK\n";
try {
    $promotions = \App\Models\Promotion::where('status', 1)->get();
    echo "✓ Active promotions: " . $promotions->count() . "\n";
    foreach ($promotions->take(3) as $promo) {
        echo "  - {$promo->code}: {$promo->type} ({$promo->value})\n";
    }
} catch (\Exception $e) {
    echo "✗ Promotion error: " . $e->getMessage() . "\n";
}

// 8. Check Services
echo "\n8. SERVICE CLASS CHECK\n";
$services = [
    'OrderService' => \App\Services\OrderService::class,
    'SeatService' => \App\Services\SeatService::class,
    'ShowtimeService' => \App\Services\ShowtimeService::class,
    'PaymentService' => \App\Services\PaymentService::class,
    'OrderExpirationService' => \App\Services\OrderExpirationService::class,
];

foreach ($services as $name => $class) {
    if (class_exists($class)) {
        echo "✓ {$name} exists\n";
    } else {
        echo "✗ {$name} missing\n";
    }
}

// 9. Check Controllers
echo "\n9. CONTROLLER CHECK\n";
$controllers = [
    'OrderController' => \App\Http\Controllers\OrderController::class,
    'SeatController' => \App\Http\Controllers\SeatController::class,
    'PaymentController' => \App\Http\Controllers\PaymentController::class,
    'ProductController' => \App\Http\Controllers\ProductController::class,
    'PromotionController' => \App\Http\Controllers\PromotionController::class,
];

foreach ($controllers as $name => $class) {
    if (class_exists($class)) {
        echo "✓ {$name} exists\n";
    } else {
        echo "✗ {$name} missing\n";
    }
}

// 10. Check Environment Variables
echo "\n10. ENVIRONMENT CONFIGURATION\n";
$envVars = [
    'APP_ENV' => env('APP_ENV'),
    'APP_DEBUG' => env('APP_DEBUG') ? 'true' : 'false',
    'DB_CONNECTION' => env('DB_CONNECTION'),
    'DB_DATABASE' => env('DB_DATABASE'),
    'JWT_SECRET' => env('JWT_SECRET') ? '✓ Set' : '✗ Missing',
    'PAYOS_CLIENT_ID' => env('PAYOS_CLIENT_ID') ? '✓ Set' : '✗ Missing',
    'PAYOS_API_KEY' => env('PAYOS_API_KEY') ? '✓ Set' : '✗ Missing',
];

foreach ($envVars as $key => $value) {
    echo "  {$key}: {$value}\n";
}

// 11. Check Routes
echo "\n11. ROUTE CHECK\n";
try {
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $apiRoutes = array_filter(iterator_to_array($routes), function($route) {
        return str_starts_with($route->uri(), 'api/');
    });
    echo "✓ Total API routes: " . count($apiRoutes) . "\n";

    $criticalRoutes = [
        'api/orders',
        'api/seats/lock',
        'api/payments',
        'api/products',
        'api/promotions/validate',
    ];

    foreach ($criticalRoutes as $uri) {
        $found = false;
        foreach ($apiRoutes as $route) {
            if (str_contains($route->uri(), str_replace('api/', '', $uri))) {
                $found = true;
                break;
            }
        }
        echo ($found ? "✓" : "✗") . " Route: {$uri}\n";
    }
} catch (\Exception $e) {
    echo "✗ Route check error: " . $e->getMessage() . "\n";
}

// 12. Test Order Creation Logic
echo "\n12. ORDER CREATION SIMULATION\n";
try {
    $showtime = \App\Models\Showtime::first();
    $user = \App\Models\User::first();

    if ($showtime && $user) {
        echo "✓ Prerequisites exist\n";
        echo "  Showtime ID: {$showtime->id}\n";
        echo "  User ID: {$user->id}\n";

        // Check seats for this showtime
        $seats = \App\Models\Seat::where('screen_id', $showtime->screen_id)->take(2)->get();
        echo "  Available seats: " . $seats->count() . "\n";
    } else {
        echo "⚠ Missing prerequisites (showtime or user)\n";
    }
} catch (\Exception $e) {
    echo "✗ Order simulation error: " . $e->getMessage() . "\n";
}

// 13. Check Middleware
echo "\n13. MIDDLEWARE CHECK\n";
$middlewares = [
    'JwtMiddleware' => \App\Http\Middleware\JwtMiddleware::class,
    'RoleMiddleware' => \App\Http\Middleware\RoleMiddleware::class,
    'PermissionMiddleware' => \App\Http\Middleware\PermissionMiddleware::class,
];

foreach ($middlewares as $name => $class) {
    if (class_exists($class)) {
        echo "✓ {$name} exists\n";
    } else {
        echo "✗ {$name} missing\n";
    }
}

// 14. Check Laravel Log for Recent Errors
echo "\n14. RECENT ERROR CHECK\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", $logContent);
    $recentLines = array_slice($lines, -50); // Last 50 lines
    $errors = array_filter($recentLines, function($line) {
        return stripos($line, 'error') !== false || stripos($line, 'exception') !== false;
    });

    if (!empty($errors)) {
        echo "⚠ Recent errors found:\n";
        foreach (array_slice($errors, -5) as $error) {
            echo "  " . substr($error, 0, 100) . "...\n";
        }
    } else {
        echo "✓ No recent errors in log\n";
    }
} else {
    echo "⚠ Log file not found\n";
}

echo "\n=== CHECK COMPLETE ===\n";
