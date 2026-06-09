# LỘ TRÌNH SỬA & REFACTOR - PHASE 2 CHẤT LƯỢNG
## Cinema Booking System | 2 Tuần | 60-80 Giờ
**Mục tiêu**: Từ 7.0/10 → 8.0/10 (Public Release Ready)

---

## TỔNG QUAN GIAI ĐOẠN 2

| Tuần | Ngày | Task | Giờ | Priority |
|------|------|------|-----|----------|
| Tuần 2 | T1 | Feature Tests (Authorization) | 8 | 🟠 HIGH |
| | T1 | Unit Tests (Services) | 12 | 🟠 HIGH |
| | T2 | Integration Tests (Orders) | 12 | 🟠 HIGH |
| | T3 | Concurrency & Race Condition Tests | 8 | 🟠 HIGH |
| Tuần 3 | T4 | N+1 Query Optimization | 10 | 🟠 HIGH |
| | T5 | Email Verification Flow | 4 | 🟠 HIGH |
| | T5 | Error Boundary Frontend | 3 | 🟠 HIGH |
| | T6 | API Response Standardization | 4 | 🟠 HIGH |
| | T7 | Performance Monitoring | 3 | 🟠 HIGH |
| | | **TOTAL** | **64 giờ** | |

---

## TASK 1: FEATURE TESTS - AUTHORIZATION (T1, 8 Giờ)

### File: tests/Feature/AuthorizationTest.php (NEW)

```php
class AuthorizationTest extends TestCase {
    // User cannot view other user's order
    public function test_user_cannot_view_others_order() { ... }
    
    // User cannot cancel other user's order
    public function test_user_cannot_cancel_others_order() { ... }
    
    // User cannot refund other user's order
    public function test_user_cannot_refund_others_order() { ... }
    
    // Guest cannot access protected endpoints
    public function test_guest_cannot_access_orders() { ... }
}
```

### Các test cần viết:
- [ ] Unauthorized GET /orders/{id}
- [ ] Unauthorized PUT /orders/{id}
- [ ] Unauthorized DELETE /orders/{id}
- [ ] Unauthorized PATCH /profile/{id}
- [ ] Guest access protection
- [ ] Admin route protection

---

## TASK 2: UNIT TESTS - SERVICES (T1, 12 Giờ)

### Files: tests/Unit/Services/*

#### OrderService Tests
```php
class OrderServiceTest extends TestCase {
    public function test_order_creation_locks_seats() { ... }
    public function test_order_fails_if_seat_taken() { ... }
    public function test_order_applies_promotion_correctly() { ... }
    public function test_order_calculation_is_accurate() { ... }
}
```

#### PaymentService Tests
```php
class PaymentServiceTest extends TestCase {
    public function test_creates_payment_link() { ... }
    public function test_webhook_finalizes_order() { ... }
    public function test_sync_updates_order_status() { ... }
}
```

#### PricingService Tests
```php
class PricingServiceTest extends TestCase {
    public function test_calculates_seat_pricing() { ... }
    public function test_applies_percentage_discount() { ... }
    public function test_applies_fixed_discount() { ... }
    public function test_handles_points_redemption() { ... }
}
```

---

## TASK 3: INTEGRATION TESTS - ORDERS (T2, 12 Giờ)

### File: tests/Feature/OrderTest.php (NEW)

```php
class OrderTest extends TestCase {
    // Complete booking flow
    public function test_complete_booking_flow() {
        // 1. User creates order
        // 2. Payment initiated
        // 3. Webhook received
        // 4. Order finalized
    }
    
    // Order expiration
    public function test_order_expires_after_15_minutes() { ... }
    
    // Order status transitions
    public function test_order_status_transitions() { ... }
}
```

---

## TASK 4: CONCURRENCY TESTS (T3, 8 Giờ)

### File: tests/Feature/ConcurrencyTest.php (NEW)

```php
class ConcurrencyTest extends TestCase {
    // Two users book same seat
    public function test_two_concurrent_bookings_same_seat() {
        // Expected: One succeeds, one fails
    }
    
    // Two users apply same promotion
    public function test_two_concurrent_promotions() {
        // Expected: One succeeds, one fails
    }
    
    // Promotion limit reached exactly
    public function test_promotion_limit_reached() {
        // Expected: Last valid, next fails
    }
}
```

### Công cụ: Apache Bench hoặc Locust
```bash
# Simulate 10 concurrent users booking same seat
ab -n 10 -c 10 -X POST http://localhost:8000/api/orders
```

---

## TASK 5: N+1 QUERY OPTIMIZATION (T4, 10 Giờ)

### 5.1: Identify N+1 Queries
```bash
# In AppServiceProvider boot():
DB::listen(function ($query) {
    if (count($query->getBindings()) > 0) {
        \Log::debug($query->sql, $query->getBindings());
    }
});
```

### 5.2: Fix OrderService queries
```php
// BEFORE (N+1)
$orders = Order::all();
foreach ($orders as $order) {
    echo $order->user->name;  // N queries
}

// AFTER (eager loading)
$orders = Order::with('user', 'orderItems', 'promotion')->get();
foreach ($orders as $order) {
    echo $order->user->name;  // 1 query
}
```

### 5.3: Fix MovieService queries
```php
// Add proper eager loading
Movie::with('categories', 'formats', 'subtitles')
    ->whereActive()
    ->get();
```

### 5.4: Add query counting to tests
```php
$this->assertQueryCount(3, function () {
    $orders = Order::with('user', 'items')->limit(10)->get();
});
```

---

## TASK 6: EMAIL VERIFICATION FLOW (T5, 4 Giờ)

### 6.1: Create Verification Notification
```
File: app/Notifications/VerifyEmailNotification.php (NEW)
├─ Generate verification code
├─ Send via mail/SMS
└─ Set expiration (24 hours)
```

### 6.2: Update AuthController
```php
// In register()
User::create([...]);
event(new UserRegistered($user));  // Trigger verification email
return response()->json(['message' => 'Check your email']);
```

### 6.3: Create Verification Endpoint
```php
// POST /api/auth/verify-email
public function verifyEmail(VerifyEmailRequest $request) {
    $user = User::where('email', $request->email)
        ->where('verification_code', $request->code)
        ->where('verification_expires_at', '>', now())
        ->firstOrFail();
    
    $user->update(['email_verified_at' => now()]);
    return $this->successResponse('Email verified');
}
```

---

## TASK 7: ERROR BOUNDARY - FRONTEND (T5, 3 Giờ)

### File: public/js/utils/errorHandler.js (NEW)

```javascript
// Global error handler
window.addEventListener('error', (e) => {
    handleError(e.error);
});

// Promise rejection handler
window.addEventListener('unhandledrejection', (e) => {
    handleError(e.reason);
});

function handleError(error) {
    console.error('Error:', error);
    showErrorNotification('Something went wrong. Please try again.');
    // Send to monitoring service (Sentry)
}
```

---

## TASK 8: API RESPONSE STANDARDIZATION (T6, 4 Giờ)

### 8.1: Create Response Helper
```php
// app/Http/Traits/RespondsWithJson.php
trait RespondsWithJson {
    protected function successResponse($data, $message = null, $code = 200) {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], $code);
    }
    
    protected function errorResponse($message, $code = 400, $errors = null) {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}
```

### 8.2: Standardize Pagination
```php
// All controllers return same format
$items = Model::paginate(15);
return $this->successResponse($items->items(), total: $items->total());
```

---

## TASK 9: PERFORMANCE MONITORING (T7, 3 Giờ)

### 9.1: Setup Laravel Telescope (Development)
```bash
composer require --dev laravel/telescope
php artisan telescope:install
```

### 9.2: Setup Sentry (Production)
```bash
composer require sentry/sentry-laravel
php artisan vendor:publish --provider="Sentry\Laravel\ServiceProvider"
```

### 9.3: Add Query Logging
```php
// In AppServiceProvider
DB::listen(function ($query) {
    if ($query->time > 100) {  // Log queries > 100ms
        \Log::warning('Slow query', ['sql' => $query->sql]);
    }
});
```

---

## TESTING TARGETS - PHASE 2

| Metric | Target | Method |
|--------|--------|--------|
| Test Coverage | 70%+ | php artisan test --coverage |
| Performance | <200ms p95 | Apache Bench |
| Error Rate | <0.1% | Monitoring |
| Database Queries | <5 per request | Query logging |
| Memory Usage | <50MB | Memory profiling |

---

## CHECKLIST HOÀN THÀNH PHASE 2

### Tests:
- [ ] Feature tests for authorization
- [ ] Unit tests for all services
- [ ] Integration tests for orders
- [ ] Concurrency tests pass
- [ ] Test coverage 70%+

### Optimization:
- [ ] N+1 queries fixed
- [ ] Eager loading implemented
- [ ] Query time < 100ms
- [ ] Database queries < 5 per request

### Features:
- [ ] Email verification implemented
- [ ] Frontend error boundary
- [ ] Response standardization
- [ ] Performance monitoring

### Quality:
- [ ] All tests pass
- [ ] No security warnings (composer audit)
- [ ] Code coverage reports
- [ ] Performance baselines

---

**Ước tính**: 60-80 giờ để hoàn thành Phase 2  
**Kết quả**: Hệ thống sẵn sàng public release
