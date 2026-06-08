# CODE REVIEW REPORT - CINEMA MOVIE BOOKING SYSTEM
**Reviewed by: Senior Software Architect**
**Date: June 8, 2026**
**Project: Cinema Movie Ticket Booking System**

---

## EXECUTIVE SUMMARY

The Cinema Movie Booking System demonstrates solid architectural patterns with proper separation of concerns. However, there are critical issues in security, performance, and production-readiness that require immediate attention. The codebase shows good understanding of Laravel best practices but lacks comprehensive error handling, database optimization, and security hardening.

**Overall Assessment: 6.5/10** ⚠️ Production concerns identified

---

## 1. KIẾN TRÚC HỆ THỐNG (System Architecture)

### ✅ STRENGTHS
- **Service Layer Pattern**: Well-implemented with `OrderService`, `PaymentService`, `AuthService` handling business logic separately
- **Gateway Pattern**: `PayOSGateway` properly abstracts payment provider integration
- **Transaction Management**: Proper use of DB::transaction() in critical operations
- **Modular Structure**: Clear separation between Controllers, Services, Models, and Requests

### ⚠️ ISSUES

#### Issue #1.1: Missing Event/Job Architecture
**Severity**: HIGH
**Current State**: Payment webhook handling is synchronous in controller
**Problem**:
```php
// PaymentController::payosWebhook - synchronous processing
public function payosWebhook(Request $request): \Illuminate\Http\JsonResponse
{
    $result = $this->paymentService->handleWebhook($request->all()); // Blocks response
}
```

**Impact**: 
- PayOS webhook timeout if processing takes >30 seconds
- No retry mechanism for failed webhook processing
- Risk of payment confirmation loss

**Solution**:
```php
// Use queued jobs
public function payosWebhook(Request $request): \Illuminate\Http\JsonResponse
{
    ProcessPaymentWebhookJob::dispatch($request->all());
    return response()->json(['success' => true]);
}
```

#### Issue #1.2: Missing Repository Pattern
**Severity**: MEDIUM
**Problem**: Direct model queries scattered across services (no abstraction layer)
**Impact**: Data access logic couples services to Eloquent, makes testing harder

#### Issue #1.3: No CQRS/Event Sourcing for Order State
**Severity**: MEDIUM
**Problem**: Order status changes not tracked with events
**Impact**: Difficult to audit payment flow and debug state issues

### 🔧 RECOMMENDATIONS
1. Implement Queue-based webhook processing
2. Add Repository layer for data access abstraction
3. Consider Domain Events for order state transitions

---

## 2. CHẤT LƯỢNG CODE (Code Quality)

### ✅ STRENGTHS
- Proper type hints throughout (PHP 7.4+)
- Good use of readonly properties
- Consistent naming conventions
- Comments for business logic

### ⚠️ ISSUES

#### Issue #2.1: Exception Handling Too Broad
**Severity**: HIGH
**Current Code** (OrderController::store):
```php
} catch (\RuntimeException $e) {
    $statusCode = in_array($e->getCode(), [403, 422], true) ? $e->getCode() : 422;
} catch (\Exception $e) {
    return $this->errorResponse('Failed to create order: ' . $e->getMessage(), 500);
}
```

**Problems**:
- Catches generic \Exception (hides bugs)
- Maps unrelated exceptions to 422 (misleading)
- Leaks internal error messages to client

**Solution**:
```php
catch (\InvalidArgumentException $e) {
    return $this->errorResponse($e->getMessage(), 422);
} catch (\RuntimeException $e) {
    $statusCode = in_array($e->getCode(), [403, 422]) ? $e->getCode() : 400;
    return $this->errorResponse($e->getMessage(), $statusCode);
} catch (\Throwable $e) {
    report($e);
    return $this->errorResponse('Order creation failed', 500);
}
```

#### Issue #2.2: Magic Numbers/Strings
**Severity**: MEDIUM
**Examples**:
- `STATUS_CANCELLED = 0, STATUS_PENDING = 1, STATUS_CONFIRMED = 2` (not using Enum)
- Gateway order code: `(int) (now()->format('ymdHis') . random_int(10, 99))`
- Hardcoded 15 minutes expiration

**Solution**: Use Laravel Enums (PHP 8.1+)
```php
enum OrderStatus: int {
    case Cancelled = 0;
    case Pending = 1;
    case Confirmed = 2;
}
```

#### Issue #2.3: Inconsistent Response Format
**Severity**: MEDIUM
**Problem**:
```php
// OrderService::format() returns many duplicate fields:
'order_code' => $order->code,
'code' => $order->code,  // Duplicate!
'total_price' => $order->total_amount,
'total' => $order->total_amount,  // Duplicate!
```

**Impact**: Confusing API responses, increases payload size

#### Issue #2.4: Missing Input Validation
**Severity**: HIGH
**Example** (OrderService::createProductOrderItems):
```php
$requestedProducts = collect($products)
    ->mapWithKeys(fn (array $product) => [(int) $product['id'] => (int) $product['quantity']])
    ->filter(fn (int $quantity) => $quantity > 0);
```

**Problem**: No validation that `$products` is actually an array structure
**Solution**: Add strict validation in Form Request

#### Issue #2.5: Unsafe String Formatting in URLs
**Severity**: MEDIUM (PaymentController):
```php
'cancelUrl' => $baseUrl . '/booking/' . $order->showtime_id . '?paymentStatus=cancelled',
```
**Problem**: Missing URL encoding for query parameters
**Solution**: Use `http_build_query()` or Laravel URL builder

---

## 3. LARAVEL BEST PRACTICES

### ✅ STRENGTHS
- Proper use of DB::transaction()
- Constructor dependency injection
- Traits for code reuse (ApiResponse)
- Middleware usage for authentication

### ⚠️ ISSUES

#### Issue #3.1: Missing Form Request Validation
**Severity**: MEDIUM
**Current**: Validation mixed in controller and service
**Better**:  Create dedicated FormRequest classes

#### Issue #3.2: N+1 Query Problems
**Severity**: HIGH
**Example** (OrderService::findForUser):
```php
$order = Order::with([...relations...])->findOrFail($id);
$order = $this->orderExpirationService->expireOrder($order)->load([
    'user', 'showtime.movie', 'showtime.screen.theater', 'orderItems', 'payment',
]); // Reload relations!
```

**Impact**: Extra database query for same data
**Solution**: Avoid reloading; use conditional loading

#### Issue #3.3: Missing Model Scopes
**Severity**: LOW
**Example**: Should use scopes for common queries
```php
Order::byStatus(Order::STATUS_PENDING)->byUser($userId)
```

#### Issue #3.4: Improper Use of lockForUpdate()
**Severity**: MEDIUM (OrderService::create):
```php
$showtime = Showtime::lockForUpdate()->findOrFail($data['showtime_id']);
// ... later ...
$seats = Seat::lockForUpdate()->whereIn('id', $seatIds)->get();
```

**Problem**: Multiple locks can cause deadlock
**Best Practice**: Lock only the critical resource

#### Issue #3.5: Missing Timestamps on Models
**Severity**: LOW
**Problem**: Order model doesn't timestamp payment state changes properly
**Better**: Add created_at/updated_at tracking consistently

---

## 4. DATABASE

### ✅ STRENGTHS
- Proper use of transactions
- Foreign key relationships
- JSON payload for flexible data storage
- Timestamps on orders

### ⚠️ CRITICAL ISSUES

#### Issue #4.1: Missing Database Indexes
**Severity**: CRITICAL
**Problem**:
```php
// No index on gateway_order_code (searched frequently)
Order::where('gateway_order_code', $orderCode)->first();

// No index on order creation flow
OrderItem::where('item_type', Seat::class)
    ->whereIn('item_id', $seatIds)
    ->whereHas('order', ...)
```

**Impact**: Slow queries in production under load
**Solutions**:
```php
// Add to migration:
$table->index('gateway_order_code');
$table->index('user_id');
$table->index(['showtime_id', 'status']);
$table->index(['item_type', 'item_id']);
```

#### Issue #4.2: Inefficient Seat Hold Query
**Severity**: MEDIUM
**Current** (OrderService::getValidSeatHold):
```php
SeatHold::where('showtime_id', $showtimeId)
    ->where('user_id', $userId)
    ->where('held_until', '>', now())
    ->lockForUpdate()
    ->first()
```

**Problem**: Scans all holds; should use primary key when available
**Solution**: Pass seat_hold_id from request and validate ownership

#### Issue #4.3: No Pagination on List Queries
**Severity**: MEDIUM
**Example**: Theater listing without limit
```php
// TheaterController::index - likely loads all theaters
```

**Solution**: Implement pagination or cursor pagination

#### Issue #4.4: Payload JSON Growth
**Severity**: MEDIUM
**Problem**: Order.payload stores duplicate data (seats, products, promotion info)
**Impact**: Database bloat, slower queries
**Solution**: Normalize into separate tables or compress JSON

#### Issue #4.5: Missing Soft Deletes
**Severity**: LOW
**Problem**: Hard deletes may violate audit trail needs
**Solution**: Add soft deletes to sensitive models (Order, User)

---

## 5. API DESIGN

### ✅ STRENGTHS
- RESTful endpoint naming
- Proper HTTP status codes
- Consistent prefix routing
- Role-based access control

### ⚠️ ISSUES

#### Issue #5.1: Inconsistent Response Format
**Severity**: MEDIUM
**Examples**:
- Booking endpoint returns HTML (not JSON)
- API inconsistency: some endpoints wrap in 'data', others don't

#### Issue #5.2: Missing API Versioning
**Severity**: MEDIUM
**Problem**: No version prefix (e.g., /api/v1/)
**Impact**: Breaking changes affect all clients
**Solution**: Implement versioning strategy

#### Issue #5.3: Weak Input Validation Errors
**Severity**: MEDIUM
**Current**: Generic error messages
**Better**: Return detailed validation errors
```php
return response()->json([
    'message' => 'Validation failed',
    'errors' => $validator->errors()
], 422);
```

#### Issue #5.4: Missing Rate Limiting
**Severity**: HIGH
**Current**: Only auth throttled at 60/minute
**Problem**: Public endpoints (movies, theaters) have no rate limit
**Solution**:
```php
Route::get('movies', [...])
    ->middleware('throttle:300,1'); // 300/minute
```

#### Issue #5.5: Webhook Signature Verification
**Severity**: CRITICAL (PaymentController)
```php
public function payosWebhook(Request $request): \Illuminate\Http\JsonResponse
{
    $result = $this->paymentService->handleWebhook($request->all());
    // PayOS webhook verification happens INSIDE service
}
```

**Problem**: Middleware should verify signature BEFORE reaching handler
**Solution**: Add webhook signature middleware
```php
Route::post('/payos/webhook', [...])
    ->middleware('verify-payos-webhook');
```

#### Issue #5.6: No API Documentation
**Severity**: MEDIUM
**Missing**: OpenAPI/Swagger specs
**Solution**: Use OpenAPI generator or Laravel API documentation tools

---

## 6. SECURITY REVIEW

### ⚠️ CRITICAL ISSUES

#### Issue #6.1: SQL Injection Risk (Medium)
**Location**: PaymentController::payosCallback
```php
$order = Order::query()
    ->where('gateway_order_code', '=', $orderCode)  // $orderCode from query param
    ->first();
```

**Severity**: LOW (Eloquent uses parameterized queries)
**Note**: Actually SAFE due to Eloquent, but type-cast to be explicit:
```php
->where('gateway_order_code', '=', (int) $orderCode)
```

#### Issue #6.2: CSRF Protection Missing on Webhook
**Severity**: CRITICAL
```php
Route::post('/payment/payos/webhook', ...)
// No verification, webhook from PayOS can be spoofed
```

**Solution**: Verify webhook signature (PayOS provides checksum key)
```php
// In middleware
$expectedSignature = hash_hmac('sha256', $payload, config('services.payos.checksum_key'));
if ($signature !== $expectedSignature) {
    abort(403);
}
```

#### Issue #6.3: Order Authorization Bypass
**Severity**: CRITICAL (BookingController::show)
```php
public function show(Request $request, int $showtimeId)
{
    $showtime = Showtime::with(...)->findOrFail($showtimeId);
    // NO CHECK that user owns the order being displayed
    
    if ($paymentStatus === 'success' && $orderCode) {
        $order = Order::where('gateway_order_code', $orderCode)->first();
        // User can see ANY order by providing its code!
    }
}
```

**Impact**: Any user can see payment details of other users' orders
**Solution**:
```php
$order = Order::where('gateway_order_code', $orderCode)
    ->where('user_id', auth()->id())
    ->firstOrFail();
```

#### Issue #6.4: Sensitive Data in Logs
**Severity**: HIGH (AuthService)
```php
Log::info('User logged in successfully', ['user_id' => $user->id]);
// If user_id is logged, potential enumeration attack
```

**Solution**: Never log user identifiers; use anonymized data
```php
Log::info('User login', ['ip' => $ipAddress]);
```

#### Issue #6.5: Google OAuth ID Token Validation (Medium)
```php
public function verifyGoogleIdToken(string $idToken): array
{
    $response = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', [
        'id_token' => $idToken,
    ]);
    // Relies on external HTTP call - slow and unreliable
}
```

**Better**: Use Google's official JWT library:
```php
use Firebase\JWT\JWT;
$decoded = JWT::decode($idToken, $publicKey, ['RS256']);
```

#### Issue #6.6: Rate Limiting Weaknesses
```php
private function loginRateKey(string $login, string $ipAddress): string
{
    return 'login_attempts:' . sha1(Str::lower($login) . '|' . $ipAddress);
}
```

**Problem**: Only rate limits by (login + IP). Account enumeration possible via email checking

**Solution**: Implement separate limits:
```php
'login_attempts:email:' . $email  // 5 attempts
'login_attempts:ip:' . $ip        // 10 attempts
```

#### Issue #6.7: Promotion Code Enumeration
**Severity**: MEDIUM (OrderService)
```php
$promotion = Promotion::query()
    ->active()
    ->valid()
    ->byCode($promotionCode)
    ->lockForUpdate()
    ->first();

if (!$promotion) {
    throw new \RuntimeException('Mã khuyến mãi không hợp lệ hoặc đã hết hạn.');
}
```

**Problem**: Same error for "invalid code" and "code expired" → allows enumeration
**Solution**:
```php
throw new \RuntimeException('Promotion code not valid.');  // Generic message
```

#### Issue #6.8: Refresh Token Storage
**Severity**: MEDIUM (if using HttpOnly cookies properly)
**Current**: RefreshToken model stores tokens
**Risk**: If database compromised, all sessions exposed
**Mitigation**: 
- Only store hashed refresh tokens
- Add rate limiting on refresh endpoint
- Add rotation strategy

#### Issue #6.9: Missing CORS Headers
**Severity**: MEDIUM
**Problem**: No CORS configuration visible
**Solution**: Configure CORS middleware properly
```php
// config/cors.php
'allowed_origins' => ['https://yourdomain.com'],
```

#### Issue #6.10: No Request Signing for API
**Severity**: MEDIUM
**Problem**: No API key or signature verification for backend-to-backend calls
**Solution**: Implement API signature verification if needed

---

## 7. FRONTEND REVIEW

### Based on Available Files (Blade Templates, JavaScript)

### ✅ OBSERVATIONS
- Using modern JavaScript modules
- Blade templates structure seems reasonable

### ⚠️ ISSUES (Cannot fully assess without seeing all JS files)

#### Issue #7.1: Security in Booking Flow
**Risk**: Payment URLs passed through query parameters
```
/booking/{showtimeId}?paymentStatus=success&orderCode=123
```
**Better**: Use Session or API for state instead of URL params

#### Issue #7.2: XSS Prevention
**Need to verify**: All user input properly escaped in Blade
```blade
{{ $user->name }} <!-- Safe (auto-escaped) -->
{!! $user->html_content !!} <!-- Dangerous if not sanitized -->
```

---

## 8. PERFORMANCE

### ⚠️ CRITICAL ISSUES

#### Issue #8.1: N+1 Queries in Order Listing
**Location**: OrderService::getUserOrders
```php
$orders->getCollection()->transform(fn (Order $order) => $this->format($order));
// format() might trigger additional queries per order
```

**Solution**: Ensure all relations loaded with()

#### Issue #8.2: Repeated Expiration Checks
**Severity**: MEDIUM
```php
// expireOrder() called TWICE in findForUser
$order = $this->orderExpirationService->expireOrder($order)->load([...]);
// Multiple DB queries per find
```

#### Issue #8.3: No Query Result Caching
**Severity**: MEDIUM
**Example**: Movie listings, theater data rarely changes
**Solution**:
```php
$movies = Cache::remember('all_movies', 3600, function () {
    return Movie::all();
});
```

#### Issue #8.4: Inefficient Seat Availability Check
**Severity**: HIGH (OrderService::getBookedSeatIds)
```php
OrderItem::query()
    ->where('item_type', Seat::class)
    ->whereIn('item_id', $seatIds)
    ->whereHas('order', function ($query) use ($showtimeId) {
        $query->where('showtime_id', $showtimeId)
            ->whereIn('status', [...]);
    })
    ->pluck('item_id')
    ->all();
```

**Problem**: Complex query with whereHas - performs multiple joins
**Better**: Direct join:
```php
Seat::whereIn('id', $seatIds)
    ->whereHas('orderItems.order', function ($q) use ($showtimeId) {
        $q->where('showtime_id', $showtimeId)->whereIn('status', $active);
    })
    ->pluck('id')
    ->all();
```

#### Issue #8.5: No Database Connection Pooling
**Severity**: MEDIUM (if running under high load)
**Solution**: Configure connection pooling for database

#### Issue #8.6: Synchronous Payment Processing
**Severity**: HIGH
- Webhook processing blocks PayOS response
- No async job queue visible
**Solution**: Use Laravel Queue for webhook processing

---

## 9. TESTING

### ⚠️ CRITICAL GAPS

#### Issue #9.1: No Test Files Found
**Severity**: CRITICAL
- No visible unit tests for services
- No integration tests for payment flow
- No API endpoint tests

**Missing Tests**:
```
tests/Unit/Services/OrderServiceTest.php
tests/Unit/Services/PaymentServiceTest.php
tests/Feature/Api/OrderApiTest.php
tests/Feature/Api/AuthApiTest.php
tests/Feature/PaymentWebhookTest.php
```

#### Issue #9.2: No Test Database Seeding
**Severity**: HIGH
- No factories or seeders visible
- Difficult to setup test data

**Required**:
```php
database/factories/OrderFactory.php
database/factories/ShowtimeFactory.php
database/seeders/TestDataSeeder.php
```

#### Issue #9.3: No Webhook Mocking Tests
**Severity**: HIGH
- PayOS webhook untested
- Cannot verify webhook signature validation
- No test for idempotency

### TESTING STANDARDS NEEDED
1. **Unit Tests**: Service logic, validators
2. **Integration Tests**: Database transactions, locks
3. **Feature Tests**: API endpoints, payment flow
4. **Contract Tests**: PayOS webhook (mock)
5. **Load Tests**: Concurrent seat bookings

---

## SUMMARY OF CRITICAL ISSUES

| Priority | Category | Issue | Impact |
|----------|----------|-------|--------|
| 🔴 CRITICAL | Security | Order authorization bypass (Issue #6.3) | Data breach |
| 🔴 CRITICAL | Security | Webhook CSRF/Spoofing (Issue #6.2) | Fake payments |
| 🔴 CRITICAL | Testing | Zero test coverage | Production failures |
| 🔴 CRITICAL | Database | Missing indexes | Performance degradation |
| 🟠 HIGH | Architecture | Sync webhook processing | Transaction loss |
| 🟠 HIGH | Security | Sensitive data in logs | User enumeration |
| 🟠 HIGH | Database | N+1 queries | Scalability issues |
| 🟠 HIGH | API | No rate limiting on public endpoints | DDoS risk |
| 🟠 HIGH | Security | SQL injection risks (low actual risk but unvalidated inputs) | Potential exploitation |

---

## IMMEDIATE ACTIONS REQUIRED (Production Readiness)

### Priority 1 (Do immediately):
1. ✅ Fix order authorization bypass (Issue #6.3)
2. ✅ Add webhook signature verification (Issue #6.2)
3. ✅ Add database indexes (Issue #4.1)
4. ✅ Implement basic test suite

### Priority 2 (Before production):
1. ✅ Queue-based webhook processing
2. ✅ Add rate limiting to public endpoints
3. ✅ Fix N+1 queries
4. ✅ Add comprehensive logging/monitoring

### Priority 3 (Next release):
1. ✅ Implement Repository pattern
2. ✅ Add API versioning
3. ✅ Domain events for order state
4. ✅ Complete test coverage (80%+)
