# SUPPLEMENTARY CODE REVIEW - CINEMA MOVIE BOOKING SYSTEM
**Deep Dive Analysis of Controllers, Services, Models, Routes, Frontend & Providers**
**Date: June 8, 2026**

---

## TABLE OF CONTENTS
1. HTTP Controllers & Requests Deep Dive
2. Advanced Services Analysis  
3. Models & Relationships Review
4. Routes & Middleware Analysis
5. Frontend (JavaScript) Security & Architecture
6. Configuration & Providers
7. Cross-Cutting Concerns
8. Priority Improvements Roadmap

---

## 1. HTTP CONTROLLERS DEEP DIVE

### AuthController Analysis

**Strengths:**
- ✅ Proper Form Request validation (RegisterRequest, LoginRequest, etc.)
- ✅ Refresh token stored in HttpOnly secure cookies (good CSRF protection)
- ✅ Proper HTTP status codes (201 for create, 401 for unauthorized)
- ✅ Error handling wraps all methods in try-catch
- ✅ Delegation to AuthService (separation of concerns)

**Issues:**

#### Issue #C1.1: Generic Error Messages Leak Implementation Details
**Severity**: MEDIUM
**Location**: Line 46, 73, 115, etc.
```php
return $this->errorResponse('Registration failed: ' . $e->getMessage(), 500);
```
**Problem**: Exception message exposed to client (e.g., "UNIQUE constraint failed on email")
**Solution**:
```php
catch (\Throwable $e) {
    \Log::error('Registration failed', ['exception' => $e]);
    return $this->errorResponse('Registration failed. Please try again.', 500);
}
```

#### Issue #C1.2: Inconsistent Validation Error Handling
**Severity**: MEDIUM
**Current**: Form Requests handle validation, but googleLogin uses `$request->validate()` directly
```php
// Inconsistent - mixes Form Request and inline validation
public function googleLogin(\Illuminate\Http\Request $request): JsonResponse
{
    $validated = $request->validate([
        'id_token' => ['required', 'string'],
    ]);
    // Should use: public function googleLogin(GoogleLoginRequest $request)
}
```

#### Issue #C1.3: Missing Rate Limiting on Auth Endpoints
**Severity**: MEDIUM
**Problem**: AuthService does client-side rate limiting in cache, but no middleware protection
**Solution**: Apply middleware in routes:
```php
Route::post('register', [AuthController::class, 'register'])
    ->middleware('throttle:5,60');  // 5 per minute
Route::post('login', [AuthController::class, 'login'])
    ->middleware('throttle:5,60');
```

#### Issue #C1.4: Cookie Security Configuration Not Verified
**Severity**: MEDIUM
**Location**: Line 297-306
```php
return $response->withCookie(cookie(
    'refresh_token',
    $refreshToken,
    $expiresIn / 60,
    '/',
    null,  // ← Should specify domain explicitly
    config('session.secure', true),  // ← Depends on config
    true,  // HttpOnly ✓
    false,
    config('session.same_site', 'lax')
));
```
**Problem**: 
- Domain is `null` (applies to all subdomains)
- Relies on config that might not be set correctly
**Solution**: Force secure settings
```php
cookie(
    'refresh_token',
    $refreshToken,
    $expiresIn / 60,
    '/',
    config('app.url_domain'),  // Explicit domain
    true,  // Force HTTPS
    true,  // HttpOnly
    false,
    'strict'  // SameSite strict
)
```

---

### OrderController Analysis

**Critical Issues Identified:**

#### Issue #C2.1: Missing Order Authorization Check
**Severity**: 🔴 CRITICAL
**Location**: OrderController::show()
```php
public function show(int $id)
{
    $order = Order::findOrFail($id);
    // NO CHECK if auth()->id() == $order->user_id
    return $this->successResponse($this->format($order));
}
```
**Impact**: Any user can view any order by ID
**Fix**:
```php
public function show(int $id)
{
    $order = Order::where('id', $id)
        ->where('user_id', auth()->id())
        ->orWhere(function($q) {
            $q->whereHas('user.roles', fn($r) => $r->whereIn('slug', ['admin', 'staff']));
        })
        ->firstOrFail();
    return $this->successResponse($this->format($order));
}
```

#### Issue #C2.2: Exception Handling Masks Real Errors
**Severity**: MEDIUM
**Current Code**:
```php
catch (\RuntimeException $e) {
    $statusCode = in_array($e->getCode(), [403, 422], true) ? $e->getCode() : 422;
} catch (\Exception $e) {
    return $this->errorResponse('Failed to create order: ' . $e->getMessage(), 500);
}
```
**Problem**: Maps all exceptions to 422 (validation error) - misleading to client
**Better**: Use specific exceptions
```php
catch (InvalidSeatException $e) {
    return $this->errorResponse($e->getMessage(), 422);
} catch (InsufficientInventoryException $e) {
    return $this->errorResponse($e->getMessage(), 409);
} catch (AuthorizationException $e) {
    return $this->errorResponse($e->getMessage(), 403);
} catch (\Throwable $e) {
    report($e);
    return $this->errorResponse('Order creation failed', 500);
}
```

---

### PaymentController Analysis

#### Issue #C3.1: Webhook Not Protected from CSRF/Spoofing
**Severity**: 🔴 CRITICAL
**Current**: `Route::post('/payment/payos/webhook', ...)`
**Problem**: PayOS can send fake webhook
**Fix**: Add middleware that verifies PayOS signature
```php
Route::post('/payment/payos/webhook', [PaymentController::class, 'handleWebhook'])
    ->middleware('verify-payos-signature');

// Middleware
public function handle(Request $request, Closure $next)
{
    $signature = $request->header('x-payos-signature');
    $payload = $request->getContent();
    
    $expectedSignature = hash_hmac('sha256', $payload, config('services.payos.checksum_key'));
    
    if (!hash_equals($expectedSignature, $signature)) {
        abort(403, 'Invalid webhook signature');
    }
    
    return $next($request);
}
```

#### Issue #C3.2: Webhook Blocking/Timeout Risk
**Severity**: HIGH
**Current**: Synchronous processing in controller
**Solution**: Dispatch to queue
```php
public function handleWebhook(Request $request)
{
    ProcessPaymentWebhookJob::dispatch($request->all());
    return response()->json(['success' => true]); // Respond immediately
}
```

---

## 2. ADVANCED SERVICES ANALYSIS

### OrderService Issues

#### Issue #S1.1: Double Lock Strategy Can Cause Deadlock
**Severity**: HIGH
**Location**: Line 97-113
```php
$showtime = Showtime::lockForUpdate()->findOrFail($data['showtime_id']);
$seats = Seat::lockForUpdate()->whereIn('id', $seatIds)->get();
OrderItem::where(...)->lockForUpdate();  // Called in getBookedSeatIds
```
**Problem**: Multiple locks on different tables can deadlock
**Better**: Single transaction with minimal locking
```php
return DB::transaction(function () {
    // Lock only critical resource
    $order = Order::lockForUpdate()->create([...]);
    
    // Read-only queries don't need locks
    $seats = Seat::whereIn('id', $seatIds)->get();
    $booked = OrderItem::where(...)->pluck('item_id')->all();
}, attempts: 3, timeout: 30);
```

#### Issue #S1.2: Race Condition in Seat Availability Check
**Severity**: HIGH
**Current Flow**:
```
1. Check seat availability (getBookedSeatIds)
2. Create order items
3. User 2 tries same seats at same time → RACE CONDITION
```
**Solution**: 
- Lock seats BEFORE checking availability
- Use Pessimistic Locking with proper isolation level

#### Issue #S1.3: Promotion Code Not Locked During Apply
**Severity**: MEDIUM
**Current**:
```php
$promotion->increment('used_count');  // After finalize, not during lock
```
**Problem**: Two users can use same promotion beyond limit simultaneously
**Fix**: Lock promotion during order creation

---

### PricingService Issues

#### Issue #S2.1: No Inventory Check Before Building Snapshot
**Severity**: HIGH
```php
// Creates pricing snapshot but doesn't verify product stock
// User later gets "Insufficient inventory" error
public function buildSnapshot(...): array
{
    // Should validate before returning
}
```

#### Issue #S2.2: Floating Point Precision Loss
**Severity**: MEDIUM
```php
$seatTotal += $unitPrice;  // Accumulating floats
$discount = $subtotal * ((float) $promotion->value / 100);
```
**Better**: Use `bcmath` or work in cents (integers)
```php
$seatTotalCents = 0;
foreach ($seats as $seat) {
    $seatTotalCents += (int) round($unitPrice * 100);
}
$seatTotal = $seatTotalCents / 100;
```

---

## 3. MODELS & RELATIONSHIPS REVIEW

### Critical Model Issues

#### Issue #M1.1: Order Model Missing Soft Deletes
**Severity**: MEDIUM
```php
// Current: Hard delete loses audit trail
class Order extends Model {
    // Missing: use SoftDeletes;
}
```
**Impact**: Cannot track deleted orders for refunds/disputes
**Fix**: Add soft deletes and audit logging

#### Issue #M1.2: Polymorphic OrderItem Not Fully Implemented
**Severity**: MEDIUM
```php
public function item()
{
    return $this->morphTo();
}
```
**Problem**: 
- No validation that item_type is valid
- No type hints in model
- Coupling to specific models
**Better**: Use Enum + explicit relationships
```php
enum ItemType: string {
    case Seat = Seat::class;
    case Product = Product::class;
}

public function seat() { return $this->belongsTo(Seat::class, 'item_id'); }
public function product() { return $this->belongsTo(Product::class, 'item_id'); }
```

#### Issue #M1.3: SeatHold Without Cleanup Strategy
**Severity**: MEDIUM
```php
class SeatHold extends Model {
    // No auto-cleanup of expired holds
    // Accumulates stale records over time
}
```
**Solution**: Add command + model scope
```php
// Schedule in console/Kernel.php
$schedule->command('seats:cleanup-expired-holds')->everyMinute();
```

#### Issue #M1.4: Promotion Usage Tracking Flawed
**Severity**: HIGH
```php
public function users(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'user_promotion')
        ->withPivot(['status', 'used_at', 'order_id', 'usage_count']);
}
```
**Problem**: 
- Per-user usage_count not atomic
- No track per-promotion per-user limits
- Can bypass global usage_limit

---

## 4. ROUTES & MIDDLEWARE ANALYSIS

### Route Security Issues

#### Issue #R1.1: Public Endpoints Missing Rate Limiting
**Severity**: HIGH
```php
Route::get('products', [ProductController::class, 'index']);  // ← No throttle
Route::get('movies', [MovieController::class, 'index']);      // ← No throttle
Route::prefix('theaters')->group(fn() => Route::get('/', ...)); // ← No throttle
```
**Impact**: DDoS vulnerability on data endpoints
**Fix**:
```php
Route::middleware('throttle:100,1')->group(function () {
    Route::get('products', ...);
    Route::get('movies', ...);
    Route::prefix('theaters')->group(...);
});
```

#### Issue #R1.2: Broadcasting Auth Endpoint Vulnerable
**Severity**: HIGH
```php
Route::middleware('auth:api')->post('broadcasting/auth', function (Request $request) {
    return \Illuminate\Support\Facades\Broadcast::auth($request);
});
```
**Problem**: 
- No channel validation before auth
- User can subscribe to channels they shouldn't access
**Fix**: Add channel authorization
```php
Broadcast::channel('order.{orderCode}', function ($user, $orderCode) {
    return Order::where('gateway_order_code', (int) $orderCode)
        ->where('user_id', $user->id)
        ->exists();  // Already correct in channels.php
});
```

#### Issue #R1.3: No CORS Configuration Visible
**Severity**: MEDIUM
**Problem**: API may allow requests from any origin
**Solution**: Configure CORS strictly
```php
// config/cors.php
'allowed_origins' => ['https://yourdomain.com'],
'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
'allowed_headers' => ['Content-Type', 'Authorization'],
```

---

## 5. FRONTEND (JAVASCRIPT) SECURITY & ARCHITECTURE

### Critical Frontend Issues

#### Issue #F1.1: Auth Token Stored in localStorage
**Severity**: HIGH
**Current** (public/js/app.js):
```javascript
let authToken = localStorage.getItem('authToken');
localStorage.setItem('authToken', authToken);  // Vulnerable to XSS
```
**Risk**: XSS attack can steal token
**Solution**: Use only HttpOnly cookies (already done in backend - remove localStorage)
```javascript
// Remove localStorage usage for tokens
// Backend already uses HttpOnly cookies ✓
```

#### Issue #F1.2: Missing CSRF Token on Forms
**Severity**: HIGH
**Problem**: No CSRF token validation on state-changing requests
**Current**:
```javascript
fetch(`${API_URL}/orders`, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${authToken}`
    },
    body: JSON.stringify({...})
});
```
**Better**: Add CSRF token
```javascript
headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${authToken}`,
    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content
}
```

#### Issue #F1.3: Inline Event Handlers (XSS Risk)
**Severity**: MEDIUM
**Problems Throughout**:
```html
<button onclick="selectMovie(${movie.id})">Đặt Vé</button>
<button onclick="toggleSeat(${seat.id}, '${seat.status}')">...</button>
```
**Risk**: If `movie.id` contains JS, executes directly
**Solution**: Use event listeners
```javascript
button.addEventListener('click', () => selectMovie(movie.id));
```

#### Issue #F1.4: No Input Sanitization in Templates
**Severity**: HIGH
```javascript
el.innerHTML = `<div>${movie.title}</div>`;  // If title has <script>, executes
```
**Should Use**:
```javascript
const div = document.createElement('div');
div.textContent = movie.title;  // Safe
el.appendChild(div);
```
**Or use library**:
```javascript
import DOMPurify from 'dompurify';
el.innerHTML = DOMPurify.sanitize(movie.title);
```

#### Issue #F1.5: No Error Boundary / Crash Recovery
**Severity**: MEDIUM
**Current**: Single error crashes entire app
**Solution**: Add error handler
```javascript
window.addEventListener('error', (event) => {
    console.error('Unhandled error:', event.error);
    showAlert('An unexpected error occurred', 'danger');
});

window.addEventListener('unhandledrejection', (event) => {
    console.error('Unhandled promise rejection:', event.reason);
    showAlert('An unexpected error occurred', 'danger');
});
```

#### Issue #F1.6: Sensitive Data in Response
**Severity**: MEDIUM
**Example**:
```javascript
const data = await response.json();
console.log(data);  // Logs sensitive user data to console
```
**Risk**: DevTools history, logs captured
**Better**: Don't log sensitive data

#### Issue #F1.7: No Request Timeout
**Severity**: MEDIUM
**Current**: Requests can hang indefinitely
```javascript
const response = await fetch(url, config);
```
**Better**: Add timeout
```javascript
const controller = new AbortController();
const timeoutId = setTimeout(() => controller.abort(), 30000);
const response = await fetch(url, { ...config, signal: controller.signal });
clearTimeout(timeoutId);
```

---

## 6. CONFIGURATION & PROVIDERS ANALYSIS

### AppServiceProvider Issues

#### Issue #P1.1: Rate Limiting Configuration Too Loose
**Severity**: MEDIUM
**Current**:
```php
RateLimiter::for('auth', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());  // OK
});

RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    // Problem: If user not authenticated, uses IP (can be shared)
});
```
**Better**: Differentiate by user
```php
RateLimiter::for('api', function (Request $request) {
    if ($request->user()) {
        return Limit::perMinute(60)->by($request->user()->id);
    }
    return Limit::perMinute(20)->by($request->ip());  // Stricter for unauthenticated
});
```

#### Issue #P1.2: Missing Custom Exception Handler
**Severity**: MEDIUM
**Problem**: No global error handler for API responses
**Current**: Laravel's default error handler returns HTML on exceptions
**Solution**: Create custom exception handler
```php
// app/Exceptions/Handler.php
public function render($request, Throwable $exception)
{
    if ($request->expectsJson()) {
        return response()->json([
            'success' => false,
            'message' => 'An error occurred',
            'error_code' => $exception->getCode()
        ], 500);
    }
    return parent::render($request, $exception);
}
```

---

## 7. CROSS-CUTTING CONCERNS

### Logging & Monitoring

#### Issue #X1.1: No Structured Logging
**Severity**: MEDIUM
**Current**: Mixed log levels, unstructured
```php
Log::info('User registered successfully', ['user_id' => $user->id]);
Log::warning('Login failed', ['login' => $login, 'ip' => $ipAddress]);
```
**Better**: Use structured logging with context
```php
Log::info('user.registered', [
    'user_id' => $user->id,
    'email' => $user->email,
    'timestamp' => now()->toIso8601String()
]);
```

#### Issue #X1.2: PII in Logs
**Severity**: HIGH
**Current**: User IDs, emails logged
**Risk**: Sensitive data in log files/dashboards
**Solution**: Redact PII
```php
Log::info('login.attempt', [
    'method' => 'email',
    'success' => true,
    'ip' => hash_hmac('sha256', $ip, config('app.key'))  // Hash IP
]);
```

### Idempotency & Reliability

#### Issue #X2.1: No Idempotency Key Handling
**Severity**: HIGH
**Problem**: If payment webhook sent twice, order finalized twice
**Solution**: Use IdempotencyKey model (exists but not used)
```php
// In PaymentService::handleWebhook
$idempotencyKey = $webhookData['transaction_id'] ?? null;
if ($idempotencyKey) {
    $cached = IdempotencyKey::byKey($idempotencyKey)->valid()->first();
    if ($cached) {
        return $cached->response;  // Return cached response
    }
}
```

#### Issue #X2.2: No Transaction Rollback on Partial Failure
**Severity**: MEDIUM
**Current**: Creates order, then payment fails → orphaned order
**Better**: Ensure atomicity
```php
$order = DB::transaction(function () {
    $order = Order::create([...]);
    // Only create payment link if order creation succeeds
    $this->paymentGateway->createLink($order);
    return $order;
});
```

---

## 8. PRIORITY IMPROVEMENTS ROADMAP

### Phase 1: CRITICAL (Fix Immediately - Production Risk)
1. **Order Authorization Check** - Prevent data exposure (Issue #C2.1)
2. **Webhook Signature Verification** - Prevent fake payments (Issue #C3.1)
3. **Frontend Token Storage** - Remove localStorage for tokens (Issue #F1.1)
4. **Promotion Usage Atomicity** - Prevent double-use (Issue #M1.4)
5. **Seat Lock Race Condition** - Prevent double-booking (Issue #S1.2)

### Phase 2: HIGH (Complete Before Public Release)
1. **Queue-based Webhook Processing** - Prevent timeouts (Issue #C3.2)
2. **Database Indexes** - Add missing indexes (Issue #4.1 from main report)
3. **Rate Limiting** - Add to public endpoints (Issue #R1.1)
4. **Test Coverage** - Implement unit/integration tests (Issue #9.1 from main report)
5. **CSRF Protection** - Add token validation (Issue #F1.2)
6. **Input Sanitization** - Prevent XSS (Issue #F1.4)

### Phase 3: MEDIUM (Before Scale-Up)
1. **Distributed Locking** - Redis-based for deadlock prevention (Issue #S1.1)
2. **Structured Logging** - Implement centralized logging (Issue #X1.1)
3. **Idempotency Keys** - Implement for webhook reliability (Issue #X2.1)
4. **PII Redaction** - Remove sensitive data from logs (Issue #X1.2)
5. **API Documentation** - Generate OpenAPI specs (Issue #5.6 from main report)

### Phase 4: LOW (Nice-to-Have Improvements)
1. **Repository Pattern** - Data access abstraction (Issue #1.2 from main report)
2. **Domain Events** - Order state tracking (Issue #1.3 from main report)
3. **Enum Usage** - Replace magic numbers (Issue #2.2 from main report)
4. **Query Caching** - Cache rarely-changing data (Issue #8.3 from main report)

---

## ESTIMATED EFFORT

| Phase | Issues | Effort | Timeline |
|-------|--------|--------|----------|
| **1 (CRITICAL)** | 5 | 40-50h | Week 1 |
| **2 (HIGH)** | 6 | 60-80h | Weeks 2-3 |
| **3 (MEDIUM)** | 5 | 50-60h | Weeks 4-5 |
| **4 (LOW)** | 4 | 40-50h | Sprint planning |
| **Total** | 20+ | **190-240h** | **5-6 weeks** |

---

## CONCLUSION

The Cinema Movie Booking System shows **solid architectural foundations** but requires **immediate attention to critical security issues** before production deployment. The main concerns are:

1. **Authorization & Data Exposure** - Users can access others' orders
2. **Payment Security** - Webhook spoofing possible
3. **Concurrency Issues** - Race conditions on seat bookings
4. **Frontend Security** - Token storage and XSS vulnerabilities

**Recommended Action**: Implement Phase 1 (CRITICAL) items within this week, then systematically address Phase 2 before any public release.

**Code Quality**: 6.5/10 → 8.0/10 (after Phase 1 & 2 fixes)
