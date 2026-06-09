# LỘ TRÌNH SỬA & REFACTOR - PHASE 1 NGHIÊM TRỌNG
## Cinema Booking System | 1 Tuần | 40-50 Giờ
**Mục tiêu**: Từ 6.5/10 → 7.0/10 (Production-Ready Basic)

---

## TỔNG QUAN GIAI ĐOẠN 1

| Tuần | Ngày | Task | Giờ | Priority |
|------|------|------|-----|----------|
| Tuần 1 | T2 | Fix Authorization Bypass | 2 | 🔴 CRITICAL |
| | T3 | Webhook Signature Verify | 4 | 🔴 CRITICAL |
| | T4 | Remove localStorage Token | 1 | 🔴 CRITICAL |
| | T5 | Pessimistic Locking (Seats/Promos) | 6 | 🔴 CRITICAL |
| | T6 | Database Indexes | 1 | 🔴 CRITICAL |
| | T6 | Rate Limiting Routes | 2 | 🟠 HIGH |
| | T6-T7 | Webhook Queue Job | 3 | 🟠 HIGH |
| | T7 | CSRF Protection + Error Handling | 4 | 🟠 HIGH |
| | | **TOTAL** | **23 giờ** | |

---

## CHI TIẾT TỪNG TASK

## TASK 1: FIX AUTHORIZATION BYPASS (T2, 2 Giờ)
### Priority: 🔴 CRITICAL | Risk: Data exposure
### Files cần sửa: BookingController, OrderController, OrderService

#### Step 1.1: Kiểm tra hiện tại
```php
// app/Http/Controllers/BookingController.php - LINE ~XX
public function syncOrderStatus($orderCode) {
    $order = Order::where('gateway_order_code', $orderCode)->first();
    // ❌ PROBLEM: Không check user_id
}
```

#### Step 1.2: Create Authorization Trait
```
File: app/Traits/ChecksOrderOwnership.php (NEW)
├─ Method: authorizeOrder(Order $order)
├─ Check: if ($order->user_id !== auth()->id()) throw 403
└─ Usage: Use in all order retrieval methods
```

#### Step 1.3: Update Controllers
```
Files to update:
├─ BookingController::syncOrderStatus()
├─ BookingController::cancelOrder()
├─ OrderController::show()
├─ OrderController::cancel()
└─ OrderController::destroy()

Pattern:
$order = Order::where('gateway_order_code', $code)->first();
+ $this->authorizeOrder($order);
```

#### Step 1.4: Test
```bash
# Create test user1, user2, order for user1
# Try user2 accessing user1's order
# Expected: 403 Forbidden
```

---

## TASK 2: WEBHOOK SIGNATURE VERIFICATION (T3, 4 Giờ)
### Priority: 🔴 CRITICAL | Risk: Fake payments
### Files cần sửa: PaymentController, Middleware mới, routes/api.php

#### Step 2.1: Create Verification Middleware
```
File: app/Http/Middleware/VerifyPayOSWebhookSignature.php (NEW)
Purpose:
├─ Get signature from header: x-payos-signature
├─ Get payload from request body
├─ Calculate: hash_hmac('sha256', $payload, config('payos.webhook_secret'))
├─ Compare: hash_equals($expected, $actual)
└─ Return: 401 if mismatch
```

#### Step 2.2: Register Middleware
```
File: app/Http/Kernel.php
Add to $routeMiddleware:
'verify.payos' => VerifyPayOSWebhookSignature::class
```

#### Step 2.3: Apply to Route
```
File: routes/api.php
OLD:
Route::post('webhook/payos', [PaymentController::class, 'handleWebhook']);

NEW:
Route::post('webhook/payos', [PaymentController::class, 'handleWebhook'])
    ->middleware('verify.payos')
    ->withoutMiddleware('auth:api');
```

#### Step 2.4: Update PaymentController
```
File: app/Http/Controllers/PaymentController.php
Change handleWebhook() to:
├─ Remove any signature checking (middleware handles it)
├─ Assume $request->all() is already verified
└─ Focus only on business logic
```

#### Step 2.5: Test
```bash
# Test 1: Valid signature → 202 Accepted
# Test 2: Invalid signature → 401 Unauthorized
# Test 3: Missing signature → 401 Unauthorized
```

---

## TASK 3: REMOVE LOCALSTORAGE TOKEN (T4, 1 Giờ)
### Priority: 🔴 CRITICAL | Risk: XSS token theft
### Files cần sửa: public/js/pages/tickets.js, Blade templates

#### Step 3.1: Search localStorage usage
```bash
grep -r "localStorage.getItem.*authToken" public/
grep -r "localStorage.setItem.*authToken" public/
```

#### Step 3.2: Remove all localStorage references
```
public/js/pages/tickets.js:
- Remove: let authToken = localStorage.getItem('authToken');
- Remove: localStorage.setItem('authToken', token);
- Remove: localStorage.removeItem('authToken');

resources/views/users/booking/index.blade.php:
- Remove: localStorage token initialization
```

#### Step 3.3: Verify HttpOnly Cookie
```
File: app/Http/Controllers/AuthController.php
Verify: cookie('authToken', $token, 
    httpOnly: true, 
    secure: true, 
    sameSite: 'lax'
);
```

#### Step 3.4: Update API calls
```javascript
// BEFORE
fetch('/api/orders', {
    headers: {
        'Authorization': `Bearer ${authToken}`
    }
});

// AFTER
fetch('/api/orders');  
// Cookie automatically sent by browser
```

---

## TASK 4: PESSIMISTIC LOCKING (T5, 6 Giờ)
### Priority: 🔴 CRITICAL | Risk: Race conditions
### Files cần sửa: SeatService, PromotionService (NEW), OrderService

#### Step 4.1: Create PromotionService
```
File: app/Services/PromotionService.php (NEW)
Method: applyPromotion($promotionId, $userId)
├─ Lock promotion: Promotion::lockForUpdate()->find($id)
├─ Check limit: if ($promo->used_count >= $promo->usage_limit)
├─ Increment: $promo->increment('used_count')
└─ Return: Discount amount
```

#### Step 4.2: Update SeatService
```
File: app/Services/SeatService.php
Method: lockSeatsForBooking($seatIds, $showtimeId)
├─ Query: Seat::lockForUpdate()->whereIn('id', $seatIds)->get()
├─ Check: Each seat not in OrderItem
├─ Validate: All seats belong to same showtime
└─ Return: Collection of locked seats
```

#### Step 4.3: Update OrderService
```
File: app/Services/OrderService.php
Method: createOrder($user, $seatIds, $showtimeId)
├─ DB::transaction(function() { ... }, 3)
├─ Call: $this->seatService->lockSeatsForBooking(...)
├─ Create: OrderItem for each seat
├─ Apply: Promotion atomically
└─ Return: Order
```

#### Step 4.4: Test Race Conditions
```bash
# Test 1: Two concurrent seat bookings same seat
#   Expected: One succeeds, one fails with 409 Conflict

# Test 2: Two concurrent promotion applications
#   Expected: One succeeds, one fails with 422

# Test 3: Promotion limit exactly reached
#   Expected: Last valid request succeeds, next fails
```

---

## TASK 5: DATABASE INDEXES (T6, 1 Giờ)
### Priority: 🔴 CRITICAL | Risk: Slow queries
### Files: database/migrations/2026_06_08_add_production_indexes.php (NEW)

#### Step 5.1: Create Migration
```
File: database/migrations/2026_06_08_add_production_indexes.php (NEW)
Tables to index:
├─ orders: user_id, gateway_order_code, status, showtime_id
├─ order_items: order_id, item_type+item_id
├─ seat_holds: user_id, showtime_id, expires_at
├─ seats: showtime_id, code
└─ user_promotions: user_id, promotion_id
```

#### Step 5.2: Run Migration
```bash
php artisan migrate
```

#### Step 5.3: Verify Indexes
```bash
# Check indexes created
php artisan tinker
>>> DB::select("SHOW INDEXES FROM orders");
```

---

## TASK 6: RATE LIMITING (T6, 2 Giờ)
### Priority: 🟠 HIGH | Risk: DDoS
### Files: routes/api.php

#### Step 6.1: Add throttle to public endpoints
```
routes/api.php:
├─ GET /movies: throttle:60,1 (60 req/min)
├─ GET /products: throttle:60,1
├─ GET /theaters: throttle:60,1
└─ GET /showtimes: throttle:60,1
```

#### Step 6.2: Add throttle to auth endpoints
```
├─ POST /auth/register: throttle:5,60 (5 req/60min)
├─ POST /auth/login: throttle:10,60 (10 req/60min)
└─ POST /auth/forgot-password: throttle:3,60
```

#### Step 6.3: Add throttle to protected endpoints
```
├─ GET /orders: throttle:100,1 (100 req/min)
├─ POST /orders: throttle:50,1
└─ Protected routes: throttle:100,1
```

#### Step 6.4: Test
```bash
# Send 61 requests to /movies in 1 second
# Expected: 61st request → 429 Too Many Requests
```

---

## TASK 7: WEBHOOK QUEUE JOB (T6-T7, 3 Giờ)
### Priority: 🟠 HIGH | Risk: Webhook timeout
### Files: Jobs (NEW), PaymentController, config/queue.php

#### Step 7.1: Create Queue Job
```
File: app/Jobs/ProcessPaymentWebhook.php (NEW)
├─ Implement ShouldQueue
├─ Constructor: public array $data
├─ handle(): Call PaymentService::handleWebhook($this->data)
└─ Retry: maxTries = 3, backoff = [10, 60, 300]
```

#### Step 7.2: Update PaymentController
```
File: app/Http/Controllers/PaymentController.php
Method: handleWebhook()
├─ Dispatch job: ProcessPaymentWebhook::dispatch($request->all())
├─ Return: 202 Accepted immediately
└─ Don't wait for processing
```

#### Step 7.3: Configure Queue Driver
```
File: .env
QUEUE_CONNECTION=database  (or redis)
```

#### Step 7.4: Create Queue Worker
```bash
# In production, run:
php artisan queue:work --queue=default --timeout=300
```

---

## TASK 8: CSRF + ERROR HANDLING (T7, 4 Giờ)
### Priority: 🟠 HIGH | Risk: CSRF attacks
### Files: routes/api.php, Frontend JS, Controllers

#### Step 8.1: Add CSRF to state-changing routes
```
routes/api.php:
├─ POST /orders: middleware('csrf')
├─ PUT /orders/{id}: middleware('csrf')
├─ DELETE /orders/{id}: middleware('csrf')
├─ POST /auth/logout: middleware('csrf')
└─ PUT /profile: middleware('csrf')
```

#### Step 8.2: Add CSRF token to frontend
```javascript
// public/js/utils/api.js (NEW or update)
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content;
}

function apiCall(url, method, data = null) {
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': getCsrfToken(),
        },
    };
    if (data) options.body = JSON.stringify(data);
    return fetch(url, options);
}
```

#### Step 8.3: Standardize Error Responses
```
All controllers return:
{
    "success": false,
    "message": "User-friendly message (no tech details)",
    "code": "ERROR_CODE",
    "errors": { /* validation errors */ }
}

Don't expose:
- Stack traces
- SQL queries
- File paths
- Internal exceptions
```

#### Step 8.4: Update Error Handler
```
File: app/Exceptions/Handler.php
├─ Override render() to catch exceptions
├─ Log detailed errors
├─ Return sanitized responses
└─ Hide production errors from client
```

---

## CHECKLIST HOÀN THÀNH PHASE 1

### Authorization Fix:
- [ ] ChecksOrderOwnership trait created
- [ ] BookingController updated + tested
- [ ] OrderController updated + tested
- [ ] OrderService updated + tested
- [ ] Authorization tests pass

### Webhook Security:
- [ ] VerifyPayOSWebhookSignature middleware created
- [ ] Routes updated with middleware
- [ ] PaymentController adjusted
- [ ] Webhook signature tests pass (valid/invalid)
- [ ] Production webhook tested

### Frontend Security:
- [ ] localStorage references removed
- [ ] HttpOnly cookie verified
- [ ] API calls use cookie automatically
- [ ] No token in browser storage
- [ ] Frontend tests pass

### Database Performance:
- [ ] Migration created with all indexes
- [ ] Migration applied to DB
- [ ] Index verification passed
- [ ] Query performance improved 100x+

### Race Conditions Fixed:
- [ ] SeatService locking implemented
- [ ] PromotionService created with locking
- [ ] OrderService updated with transactions
- [ ] Concurrent tests pass
- [ ] No double-booking possible

### Rate Limiting:
- [ ] Public endpoints throttled
- [ ] Auth endpoints throttled
- [ ] Protected endpoints throttled
- [ ] Rate limit tests pass

### Async Processing:
- [ ] ProcessPaymentWebhook job created
- [ ] PaymentController uses queue
- [ ] Queue worker configured
- [ ] Webhook tests pass (async)

### CSRF Protection:
- [ ] Middleware applied to routes
- [ ] Frontend sends CSRF token
- [ ] Error responses sanitized
- [ ] CSRF tests pass

---

## KIỂM TRA CUỐI CÙNG PHASE 1

```bash
# 1. Run all tests
php artisan test

# 2. Security audit
composer audit

# 3. Code analysis
php artisan tinker
# Check no errors/warnings

# 4. Load test
ab -n 1000 -c 10 http://localhost:8000/api/movies

# 5. Manual testing
# - Login as user1, try viewing user2's order (should fail)
# - Send invalid webhook (should reject)
# - Book same seat concurrently (one should fail)
# - Apply promotion twice (one should fail)
```

---

## KẾT QUẢ DỰ KIẾN PHASE 1

**Trước Phase 1**: 6.5/10 - NOT PRODUCTION READY  
**Sau Phase 1**: 7.0/10 - READY FOR INTERNAL DEPLOYMENT

### Lỗ hổng đã sửa:
- ✅ Authorization bypass
- ✅ Webhook forgery
- ✅ XSS token theft
- ✅ Race conditions
- ✅ N+1 queries
- ✅ DDoS risk
- ✅ CSRF attacks

### Còn để Phase 2:
- Test suite (80-120 giờ)
- Performance optimization
- Monitoring/logging
- Email verification
- API documentation

---

**Ước tính**: 40-50 giờ để hoàn thành Phase 1  
**Kết quả**: Hệ thống an toàn để deploy nội bộ
