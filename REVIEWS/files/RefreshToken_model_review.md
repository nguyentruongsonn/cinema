# File Review: RefreshToken.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Models/RefreshToken.php  
**Lines:** 134  
**Type:** Eloquent Model - Refresh Token Persistence

---

## File Information

**Path:** `app/Models/RefreshToken.php`  
**Type:** Eloquent Model  
**Lines:** 134  
**Complexity:** Medium  

**Purpose:**  
Represents persisted refresh tokens:
- Generates refresh tokens
- Stores hashed token values
- Looks up active tokens by plaintext token input
- Tracks device/IP/user-agent metadata
- Supports token revocation and cleanup

**Security Impact:** 🔴 CRITICAL - Directly affects authentication persistence and token replay resistance

---

## Overall Score

**Code Quality:** 6.4/10  
**Security:** 5.4/10  
**Performance:** 6.4/10  
**Maintainability:** 6.2/10  
**Laravel Best Practice:** 6.0/10  

**Overall Score:** 6.1/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

1. ✅ **Refresh Tokens Are Not Stored in Plaintext** - Uses SHA-256 hashing before persistence
2. ✅ **Expiration Is Enforced During Lookup** - `findByPlainToken()` excludes expired tokens
3. ✅ **Revocation Is Supported** - `revoked_at` enables token invalidation
4. ✅ **Token Metadata Captured** - Stores device, IP, and user-agent information
5. ✅ **Cleanup Method Exists** - Expired/revoked token cleanup is considered
6. ✅ **Datetime Casts Present** - Token timestamps are cast to Carbon instances

---

## Issues Found

### Issue #1: Token Field Is Mass Assignable

**Severity:** 🔴 CRITICAL  
**Category:** Security - Token Integrity / Mass Assignment  
**Location:** Lines 14-23

**Evidence:**
```php
protected $fillable = [
    'user_id',
    'token',
    'device_name',
    'ip_address',
    'user_agent',
    'expires_at',
    'revoked_at',
    'last_used_at',
];
```

**Problem:**
The `token` field is mass assignable. This is authentication material and should not be broadly assignable through generic model creation/update paths.

The class has a secure generation method at lines 42-68, but `$fillable` allows bypassing that method and writing arbitrary token hashes through `RefreshToken::create()` or `$refreshToken->update()`.

**Why this matters:**
If any controller/service passes untrusted data into this model, an attacker may be able to inject a chosen token hash or manipulate token state. Refresh tokens are equivalent to long-lived credentials.

**How to fix:**
Do not make `token`, `user_id`, `revoked_at`, or `last_used_at` broadly fillable. Use explicit `forceFill()` inside trusted factory methods.

**Example:**
```php
protected $fillable = [
    'device_name',
    'ip_address',
    'user_agent',
];

protected $guarded = [
    'id',
    'user_id',
    'token',
    'expires_at',
    'revoked_at',
    'last_used_at',
];
```

Or:

```php
protected $guarded = ['id'];
```

with strict service-layer controls and no direct request mass assignment.

---

### Issue #2: Revocation and Usage Metadata Are Mass Assignable

**Severity:** 🟠 HIGH  
**Category:** Security / Audit Integrity  
**Location:** Lines 14-23

**Evidence:**
```php
'revoked_at',
'last_used_at',
```

**Problem:**
`revoked_at` and `last_used_at` are security/audit fields but are mass assignable.

**Why this matters:**
Refresh token validity depends on `revoked_at`. Allowing broad assignment means a code path using untrusted request data could clear revocation, forge usage timestamps, or invalidate active sessions unintentionally.

**How to fix:**
Only mutate these fields through explicit methods:

```php
public function revoke(): void
{
    $this->forceFill(['revoked_at' => now()])->save();
}

public function markAsUsed(): void
{
    $this->forceFill(['last_used_at' => now()])->save();
}
```

Then remove them from `$fillable`.

---

### Issue #3: No Relationship Return Type

**Severity:** 🔵 LOW  
**Category:** Laravel Best Practice / Static Analysis  
**Location:** Lines 34-37

**Evidence:**
```php
public function user()
{
    return $this->belongsTo(User::class);
}
```

**Problem:**
The relationship does not declare a return type. Other models in this project generally type relationships. This reduces static analysis quality and consistency.

**How to fix:**
```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;

public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}
```

---

### Issue #4: Imported `Hash` Facade Is Unused

**Severity:** 🔵 LOW  
**Category:** Clean Code / Maintainability  
**Location:** Line 7

**Evidence:**
```php
use Illuminate\Support\Facades\Hash;
```

**Problem:**
`Hash` is imported but never used. The class uses native `hash('sha256', ...)` instead.

**Why this matters:**
Unused imports are small but signal weak hygiene and increase noise during maintenance.

**How to fix:**
Remove the unused import.

```php
use Illuminate\Support\Str;
```

---

### Issue #5: Token Lookup Is Not Constant-Time Compared at Application Boundary

**Severity:** 🟡 MEDIUM  
**Category:** Security - Token Verification  
**Location:** Lines 73-82

**Evidence:**
```php
$hashedToken = hash('sha256', $plainToken);

return self::query()
    ->where('token', '=', $hashedToken)
    ->whereNull('revoked_at')
    ->where('expires_at', '>', now())
    ->first();
```

**Problem:**
The code hashes the submitted token and performs a direct database equality lookup. This is common for opaque tokens, but it means the database lookup itself is the token verifier and there is no `hash_equals()` verification after retrieval.

Because tokens are 64 random characters, practical timing risk is low. However, for credential verification code, a defense-in-depth pattern is to compare the stored hash using `hash_equals()` after retrieving a candidate when lookup design allows it.

**Why this matters:**
Refresh token validation is security-critical. Credential verification paths should avoid avoidable timing side channels and should be explicitly designed.

**How to fix:**
If keeping deterministic SHA-256 lookup for indexing, document this design and enforce a unique index on `token`. If switching to stronger password-style hashing, lookup must use a selector/token split.

Recommended production design:
- random public selector stored/indexed
- secret verifier stored hashed
- lookup by selector
- verify secret using `hash_equals()` or password hash verification

Example concept:
```php
// token returned to client: selector.secret
$record = self::where('selector', $selector)->first();

if (!$record || !hash_equals($record->token_hash, hash('sha256', $secret))) {
    return null;
}
```

---

### Issue #6: No Rotation / Replay Detection in Model

**Severity:** 🟠 HIGH  
**Category:** Authentication / Token Replay  
**Location:** Lines 73-107

**Evidence:**
```php
public static function findByPlainToken(string $plainToken): ?self
{
    $hashedToken = hash('sha256', $plainToken);

    return self::query()
        ->where('token', '=', $hashedToken)
        ->whereNull('revoked_at')
        ->where('expires_at', '>', now())
        ->first();
}

public function markAsUsed(): void
{
    $this->update(['last_used_at' => now()]);
}
```

**Problem:**
The model supports lookup and usage tracking but does not enforce refresh token rotation or replay detection. A stolen refresh token can remain usable until expiration unless the service layer rotates/revokes it.

**Why this matters:**
Refresh tokens are long-lived credentials. Production systems should rotate refresh tokens on every use and detect reuse of already-rotated tokens.

**How to fix:**
Add explicit token rotation semantics in the authentication service:
1. Find token
2. Lock token row
3. Verify still active
4. Revoke old token
5. Issue new token
6. Commit transaction

Example:
```php
DB::transaction(function () use ($plainToken) {
    $token = RefreshToken::where('token', hash('sha256', $plainToken))
        ->lockForUpdate()
        ->firstOrFail();

    if (!$token->isValid()) {
        throw new AuthenticationException();
    }

    $token->revoke();

    return RefreshToken::generate($token->user_id);
});
```

---

### Issue #7: Token Generation Has No Device/User Token Limit

**Severity:** 🟡 MEDIUM  
**Category:** Security / Abuse Control  
**Location:** Lines 42-68

**Evidence:**
```php
$refreshToken = self::create([
    'user_id' => $userId,
    'token' => $hashedToken,
    'device_name' => $deviceName,
    'ip_address' => $ipAddress,
    'user_agent' => $userAgent,
    'expires_at' => now()->addDays((int) config('auth.refresh_token_ttl', 30)),
]);
```

**Problem:**
Every call to `generate()` creates a new refresh token. There is no visible cap per user or per device.

**Why this matters:**
A compromised account or buggy client can create unlimited refresh token rows, causing:
- database growth
- session management issues
- difficulty revoking suspicious sessions
- increased attack surface

**How to fix:**
Enforce a maximum active refresh token count per user/device in the service layer or generation method.

Example:
```php
self::where('user_id', $userId)
    ->whereNull('revoked_at')
    ->where('expires_at', '>', now())
    ->oldest()
    ->limit(max(0, $activeCount - $maxAllowed))
    ->update(['revoked_at' => now()]);
```

---

### Issue #8: `generate()` Is Not Transactional and Does Not Handle Token Collision

**Severity:** 🟡 MEDIUM  
**Category:** Database Correctness / Reliability  
**Location:** Lines 42-68

**Evidence:**
```php
$plainToken = Str::random(64);
$hashedToken = hash('sha256', $plainToken);

$refreshToken = self::create([
    'user_id' => $userId,
    'token' => $hashedToken,
    ...
]);
```

**Problem:**
There is no collision handling. A collision is extremely unlikely with 64 random characters and SHA-256, but production token code should still rely on a unique database constraint and retry on duplicate key violations.

**Why this matters:**
Security-critical identifiers should have database-enforced uniqueness. Application probability is not a substitute for database integrity.

**How to fix:**
Add a unique index on `token` and retry generation if insertion fails due to duplicate key.

---

### Issue #9: No Scope Methods for Active/Expired/Revoked Tokens

**Severity:** 🔵 LOW  
**Category:** Maintainability / Query Duplication  
**Location:** Lines 73-82 and 112-132

**Evidence:**
```php
->whereNull('revoked_at')
->where('expires_at', '>', now())
```

```php
->where(function ($query) use ($cutoffDate) {
    $query->where('expires_at', '<', $cutoffDate)
        ->orWhere('revoked_at', '<', $cutoffDate);
})
```

**Problem:**
Active/revoked/expired query conditions are repeated inline. This is not yet severe, but it will become error-prone as token queries grow.

**How to fix:**
Add typed scopes.

```php
use Illuminate\Database\Eloquent\Builder;

public function scopeActive(Builder $query): Builder
{
    return $query->whereNull('revoked_at')
        ->where('expires_at', '>', now());
}

public function scopeRevoked(Builder $query): Builder
{
    return $query->whereNotNull('revoked_at');
}
```

Then:
```php
return self::query()
    ->where('token', hash('sha256', $plainToken))
    ->active()
    ->first();
```

---

### Issue #10: Cleanup Query Does Not Explicitly Include Non-Null `revoked_at`

**Severity:** 🔵 LOW  
**Category:** Database Correctness / Readability  
**Location:** Lines 123-132

**Evidence:**
```php
return self::query()
    ->where(function ($query) use ($cutoffDate) {
        $query->where('expires_at', '<', $cutoffDate)
            ->orWhere('revoked_at', '<', $cutoffDate);
    })
    ->delete();
```

**Problem:**
SQL comparison with `NULL` evaluates to unknown, so this works because `revoked_at < cutoffDate` excludes nulls implicitly. However, explicit null checks improve readability and avoid ambiguity.

**How to fix:**
```php
return self::query()
    ->where('expires_at', '<', $cutoffDate)
    ->orWhere(function ($query) use ($cutoffDate) {
        $query->whereNotNull('revoked_at')
            ->where('revoked_at', '<', $cutoffDate);
    })
    ->delete();
```

---

## Recommendations

### IMMEDIATE

1. **Remove Security Fields from `$fillable`** - `token`, `user_id`, `expires_at`, `revoked_at`, `last_used_at`
2. **Use `forceFill()` in Trusted Methods** - Avoid generic mass assignment for credential fields
3. **Add Unique Index on `token`** - Enforce database integrity
4. **Implement Refresh Token Rotation** - Revoke old token and issue new token atomically
5. **Use Row Locks During Refresh** - Prevent concurrent reuse/race conditions

### SHORT TERM

6. **Add Relationship Return Type** - `user(): BelongsTo`
7. **Remove Unused `Hash` Import**
8. **Add Active/Revoked/Expired Scopes**
9. **Add User/Device Token Limits**
10. **Add Auth Audit Logging** - token created, used, revoked, reuse detected

### LONG TERM

11. **Use Selector + Verifier Token Design** - Better lookup and verification architecture
12. **Add Replay Detection** - Detect reuse of revoked rotated tokens
13. **Add Scheduled Cleanup Command** - Ensure `cleanup()` is actually called
14. **Add Security Tests** - Expiry, revocation, rotation, concurrent refresh, replay

---

## Improved Version Snippet

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RefreshToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_name',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function generate(
        int $userId,
        ?string $deviceName = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        $plainToken = Str::random(64);
        $hashedToken = hash('sha256', $plainToken);

        $refreshToken = new self([
            'device_name' => $deviceName,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        $refreshToken->forceFill([
            'user_id' => $userId,
            'token' => $hashedToken,
            'expires_at' => now()->addDays((int) config('auth.refresh_token_ttl', 30)),
        ])->save();

        return [
            'plain_token' => $plainToken,
            'model' => $refreshToken,
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    public static function findByPlainToken(string $plainToken): ?self
    {
        return self::query()
            ->where('token', hash('sha256', $plainToken))
            ->active()
            ->first();
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }

    public function markAsUsed(): void
    {
        $this->forceFill(['last_used_at' => now()])->save();
    }
}
```

---

## Summary

RefreshToken.php has a good foundation because it stores hashed tokens and supports expiration/revocation. However, the model is not production-hardened enough for long-lived authentication credentials.

**Strengths:**
- Does not store plaintext refresh tokens
- Filters revoked and expired tokens during lookup
- Tracks useful session metadata
- Provides revocation and cleanup helpers

**Main Gaps:**
1. Token and security metadata fields are mass assignable
2. No refresh token rotation or replay detection in the model/service boundary
3. No row locking or transaction pattern for concurrent refresh
4. No visible token uniqueness enforcement or collision handling
5. Missing relationship type and unused import

**Status:** 🚫 Request changes before production acceptance

---

*Review completed: 2026-07-14 11:57 AM*  
*File #28/137 - Phase 2: Security Layer COMPLETE (12/12 complete)*
