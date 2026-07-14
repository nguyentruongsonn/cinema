# File Review: VerifyPayOSWebhookSignature.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Middleware/VerifyPayOSWebhookSignature.php  
**Lines:** 67  
**Type:** Security Middleware - Webhook Signature Verification

---

## File Information

**Path:** `app/Http/Middleware/VerifyPayOSWebhookSignature.php`  
**Type:** HTTP Middleware  
**Lines:** 67  
**Complexity:** Low  

**Purpose:**  
Verifies PayOS webhook signatures to prevent forgery attacks:
- Validates x-payos-signature header
- Calculates HMAC-SHA256 of request body
- Uses constant-time comparison to prevent timing attacks
- Critical for payment webhook security

**Security Impact:** 🔴 CRITICAL - Protects payment webhooks from forgery

---

## Overall Score

**Code Quality:** 7.5/10  
**Security:** 6.5/10  
**Performance:** 7.5/10  
**Maintainability:** 8.0/10  
**Laravel Best Practice:** 7.5/10  

**Overall Score:** 7.4/10

**Decision:** ⚠️ **APPROVE WITH IMPROVEMENTS**

---

## Strengths

1. ✅ **Proper HMAC-SHA256** - Strong cryptographic signature
2. ✅ **Constant-Time Comparison** - Uses hash_equals() to prevent timing attacks
3. ✅ **Proper Logging** - Logs suspicious activity without exposing secrets
4. ✅ **Good Documentation** - Clear PHPDoc explaining purpose
5. ✅ **Proper Return Type** - Mixed return type declared

---

## Issues Found

### Issue #1: No Replay Attack Protection

**Severity:** 🟠 HIGH  
**Category:** Security - Replay Attacks  
**Location:** Missing functionality

**Evidence:**
```php
// No timestamp validation
// No nonce checking
$expectedSignature = hash_hmac('sha256', $body, $webhookSecret);
```

**Problem:**
Valid webhooks can be replayed by attackers:

**Attack Scenario:**
```
1. Attacker intercepts a legitimate webhook (e.g., payment_success)
2. Webhook has valid signature (HMAC is correct)
3. Attacker replays this exact webhook multiple times
4. Each replay has valid signature and will be accepted
5. Result: Duplicate payment processing, order duplication
```

**Why Signature Alone Is Not Enough:**
```php
// This webhook can be replayed infinitely:
POST /api/webhooks/payos
x-payos-signature: valid_signature_here

{
    "orderCode": 123,
    "amount": 100000,
    "status": "PAID",
    "transactionDateTime": "2024-01-01 10:00:00"
}

// Signature is valid - webhook accepted!
// But this payment already processed hours ago!
```

**Impact:**
- Duplicate payment processing
- Duplicate orders created
- Money credited multiple times
- Business logic executed repeatedly

**Recommended Fix:**
```php
public function handle(Request $request, Closure $next): mixed
{
    // Extract signature
    $providedSignature = $request->header('x-payos-signature');
    
    if (!$providedSignature) {
        return response()->json([
            'success' => false,
            'message' => 'Missing signature',
        ], 401);
    }
    
    // Validate signature (existing code)
    $webhookSecret = config('services.payos.webhook_secret');
    $body = $request->getContent();
    $expectedSignature = hash_hmac('sha256', $body, $webhookSecret);
    
    if (!hash_equals($expectedSignature, $providedSignature)) {
        Log::warning('Invalid PayOS webhook signature', [
            'ip' => $request->ip(),
        ]);
        return response()->json(['success' => false], 401);
    }
    
    // NEW: Check for replay attacks
    $webhook = json_decode($body, true);
    
    // Check timestamp (webhook should be recent)
    if (isset($webhook['transactionDateTime'])) {
        $timestamp = strtotime($webhook['transactionDateTime']);
        $now = time();
        $maxAge = 300; // 5 minutes
        
        if (abs($now - $timestamp) > $maxAge) {
            Log::warning('PayOS webhook too old', [
                'timestamp' => $webhook['transactionDateTime'],
                'age_seconds' => abs($now - $timestamp),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Webhook expired',
            ], 401);
        }
    }
    
    // Check if webhook already processed (idempotency)
    if (isset($webhook['orderCode'])) {
        $processed = \Cache::remember(
            "payos_webhook:{$webhook['orderCode']}",
            600, // 10 minutes
            function () {
                return false;
            }
        );
        
        if ($processed) {
            Log::info('PayOS webhook already processed', [
                'order_code' => $webhook['orderCode'],
            ]);
            
            // Return success to prevent retry
            return response()->json(['success' => true], 200);
        }
        
        // Mark as processing
        \Cache::put("payos_webhook:{$webhook['orderCode']}", true, 600);
    }
    
    return $next($request);
}
```

---

### Issue #2: No IP Whitelist Validation

**Severity:** 🟡 MEDIUM  
**Category:** Security - Source Validation  
**Location:** Missing functionality

**Evidence:**
```php
// No check for PayOS server IPs
// Anyone can send webhooks if they know the signature
```

**Problem:**
Doesn't validate that webhook comes from PayOS servers. While signature prevents forgery, IP whitelisting adds defense-in-depth.

**Recommended Fix:**
```php
public function handle(Request $request, Closure $next): mixed
{
    // Check IP whitelist (if PayOS provides IPs)
    $allowedIps = config('services.payos.webhook_ips', []);
    
    if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps)) {
        Log::warning('PayOS webhook from unauthorized IP', [
            'ip' => $request->ip(),
            'allowed_ips' => $allowedIps,
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized source',
        ], 403);
    }
    
    // Continue with signature validation...
}

// In config/services.php:
'payos' => [
    'webhook_secret' => env('PAYOS_WEBHOOK_SECRET'),
    'webhook_ips' => explode(',', env('PAYOS_WEBHOOK_IPS', '')),
],
```

---

### Issue #3: Information Disclosure in Error Messages

**Severity:** 🟡 MEDIUM  
**Category:** Security - Information Leakage  
**Location:** Lines 38-41

**Evidence:**
```php
return response()->json([
    'success' => false,
    'message' => 'Webhook not configured',  // ← Reveals internal state
], 500);
```

**Problem:**
Error message "Webhook not configured" reveals:
- System is misconfigured
- Webhook secret is missing
- Internal application state

Attackers can use this to:
- Probe for misconfigurations
- Identify attack surface
- Plan targeted attacks

**Recommended Fix:**
```php
if (!$webhookSecret) {
    Log::error('PayOS webhook secret not configured', [
        'error' => 'PAYOS_WEBHOOK_SECRET not set',
    ]);
    
    // Return generic error - don't reveal configuration state
    return response()->json([
        'success' => false,
        'message' => 'Service temporarily unavailable',
    ], 503);
}
```

---

### Issue #4: No Rate Limiting

**Severity:** 🟡 MEDIUM  
**Category:** Security - DOS Protection  
**Location:** Missing functionality

**Evidence:**
```php
// No rate limiting on webhook endpoint
```

**Problem:**
Unlimited webhook attempts allow:
- DOS attacks with invalid signatures
- Resource exhaustion
- Log flooding
- System slowdown

**Recommended Fix:**
```php
use Illuminate\Support\Facades\RateLimiter;

public function handle(Request $request, Closure $next): mixed
{
    // Rate limit by IP
    $key = 'payos-webhook:' . $request->ip();
    
    if (RateLimiter::tooManyAttempts($key, 30)) {
        Log::warning('PayOS webhook rate limit exceeded', [
            'ip' => $request->ip(),
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Too many requests',
        ], 429);
    }
    
    RateLimiter::hit($key, 60); // 30 attempts per minute
    
    // Continue with validation...
}
```

**Or use Laravel's throttle middleware:**
```php
// In routes/api.php
Route::post('/webhooks/payos', [WebhookController::class, 'handle'])
    ->middleware(['throttle:30,1', VerifyPayOSWebhookSignature::class]);
```

---

### Issue #5: Signature Prefix Logging Could Be More Secure

**Severity:** 🔵 LOW  
**Category:** Security - Logging  
**Location:** Lines 53-54

**Evidence:**
```php
'provided' => substr($providedSignature, 0, 10) . '...',
'expected' => substr($expectedSignature, 0, 10) . '...',
```

**Problem:**
Logs first 10 characters of signatures. While better than logging full signatures, even partial signatures could be useful to attackers.

**Recommended Fix:**
```php
// Option 1: Log hash of signature instead
'provided_hash' => hash('sha256', $providedSignature),
'expected_hash' => hash('sha256', $expectedSignature),

// Option 2: Don't log signatures at all
Log::warning('Invalid PayOS webhook signature', [
    'ip' => $request->ip(),
    'matches' => false,
]);
```

---

## Recommendations

### IMMEDIATE (HIGH PRIORITY)

1. **Add Replay Attack Protection** - Timestamp validation + idempotency check
2. **Implement Idempotency Key Tracking** - Prevent duplicate processing
3. **Add Rate Limiting** - Protect against DOS attacks
4. **Fix Information Disclosure** - Generic error messages

### SHORT TERM

5. **Add IP Whitelist** - Validate source if PayOS provides IPs
6. **Improve Logging** - Don't log signature prefixes
7. **Add Webhook Processing Status** - Track processed webhooks in database
8. **Add Retry Logic** - Handle transient failures gracefully

### LONG TERM

9. **Add Webhook Monitoring** - Alert on suspicious patterns
10. **Implement Webhook Queue** - Process webhooks asynchronously
11. **Add Webhook Testing** - Test signature validation
12. **Document Webhook Flow** - Clear documentation for developers

---

## Complete Improved Version

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Verifies PayOS webhook signature to prevent forgery and replay attacks.
 */
class VerifyPayOSWebhookSignature
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Rate limiting
        if (!$this->checkRateLimit($request)) {
            return response()->json(['success' => false], 429);
        }
        
        // IP whitelist (optional)
        if (!$this->checkIpWhitelist($request)) {
            return response()->json(['success' => false], 403);
        }
        
        // Signature validation
        $providedSignature = $request->header('x-payos-signature');
        
        if (!$providedSignature) {
            return response()->json(['success' => false], 401);
        }
        
        $webhookSecret = config('services.payos.webhook_secret');
        
        if (!$webhookSecret) {
            Log::error('PayOS webhook secret not configured');
            return response()->json(['success' => false], 503);
        }
        
        $body = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $body, $webhookSecret);
        
        if (!hash_equals($expectedSignature, $providedSignature)) {
            Log::warning('Invalid PayOS webhook signature', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['success' => false], 401);
        }
        
        // Replay protection
        if (!$this->checkReplay($request, $body)) {
            return response()->json(['success' => true], 200);
        }
        
        return $next($request);
    }
    
    private function checkRateLimit(Request $request): bool
    {
        $key = 'payos-webhook:' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 30)) {
            Log::warning('PayOS webhook rate limit exceeded', [
                'ip' => $request->ip(),
            ]);
            return false;
        }
        
        RateLimiter::hit($key, 60);
        return true;
    }
    
    private function checkIpWhitelist(Request $request): bool
    {
        $allowedIps = config('services.payos.webhook_ips', []);
        
        if (empty($allowedIps)) {
            return true;
        }
        
        if (!in_array($request->ip(), $allowedIps)) {
            Log::warning('PayOS webhook from unauthorized IP', [
                'ip' => $request->ip(),
            ]);
            return false;
        }
        
        return true;
    }
    
    private function checkReplay(Request $request, string $body): bool
    {
        $webhook = json_decode($body, true);
        
        if (!isset($webhook['orderCode'])) {
            return true;
        }
        
        // Check if already processed
        $cacheKey = "payos_webhook:{$webhook['orderCode']}";
        
        if (Cache::has($cacheKey)) {
            Log::info('PayOS webhook already processed', [
                'order_code' => $webhook['orderCode'],
            ]);
            return false; // Already processed
        }
        
        // Mark as processing
        Cache::put($cacheKey, true, 600); // 10 minutes
        
        return true;
    }
}
```

---

## Summary

VerifyPayOSWebhookSignature middleware implements proper signature verification but lacks replay attack protection.

**Strengths:**
- Proper HMAC-SHA256 signature verification
- Constant-time comparison (prevents timing attacks)
- Good logging practices
- Clean code structure

**Main Gaps:**
1. **No replay attack protection** - Valid webhooks can be replayed
2. **No idempotency checking** - Duplicate processing possible
3. **No rate limiting** - Vulnerable to DOS
4. **Information disclosure** - Error messages reveal config state

**Critical Missing Feature:**
The biggest security gap is **lack of replay attack protection**. Even though signature verification works correctly, an attacker who intercepts a valid webhook can replay it multiple times, causing duplicate payment processing.

After implementing replay protection with timestamp validation and idempotency tracking, this middleware will provide robust webhook security.

**Status:** ⚠️ Important improvements needed (replay protection required)

---

*Review completed: 2026-07-14 03:23 AM*  
*File #19/137 - Phase 2: Security Layer (3/12 complete)*
