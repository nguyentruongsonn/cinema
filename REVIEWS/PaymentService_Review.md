# Code Review: PaymentService.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Backend Engineer  
**File Path:** `app/Services/PaymentService.php`  
**Lines of Code:** 415  
**Complexity:** Very High (money, payment gateway, webhooks, order fulfillment)

---

## Overall Assessment

**Score:** 5.0/10  
**Decision:** 🔴 **BLOCKING - CRITICAL VULNERABILITIES**

**Summary:** Contains the CRITICAL free tickets exploit (`markPaidFromReturn`) and multiple high-severity issues. Good transaction usage and locking, but critical security flaws block production.

---

## Critical Vulnerabilities

### 🔴 CRITICAL #1: markPaidFromReturn() - Payment Fraud Enabler

**Location:** Lines 244-251  
**Severity:** CRITICAL - Direct Money Loss  
**CVSS Score:** 9.8

**Vulnerability:**
```php
public function markPaidFromReturn(Order $order): array
{
    return $this->fulfillment->finalize((int) $order->gateway_order_code);
    // NO VERIFICATION WITH PAYMENT GATEWAY
    // Called from PaymentController when user returns with ?status=PAID
}
```

**This is the ROOT CAUSE of the free tickets exploit.**

**Exploitation Chain:**
1. Attacker creates order for expensive tickets
2. Attacker manipulates return URL: `?orderCode=123&status=PAID`
3. PaymentController.payosCallback() sees `status=PAID`
4. Controller calls `markPaidFromReturn($order)`
5. This method IMMEDIATELY calls `fulfillment->finalize()`
6. Order marked as paid, tickets issued, NO PAYMENT MADE

**Impact:**
- **UNLIMITED FREE TICKETS**
- **DIRECT FINANCIAL LOSS**
- **BUSINESS BANKRUPTCY RISK**
- Zero payment verification
- Completely bypasses payment gateway

**Why This Exists:**
This method appears to be a development shortcut or misunderstood payment flow. Return URLs should NEVER trigger payment finalization.

**Fix Required:**

**Option 1: DELETE THIS METHOD ENTIRELY (RECOMMENDED)**
```php
// REMOVE lines 244-251 completely
// Return URLs should only show UI status, not update payment state
```

**Option 2: Add Gateway Verification (if method must exist)**
```php
public function markPaidFromReturn(Order $order): array
{
    // CRITICAL: Verify with gateway before marking paid
    Log::info('Verifying payment with gateway', [
        'order_id' => $order->id,
        'gateway_order_code' => $order->gateway_order_code,
    ]);
    
    try {
        // Query the actual payment gateway for payment status
        $paymentInfo = $this->gateway->getPaymentInformation($order->gateway_order_code);
        
        if (!$paymentInfo) {
            Log::error('Gateway returned no payment information', [
                'order_id' => $order->id,
                'gateway_order_code' => $order->gateway_order_code,
            ]);
            throw new \RuntimeException('Cannot verify payment status with gateway');
        }
        
        $actualStatus = $this->extractGatewayStatus($paymentInfo);
        
        if (!$this->isSuccessfulGatewayStatus($actualStatus)) {
            Log::warning('Gateway reports payment not successful', [
                'order_id' => $order->id,
                'gateway_status' => $actualStatus,
            ]);
            throw new \RuntimeException('Payment not confirmed by gateway');
        }
        
        // Only if gateway confirms payment, proceed
        return $this->fulfillment->finalize((int) $order->gateway_order_code);
        
    } catch (\Throwable $e) {
        Log::error('Payment verification failed', [
            'order_id' => $order->id,
            'error' => $e->getMessage(),
        ]);
        throw $e;
    }
}

private function isSuccessfulGatewayStatus(string $status): bool
{
    return in_array($status, ['PAID', 'COMPLETED', 'SUCCESS'], true);
}
```

**Recommended Action:**
DELETE this method. Payment finalization should ONLY happen via verified webhooks, never from return URLs.

---

### 🔴 CRITICAL #2: Order Code Generation Race Condition

**Location:** Lines 327-334  
**Severity:** CRITICAL - Data Corruption  
**CVSS Score:** 8.1

**Vulnerability:**
```php
private function generateOrderCode(): int
{
    do {
        $code = (int) (now()->format('ymdHis') . random_int(100, 999));
    } while (Order::where('gateway_order_code', $code)->exists());
    
    return $code;
}
```

**Race Condition:**
```
Time    | Request A                      | Request B
--------|--------------------------------|--------------------------------
T+0ms   | Generate code: 2607140200123   | Generate code: 2607140200123
T+1ms   | Check exists() → false         | Check exists() → false
T+2ms   | Return 2607140200123           | Return 2607140200123
T+3ms   | Create order with code         | Create order with code
T+4ms   | SUCCESS                        | DATABASE ERROR: Duplicate key!
```

**Window of Vulnerability:**
Between `exists()` check and order creation (2-5ms), another request could generate the same code.

**Impact:**
- Order creation randomly fails
- Poor user experience
- User might get "payment pending" but order failed
- Under high load, multiple failures
- Payment succeeded but order not created

**Why It Happens:**
- `exists()` is not atomic with order creation
- High-resolution timestamp (microseconds) not used
- No database-level uniqueness enforcement in check
- Random component too small (100-999 = 900 possibilities)

**Fix Required:**
```php
private function generateOrderCode(): int
{
    $maxAttempts = 10;
    
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        // Use microseconds for better uniqueness
        $timestamp = now()->format('ymdHisu'); // Added microseconds
        $random = random_int(1000, 9999); // Larger random space
        $code = (int) ($timestamp . $random);
        
        // Try to create with this code immediately
        // Don't check exists() separately - let database uniqueness handle it
        
        // Alternative: Use atomic insert with ON DUPLICATE KEY or try-catch
        try {
            // If this succeeds, code is unique
            DB::table('orders')->insert([
                'gateway_order_code' => $code,
                'created_at' => now(),
            ]);
            
            // Code is unique, delete the test row
            DB::table('orders')->where('gateway_order_code', $code)->delete();
            
            return $code;
        } catch (\Illuminate\Database\QueryException $e) {
            // Duplicate key, try again
            if ($attempt === $maxAttempts) {
                Log::error('Failed to generate unique order code after max attempts', [
                    'attempts' => $maxAttempts,
                ]);
                throw new \RuntimeException('Failed to generate unique order code');
            }
            
            usleep(100); // 0.1ms delay before retry
            continue;
        }
    }
    
    throw new \RuntimeException('Failed to generate unique order code');
}

// Better approach: Use database sequence or UUID
private function generateOrderCodeBetter(): string
{
    // Use UUID for guaranteed uniqueness
    return (string) Str::uuid();
    
    // Or use database sequence
    // return DB::select('SELECT NEXT VALUE FOR order_code_sequence')[0]->value;
}
```

**Also ensure database has unique constraint:**
```sql
ALTER TABLE orders ADD UNIQUE INDEX idx_gateway_order_code (gateway_order_code);
```

---

### 🔴 HIGH #3: Webhook Signature Not Verified in handleWebhook

**Location:** Lines 253-291  
**Severity:** HIGH - Could Be Critical  
**CVSS Score:** 8.8

**Issue:**
```php
public function handleWebhook(array $payload): array
{
    // NO signature verification visible here
    // Relies entirely on controller/middleware
    
    $webhookCode = $payload['code'] ?? null;
    // ... processes webhook ...
}
```

**Problem:**
- If middleware is bypassed or has bugs, webhook is unprotected
- No defense-in-depth
- Service should validate independently

**Fix Required:**
```php
public function handleWebhook(array $payload, Request $request = null): array
{
    // Defense in depth: Verify signature even if middleware exists
    if ($request && !$this->verifyWebhookSignature($request)) {
        Log::critical('Webhook signature verification failed in service layer', [
            'payload' => $payload,
            'ip' => $request->ip(),
        ]);
        throw new \UnauthorizedHttpException('Invalid webhook signature');
    }
    
    // Continue with webhook processing...
}

public function verifyWebhookSignature(Request $request): bool
{
    $signature = $request->header('X-PayOS-Signature');
    
    if (!$signature) {
        Log::warning('Webhook signature header missing');
        return false;
    }
    
    // Verify signature against payload
    $payload = $request->getContent();
    $expectedSignature = hash_hmac('sha256', $payload, config('payment.payos.webhook_secret'));
    
    if (!hash_equals($expectedSignature, $signature)) {
        Log::warning('Webhook signature mismatch', [
            'expected' => substr($expectedSignature, 0, 10) . '...',
            'received' => substr($signature, 0, 10) . '...',
        ]);
        return false;
    }
    
    return true;
}
```

---

## High Priority Issues

### 🟠 HIGH #4: No Idempotency in initiate()

**Location:** Lines 102-202  
**Severity:** HIGH - Duplicate Orders

**Issue:**
User could create multiple orders for same seats by:
- Double-clicking "Pay Now" button
- Network retry
- Browser back/forward
- Malicious multiple requests

**Exploitation:**
```bash
# User selects seats, clicks "Pay" multiple times rapidly
POST /api/orders { "showtime_id": 123, "seat_ids": [1,2,3] }
POST /api/orders { "showtime_id": 123, "seat_ids": [1,2,3] } # Same request
POST /api/orders { "showtime_id": 123, "seat_ids": [1,2,3] } # Same request

# Result: 3 orders created, 3 seat holds consumed, user confused
```

**Impact:**
- Multiple pending orders for same seats
- Confusing user experience
- Wasted seat holds
- Manual cleanup required
- Payment could succeed for wrong order

**Fix Required:**
```php
public function initiate(array $data, $user): array
{
    return DB::transaction(function () use ($data, $user) {
        $showtimeId = (int) $data['showtime_id'];
        $seatIds = array_map('intval', $data['seat_ids']);
        sort($seatIds);
        
        // IDEMPOTENCY CHECK: Look for recent duplicate order
        $recentDuplicate = Order::where('user_id', $user->id)
            ->where('showtime_id', $showtimeId)
            ->where('created_at', '>', now()->subMinutes(5))
            ->where('status', Order::STATUS_PENDING)
            ->whereJsonLength('payload->seat_ids', count($seatIds))
            ->get()
            ->first(function (Order $order) use ($seatIds) {
                $orderSeatIds = $order->payload['seat_ids'] ?? [];
                sort($orderSeatIds);
                return $orderSeatIds === $seatIds;
            });
        
        if ($recentDuplicate) {
            Log::info('Idempotent order creation - returning existing order', [
                'order_id' => $recentDuplicate->id,
                'user_id' => $user->id,
            ]);
            
            // Return existing order instead of creating duplicate
            $payment = Payment::where('order_id', $recentDuplicate->id)
                ->latest()
                ->first();
            
            if ($payment) {
                return $this->formatPaymentResponse($recentDuplicate, $payment);
            }
        }
        
        // Continue with normal order creation...
    });
}
```

---

### 🟠 HIGH #5: validateSeatHold() Has TOCTOU Race

**Location:** Lines 297-319  
**Severity:** HIGH - Race Condition

**Issue:**
```php
private function validateSeatHold(int $showtimeId, int $userId, ?int $seatHoldId, array $seatIds): void
{
    // 1. Query hold
    $holdQuery = SeatHold::query()
        ->where('showtime_id', $showtimeId)
        ->where('user_id', $userId)
        ->where('held_until', '>', now());
    
    // ... validate ...
    
    // 2. Validate seat IDs match
    if ($seatIds !== $heldSeatIds) {
        throw new \RuntimeException('...');
    }
    
    // TIME GAP HERE - hold could expire or be deleted
    
    // 3. Later in initiate(), hold is used
}
```

**Race Condition:**
Between validation and usage, seat hold could:
- Expire (held_until passes)
- Be deleted by another request
- Be modified

**Fix Required:**
```php
private function validateSeatHold(int $showtimeId, int $userId, ?int $seatHoldId, array $seatIds): SeatHold
{
    $holdQuery = SeatHold::query()
        ->where('showtime_id', $showtimeId)
        ->where('user_id', $userId)
        ->where('held_until', '>', now())
        ->lockForUpdate(); // ← ADD LOCK
    
    if ($seatHoldId) {
        $holdQuery->whereKey($seatHoldId);
    }
    
    $seatHold = $holdQuery->first();
    
    if (!$seatHold) {
        throw new \RuntimeException(__('seats.hold_expired'));
    }
    
    // Validate
    $heldSeatIds = array_values(array_map('intval', (array) $seatHold->seat_ids));
    sort($seatIds);
    sort($heldSeatIds);
    
    if ($seatIds !== $heldSeatIds) {
        throw new \RuntimeException(__('seats.hold_mismatch'));
    }
    
    // Return the locked hold for caller to use
    return $seatHold;
}

// In initiate(), use the returned hold
$seatHold = $this->validateSeatHold($showtimeId, $user->id, $seatHoldId, $seatIds);
// Now safe to use $seatHold
```

---

### 🟠 HIGH #6: Insufficient Logging

**Location:** Lines 102-415 (entire file)

**Issue:**
Payment operations are barely logged. Critical for:
- Debugging payment issues
- Fraud detection
- Compliance (financial regulations)
- Customer support
- Audit trail

**Missing Logs:**
- Payment initiation attempts
- Seat validation results
- Order creation
- Payment link generation
- Webhook processing details
- Gateway API calls
- All failures and errors

**Fix Required:**
```php
public function initiate(array $data, $user): array
{
    Log::info('Payment initiation started', [
        'user_id' => $user->id,
        'showtime_id' => $data['showtime_id'],
        'seat_count' => count($data['seat_ids']),
        'product_count' => count($data['products'] ?? []),
    ]);
    
    return DB::transaction(function () use ($data, $user) {
        // ... validation ...
        
        Log::info('Seat hold validated', [
            'seat_hold_id' => $seatHold->id,
            'seat_ids' => $seatIds,
        ]);
        
        // ... create order ...
        
        Log::info('Order created for payment', [
            'order_id' => $order->id,
            'total_amount' => $totalPrice,
        ]);
        
        // ... create payment ...
        
        Log::info('Payment record created', [
            'payment_id' => $payment->id,
            'gateway' => 'payos',
            'amount' => $totalPrice,
        ]);
        
        // ... call gateway ...
        
        Log::info('Payment link generated', [
            'order_id' => $order->id,
            'checkout_url' => $checkoutUrl,
        ]);
        
        return $this->formatPaymentResponse($order, $payment);
    });
}

public function handleWebhook(array $payload): array
{
    Log::info('Webhook received', [
        'code' => $payload['code'] ?? null,
        'order_code' => $payload['data']['orderCode'] ?? null,
        'amount' => $payload['data']['amount'] ?? null,
    ]);
    
    // ... process ...
    
    Log::info('Webhook processed successfully', [
        'order_id' => $order->id,
        'result' => $result,
    ]);
}
```

---

## Medium Priority Issues

### 🟡 MEDIUM #7: Hardcoded Business Logic

**Location:** Lines 111, 180

**Issue:**
```php
$expirationMinutes = 15; // Hardcoded
$description = sprintf('Thanh toan don hang %s', $order->code); // Hardcoded Vietnamese
```

**Fix:**
```php
$expirationMinutes = config('payment.order_expiration_minutes', 15);
$description = __('payment.order_description', ['code' => $order->code]);
```

---

### 🟡 MEDIUM #8: No Gateway Error Handling

**Location:** Lines 183-188

**Issue:**
```php
$response = $this->gateway->createPaymentLink($payload);
// What if gateway is down? Returns error? Throws exception?
// No try-catch, no error handling
```

**Fix:**
```php
try {
    $response = $this->gateway->createPaymentLink($payload);
    
    if (!$response || !isset($response['checkoutUrl'])) {
        throw new \RuntimeException('Gateway returned invalid response');
    }
    
} catch (\Throwable $e) {
    Log::error('Payment gateway error', [
        'order_id' => $order->id,
        'error' => $e->getMessage(),
    ]);
    
    // Mark payment as failed
    $payment->update([
        'status' => Payment::STATUS_FAILED,
        'failed_at' => now(),
    ]);
    
    throw new \RuntimeException(__('payment.gateway_error'), 0, $e);
}
```

---

## Summary

**Total Issues:** 8 major issues found
- 🔴 Critical: 2 (Production Blocking)
- 🟠 High: 4
- 🟡 Medium: 2

**Security Risk:** EXTREME (free tickets exploit)  
**Business Impact:** SEVERE (potential bankruptcy)  
**Code Quality:** MIXED (good locking, but critical flaws)

---

## Positive Findings

- ✅ Good use of DB transactions
- ✅ Proper pessimistic locking with lockForUpdate()
- ✅ Comprehensive payload building
- ✅ Seat validation logic is thorough
- ✅ Webhook idempotency check exists
- ✅ Good separation of concerns (uses OrderService, OrderFulfillmentService)

---

## Recommendations

### IMMEDIATE (BLOCKING):
1. **DELETE `markPaidFromReturn()` method** or add gateway verification
2. Fix order code race condition with microseconds + larger random
3. Add explicit webhook signature verification in service
4. Add database unique constraint on gateway_order_code

### HIGH PRIORITY:
5. Add idempotency check to prevent duplicate orders
6. Add pessimistic locking to validateSeatHold()
7. Add comprehensive audit logging throughout

### MEDIUM PRIORITY:
8. Move hardcoded values to config
9. Add proper gateway error handling
10. Use localization for all messages

---

## Test Cases Required

```php
// Test: markPaidFromReturn requires gateway verification
public function test_mark_paid_from_return_verifies_with_gateway()
{
    $order = Order::factory()->create(['payment_status' => 'pending']);
    
    // Mock gateway to return "not paid"
    $this->mockGateway(['status' => 'PENDING']);
    
    $this->expectException(\RuntimeException::class);
    $service->markPaidFromReturn($order);
}

// Test: Order code generation is unique under concurrent load
public function test_order_code_generation_handles_concurrency()
{
    $codes = [];
    
    // Simulate 100 concurrent requests
    for ($i = 0; $i < 100; $i++) {
        $code = $service->generateOrderCode();
        $this->assertNotContains($code, $codes);
        $codes[] = $code;
    }
}

// Test: Idempotency prevents duplicate orders
public function test_duplicate_payment_initiation_returns_existing_order()
{
    $data = ['showtime_id' => 1, 'seat_ids' => [1,2,3]];
    
    $result1 = $service->initiate($data, $user);
    $result2 = $service->initiate($data, $user); // Duplicate
    
    $this->assertEquals($result1['order_id'], $result2['order_id']);
}

// Test: Webhook requires signature
public function test_webhook_rejects_invalid_signature()
{
    $this->expectException(\UnauthorizedHttpException::class);
    
    $request = Request::create('/webhook', 'POST', [], [], [], [], json_encode([
        'code' => '00',
        'data' => ['orderCode' => 123],
    ]));
    
    $service->handleWebhook($request->all(), $request);
}
```

---

## Conclusion

**This service BLOCKS production deployment due to the `markPaidFromReturn()` vulnerability.**

While the service has good architectural patterns (transactions, locking, service separation), the critical payment fraud vulnerability makes it unsuitable for production.

**Required Actions:**
1. Remove or completely redesign `markPaidFromReturn()`
2. Fix race conditions in order code generation
3. Add idempotency and comprehensive logging
4. Add security testing for all payment flows

**Estimated Fix Time:** 2-3 days

**Status:** 🔴 **REJECTED - CRITICAL FIXES REQUIRED**
