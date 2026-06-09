# DETAILED IMPLEMENTATION GUIDE - CRITICAL FIXES
## Cinema Booking System | Step-by-Step Fixes for Production Ready

---

## FIX #1: AUTHORIZATION BYPASS (Priority: 🔴 CRITICAL, Time: 2 hours)

### Location: app/Http/Controllers/BookingController.php, OrderController.php

### Step 1: Create Trait for Authorization
```php
// app/Traits/ChecksOrderOwnership.php
<?php
namespace App\Traits;

use App\Models\Order;
use Illuminate\Auth\Access\AuthorizationException;

trait ChecksOrderOwnership {
    protected function authorizeOrder(Order $order): void {
        if ($order->user_id !== auth()->id()) {
            throw new AuthorizationException('Unauthorized');
        }
    }
}
```

### Step 2: Update BookingController
```php
// In app/Http/Controllers/BookingController.php
use App\Traits\ChecksOrderOwnership;

class BookingController {
    use ChecksOrderOwnership;
    
    public function syncOrderStatus($orderCode) {
        $order = Order::where('gateway_order_code', $orderCode)->first();
        
        // ADD THIS CHECK
        $this->authorizeOrder($order);
        
        // Rest of code...
    }
}
```

### Step 3: Test Authorization
```php
// tests/Feature/AuthorizationTest.php
public function test_user_cannot_view_other_users_order() {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user1->id]);
    
    $this->actingAs($user2)
        ->getJson("/api/orders/{$order->gateway_order_code}")
        ->assertForbidden();
}
```

---

## FIX #2: WEBHOOK SIGNATURE VERIFICATION (Priority: 🔴 CRITICAL, Time: 4 hours)

### Step 1: Create Middleware
```php
// app/Http/Middleware/VerifyPayOSSignature.php
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyPayOSSignature {
    public function handle(Request $request, Closure $next) {
        $signature = $request->header('x-payos-signature');
        $payload = file_get_contents('php://input');
        
        $expectedSignature = hash_hmac(
            'sha256',
            $payload,
            config('payos.client_secret')
        );
        
        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }
        
        return $next($request);
    }
}
```

### Step 2: Register Middleware
```php
// app/Http/Kernel.php
protected $routeMiddleware = [
    'verify.payos' => \App\Http\Middleware\VerifyPayOSSignature::class,
];
```

### Step 3: Apply to Routes
```php
// routes/api.php
Route::post('webhook/payos', [PaymentController::class, 'handleWebhook'])
    ->middleware('verify.payos')
    ->withoutMiddleware('auth:api');
```

---

## FIX #3: RACE CONDITIONS - PESSIMISTIC LOCKING (Priority: 🔴 CRITICAL, Time: 6 hours)

### Step 1: Fix Promotion Double-Use
```php
// app/Services/PromotionService.php (create if needed)
public function applyPromotion(Promotion $promotionId): void {
    $promotion = Promotion::lockForUpdate()->find($promotionId);
    
    if ($promotion->used_count >= $promotion->usage_limit) {
        throw new \InvalidArgumentException('Promotion limit reached');
    }
    
    $promotion->increment('used_count');
}
```

### Step 2: Fix Seat Double-Booking
```php
// app/Services/SeatService.php
public function lockSeatsForBooking(array $seatIds, int $showtimeId): Collection {
    return Seat::lockForUpdate()
        ->whereIn('id', $seatIds)
        ->with('holds')
        ->get()
        ->each(function ($seat) {
            $seat->assertAvailable();
        });
}
```

### Step 3: Update Order Creation
```php
// app/Services/OrderService.php
public function createOrder(User $user, array $seatIds, int $showtimeId): Order {
    return DB::transaction(function () use ($user, $seatIds, $showtimeId) {
        // Lock and verify seats
        $seats = $this->seatService->lockSeatsForBooking($seatIds, $showtimeId);
        
        // Create order items
        $orderItems = collect($seatIds)->map(fn($seatId) => [
            'item_type' => 'seat',
            'item_id' => $seatId,
        ]);
        
        return Order::create([...]);
    }, 3); // 3 retry attempts
}
```

---

## FIX #4: MISSING DATABASE INDEXES (Priority: 🔴 CRITICAL, Time: 1 hour)

### Create Migration
```php
// database/migrations/2026_06_08_add_indexes.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('gateway_order_code');
            $table->index('status');
            $table->index('showtime_id');
            $table->index(['created_at', 'user_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index('order_id');
            $table->index(['item_type', 'item_id']);
        });

        Schema::table('seat_holds', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('showtime_id');
            $table->index('expires_at');
        });

        Schema::table('user_promotions', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('promotion_id');
        });
    }

    public function down(): void {
        // Drop indexes
    }
};
```

### Run Migration
```bash
php artisan migrate
```

---

## FIX #5: RATE LIMITING (Priority: 🟠 HIGH, Time: 2 hours)

### Step 1: Update Routes
```php
// routes/api.php
Route::middleware('throttle:60,1')->group(function () {
    Route::get('movies', [MovieController::class, 'index']);
    Route::get('products', [ProductController::class, 'index']);
    Route::get('theaters', [TheaterController::class, 'index']);
});

Route::middleware('throttle:30,1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
});

Route::middleware(['auth:api', 'throttle:100,1'])->group(function () {
    Route::apiResource('orders', OrderController::class);
});
```

---

## FIX #6: WEBHOOK QUEUE PROCESSING (Priority: 🟠 HIGH, Time: 3 hours)

### Step 1: Create Job
```php
// app/Jobs/ProcessPaymentWebhook.php
<?php
namespace App\Jobs;

use App\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessPaymentWebhook implements ShouldQueue {
    use Queueable;

    public function __construct(
        public array $data
    ) {}

    public function handle(PaymentService $service): void {
        $service->handleWebhook($this->data);
    }
}
```

### Step 2: Update Controller
```php
// app/Http/Controllers/PaymentController.php
public function handleWebhook(Request $request) {
    ProcessPaymentWebhook::dispatch($request->all());
    return response()->json(['received' => true], 202);
}
```

---

## FIX #7: REMOVE LOCALSTORAGE TOKENS (Priority: 🔴 CRITICAL, Time: 1 hour)

### Update Frontend
```javascript
// public/js/pages/tickets.js - REMOVE THIS
// let authToken = localStorage.getItem('authToken');

// VERIFY backend is already sending HttpOnly cookie
// No frontend change needed - just remove localStorage usage

// Replace any token access with:
// Token is automatically sent in HttpOnly cookie by browser
```

---

## FIX #8: ADD CSRF PROTECTION (Priority: 🟠 HIGH, Time: 2 hours)

### Step 1: Add CSRF Middleware
```php
// routes/api.php
Route::middleware(['csrf'])->group(function () {
    Route::post('orders', [OrderController::class, 'store']);
    Route::put('orders/{id}', [OrderController::class, 'update']);
    Route::delete('orders/{id}', [OrderController::class, 'destroy']);
});
```

### Step 2: Frontend - Add Token
```javascript
// Get CSRF token from meta tag
const getCsrfToken = () => {
    return document.querySelector('meta[name="csrf-token"]')?.content;
};

// Use in all POST/PUT/DELETE requests
fetch('/api/orders', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': getCsrfToken(),
    },
    body: JSON.stringify(data),
});
```

---

## PHASE 1 COMPLETION CHECKLIST

- [ ] Authorization trait added + tested
- [ ] Webhook signature middleware created
- [ ] Database indexes migrated
- [ ] Locking implemented for seats/promotions
- [ ] Rate limiting applied to routes
- [ ] Queue job for webhooks created
- [ ] localStorage token usage removed
- [ ] CSRF tokens added to forms
- [ ] All Phase 1 tests pass
- [ ] Security audit passed (composer audit)

**Estimated Total**: 40-50 hours for complete Phase 1

---

**Next**: After Phase 1 complete, proceed with Phase 2 (Test Suite + Performance)
