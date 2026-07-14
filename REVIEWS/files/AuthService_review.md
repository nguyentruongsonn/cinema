# File Review: AuthService.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Services/AuthService.php  
**Lines:** 418  
**Type:** Service - Authentication & Authorization

---

## File Information

**Path:** `app/Services/AuthService.php`  
**Type:** Service Layer - Core Authentication  
**Lines:** 418  
**Complexity:** High  

**Purpose:**  
Handles all authentication operations including:
- User registration
- Login/Logout
- Google OAuth
- Password reset
- Email verification
- Refresh token management
- Rate limiting

**Dependencies:**
- JWT Authentication (tymon/jwt-auth)
- Laravel Password Reset
- Google OAuth API
- Models: User, Role, RefreshToken, LoginHistory

---

## Overall Score

**Code Quality:** 7.5/10  
**Security:** 7.0/10  
**Performance:** 8.0/10  
**Maintainability:** 7.5/10  
**Laravel Best Practice:** 8.0/10  

**Overall Score:** 7.6/10

**Decision:** ✅ **APPROVE WITH COMMENTS**

---

## Strengths

1. ✅ **Refresh Token Rotation** - Excellent security practice (Lines 305-336)
2. ✅ **Rate Limiting** - Prevents brute force attacks (Lines 21-22, 372-381)
3. ✅ **Login History Tracking** - Comprehensive audit trail (Lines 101-109, 157-165)
4. ✅ **Timing-Safe Comparison** - Uses `hash_equals()` for email verification (Line 266)
5. ✅ **Google OAuth Validation** - Proper client_id and email_verified checks (Lines 395-401)
6. ✅ **Password Reset Token Revocation** - Revokes all refresh tokens (Lines 211, 238)
7. ✅ **Session Token Tracking** - Links JWT to login history (Lines 97, 155, 288-293)
8. ✅ **Proper Hashing** - Uses Hash::make() consistently (Lines 31, 142, 209, 233)

---

## Issues Found

### Issue #1: No Email Verification Required on Registration

**Severity:** 🟡 MEDIUM  
**Category:** Security  
**Location:** Lines 26-33

**Evidence:**
```php
$user = User::create([
    'name' => $data['name'],
    'email' => $data['email'],
    'username' => $data['username'] ?? null,
    'phone' => $data['phone'] ?? null,
    'password' => Hash::make($data['password']),
    'status' => 1, // ← Active immediately
]);
```

**Problem:**
User accounts are created with `status => 1` (active) and immediately receive tokens without email verification. This allows:
- Spam account creation
- Email spoofing
- Resource abuse

**Impact:**
- Attackers can register with fake/others' emails
- No verification that user owns the email
- Potential for spam/abuse

**Recommended Fix:**
```php
$user = User::create([
    'name' => $data['name'],
    'email' => $data['email'],
    'username' => $data['username'] ?? null,
    'phone' => $data['phone'] ?? null,
    'password' => Hash::make($data['password']),
    'status' => 0, // ← Inactive until verified
]);

// Send verification email immediately
$user->sendEmailVerificationNotification();

// Only return tokens after verification
// Or return tokens but limit functionality until verified
```

**Alternative Approach:**
Allow registration but restrict account capabilities until email verified:
```php
'status' => 1,
'email_verified_at' => null, // ← Check this in middleware
```

**Test Required:**
```php
public function test_unverified_users_cannot_access_protected_resources()
{
    $user = User::factory()->unverified()->create();
    
    $this->actingAs($user)
        ->get('/api/protected-resource')
        ->assertStatus(403);
}
```

---

### Issue #2: updateProfile Allows Unrestricted Field Updates

**Severity:** 🟠 HIGH  
**Category:** Security - Mass Assignment Risk  
**Location:** Lines 189-196

**Evidence:**
```php
public function updateProfile(array $data): User
{
    $user = auth()->user();
    $user->update($data); // ← No field filtering!
    
    Log::info('User profile updated', ['user_id' => $user->id]);
    
    return $user->fresh()->load('role.permissions');
}
```

**Problem:**
Accepts `$data` array without validation or filtering. If controller doesn't properly validate, users could modify:
- `role_id` (privilege escalation)
- `status` (activate banned account)
- `email` (without verification)
- `balance` (if exists)
- Any other sensitive field

**Attack Scenario:**
```php
// Attacker sends:
POST /api/profile
{
  "name": "John",
  "role_id": 1,  // ← Admin role!
  "status": 1,
  "balance": 999999
}
```

**Impact:**
- Privilege escalation to admin
- Bypass account suspension
- Modify sensitive fields

**Root Cause:**
Trusting input without explicit field whitelisting.

**Recommended Fix:**
```php
public function updateProfile(array $data): User
{
    $user = auth()->user();
    
    // Whitelist allowed fields
    $allowed = ['name', 'phone', 'avatar_url', 'bio'];
    $filtered = array_intersect_key($data, array_flip($allowed));
    
    $user->update($filtered);
    
    Log::info('User profile updated', [
        'user_id' => $user->id,
        'fields' => array_keys($filtered)
    ]);
    
    return $user->fresh()->load('role.permissions');
}
```

**Better Approach:**
Use Form Request with validated fields only:
```php
public function updateProfile(UpdateProfileRequest $request): User
{
    $user = auth()->user();
    $user->update($request->validated()); // ← Already filtered
    
    Log::info('User profile updated', ['user_id' => $user->id]);
    
    return $user->fresh()->load('role.permissions');
}
```

**Test Required:**
```php
public function test_users_cannot_modify_role_via_profile_update()
{
    $user = User::factory()->create(['role_id' => 2]); // Normal user
    
    $this->actingAs($user)
        ->put('/api/profile', [
            'name' => 'New Name',
            'role_id' => 1 // Try to become admin
        ])
        ->assertStatus(200);
    
    $user->refresh();
    $this->assertEquals(2, $user->role_id); // Still normal user
}
```

---

### Issue #3: Google OAuth Race Condition in firstOrCreate

**Severity:** 🟡 MEDIUM  
**Category:** Concurrency  
**Location:** Lines 135-145

**Evidence:**
```php
$user = User::firstOrCreate(
    ['email' => $email],
    [
        'name' => $googleUser['name'] ?? Str::before($email, '@'),
        'username' => $this->uniqueUsername(Str::slug(Str::before($email, '@'), '_')),
        'avatar_url' => $googleUser['picture'] ?? null,
        'email_verified_at' => now(),
        'password' => Hash::make(Str::random(32)),
        'status' => 1,
    ]
);
```

**Problem:**
`firstOrCreate` is not atomic. In concurrent requests:
1. Thread A checks: email doesn't exist
2. Thread B checks: email doesn't exist
3. Thread A creates user
4. Thread B tries to create → DUPLICATE KEY ERROR

**Impact:**
- 500 error for second user
- Poor user experience
- Potential race condition in production

**Recommended Fix:**
```php
try {
    $user = User::firstOrCreate(
        ['email' => $email],
        [
            'name' => $googleUser['name'] ?? Str::before($email, '@'),
            'username' => $this->uniqueUsername(Str::slug(Str::before($email, '@'), '_')),
            'avatar_url' => $googleUser['picture'] ?? null,
            'email_verified_at' => now(),
            'password' => Hash::make(Str::random(32)),
            'status' => 1,
        ]
    );
} catch (\Illuminate\Database\QueryException $e) {
    if ($e->getCode() === '23000') { // Duplicate key
        // Retry once
        $user = User::where('email', $email)->firstOrFail();
    } else {
        throw $e;
    }
}
```

**Better Approach:**
Use database transaction with lock:
```php
$user = DB::transaction(function () use ($email, $googleUser) {
    return User::lockForUpdate()
        ->where('email', $email)
        ->first() ?? User::create([...]);
});
```

---

### Issue #4: uniqueUsername Has N+1 Query Pattern

**Severity:** 🔵 LOW  
**Category:** Performance  
**Location:** Lines 406-417

**Evidence:**
```php
private function uniqueUsername(string $base): string
{
    $base = $base ?: 'user';
    $username = $base;
    $counter = 1;
    
    while (User::where('username', $username)->exists()) { // ← Query in loop
        $username = $base . '_' . $counter++;
    }
    
    return $username;
}
```

**Problem:**
Each iteration runs a separate database query. If `user` already has `user`, `user_1`, `user_2`... `user_99`, this runs 100 queries.

**Impact:**
- Performance degradation with many users
- Slow registration/OAuth
- Database load

**Recommended Fix:**
```php
private function uniqueUsername(string $base): string
{
    $base = $base ?: 'user';
    
    // Get highest counter in one query
    $lastUsername = User::where('username', 'LIKE', $base . '%')
        ->orderByRaw('LENGTH(username) DESC, username DESC')
        ->value('username');
    
    if (!$lastUsername) {
        return $base;
    }
    
    // Extract counter from last username
    if (preg_match('/_(\d+)$/', $lastUsername, $matches)) {
        $counter = (int) $matches[1] + 1;
    } else {
        $counter = 1;
    }
    
    return $base . '_' . $counter;
}
```

**Alternative:**
```php
private function uniqueUsername(string $base): string
{
    $base = $base ?: 'user';
    
    // Add random suffix instead of counter
    do {
        $username = $base . '_' . Str::random(6);
    } while (User::where('username', $username)->exists());
    
    return $username;
}
```

---

### Issue #5: No Device/Token Limit Per User

**Severity:** 🟡 MEDIUM  
**Category:** Security - Resource Management  
**Location:** Lines 38-43, 111-116

**Evidence:**
```php
$refreshTokenData = RefreshToken::generate(
    $user->id,
    $data['device_name'] ?? null,
    $ipAddress,
    $userAgent
);
// No check for existing token count
```

**Problem:**
Users can create unlimited refresh tokens by logging in repeatedly. This allows:
- Token hoarding
- Resource abuse
- Difficulty revoking all sessions

**Impact:**
- Database bloat with old tokens
- Memory/storage consumption
- Security risk if tokens leak

**Recommended Fix:**
```php
public function login(array $credentials, string $ipAddress, ?string $userAgent = null): ?array
{
    // ... existing login logic ...
    
    // Limit tokens per user
    $tokenCount = RefreshToken::where('user_id', $user->id)
        ->where('revoked', false)
        ->count();
    
    if ($tokenCount >= config('auth.max_devices_per_user', 5)) {
        // Revoke oldest token
        RefreshToken::where('user_id', $user->id)
            ->where('revoked', false)
            ->oldest('created_at')
            ->first()
            ?->revoke();
    }
    
    $refreshTokenData = RefreshToken::generate(...);
    // ...
}
```

---

### Issue #6: Rate Limiting Can Be Bypassed With Multiple IPs

**Severity:** 🟡 MEDIUM  
**Category:** Security - Rate Limiting  
**Location:** Lines 367-370

**Evidence:**
```php
private function loginRateKey(string $login, string $ipAddress): string
{
    return 'login_attempts:' . sha1(Str::lower($login) . '|' . $ipAddress);
}
```

**Problem:**
Rate limit key combines login + IP. Attacker can bypass by:
- Using VPN/proxies (change IP)
- Using Tor
- Distributed attack

**Impact:**
- Brute force still possible with IP rotation
- Account enumeration possible

**Recommended Fix:**
Add account-level rate limiting in addition to IP-based:
```php
public function login(array $credentials, string $ipAddress, ?string $userAgent = null): ?array
{
    $login = (string) ($credentials['login'] ?? $credentials['email'] ?? '');
    
    // Check both IP-based AND account-based rate limits
    $ipRateKey = $this->loginRateKey($login, $ipAddress);
    $accountRateKey = 'login_attempts_account:' . sha1(Str::lower($login));
    
    if ($this->isRateLimited($ipRateKey) || $this->isRateLimited($accountRateKey)) {
        throw new RuntimeException('Quá nhiều lần đăng nhập thất bại. Vui lòng thử lại sau.');
    }
    
    // ... login logic ...
    
    if (!$accessToken) {
        $this->hitRateLimit($ipRateKey);
        $this->hitRateLimit($accountRateKey); // ← Also increment account limit
        // ...
    } else {
        Cache::forget($ipRateKey);
        Cache::forget($accountRateKey); // ← Clear both on success
    }
    
    // ...
}
```

---

### Issue #7: Google OAuth Password Not User-Settable

**Severity:** 🔵 LOW  
**Category:** User Experience  
**Location:** Line 142

**Evidence:**
```php
'password' => Hash::make(Str::random(32)), // ← Random, user doesn't know it
```

**Problem:**
Users who register via Google OAuth get a random password they don't know. They can't:
- Login with email/password if Google is down
- Use password-based features
- Recover account if Google account lost

**Impact:**
- Account recovery issues
- Vendor lock-in to Google
- User frustration

**Recommended Fix:**
Allow users to set password after OAuth registration:
```php
// In registration
'password' => null, // ← Allow null for OAuth users

// Add endpoint to set initial password
public function setInitialPassword(string $newPassword): bool
{
    $user = auth()->user();
    
    if ($user->password !== null) {
        return false; // Already has password
    }
    
    $user->update(['password' => Hash::make($newPassword)]);
    
    Log::info('Initial password set for OAuth user', ['user_id' => $user->id]);
    
    return true;
}
```

---

### Issue #8: Sensitive Data in Logs

**Severity:** 🔵 LOW  
**Category:** Security - Information Disclosure  
**Location:** Line 222

**Evidence:**
```php
Log::info('Password reset link sent', ['email' => $email, 'status' => $status]);
```

**Problem:**
Logs email addresses which are PII (Personally Identifiable Information). In log aggregation systems, this could:
- Violate GDPR/privacy laws
- Expose user emails to log viewers
- Leak sensitive information

**Impact:**
- Privacy violation
- Compliance issues
- Information disclosure

**Recommended Fix:**
```php
Log::info('Password reset link sent', [
    'email_hash' => hash('sha256', $email), // ← Hash instead of plain
    'status' => $status
]);
```

Or use user_id if available:
```php
$user = User::where('email', $email)->first();
Log::info('Password reset link sent', [
    'user_id' => $user?->id,
    'status' => $status
]);
```

---

## Recommendations

### Immediate (High Priority)

1. **Fix updateProfile Mass Assignment** - Critical security issue
2. **Add Email Verification Requirement** - Prevent spam accounts
3. **Add Device/Token Limits** - Prevent resource abuse
4. **Enhance Rate Limiting** - Add account-level limits

### Short Term

5. **Handle Google OAuth Race Condition** - Add transaction/retry logic
6. **Optimize uniqueUsername** - Single query instead of loop
7. **Allow OAuth Users to Set Password** - Better UX
8. **Remove PII from Logs** - Privacy compliance

### Long Term

9. **Add 2FA Support** - Enhanced security
10. **Implement Device Management** - Let users view/revoke devices
11. **Add Security Events** - Failed login notifications
12. **Implement Account Lockout** - After X failed attempts

---

## Test Requirements

```php
// Test 1: Mass assignment protection
public function test_profile_update_cannot_modify_role()
{
    $user = User::factory()->create(['role_id' => 2]);
    
    $this->actingAs($user)
        ->put('/api/profile', ['role_id' => 1])
        ->assertStatus(200);
    
    $this->assertEquals(2, $user->fresh()->role_id);
}

// Test 2: Rate limiting
public function test_login_rate_limit_blocks_after_max_attempts()
{
    for ($i = 0; $i < 11; $i++) {
        $response = $this->post('/api/login', [
            'login' => 'test@example.com',
            'password' => 'wrong'
        ]);
    }
    
    $response->assertStatus(429);
}

// Test 3: Email verification required
public function test_unverified_users_cannot_access_resources()
{
    $user = User::factory()->unverified()->create();
    
    $this->actingAs($user)
        ->get('/api/profile')
        ->assertStatus(403);
}

// Test 4: Refresh token rotation
public function test_refresh_token_is_rotated_on_use()
{
    $user = User::factory()->create();
    $token = RefreshToken::generate($user->id, null, '127.0.0.1', null);
    
    $response = $this->post('/api/refresh', [
        'refresh_token' => $token['plain_token']
    ]);
    
    $response->assertStatus(200);
    $this->assertTrue($token['model']->fresh()->revoked);
}

// Test 5: Device limit
public function test_exceeding_device_limit_revokes_oldest()
{
    $user = User::factory()->create();
    
    // Create max devices
    for ($i = 0; $i < 6; $i++) {
        $this->post('/api/login', [
            'login' => $user->email,
            'password' => 'password'
        ]);
    }
    
    $activeTokens = RefreshToken::where('user_id', $user->id)
        ->where('revoked', false)
        ->count();
    
    $this->assertLessThanOrEqual(5, $activeTokens);
}
```

---

## Security Checklist

**Authentication:**
- [x] Password properly hashed
- [x] JWT tokens used correctly
- [x] Rate limiting implemented
- [ ] Email verification required
- [ ] 2FA supported
- [x] Session tracking

**Authorization:**
- [ ] Profile updates properly filtered
- [x] Role assignment controlled
- [x] Token validation strict

**Data Protection:**
- [x] Timing-safe comparisons
- [x] Secure token generation
- [ ] PII not logged in plaintext
- [x] Password reset secure

**Concurrency:**
- [ ] firstOrCreate race condition handled
- [x] Refresh token rotation atomic
- [x] Rate limiting atomic

---

## Summary

AuthService is well-implemented with good security practices like refresh token rotation, rate limiting, and comprehensive logging. Main concerns are:

1. **Mass assignment risk** in updateProfile
2. **No email verification** on registration
3. **Race condition** in Google OAuth
4. **Resource limits** missing for tokens

After addressing these issues, the service will be production-ready with strong security posture.

**Status:** ✅ Approve with required fixes before production

---

*Review completed: 2026-07-14 02:52 AM*
