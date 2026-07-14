# Code Review: PaymentController.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Backend Engineer  
**File Path:** `app/Http/Controllers/PaymentController.php`  
**Lines of Code:** 158  
**Complexity:** High (handles money, webhooks, payment callbacks)

---

## Overall Assessment

**Score:** 2.0/10  
**Decision:** 🔴 **BLOCKING - CRITICAL SECURITY VULNERABILITIES**

**Summary:** This controller has SEVERE SECURITY VULNERABILITIES that enable payment fraud. Multiple critical money-loss exploits found. DO NOT DEPLOY.

---

## Critical Vulnerabilities

### 🔴 CRITICAL #1: Payment Fraud - Free Tickets Exploit

**Location:** Lines 62-67  
**Severity:** CRITICAL - Money Loss  
**CVSS Score:** 9.8

**Vulnerability:**
```php
$isSuccessfulReturn = $status === 'PAID'  // USER CONTROLLED
    || $status === 'success'               // USER CONTROLLED  
    || $request->query('code') === '00';   // USER CONTROLLED

if ($isSuccessfulReturn) {
    $this->paymentService->markPaidFromReturn($order); // MARKS AS PAID!
}
```

**Exploitation:**
```bash
# Step 1: Attacker creates order #12345 for $100 tickets
# Step 2: Attacker navigates to:
https://cinema.com/payment/callback?orderCode=12345&status=PAID&code=00

# Result: Order marked as paid WITHOUT actual payment
# Attacker gets free tickets worth $100
```

**Impact:**
- Unlimited free tickets
- Direct financial loss
- Business bankruptcy if exploited at scale
- Legal liability for payment fraud
- Zero authentication on payment confirmation

**Root Cause:**
The controller trusts user-provided URL query parameters to mark orders as paid. Return URLs should NEVER be used for payment confirmation - only webhooks with verified signatures should update payment status.

**Fix Required:**
```php
public function payosCallback(Request $request): RedirectResponse
{
    $orderCode = $request->query('orderCode');
    
    // Find order
    $order = null;
    if (is_string($orderCode) && $orderCode !== '') {
        $order = $this->orderService->findByGatewayCode((int) $orderCode);
        
        // Authorization check
        if ($order && Auth::check() && (int) $order->user_id !== (int) Auth::id()) {
            abort(403, __('payment.unauthorized'));
        }
    }
    
    // DO NOT mark as paid based on query params!
    // Only redirect back with status for UX purposes
    // The webhook will handle actual payment confirmation
    
    if ($order) {
        // Optional: Trigger async sync from gateway (but don't trust it immediately)
        dispatch(new SyncPaymentStatusJob($order->id));
        
        $encryptedShowtimeId = $order->showtime?->encrypted_id;
        
        // Redirect back with current status from database (not from query params)
        $paymentStatus = match($order->payment_status) {
            'paid' => 'success',
            'pending' => 'pending',
            'cancelled' => 'cancelled',
            'failed' => 'failed',
            default => 'unknown'
        };
        
        if ($encryptedShowtimeId) {
            return redirect()->route('booking.show', [
                'encryptedShowtimeId' => $encryptedShowtimeId,
                'paymentStatus' => $paymentStatus,
                'orderCode' => $orderCode,
            ]);
        }
    }
    
    return redirect()->route('home');
}
```

**Critical Principle:**
- Return URLs = User Experience only (redirect, show loading)
- Webhooks = Source of Truth for payment status
- NEVER update payment status based on return URL parameters

---

### 🔴 CRITICAL #2: Webhook Signature Verification Uncertainty

**Location:** Lines 136-157  
**Severity:** CRITICAL - Money Loss  
**CVSS Score:** 9.1

**Vulnerability:**
```php
public function payosWebhook(Request $request): \Illuminate\Http\JsonResponse
{
    try {
        // NO visible signature verification here
        $result = $this->paymentService->handleWebhook($request->all());
        // ...
```

**Issue:**
If the `VerifyPayOSWebhookSignature` middleware is not properly applied or has bugs, this endpoint is completely unprotected. Anyone could POST fake payment confirmations.

**Exploitation:**
```bash
# Attacker discovers webhook URL
curl -X POST https://cinema.com/payment/payos/webhook \
  -H "Content-Type: application/json" \
  -d '{
    "code": "00",
    "data": {
      "orderCode": 12345,
      "amount": 100000,
      "transactionDateTime": "2026-07-14"
    }
  }'

# If no signature check: Order marked as paid
```

**Impact:**
- Complete payment system compromise
- Unlimited free tickets
- Massive financial loss
- Business bankruptcy

**Fix Required:**
```php
public function payosWebhook(Request $request): \Illuminate\Http\JsonResponse
{
    // CRITICAL: Verify signature even if middleware exists (defense in depth)
    if (!$this->paymentService->verifyWebhookSignature($request)) {
        Log::critical('Webhook signature verification failed', [
            'ip' => $request->ip(),
            'payload' => $request->all(),
            'headers' => $request->headers->all(),
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Invalid signature',
        ], 401);
    }
    
    try {
        // Log all webhook attempts for audit
        Log::info('Webhook received', [
            'payload' => $request->all(),
            'ip' => $request->ip(),
        ]);
        
        $result = $this->paymentService->handleWebhook($request->all());
        
        // Log success
        Log::info('Webhook processed successfully', [
            'order_id' => $result['order_id'] ?? null,
            'already_processed' => $result['already_processed'] ?? false,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => match (true) {
                $result['already_processed'] ?? false => 'Order already processed',
                $result['skipped'] ?? false => 'Webhook processed without successful payment',
                default => 'Payment processed successfully',
            },
        ]);
    } catch (Throwable $e) {
        // Log with full context
        Log::critical('Webhook processing failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'payload' => $request->all(),
        ]);
        
        report($e);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to process webhook',
        ], 500);
    }
}
```

**Also verify middleware is properly applied:**
```php
// In routes/api.php or routes/web.php
Route::post('/payment/payos/webhook', [PaymentController::class, 'payosWebhook'])
    ->middleware('verify.payos.webhook')  // ← CRITICAL: Verify this exists
    ->withoutMiddleware(['throttle', 'csrf']);
```

---

### 🔴 HIGH #3: Flawed Authorization Logic

**Location:** Lines 53-55, 109-111  
**Severity:** HIGH - Authorization Bypass

**Vulnerability:**
```php
// If user is authenticated, ensure order belongs to them (defense in depth)
// Allow guest access in case session expired during payment
if ($order && Auth::check() && (int) $order->user_id !== (int) Auth::id()) {
    $order = null;
}
```

**Issue:**
The check only applies if user IS authenticated, but allows the operation if user is NOT authenticated (guest). This creates a security loophole.

**Exploitation:**
```bash
# Step 1: User A creates order #123
# Step 2: Attacker B learns order #123 exists
# Step 3: Attacker B logs out (becomes guest)
# Step 4: Attacker B accesses /payment/callback?orderCode=123&status=PAID
# Step 5: Authorization check passes because attacker is guest
# Step 6: System processes payment callback for User A's order
```

**Impact:**
- Attacker can access any order by simply logging out
- "Session expired during payment" is not a valid security reason
- Could be exploited to view/manipulate other users' orders

**Fix Required:**
```php
// Option 1: Strict enforcement
if ($order) {
    // Order MUST belong to authenticated user
    if (!Auth::check() || (int) $order->user_id !== (int) Auth::id()) {
        Log::warning('Unauthorized payment callback access', [
            'order_id' => $order->id,
            'order_user_id' => $order->user_id,
            'accessing_user_id' => Auth::id(),
            'is_guest' => !Auth::check(),
            'ip' => $request->ip(),
        ]);
        abort(403, __('payment.unauthorized'));
    }
}

// Option 2: Use signed URLs
// When creating payment link, generate signed return URL
$returnUrl = URL::temporarySignedRoute(
    'payment.callback',
    now()->addHours(2),
    ['orderCode' => $order->gateway_order_code]
);

// In controller
public function payosCallback(Request $request): RedirectResponse
{
    // Verify signature
    if (!$request->hasValidSignature()) {
        abort(403, __('payment.invalid_signature'));
    }
    
    // ... process callback
}
```

---

## High Priority Issues

### 🟠 HIGH #4: Unsafe Type Casting

**Location:** Lines 49, 105  
**Severity:** HIGH - Type Safety

**Issue:**
```php
$order = $this->orderService->findByGatewayCode((int) $orderCode);
// What if $orderCode = "abc123" or "123abc" or "999999999999999999999"?
```

**Problems:**
- Type casting `(int) "abc"` returns `0`
- Type casting `(int) "123abc"` returns `123`
- Large numbers could overflow
- Could retrieve wrong order

**Fix:**
```php
// Validate before casting
if (!is_numeric($orderCode)) {
    Log::warning('Invalid orderCode format', [
        'orderCode' => $orderCode,
        'ip' => $request->ip(),
    ]);
    return redirect()->route('home');
}

$orderCodeInt = (int) $orderCode;

// Additional validation
if ($orderCodeInt <= 0 || $orderCodeInt > PHP_INT_MAX) {
    Log::warning('OrderCode out of valid range', [
        'orderCode' => $orderCode,
        'ip' => $request->ip(),
    ]);
    return redirect()->route('home');
}

$order = $this->orderService->findByGatewayCode($orderCodeInt);
```

---

### 🟠 HIGH #5: No FormRequest Validation

**Location:** Lines 37, 99, 136  
**Severity:** HIGH - Validation

**Issue:**
No FormRequest validation for any of the methods. Query parameters and webhook payload are not validated.

**Fix:**
```php
// app/Http/Requests/PayosCallbackRequest.php
class PayosCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    
    public function rules(): array
    {
        return [
            'orderCode' => ['required', 'string', 'regex:/^\d+$/'],
            'status' => ['nullable', 'string', 'in:PAID,CANCELLED,PENDING'],
            'code' => ['nullable', 'string'],
        ];
    }
}

// app/Http/Requests/PayosWebhookRequest.php
class PayosWebhookRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'desc' => ['nullable', 'string'],
            'data' => ['required', 'array'],
            'data.orderCode' => ['required', 'integer'],
            'data.amount' => ['required', 'integer', 'min:0'],
            'signature' => ['required', 'string'],
        ];
    }
}

// In controller
public function payosCallback(PayosCallbackRequest $request): RedirectResponse
public function payosWebhook(PayosWebhookRequest $request): \Illuminate\Http\JsonResponse
```

---

### 🟠 HIGH #6: Inconsistent Success Detection

**Location:** Lines 62-64, 79-84  
**Severity:** HIGH - Business Logic

**Issue:**
Multiple inconsistent ways to determine payment success. Code is duplicated and brittle.

```php
// First check (line 62-64)
$isSuccessfulReturn = $status === 'PAID'
    || $status === 'success'
    || $request->query('code') === '00';

// Second check (line 79-84) - DIFFERENT!
if ($encryptedShowtimeId && (
    $status === 'PAID'
    || $status === 'success'
    || $request->query('code') === '00'
    || (isset($order) && $order?->payment_status === 'paid')  // Extra condition!
)) {
```

**Fix:**
```php
// Create enum or constants
enum PaymentStatus: string
{
    case PAID = 'PAID';
    case SUCCESS = 'success';
    case CANCELLED = 'CANCELLED';
    case PENDING = 'PENDING';
}

enum PaymentCode: string
{
    case SUCCESS = '00';
    case CANCELLED = '01';
}

// Extract to method
private function isSuccessfulPaymentReturn(Request $request, ?Order $order = null): bool
{
    $status = $request->query('status');
    $code = $request->query('code');
    
    return $status === PaymentStatus::PAID->value
        || $status === PaymentStatus::SUCCESS->value
        || $code === PaymentCode::SUCCESS->value
        || ($order && $order->payment_status === 'paid');
}

// Use in methods
if ($this->isSuccessfulPaymentReturn($request, $order)) {
    // ... handle success
}
```

---

## Medium Priority Issues

### 🟡 MEDIUM #7: No Audit Logging

**Location:** Lines 37-157 (entire file)

**Issue:**
Minimal logging for critical payment operations. No audit trail for:
- Payment callback attempts
- Authorization failures
- Invalid orderCode attempts
- Cancel operations
- Webhook processing details

**Impact:**
- Cannot debug payment issues
- No fraud detection capability
- Cannot track suspicious activity
- Hard to provide customer support
- No compliance trail

**Fix:**
```php
Log::info('Payment callback received', [
    'query_params' => $request->query(),
    'ip' => $request->ip(),
    'user_agent' => $request->userAgent(),
    'user_id' => Auth::id(),
]);

// ... after finding order
Log::info('Syncing payment status from gateway', [
    'order_id' => $order->id,
    'order_code' => $orderCode,
    'return_status' => $status,
]);
```

---

### 🟡 MEDIUM #8: No Rate Limiting

**Location:** Lines 37, 99, 136

**Issue:**
No rate limiting on callback or webhook endpoints.

**Impact:**
- Could overwhelm database with queries
- Attacker could spam callbacks
- Performance degradation
- Potential service disruption

**Fix:**
```php
// In routes/web.php or routes/api.php
Route::middleware(['throttle:payment-callback'])->group(function () {
    Route::get('/payment/payos/callback', [PaymentController::class, 'payosCallback']);
    Route::get('/payment/payos/cancel', [PaymentController::class, 'payosCancel']);
});

Route::post('/payment/payos/webhook', [PaymentController::class, 'payosWebhook'])
    ->middleware(['throttle:payment-webhook']);

// In app/Providers/RouteServiceProvider.php
RateLimiter::for('payment-callback', function (Request $request) {
    return Limit::perMinute(10)->by($request->ip());
});

RateLimiter::for('payment-webhook', function (Request $request) {
    return Limit::perMinute(60)->by($request->ip());
});
```

---

### 🟡 MEDIUM #9: Silent Failures

**Location:** Lines 92, 129

**Issue:**
```php
return redirect()->route('home');
// User has no idea what happened or why
```

**Impact:**
- Poor user experience
- User doesn't know if payment succeeded or failed
- Cannot troubleshoot issues

**Fix:**
```php
// For errors
return redirect()->route('home')->with('error', __('payment.processing_error'));

// Or redirect to order status page
if ($order) {
    return redirect()->route('orders.show', $order->id)
        ->with('warning', __('payment.status_unknown'));
}
```

---

## Low Priority Issues

### 🔵 LOW #10: Dead Code

**Location:** Lines 28-31

**Issue:**
Unused `index()` method that just redirects.

**Fix:**
Remove entirely or add clear deprecation documentation.

---

## Summary

**Total Issues:** 11
- 🔴 Critical: 2 (Production Blocking)
- 🟠 High: 4
- 🟡 Medium: 3
- 🔵 Low: 2

**Security Risk:** EXTREME  
**Business Impact:** SEVERE - Could bankrupt business  
**Technical Debt:** HIGH

---

## Recommendations

### Immediate Actions (BLOCKING):
1. Remove `markPaidFromReturn()` call or add gateway verification
2. Add explicit webhook signature verification in controller
3. Fix authorization logic (no guest bypass)
4. Add FormRequest validation

### High Priority:
5. Add comprehensive audit logging
6. Implement rate limiting
7. Fix type casting safety
8. Extract success detection to single method

### Medium Priority:
9. Improve error handling and user feedback
10. Remove dead code

---

## Test Cases Required

```php
// Test: Cannot mark order as paid via URL manipulation
public function test_callback_cannot_mark_order_paid_without_gateway_verification()
{
    $order = Order::factory()->create(['payment_status' => 'pending']);
    
    $response = $this->get("/payment/payos/callback?orderCode={$order->gateway_order_code}&status=PAID");
    
    $order->refresh();
    $this->assertNotEquals('paid', $order->payment_status);
}

// Test: Webhook requires valid signature
public function test_webhook_rejects_invalid_signature()
{
    $response = $this->postJson('/payment/payos/webhook', [
        'code' => '00',
        'data' => ['orderCode' => 123],
    ]);
    
    $response->assertStatus(401);
}

// Test: Authorization required for callbacks
public function test_callback_requires_order_ownership()
{
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $userA->id]);
    
    $this->actingAs($userB);
    $response = $this->get("/payment/payos/callback?orderCode={$order->gateway_order_code}");
    
    $response->assertStatus(403);
}
```

---

## Conclusion

**This controller ABSOLUTELY BLOCKS production deployment.**

The payment fraud vulnerabilities are CRITICAL and could cause IMMEDIATE FINANCIAL LOSS if deployed. The fundamental design is flawed: return URLs should NEVER be trusted for payment confirmation.

**Required Actions:**
1. Immediate redesign of callback handling
2. Add explicit security checks in all payment methods
3. Comprehensive security testing before any deployment
4. Code review by security specialist

**Estimated Fix Time:** 2-3 days for critical issues

**Status:** 🔴 **REJECTED - MUST FIX BEFORE MERGE**
