# File Review: ResetPasswordRequest.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Requests/ResetPasswordRequest.php  
**Lines:** 26  
**Type:** FormRequest Validation

---

## File Summary

`ResetPasswordRequest` validates password reset payloads containing a reset token, email, password, and password confirmation.

This file is very small, but it protects a critical authentication recovery boundary. The current validation is under-specified for production: password policy is weaker than registration, token/email normalization is missing, length bounds are missing, and the request does not express anti-abuse, token replay, generic failure, or session invalidation expectations.

---

## Overall Score

**Overall Score:** 5.4/10

**Decision:** 🟠 **REQUEST CHANGES**

---

## Strengths

- Uses Laravel `FormRequest`.
- Requires reset token.
- Requires email.
- Requires password confirmation.
- Uses Laravel `Password` rule object instead of only string `min`.
- Keeps `authorize()` open, which is appropriate for password reset endpoints.

---

## Issues

### Issue #1

**Severity:** High  
**Category:** Security / Password Policy Consistency  
**Location:** app/Http/Requests/ResetPasswordRequest.php:23

**Problem**

Password reset allows a weaker password than registration.

```php
'password' => ['required', 'string', Password::min(6), 'confirmed'],
```

Registration uses a stronger rule requiring at least 8 characters, letters, and numbers. Password reset should not downgrade account security.

**Why this matters**

An attacker who compromises a reset token can set a weaker password than the registration policy. Users can also weaken their own password through reset, creating inconsistent security behavior.

**How to fix**

Use the same centralized password policy for registration, password change, and password reset.

```php
'password' => [
    'required',
    'string',
    'confirmed',
    Password::min(8)->letters()->numbers(),
],
```

Prefer a shared policy/helper to prevent drift.

---

### Issue #2

**Severity:** High  
**Category:** Security / Abuse Protection  
**Location:** app/Http/Requests/ResetPasswordRequest.php:8-26

**Problem**

The request does not express any rate-limit, lockout, or abuse-prevention expectation for password reset submissions.

```php
class ResetPasswordRequest extends FormRequest
```

**Why this matters**

Password reset endpoints are high-value targets. Attackers can brute-force weak tokens, repeatedly submit resets, or cause expensive password hashing workloads. Validation alone is not sufficient.

**How to fix**

Apply route-level throttling keyed by IP and normalized email.

```php
Route::post('/reset-password', ...)
    ->middleware('throttle:password-reset');
```

Also log suspicious reset activity.

---

### Issue #3

**Severity:** Medium  
**Category:** Validation / Input Bounds  
**Location:** app/Http/Requests/ResetPasswordRequest.php:21-23

**Problem**

`token`, `email`, and `password` do not have maximum length bounds.

```php
'token' => ['required', 'string'],
'email' => ['required', 'email'],
'password' => ['required', 'string', Password::min(6), 'confirmed'],
```

**Why this matters**

Unbounded strings can increase memory usage, log size, hashing cost, and database lookup overhead. Password hashing extremely large inputs can be used for denial-of-service.

**How to fix**

Add explicit maximum lengths.

```php
'token' => ['required', 'string', 'max:255'],
'email' => ['required', 'email:rfc', 'max:255'],
'password' => ['required', 'string', 'max:1024', 'confirmed', Password::min(8)->letters()->numbers()],
```

---

### Issue #4

**Severity:** Medium  
**Category:** Data Consistency / Email Normalization  
**Location:** app/Http/Requests/ResetPasswordRequest.php:22

**Problem**

Email is validated but not trimmed or lowercased.

```php
'email' => ['required', 'email'],
```

**Why this matters**

Password reset token lookup typically matches token + email. If registration/login normalize email but reset does not, reset may fail for equivalent casing or accidental whitespace. This also weakens rate-limit keys.

**How to fix**

Normalize email in `prepareForValidation()`.

```php
protected function prepareForValidation(): void
{
    $email = $this->input('email');

    if (is_string($email)) {
        $this->merge(['email' => strtolower(trim($email))]);
    }
}
```

---

### Issue #5

**Severity:** Medium  
**Category:** Security / Token Handling  
**Location:** app/Http/Requests/ResetPasswordRequest.php:21

**Problem**

The reset token is only validated as a generic string.

```php
'token' => ['required', 'string'],
```

**Why this matters**

If reset tokens have a known format or length, validation should reject clearly malformed input before hitting the password broker/database. This reduces attack surface and noise. It also prevents accidental whitespace from causing hard-to-debug failures.

**How to fix**

Trim token input and constrain expected length/format according to the token implementation.

```php
'token' => ['required', 'string', 'max:255'];
```

If tokens are UUIDs or fixed-length hashes, use stricter validation.

---

### Issue #6

**Severity:** Medium  
**Category:** Security / Token Replay and Session Invalidation  
**Location:** app/Http/Requests/ResetPasswordRequest.php:18-24

**Problem**

The request validates reset payload shape only. It does not communicate required downstream security behavior: single-use token consumption, token expiration, refresh-token/session revocation, and remember-token rotation.

```php
public function rules(): array
{
    return [
        'token' => ['required', 'string'],
        'email' => ['required', 'email'],
        'password' => ['required', 'string', Password::min(6), 'confirmed'],
    ];
}
```

**Why this matters**

Password reset is account recovery. After a password reset, existing sessions and refresh tokens should usually be revoked, and the reset token must be consumed atomically to prevent replay. Without this, an attacker with an old session may remain authenticated after the victim resets the password.

**How to fix**

Enforce this in the reset service/controller:

- Consume reset token atomically.
- Rotate remember token.
- Revoke refresh tokens/sessions.
- Audit-log password reset.
- Return generic errors for invalid token/email combinations.

---

### Issue #7

**Severity:** Medium  
**Category:** Account Enumeration / API Behavior  
**Location:** app/Http/Requests/ResetPasswordRequest.php:21-23

**Problem**

The request accepts email and token separately, but there is no visible requirement that downstream failures return a generic response.

```php
'token' => ['required', 'string'],
'email' => ['required', 'email'],
```

**Why this matters**

Password reset flows must not disclose whether an email exists, whether a token exists, or whether a token is expired. Differentiated errors help attackers enumerate users and validate stolen/reset tokens.

**How to fix**

Ensure controller/service returns the same public error for invalid token, invalid email, expired token, or already-used token.

```php
throw ValidationException::withMessages([
    'email' => ['Không thể đặt lại mật khẩu với thông tin đã cung cấp.'],
]);
```

---

### Issue #8

**Severity:** Low  
**Category:** Laravel Best Practice / Email Validation Strictness  
**Location:** app/Http/Requests/ResetPasswordRequest.php:22

**Problem**

Email validation does not specify the same strictness as registration.

```php
'email' => ['required', 'email'],
```

Registration uses:

```php
'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
```

**Why this matters**

Different email validation semantics across auth flows create inconsistent client behavior. A user may register with an address that validates under one rule but reset under another.

**How to fix**

Use a shared email validation rule.

```php
'email' => ['required', 'email:rfc', 'max:255'],
```

---

### Issue #9

**Severity:** Low  
**Category:** API Consistency / Localization  
**Location:** app/Http/Requests/ResetPasswordRequest.php:8-26

**Problem**

No custom validation messages are defined, while adjacent auth requests define Vietnamese messages.

```php
class ResetPasswordRequest extends FormRequest
```

**Why this matters**

API validation responses will be inconsistent across authentication endpoints. Users may receive localized messages for login/register but default framework messages for reset password.

**How to fix**

Either centralize messages in language files or define consistent request messages.

---

## Security Review

Security concerns:

- Password reset allows weaker password policy than registration.
- Missing max length bounds on token, email, and password.
- No visible route throttling/abuse protection.
- Email/token normalization is missing.
- Downstream token replay prevention, session invalidation, and audit logging are not visible in this request.
- Generic non-enumerating failure behavior depends on downstream implementation.

No SQL injection, XSS, CSRF, or mass-assignment issue is directly visible in this file.

---

## Performance Review

Performance concerns:

- Unbounded password input can increase hashing cost and enable DoS.
- Unbounded email/token strings can increase database lookup and logging overhead.
- Missing reset throttling can create excessive password-hash and token-lookup load.

---

## Database Review

No direct database access exists in this file. Downstream token lookup must use indexed normalized email and token fields. Reset token consumption should be atomic and single-use.

---

## Concurrency Review

Concurrency concerns are downstream but critical:

- Two concurrent reset submissions with the same token must not both succeed.
- Token consumption and password update should be atomic.
- Session/refresh-token revocation should happen in the same logical reset workflow.

---

## Laravel Best Practice Review

Recommended improvements:

- Normalize email/token in `prepareForValidation()`.
- Add max length bounds.
- Use the same password policy as registration/change password.
- Use `email:rfc` consistently.
- Centralize auth validation messages in language files.
- Enforce reset throttling at route/controller level.
- Ensure downstream reset logic uses Laravel password broker semantics safely.

---

## Testing Review

Recommended tests:

1. Missing token fails validation.
2. Missing email fails validation.
3. Email is trimmed and lowercased.
4. Overlong token/email/password are rejected.
5. Password reset policy matches registration policy.
6. Password confirmation mismatch fails.
7. Invalid token and invalid email return the same public error.
8. Reset token cannot be reused.
9. Concurrent reset submissions cannot both succeed.
10. Existing sessions/refresh tokens are revoked after successful reset.
11. Reset endpoint is throttled.
12. Reset action is audit-logged.

---

## Final Decision

🟠 **REQUEST CHANGES**

`ResetPasswordRequest` is too weak for a password recovery boundary. Align password policy with registration, normalize and bound inputs, require reset throttling, and verify downstream implementation consumes tokens atomically while revoking active credentials.

---

_Review completed: 2026-07-14 05:25 PM_  
_File #82/137 - Phase 5: Requests (7/29 complete)_
