# 🛠️ IMPLEMENTATION GUIDE
## Cinema Booking System - Quick-Fix Solutions

**Purpose**: Ready-to-implement solutions for P1 and P2 priority issues  
**Target Audience**: Development Team  
**Time to Implement**: 6-8 hours for all P1 issues

---

## 🔴 P1 CRITICAL - Security Issues (Fix Immediately)

### Issue #1: Unauthenticated Payment Webhook
**File**: `routes/api.php` (Line 156)  
**Risk**: 🔴 CRITICAL - Payment Manipulation  
**Estimated Fix Time**: 2.5 hours

#### Step 1: Create Webhook Middleware

Create: `app/Http/Middleware/VerifyPayOSSignature.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyPayOSSignature
{
    /**
     * Verify PayOS webhook signature to prevent tampering.
     * PayOS sends signature in x-payos-signature header.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('x-payos-signature');
        $payosSecret = config('payos.secret_key');

        if (!$signature || !$payosSecret) {
            Log::warning('Missing PayOS webhook signature', [
                'has_signature' => !!$signature,
                'has_secret' => !!$payosSecret,
            ]);
            return response()->json(
                ['error' => 'Invalid webhook signature'],
                403
            );
        }

        // Construct data that was signed
        $rawBody = $request->getContent();
        $computed = hash_hmac('sha256', $rawBody, $payosSecret);

        // Use timing-safe comparison
        if (!hash_equals($signature, $computed)) {
            Log::warning('Invalid PayOS webhook signature', [
                'ip' => $request->ip(),
                'expected' => substr($computed, 0, 10) . '...',
            ]);
            return response()->json(
                ['error' => 'Invalid webhook signature'],
                403
            );
        }

        // Signature valid, proceed
        return $next($request);
    }
}
```

#### Step 2: Register Middleware

In `app/Http/Kernel.php`, add to `$routeMiddleware`:

```php
protected $routeMiddleware = [
    // ... existing middleware
    'verify.payos.signature' => \App\Http\Middleware\VerifyPayOSSignature::class,
];
```

#### Step 3: Update Route

In `routes/api.php` (Line 155-156), change:

```php
// ❌ OLD - VULNERABLE
Route::post('payos/webhook', [UserPaymentController::class, 'handleWebhook']);

// ✅ NEW - SECURED
Route::post('payos/webhook', [UserPaymentController::class, 'handleWebhook'])
    ->middleware('verify.payos.signature')
    ->name('webhook.payos');
```

#### Step 4: Update PaymentService Webhook Handler

In `app/Services/PaymentService.php`, enhance error handling:

```php
/**
 * Handle PayOS webhook with validation.
 */
public function handleWebhook(array $rawData): array
{
    try {
        // Verify data integrity
        if (empty($rawData)) {
            throw new \InvalidArgumentException('Webhook data is empty');
        }

        $webhookData = $this->gateway->verifyWebhook($rawData);

        $orderCode = $webhookData['orderCode'] ?? $webhookData['order_code'] ?? null;
        $status = strtoupper((string) ($webhookData['status'] ?? ''));

        if (!$orderCode) {
            Log::warning('Webhook missing orderCode', $webhookData);
            throw new \InvalidArgumentException('Thiếu orderCode trong webhook.');
        }

        if ($status !== 'PAID' && $status !== 'COMPLETED') {
            Order::where('gateway_order_code', (int) $orderCode)
                ->where('status', Order::STATUS_PENDING)
                ->update(['payment_status' => strtolower($status ?: 'failed')]);

            return ['already_processed' => false, 'skipped' => true];
        }

        return $this->fulfillment->finalize((int) $orderCode);
    } catch (\Exception $e) {
        Log::error('Webhook processing failed', [
            'error' => $e->getMessage(),
            'data' => $rawData,
        ]);
        throw $e;
    }
}
```

---

### Issue #2: Order Access Control Type Casting
**File**: `app/Services/OrderService.php` (Lines 399-404)  
**Risk**: 🔴 HIGH - Authorization Bypass  
**Estimated Fix Time**: 30 minutes

#### Current Code (❌ Vulnerable)

```php
private function ensureUserCanAccess(Order $order, $user): void
{
    if ((int) $order->user_id !== (int) $user->id && !$this->isStaffUser($user)) {
        throw new \RuntimeException('Unauthorized', 403);
    }
}
```

#### Fixed Code (✅ Secure)

```php
private function ensureUserCanAccess(Order $order, $user): void
{
    // Strict comparison without type casting
    if ($order->user_id !== $user->id && !$this->isStaffUser($user)) {
        throw new UnauthorizedException('You do not have access to this order');
    }
}
```

Create custom exception: `app/Exceptions/UnauthorizedException.php`

```php
<?php

namespace App\Exceptions;

use Exception;

class UnauthorizedException extends Exception
{
    public function __construct(string $message = 'Unauthorized')
    {
        parent::__construct($message, 403);
    }

    public function render()
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => $this->message,
            ]
        ], 403);
    }
}
```

Update `app/Services/OrderService.php` imports:

```php
use App\Exceptions\UnauthorizedException;
```

---

### Issue #3: N+1 Query Problem
**File**: `app/Services/OrderService.php` (Lines 111-124)  
**Risk**: 🔴 HIGH - Performance Degradation  
**Estimated Fix Time**: 3 hours

#### Current Code (❌ Performance Issue)

```php
public function getUserOrders($user, int $perPage = 15): LengthAwarePaginator
{
    $this->orderExpirationService->expirePendingOrders();

    $orders = Order::where('user_id', $user->id)
        ->with([
            'showtime.movie',
            'showtime.format',
            'showtime.sound',
            'showtime.subtitle',
            'showtime.screen.theater.branch',
            'orderItems.item',
            'payment',
        ])
        ->latest()
        ->paginate($perPage);

    $orders->getCollection()->transform(fn (Order $order) => $this->format($order));

    return $orders;
}
```

#### Fixed Code (✅ Optimized)

```php
public function getUserOrders(User $user, int $perPage = 10): LengthAwarePaginator
{
    $this->orderExpirationService->expirePendingOrders();

    // Select only needed columns to reduce payload
    $orders = Order::where('user_id', $user->id)
        ->select([
            'id', 'code', 'user_id', 'showtime_id', 
            'total_amount', 'status', 'payment_status',
            'created_at', 'paid_at', 'cancelled_at', 'expired_at'
        ])
        ->with([
            // Only load necessary relationships with column selection
            'showtime:id,movie_id,screen_id,scheduled_at,price' => function ($query) {
                $query->select('id', 'movie_id', 'screen_id', 'scheduled_at', 'price');
            },
            'showtime.movie:id,title,slug,poster_url',
            'showtime.format:id,name',
            'showtime.sound:id,name',
            'showtime.screen:id,theater_id,name' => function ($query) {
                $query->select('id', 'theater_id', 'name');
            },
            'showtime.screen.theater:id,name,branch_id',
            'showtime.screen.theater.branch:id,name',
            'orderItems:id,order_id,item_type,item_id,quantity,unit_price,metadata',
            'orderItems.item',
            'payment:id,order_id,status,transaction_id',
        ])
        ->latest()
        ->paginate($perPage);

    return $orders;
}
```

**Alternative: Use API Resource instead of format() method**

Create: `app/Http/Resources/OrderResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        $statusMap = [
            0 => 'cancelled',
            1 => 'pending',
            2 => 'confirmed',
        ];

        return [
            'id' => $this->id,
            'code' => $this->code,
            'order_code' => $this->code,
            'gateway_order_code' => $this->gateway_order_code,
            'user_id' => $this->user_id,
            'showtime_id' => $this->showtime_id,
            'status' => $statusMap[$this->status] ?? 'unknown',
            'status_code' => (int) $this->status,
            'payment_status' => $this->payment_status,
            'total_amount' => (float) $this->total_amount,
            'total_price' => (float) $this->total_amount,
            'total' => (float) $this->total_amount,
            'movie_title' => $this->showtime?->movie?->title,
            'poster_url' => $this->showtime?->movie?->poster_url,
            'show_date' => $this->showtime?->scheduled_at,
            'theater_name' => $this->showtime?->screen?->theater?->name,
            'screen_name' => $this->showtime?->screen?->name,
            'branch_name' => $this->showtime?->screen?->theater?->branch?->name,
            'items' => $this->orderItems,
            'payment' => $this->payment,
            'created_at' => $this->created_at,
            'paid_at' => $this->paid_at,
            'cancelled_at' => $this->cancelled_at,
            'expired_at' => $this->expired_at,
        ];
    }
}
```

Update Controller: `app/Http/Controllers/OrderController.php`

```php
use App\Http\Resources\OrderResource;

public function userOrders(Request $request)
{
    $user = auth()->user();
    $orders = $this->orderService->getUserOrders($user);
    
    return response()->json([
        'success' => true,
        'data' => OrderResource::collection($orders),
    ]);
}
```

---

## 🟠 P2 HIGH - Security & Architecture (Next Sprint)

### Issue #4: Add Rate Limiting to Public Routes
**File**: `routes/api.php`  
**Risk**: 🟠 HIGH - DDoS Vulnerability  
**Estimated Fix Time**: 1 hour

#### Solution

In `routes/api.php`, update public routes:

```php
// ✅ Rate-limited public routes
Route::middleware('throttle:100,1')->group(function () {
    Route::get('home', [HomeController::class, 'data'])
        ->name('home.data');

    Route::prefix('movies')->group(function () {
        Route::get('/', [MovieController::class, 'index'])
            ->name('api.movies.index');
        Route::get('now-showing', [MovieController::class, 'nowShowing'])
            ->name('api.movies.now-showing');
        Route::get('coming-soon', [MovieController::class, 'comingSoon'])
            ->name('api.movies.coming-soon');
        Route::get('search', [MovieController::class, 'search'])
            ->name('api.movies.search');
        Route::get('{slug}', [MovieController::class, 'show'])
            ->name('api.movies.show');
        Route::get('{slug}/showtimes', [ShowtimeController::class, 'getMovieShowtimes'])
            ->name('api.movies.showtimes');
    });

    Route::prefix('theaters')->group(function () {
        Route::get('/', [TheaterController::class, 'index']);
        Route::get('cities', [TheaterController::class, 'cities']);
        Route::get('{id}/screens', [TheaterController::class, 'screens']);
        Route::get('{id}', [TheaterController::class, 'show']);
    });

    Route::prefix('screens')->group(function () {
        Route::get('/', [ScreenController::class, 'index']);
        Route::get('{id}', [ScreenController::class, 'show']);
    });

    Route::prefix('showtimes')->group(function () {
        Route::get('/', [ShowtimeController::class, 'index']);
        Route::get('{id}', [ShowtimeController::class, 'show']);
    });
});
```

---

### Issue #5: Implement Custom Exceptions
**File**: Create in `app/Exceptions/`  
**Risk**: 🟠 HIGH - Code Quality  
**Estimated Fix Time**: 1.5 hours

Create: `app/Exceptions/SeatAlreadyBookedException.php`

```php
<?php

namespace App\Exceptions;

use Exception;

class SeatAlreadyBookedException extends Exception
{
    public function __construct(array $seatIds = [])
    {
        $message = 'Một số ghế đã được đặt hoặc đang chờ thanh toán';
        if (!empty($seatIds)) {
            $message .= ': ' . implode(', ', $seatIds);
        }
        parent::__construct($message, 422);
    }

    public function render()
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'SEAT_ALREADY_BOOKED',
                'message' => $this->message,
            ]
        ], $this->code);
    }
}
```

Create: `app/Exceptions/InvalidPromotionCodeException.php`

```php
<?php

namespace App\Exceptions;

use Exception;

class InvalidPromotionCodeException extends Exception
{
    public function __construct(string $message = 'Mã khuyến mãi không hợp lệ hoặc đã hết hạn')
    {
        parent::__construct($message, 422);
    }

    public function render()
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'INVALID_PROMOTION',
                'message' => $this->message,
            ]
        ], 422);
    }
}
```

Update `app/Services/OrderService.php`:

```php
use App\Exceptions\SeatAlreadyBookedException;
use App\Exceptions\InvalidPromotionCodeException;

// In create method, replace:
if (!empty($bookedSeatIds)) {
    throw new SeatAlreadyBookedException($bookedSeatIds);
}

// In applyPromotion method, replace:
if (!$promotion) {
    throw new InvalidPromotionCodeException();
}
```

---

### Issue #6: Move JWT to HttpOnly Cookies
**File**: `app/Http/Controllers/AuthController.php`  
**Risk**: 🟠 HIGH - XSS Vulnerability  
**Estimated Fix Time**: 2 hours

#### Create Cookie Helper Trait

Create: `app/Traits/ManagesCookies.php`

```php
<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ManagesCookies
{
    protected function addAuthCookies(JsonResponse $response, string $accessToken, string $refreshToken): JsonResponse
    {
        $accessTokenTTL = config('auth.jwt.ttl', 60); // minutes
        $refreshTokenTTL = config('auth.refresh_token_ttl', 30); // days

        return $response
            ->cookie(
                'access_token',
                $accessToken,
                $accessTokenTTL, // minutes
                '/',
                config('session.domain'),
                config('app.env') === 'production',
                true, // httpOnly
                false,
                'lax'
            )
            ->cookie(
                'refresh_token',
                $refreshToken,
                $refreshTokenTTL * 24 * 60, // convert to minutes
                '/',
                config('session.domain'),
                config('app.env') === 'production',
                true, // httpOnly
                false,
                'lax'
            );
    }
}
```

Update `app/Http/Controllers/AuthController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Traits\ManagesCookies;
// ... other imports

class AuthController extends Controller
{
    use ManagesCookies;

    public function login(LoginRequest $request): JsonResponse
    {
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        $credentials = $request->validated();
        $result = $this->authService->login($credentials, $ipAddress, $userAgent);

        if (!$result) {
            return response()->json([
                'success' => false,
                'error' => 'Đăng nhập không thành công',
            ], 401);
        }

        $response = response()->json([
            'success' => true,
            'message' => 'Đăng nhập thành công',
            'data' => $result['user'],
            'token_type' => 'Bearer',
            'expires_in' => $result['expires_in'],
        ]);

        // Add HttpOnly cookies
        return $this->addAuthCookies($response, $result['access_token'], $result['refresh_token']);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        $result = $this->authService->register($request->validated(), $ipAddress, $userAgent);

        $response = response()->json([
            'success' => true,
            'message' => 'Đăng ký thành công',
            'data' => $result['user'],
        ], 201);

        return $this->addAuthCookies($response, $result['access_token'], $result['refresh_token']);
    }
}
```

Update Frontend: `public/js/auth-manager.js`

```javascript
// ✅ NEW - Read from cookies instead of localStorage
class AuthManager {
    getAccessToken() {
        // Token will be automatically sent in cookies by browser
        return this.getFromCookie('access_token');
    }

    getFromCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }

    async fetchWithAuth(url, options = {}) {
        return fetch(url, {
            ...options,
            credentials: 'include', // Include cookies
            headers: {
                ...options.headers,
                'Content-Type': 'application/json',
            },
        });
    }
}
```

---

## ✅ Verification Checklist

After implementing fixes, verify:

- [ ] P1 Security issues resolved
  - [ ] Webhook requires signature
  - [ ] Order access control strict comparison
  - [ ] N+1 queries optimized
- [ ] Code compiles without errors
- [ ] Tests pass (existing tests)
- [ ] Manual security testing completed
- [ ] Performance improved (check query logs)
- [ ] Team review completed

---

## 📊 Implementation Timeline

| Phase | Duration | Tasks |
|-------|----------|-------|
| Phase 1 (Day 1) | 4 hours | Implement webhook security + custom exceptions |
| Phase 2 (Day 1-2) | 2 hours | Fix order access control |
| Phase 3 (Day 2-3) | 3 hours | Optimize N+1 queries |
| Phase 4 (Day 3-4) | 2 hours | Move JWT to cookies + rate limiting |
| Verification | 1 hour | Testing and deployment prep |
| **Total** | **12 hours** | **All P1 issues** |

---

**Generated**: June 8, 2026  
**Status**: Ready for Implementation  
**Questions**: Escalate to Tech Lead
