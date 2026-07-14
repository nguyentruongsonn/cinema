# File Review: ChangePasswordRequest.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Requests/ChangePasswordRequest.php  
**Lines:** 25  
**Type:** FormRequest Validation

---

## File Summary

`ChangePasswordRequest` validates password-change input with `current_password`, `new_password`, and Laravel's password confirmation convention. It uses a `FormRequest`, but authorization is unconditional and password policy is weak for production authentication flows.

For a cinema booking system with user accounts, stored orders, payments, loyalty data, and personal information, password-change validation must be stricter and must enforce authenticated context, current password correctness, strong password policy, and session/token invalidation expectations.

---

## Overall Score

**Overall Score:** 5.2/10

**Decision:** 🟠 **REQUEST CHANGES**

---

## Strengths

- Uses Laravel `FormRequest`.
- Requires `current_password`.
- Requires password confirmation via `confirmed`.
- Uses Laravel `Password` rule object rather than only raw string length.
- Keeps validation focused and small.

---

## Issues

### Issue #1

**Severity:** High  
**Category:** Authorization / Authentication Boundary  
**Location:** app/Http/Requests/ChangePasswordRequest.php:10-13

**Problem**

The request authorizes all callers.

```php
public function authorize(): bool
{
    return true;
}
```

**Why this matters**

Password change is a sensitive account-security action. The request should explicitly require an authenticated user. Relying only on route middleware is weaker because a route misconfiguration can expose this request flow.

**How to fix**

Require an authenticated user in `authorize()`.

```php
public function authorize(): bool
{
    return $this->user() !== null;
}
```

If admins can change another user's password, use a separate request and policy. Do not mix self-service password change with administrative password reset.

---

### Issue #2

**Severity:** High  
**Category:** Validation / Password Verification  
**Location:** app/Http/Requests/ChangePasswordRequest.php:21

**Problem**

`current_password` is required as a string but not validated using Laravel's `current_password` rule.

```php
'current_password' => ['required', 'string'],
```

**Why this matters**

A secure password-change request must validate that the provided current password matches the authenticated user's current password. If controller/service code forgets or incorrectly implements that check, any authenticated session could change the password without proving current password knowledge.

**How to fix**

Use Laravel's `current_password` validation rule.

```php
'current_password' => ['required', 'string', 'current_password'],
```

If using a non-default guard:

```php
'current_password' => ['required', 'string', 'current_password:api'],
```

---

### Issue #3

**Severity:** High  
**Category:** Security / Weak Password Policy  
**Location:** app/Http/Requests/ChangePasswordRequest.php:22

**Problem**

The password minimum length is only 6 characters.

```php
'new_password' => ['required', 'string', Password::min(6), 'confirmed'],
```

**Why this matters**

Six-character passwords are not acceptable for production account security. Users may have payment/order history and personal data attached to accounts. Weak passwords materially increase account takeover risk.

**How to fix**

Use Laravel's default password policy or a stronger explicit policy.

```php
'new_password' => [
    'required',
    'string',
    Password::defaults(),
    'confirmed',
],
```

Or:

```php
Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()
```

---

### Issue #4

**Severity:** Medium  
**Category:** Security / Password Reuse  
**Location:** app/Http/Requests/ChangePasswordRequest.php:22

**Problem**

The request does not prevent the new password from being the same as the current password.

```php
'new_password' => ['required', 'string', Password::min(6), 'confirmed'],
```

**Why this matters**

Allowing users to "change" to the same password undermines account recovery/security workflows and can hide failed security hygiene. It also makes audit events less meaningful.

**How to fix**

Add `different:current_password`.

```php
'new_password' => [
    'required',
    'string',
    'different:current_password',
    Password::defaults(),
    'confirmed',
],
```

---

### Issue #5

**Severity:** Medium  
**Category:** Session Security / Token Rotation  
**Location:** app/Http/Requests/ChangePasswordRequest.php:18-24

**Problem**

The request contract contains no indication that password change should invalidate other sessions/refresh tokens.

```php
public function rules(): array
{
    return [
        'current_password' => ['required', 'string'],
        'new_password' => ['required', 'string', Password::min(6), 'confirmed'],
    ];
}
```

**Why this matters**

After a password change, existing refresh tokens and sessions should normally be revoked, especially if the change is security-driven. While revocation is service/controller logic, the request should align with a secure flow and tests should enforce it.

**How to fix**

Ensure the password-change service revokes other sessions/tokens after successful password update. If user choice is allowed, validate a strict field:

```php
'logout_other_devices' => ['sometimes', 'boolean'],
```

Default should be secure.

---

### Issue #6

**Severity:** Medium  
**Category:** Abuse Protection / Rate Limiting  
**Location:** app/Http/Requests/ChangePasswordRequest.php:8-25

**Problem**

No rate-limiting or throttling expectation is visible in this request.

```php
class ChangePasswordRequest extends FormRequest
```

**Why this matters**

Password-change endpoints can be abused to brute-force current passwords for already-authenticated/stolen sessions. This request validates the shape only; endpoint-level throttling should be explicitly applied in route/controller middleware.

**How to fix**

Apply throttling to the route/controller and test it.

```php
Route::post('/password/change', ...)
    ->middleware(['auth:api', 'throttle:password-change']);
```

---

### Issue #7

**Severity:** Medium  
**Category:** API Contract / Input Bounds  
**Location:** app/Http/Requests/ChangePasswordRequest.php:21-22

**Problem**

The request does not define maximum lengths for password fields.

```php
'current_password' => ['required', 'string'],
'new_password' => ['required', 'string', Password::min(6), 'confirmed'],
```

**Why this matters**

Unbounded strings can increase hashing cost and memory usage. Password fields should have sane maximum lengths to reduce abuse risk while preserving compatibility.

**How to fix**

Add max length rules.

```php
'current_password' => ['required', 'string', 'max:1024', 'current_password'],
'new_password' => ['required', 'string', 'max:1024', Password::defaults(), 'confirmed'],
```

---

### Issue #8

**Severity:** Low  
**Category:** Clean Code / Naming Consistency  
**Location:** app/Http/Requests/ChangePasswordRequest.php:22

**Problem**

The request uses `new_password` instead of Laravel's common `password` field convention.

```php
'new_password' => ['required', 'string', Password::min(6), 'confirmed'],
```

**Why this matters**

`confirmed` expects `new_password_confirmation`, which is valid but less conventional than `password` / `password_confirmation`. This can be fine, but API documentation and frontend contracts must be clear.

**How to fix**

Either keep it and document it explicitly, or align with convention.

```php
'password' => ['required', 'string', Password::defaults(), 'confirmed'],
```

---

## Security Review

Security concerns:

- Blanket authorization does not explicitly require authenticated user.
- Current password is not verified by the request rule.
- Password policy is weak at only 6 characters.
- New password can be same as current password.
- No max length bounds for password fields.
- No visible throttle expectation for password-change attempts.

No SQL injection or XSS risk is visible in this request.

---

## Performance Review

Performance concerns:

- Unbounded password strings can increase hashing/comparison cost downstream.
- Password-change brute force attempts should be throttled outside this request.

---

## Database Review

No direct database access exists in this file. Data correctness depends on downstream password update logic hashing the new password and revoking old tokens/sessions.

---

## Concurrency Review

No direct concurrency logic exists in this file. Password changes should be serialized at the user-account level if token revocation and password update happen across multiple tables.

---

## Laravel Best Practice Review

Recommended improvements:

- Use `current_password` validation rule.
- Use `Password::defaults()` configured centrally.
- Require authenticated user in `authorize()`.
- Add `different:current_password`.
- Add maximum length bounds.
- Apply route/controller throttling for password-change attempts.

---

## Testing Review

Recommended tests:

1. Unauthenticated users cannot submit password change.
2. Wrong `current_password` is rejected.
3. Weak passwords are rejected.
4. Password confirmation is required.
5. New password cannot equal current password.
6. Very long password inputs are rejected.
7. Successful password change hashes the new password.
8. Successful password change revokes old refresh tokens/sessions.
9. Password-change endpoint is throttled.

---

## Final Decision

🟠 **REQUEST CHANGES**

`ChangePasswordRequest` has the right structure but is not strict enough for a production authentication flow. It must explicitly require authenticated context, validate the current password using Laravel's built-in rule, strengthen password policy, prevent reuse, and bound input length.

---

_Review completed: 2026-07-14 05:00 PM_  
_File #77/137 - Phase 5: Requests (2/29 complete)_