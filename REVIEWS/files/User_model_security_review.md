# File Review: User.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Models/User.php  
**Lines:** 111  
**Type:** Eloquent Model - Authenticatable User / JWT Subject

---

## File Information

**Path:** `app/Models/User.php`  
**Type:** Eloquent Model  
**Lines:** 111  
**Complexity:** Medium  

**Purpose:**  
Represents authenticated users:
- Stores profile, authentication, status, and login metadata
- Implements JWT identity contract
- Defines relationships to roles, orders, seat holds, login histories, and promotions
- Provides role/permission helper methods and basic query scopes

**Security Impact:** 🔴 CRITICAL - Primary identity model for authentication and authorization

---

## Overall Score

**Code Quality:** 5.8/10  
**Security:** 4.8/10  
**Performance:** 5.5/10  
**Maintainability:** 5.8/10  
**Laravel Best Practice:** 5.5/10  

**Overall Score:** 5.5/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

1. ✅ **Sensitive Fields Hidden** - `password` and `remember_token` are excluded from serialization
2. ✅ **JWTSubject Implemented** - Integrates with JWT authentication
3. ✅ **Relationship Return Types** - Relationship methods declare Eloquent relation types
4. ✅ **Useful Domain Relationships** - Orders, seat holds, login history, and promotions are represented
5. ✅ **Basic Authorization Helpers** - `hasRole()`, `hasAnyRole()`, and `hasPermission()` centralize checks

---

## Issues Found

### Issue #1: Privileged and Security-Sensitive Fields Are Mass Assignable

**Severity:** 🔴 CRITICAL  
**Category:** Security - Mass Assignment / Privilege Escalation  
**Location:** Lines 17-32

**Evidence:**
```php
protected $fillable = [
    'name',
    'email',
    'username',
    'phone',
    'password',
    'avatar_url',
    'birthday',
    'gender',
    'address',
    'loyalty_points',
    'email_verified_at',
    'status',
    'last_login_at',
    'last_login_ip',
];
```

**Problem:**
The model allows mass assignment of fields that should never be client-controlled:
- `loyalty_points`
- `email_verified_at`
- `status`
- `last_login_at`
- `last_login_ip`

These fields affect account state, authorization eligibility, reward value, and audit metadata.

**Why this matters:**
If any controller/service calls `$user->update($request->all())` or `User::create($request->all())`, users may:
- activate disabled accounts via `status`
- mark their email as verified via `email_verified_at`
- grant themselves loyalty points
- forge login audit data

This is production-blocking for an identity model.

**How to fix:**
Only allow user-editable profile fields in `$fillable`. Mutate security fields through explicit service methods.

**Example:**
```php
protected $fillable = [
    'name',
    'email',
    'username',
    'phone',
    'password',
    'avatar_url',
    'birthday',
    'gender',
    'address',
];

// Explicit privileged mutations:
$user->forceFill([
    'status' => false,
])->save();

$user->forceFill([
    'last_login_at' => now(),
    'last_login_ip' => $request->ip(),
])->save();
```

---

### Issue #2: Password Is Fillable Without a Model Mutator

**Severity:** 🔴 CRITICAL  
**Category:** Security - Password Handling  
**Location:** Lines 17-32

**Evidence:**
```php
protected $fillable = [
    // ...
    'password',
    // ...
];
```

There is no `password` mutator/cast in this file.

**Problem:**
`password` is mass assignable, but the model does not guarantee hashing. If any code path assigns a plain password directly, it can be stored in plaintext unless every caller remembers to hash it.

**Why this matters:**
Password hashing must be enforced consistently at the model or service boundary. Relying on every caller to remember `Hash::make()` is fragile.

**How to fix:**
Use Laravel's hashed cast or an explicit mutator.

**Example:**
```php
protected $casts = [
    'email_verified_at' => 'datetime',
    'last_login_at' => 'datetime',
    'birthday' => 'date',
    'status' => 'boolean',
    'password' => 'hashed',
];
```

If using older Laravel versions, use:
```php
use Illuminate\Support\Facades\Hash;

public function setPasswordAttribute(?string $value): void
{
    if ($value !== null && !Hash::needsRehash($value)) {
        $this->attributes['password'] = Hash::make($value);
        return;
    }

    $this->attributes['password'] = $value;
}
```

---

### Issue #3: JWT Claims Are Empty

**Severity:** 🟡 MEDIUM  
**Category:** Authentication / Token Revocation  
**Location:** Lines 79-82

**Evidence:**
```php
public function getJWTCustomClaims()
{
    return [];
}
```

**Problem:**
JWT custom claims are empty. This is not always wrong, but the model has security-relevant fields such as `status`, `email_verified_at`, and `last_login_at`. Tokens issued before account disablement or password reset may remain valid unless middleware checks the database every request.

**Why this matters:**
For stateless JWT systems, token invalidation is difficult. Empty claims make it harder to reject stale tokens based on account status changes or credential rotation.

**How to fix:**
Add a token version or password-change timestamp claim if the application supports token invalidation checks.

**Example:**
```php
public function getJWTCustomClaims(): array
{
    return [
        'token_version' => $this->token_version,
    ];
}
```

Middleware should compare token claim with database value.

---

### Issue #4: Authorization Helpers Can Cause N+1 Queries

**Severity:** 🟡 MEDIUM  
**Category:** Performance / Authorization  
**Location:** Lines 85-99

**Evidence:**
```php
public function hasRole(string $slug): bool
{
    return $this->role?->slug === $slug;
}

public function hasAnyRole(array $slugs): bool
{
    return $this->role && in_array($this->role->slug, $slugs);
}

public function hasPermission(string $permissionSlug): bool
{
    return $this->role?->permissions()->where('slug', $permissionSlug)->exists() ?? false;
}
```

**Problem:**
`hasRole()` and `hasAnyRole()` may lazy-load `role`. `hasPermission()` executes a permissions query every time it is called.

**Why this matters:**
Authorization checks often run in middleware and policy layers. Repeated checks can create hidden N+1 query patterns.

**How to fix:**
Eager-load `role.permissions` where authorization is checked frequently, or use loaded relationships when available.

**Example:**
```php
public function hasPermission(string $permissionSlug): bool
{
    if (!$this->relationLoaded('role')) {
        $this->load('role.permissions');
    }

    return $this->role?->permissions
        ->contains('slug', $permissionSlug) ?? false;
}
```

For high-traffic systems, cache permissions per user/role.

---

### Issue #5: `in_array()` Uses Loose Comparison

**Severity:** 🔵 LOW  
**Category:** Correctness / Security Hardening  
**Location:** Line 93

**Evidence:**
```php
return $this->role && in_array($this->role->slug, $slugs);
```

**Problem:**
`in_array()` defaults to loose comparison. Role slugs are strings, so strict comparison should be used.

**How to fix:**
```php
return $this->role && in_array($this->role->slug, $slugs, true);
```

---

### Issue #6: Query Scopes Lack Type Declarations

**Severity:** 🔵 LOW  
**Category:** Code Quality / Type Safety  
**Location:** Lines 102-110

**Evidence:**
```php
public function scopeActive($query)
{
    return $query->where('status', 1);
}

public function scopeByEmail($query, $email)
{
    return $query->where('email', $email);
}
```

**Problem:**
Scopes have no parameter or return types, reducing static analysis coverage.

**How to fix:**
```php
use Illuminate\Database\Eloquent\Builder;

public function scopeActive(Builder $query): Builder
{
    return $query->where('status', true);
}

public function scopeByEmail(Builder $query, string $email): Builder
{
    return $query->where('email', $email);
}
```

---

### Issue #7: `email` Is Not Normalized at Model Boundary

**Severity:** 🟡 MEDIUM  
**Category:** Authentication / Data Correctness  
**Location:** Lines 17-32 and 107-110

**Evidence:**
```php
'email',
```

```php
public function scopeByEmail($query, $email)
{
    return $query->where('email', $email);
}
```

**Problem:**
The model does not normalize email values. If controllers/services do not consistently lowercase and trim emails, duplicate accounts or failed login lookups can occur.

**How to fix:**
Normalize email input through validation or a model mutator.

**Example:**
```php
public function setEmailAttribute(?string $value): void
{
    $this->attributes['email'] = $value
        ? mb_strtolower(trim($value))
        : null;
}
```

Also enforce a unique database index on normalized email.

---

## Recommendations

### IMMEDIATE

1. **Remove Security Fields from `$fillable`** - `loyalty_points`, `email_verified_at`, `status`, `last_login_at`, `last_login_ip`
2. **Guarantee Password Hashing** - Add `'password' => 'hashed'` cast or a mutator
3. **Use Strict Role Comparison** - Add third argument `true` to `in_array()`
4. **Review All User Update Paths** - Ensure no mass assignment from untrusted request data

### SHORT TERM

5. **Add Typed Scopes and JWT Methods** - Improve static analysis
6. **Normalize Email** - Lowercase/trim before persistence and lookup
7. **Optimize Authorization Helpers** - Avoid repeated lazy-loaded queries
8. **Add Account Status Checks to Auth Middleware** - Disabled users should not authenticate

### LONG TERM

9. **Implement Token Versioning** - Support JWT revocation after password change/account disablement
10. **Cache Role Permissions** - Improve authorization performance
11. **Add Audit Events** - Log status changes, role changes, login metadata updates
12. **Split Profile Updates from Admin Mutations** - Separate DTO/FormRequest/service methods

---

## Improved Version Snippet

```php
use Illuminate\Database\Eloquent\Builder;

protected $fillable = [
    'name',
    'email',
    'username',
    'phone',
    'password',
    'avatar_url',
    'birthday',
    'gender',
    'address',
];

protected $casts = [
    'email_verified_at' => 'datetime',
    'last_login_at' => 'datetime',
    'birthday' => 'date',
    'status' => 'boolean',
    'password' => 'hashed',
];

public function hasAnyRole(array $slugs): bool
{
    return $this->role && in_array($this->role->slug, $slugs, true);
}

public function scopeActive(Builder $query): Builder
{
    return $query->where('status', true);
}

public function scopeByEmail(Builder $query, string $email): Builder
{
    return $query->where('email', mb_strtolower(trim($email)));
}
```

---

## Summary

User.php is functional but contains critical security risks due to overly broad mass assignment and lack of guaranteed password hashing.

**Strengths:**
- Hides password and remember token
- Implements JWTSubject
- Defines core relationships
- Provides role and permission helpers

**Main Gaps:**
1. Security-sensitive fields are mass assignable
2. Password hashing is not guaranteed in the model
3. JWT claims do not help with token revocation
4. Authorization helpers can cause repeated queries
5. Email normalization is not enforced

**Status:** 🚫 Request changes before production acceptance

---

*Review completed: 2026-07-14 11:53 AM*  
*File #27/137 - Phase 2: Security Layer (11/12 complete)*
