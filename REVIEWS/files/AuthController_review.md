# File Review: AuthController.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Controllers/AuthController.php  
**Lines:** 340  
**Type:** Controller - Authentication Endpoints

---

## File Information

**Path:** `app/Http/Controllers/AuthController.php`  
**Type:** Controller - API Endpoints  
**Lines:** 340  
**Complexity:** Medium  

**Purpose:**  
Handles all authentication HTTP endpoints:
- Registration & login
- Google OAuth
- Password management
- Email verification
- Token refresh
- Profile management

**Dependencies:**
- AuthService (business logic)
- Multiple FormRequests
- ApiResponse trait

---

## Overall Score

**Code Quality:** 7.0/10  
**Security:** 6.5/10  
**Performance:** 8.0/10  
**Maintainability:** 7.5/10  
**Laravel Best Practice:** 8.0/10  

**Overall Score:** 7.4/10

**Decision:** ⚠️ **APPROVE WITH REQUIRED CHANGES**

---

## Strengths

1. ✅ **FormRequest Validation** - Proper use of FormRequests for most endpoints
2. ✅ **Dependency Injection** - Clean constructor injection with readonly
3. ✅ **Cookie Security** - httpOnly cookies for tokens (Lines 324, 335)
4. ✅ **User Enumeration Prevention** - Password reset doesn't leak user existence (Line 245)
5. ✅ **Session Management** - Proper logout with session invalidation (Lines 131-134)
6. ✅ **Thin Controller** - Business logic delegated to AuthService
7. ✅ **Consistent Response Format** - Uses ApiResponse trait

---

## Issues Found

### Issue #1: Information Disclosure in Exception Handlers

**Severity:** 🟠 HIGH  
**Category:** Security - Information Disclosure  
**Location:** Lines 47, 74, 101, 116, 143, 172, 187, 202, 223, 263, 281, 302

**Evidence:**
```php
} catch (\Throwable $e) {
    return $this->errorResponse('Registration failed: ' . $e->getMessage(), 500);
}
```

**Problem:**
ALL exception handlers expose internal error messages directly to users. This pattern repeats in every single method. Could leak:
- Database errors ("SQLSTATE[...]")
- File system paths
- Configuration issues
- Stack traces
- Internal service details
- Third-party API errors

**Attack Scenario:**
```php
// Database connection fails
// Error: "Registration failed: SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost'"
// ← Attacker learns database user is 'root' and tries default passwords
```

**Impact:**
- Sensitive information disclosure
- Aids reconnaissance
- Exposes infrastructure details
- Violates security best practices

**Recommended Fix:**
```php
} catch (\Throwable $e) {
    Log::error('Registration failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'ip' => $request->ip()
    ]);
    
    return $this->errorResponse(
        'Registration failed. Please try again later.',
        500
    );
}
```

**Better Approach - Differentiate Expected vs Unexpected:**
```php
} catch (\DomainException $e) {
    // Expected business rule violation - safe to show
    return $this->errorResponse($e->getMessage(), 400);
} catch (\Throwable $e) {
    // Unexpected infrastructure error - log and hide
    Log::error('Registration failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    return $this->errorResponse(
        'An unexpected error occurred. Please try again.',
        500
    );
}
```

**Apply to ALL 12 exception handlers in this file.**

---

### Issue #2: Cookie Security Depends on Configuration

**Severity:** 🟡 MEDIUM  
**Category:** Security - Configuration Dependency  
**Location:** Lines 323, 334

**Evidence:**
```php
secure: config('session.secure'),
```

**Problem:**
Cookie security depends on config value. If misconfigured:
- `secure: false` → Tokens sent over HTTP
- Tokens interceptable on insecure networks
- Man-in-the-middle attacks possible

**Impact:**
- Token theft via network sniffing
- Session hijacking
- Credentials compromise

**Recommended Fix:**
```php
// In setAuthCookies method
$isProduction = app()->environment('production');
$secure = $isProduction ? true : config('session.secure');

if ($isProduction && !$secure) {
    throw new \RuntimeException('Secure cookies required in production');
}

return $response
    ->withCookie(cookie(
        name: 'access_token',
        value: $accessToken,
        minutes: (int) ceil($accessExpiresIn / 60),
        path: '/',
        domain: config('session.domain'),
        secure: $secure, // ← Force true in production
        httpOnly: true,
        raw: false,
        sameSite: config('session.same_site', 'lax')
    ))
    // ...
```

**Better Approach - AppServiceProvider Boot Check:**
```php
// In AppServiceProvider::boot()
public function boot(): void
{
    if (app()->environment('production')) {
        if (!config('session.secure')) {
            throw new \RuntimeException(
                'SESSION_SECURE must be true in production'
            );
        }
        
        if (config('session.same_site') !== 'strict') {
            Log::warning('SameSite should be "strict" in production');
        }
    }
}
```

---

### Issue #3: Inconsistent Validation Pattern

**Severity:** 🔵 LOW  
**Category:** Code Consistency  
**Location:** Lines 83-85

**Evidence:**
```php
public function googleLogin(\Illuminate\Http\Request $request): JsonResponse
{
    $validated = $request->validate([
        'id_token' => ['required', 'string'],
    ]);
    // ← Inline validation instead of FormRequest
```

**Problem:**
All other methods use FormRequest, but `googleLogin` uses inline validation. This is inconsistent and breaks the pattern.

**Impact:**
- Code inconsistency
- Harder to maintain
- Validation logic scattered

**Recommended Fix:**
```php
// Create GoogleLoginRequest
class GoogleLoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string'],
        ];
    }
}

// In Controller
public function googleLogin(GoogleLoginRequest $request): JsonResponse
{
    try {
        $result = $this->authService->loginWithGoogle(
            $request->validated('id_token'),
            $request->ip(),
            $request->userAgent()
        );
        // ...
```

---

### Issue #4: SameSite Could Be Stricter

**Severity:** 🔵 LOW  
**Category:** Security - CSRF Protection  
**Location:** Lines 326, 337

**Evidence:**
```php
sameSite: config('session.same_site', 'lax')
```

**Problem:**
SameSite 'lax' allows cookies on top-level navigation. For API tokens, 'strict' is more secure:
- 'lax' = cookie sent on GET from external site
- 'strict' = cookie NEVER sent cross-site

**Impact:**
- Reduced CSRF protection
- Potential for certain attack vectors

**Recommended Fix:**
```php
sameSite: config('session.same_site', 'strict') // ← Default to 'strict'
```

Or create separate config:
```php
// config/auth.php
'cookie_same_site' => env('AUTH_COOKIE_SAME_SITE', 'strict'),

// In controller
sameSite: config('auth.cookie_same_site', 'strict')
```

**Note:** This might break some legitimate cross-site flows. Test thoroughly.

---

### Issue #5: No Explicit Rate Limiting in Code

**Severity:** 🔵 LOW  
**Category:** Security - Rate Limiting  
**Location:** All public methods

**Evidence:**
```php
public function login(LoginRequest $request): JsonResponse
{
    // No visible rate limiting
```

**Problem:**
Controller doesn't show rate limiting. While it may be applied via middleware in routes, it's not explicit in code. If middleware is removed, protection is lost.

**Impact:**
- Brute force vulnerability if middleware removed
- No code-level visibility of protection
- Maintenance risk

**Recommended Fix:**
Add RateLimiter attributes (Laravel 11):
```php
use Illuminate\Routing\Middleware\ThrottleRequests;

#[ThrottleRequests('login')]
public function login(LoginRequest $request): JsonResponse
{
    // ...
}

#[ThrottleRequests('auth')]
public function register(RegisterRequest $request): JsonResponse
{
    // ...
}
```

Or verify in routes documentation.

---

## Recommendations

### Immediate (High Priority)

1. **Fix Information Disclosure** - Never expose `$e->getMessage()` directly
2. **Enforce Secure Cookies in Production** - Validate config at boot
3. **Add Logging for All Errors** - Internal logging for debugging
4. **Create GoogleLoginRequest** - Consistency with other endpoints

### Short Term

5. **Strengthen SameSite Policy** - Use 'strict' for better CSRF protection
6. **Add Rate Limit Attributes** - Make rate limiting explicit
7. **Document Cookie Strategy** - Why tokens in cookies vs headers
8. **Add Request ID Logging** - Trace requests across layers

### Long Term

9. **Implement Custom Exceptions** - Domain exceptions for expected errors
10. **Add Response DTOs** - Type-safe response structures
11. **Add API Versioning** - Prepare for breaking changes
12. **Add Request/Response Logging** - Full audit trail

---

## Test Requirements

```php
// Test 1: Information disclosure
public function test_errors_do_not_expose_internal_messages()
{
    // Mock AuthService to throw database exception
    $this->mock(AuthService::class)
        ->shouldReceive('login')
        ->andThrow(new \PDOException('SQLSTATE[HY000] [1045] Access denied'));
    
    $response = $this->postJson('/api/auth/login', [
        'login' => 'test@example.com',
        'password' => 'password'
    ]);
    
    // Should NOT contain database details
    $response->assertStatus(500);
    $this->assertStringNotContainsString('SQLSTATE', $response->content());
    $this->assertStringNotContainsString('PDO', $response->content());
}

// Test 2: Secure cookies in production
public function test_cookies_are_secure_in_production()
{
    app()->detectEnvironment(fn() => 'production');
    
    $response = $this->postJson('/api/auth/login', [
        'login' => 'user@example.com',
        'password' => 'password'
    ]);
    
    $cookies = $response->headers->getCookies();
    $accessTokenCookie = collect($cookies)->firstWhere('name', 'access_token');
    
    $this->assertTrue($accessTokenCookie->isSecure());
    $this->assertTrue($accessTokenCookie->isHttpOnly());
}

// Test 3: User enumeration prevention
public function test_password_reset_does_not_reveal_user_existence()
{
    // Non-existent user
    $response1 = $this->postJson('/api/auth/forgot-password', [
        'email' => 'nonexistent@example.com'
    ]);
    
    // Existing user
    $response2 = $this->postJson('/api/auth/forgot-password', [
        'email' => 'real@example.com'
    ]);
    
    // Both should return same success message
    $response1->assertStatus(200);
    $response2->assertStatus(200);
    $this->assertEquals(
        $response1->json('message'),
        $response2->json('message')
    );
}

// Test 4: Rate limiting
public function test_login_is_rate_limited()
{
    for ($i = 0; $i < 11; $i++) {
        $response = $this->postJson('/api/auth/login', [
            'login' => 'test@example.com',
            'password' => 'wrong'
        ]);
    }
    
    $response->assertStatus(429); // Too Many Requests
}
```

---

## Security Checklist

**Authentication:**
- [x] FormRequest validation used
- [x] Rate limiting (assumed via middleware)
- [ ] No information disclosure in errors
- [x] Proper session management
- [x] Token in httpOnly cookies

**Authorization:**
- [x] Uses middleware for protected routes
- [x] Proper user context from auth()
- [x] No direct user_id manipulation

**Data Protection:**
- [x] httpOnly cookies
- [ ] Secure cookies enforced in production
- [x] CSRF token regeneration on logout
- [ ] SameSite could be stricter

**Error Handling:**
- [ ] Generic error messages to users
- [ ] Detailed logging internally
- [x] User enumeration prevention (password reset)

---

## Specific Praise

**Excellent Security Practice:** Lines 240-246
```php
} catch (\Throwable $e) {
    report($e);
    
    // Do not expose password reset internals or infrastructure failures to clients.
    // This also avoids user enumeration and reset-token leakage in API responses.
    return $this->successResponse(null, 'If the email exists, a password reset link will be sent');
}
```

This is TEXTBOOK password reset security:
- Logs error internally with `report($e)`
- Returns generic success message
- Prevents user enumeration
- Excellent comment explaining the reasoning

**Recommendation:** Apply this same pattern to ALL exception handlers.

---

## Summary

AuthController is a well-structured controller that properly delegates business logic to AuthService and uses FormRequests for validation. Cookie-based token storage is implemented correctly with httpOnly flags.

Main concern is **information disclosure** in exception messages - this is repeated in 12 different exception handlers and needs systematic fix.

The password reset handler (Lines 240-246) demonstrates excellent security awareness with user enumeration prevention and should serve as the template for all other exception handlers.

After fixing information disclosure and enforcing secure cookies in production, this controller will be production-ready.

**Status:** ⚠️ Required changes (information disclosure fix is critical)

---

*Review completed: 2026-07-14 03:02 AM*
