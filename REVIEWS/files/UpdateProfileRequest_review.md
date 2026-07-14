# File Review: UpdateProfileRequest.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Requests/UpdateProfileRequest.php  
**Lines:** 28  
**Type:** FormRequest Validation

---

## File Summary

`UpdateProfileRequest` validates user profile update fields: name, phone, avatar URL, birthday, gender, and address.

This request is under-specified for production profile updates. It allows blanket authorization, weak phone validation, arbitrary avatar URL strings, no input normalization, no birthday boundary checks, and unclear partial-update semantics. Because profile data is user-controlled and likely displayed throughout the application, strict validation and output-safety assumptions matter.

---

## Overall Score

**Overall Score:** 5.6/10

**Decision:** 🟠 **REQUEST CHANGES**

---

## Strengths

- Uses Laravel `FormRequest`.
- Restricts accepted gender values.
- Applies length bounds to name, phone, avatar URL, and address.
- Uses `sometimes` for `name`, which supports partial profile updates.
- Keeps the request small and easy to read.

---

## Issues

### Issue #1

**Severity:** High  
**Category:** Authorization / IDOR  
**Location:** app/Http/Requests/UpdateProfileRequest.php:9-12

**Problem**

The request authorizes every caller unconditionally.

```php
public function authorize(): bool
{
    return true;
}
```

**Why this matters**

For a self-profile endpoint, the authenticated user should only update their own profile. If a controller or route accepts a user ID and relies on this FormRequest for authorization, this creates an IDOR risk. The request does not document or enforce ownership.

**How to fix**

Authorize against the authenticated user and target profile when route parameters exist.

```php
public function authorize(): bool
{
    return $this->user() !== null
        && (int) $this->route('user')?->id === (int) $this->user()->id;
}
```

If the route only updates the authenticated user, still require authentication at middleware level and keep that contract explicit in route tests.

---

### Issue #2

**Severity:** Medium  
**Category:** Validation / Required Update Semantics  
**Location:** app/Http/Requests/UpdateProfileRequest.php:19-26

**Problem**

All fields are optional or nullable, so an empty payload can pass validation.

```php
return [
    'name' => ['sometimes', 'string', 'max:255'],
    'phone' => ['nullable', 'string', 'max:20'],
    'avatar_url' => ['nullable', 'string', 'max:255'],
    'birthday' => ['nullable', 'date'],
    'gender' => ['nullable', 'in:male,female,other'],
    'address' => ['nullable', 'string', 'max:1000'],
];
```

**Why this matters**

Accepting empty profile update payloads creates ambiguous API behavior: did the client intentionally update nothing, or did it send a malformed request? This complicates auditing, client debugging, and idempotency semantics.

**How to fix**

Require at least one updateable field.

```php
public function rules(): array
{
    return [
        'name' => ['sometimes', 'string', 'max:255'],
        // ...
    ];
}

public function withValidator($validator): void
{
    $validator->after(function ($validator) {
        if (empty($this->validated())) {
            $validator->errors()->add('payload', 'At least one profile field is required.');
        }
    });
}
```

Alternatively use route/controller logic to reject empty updates.

---

### Issue #3

**Severity:** Medium  
**Category:** Security / Avatar URL Validation  
**Location:** app/Http/Requests/UpdateProfileRequest.php:22

**Problem**

`avatar_url` is accepted as any string up to 255 characters.

```php
'avatar_url' => ['nullable', 'string', 'max:255'],
```

**Why this matters**

If avatar URLs are rendered in frontend templates, arbitrary schemes or malformed values can create XSS, tracking, mixed-content, or broken image issues depending on frontend handling. If the backend later fetches the URL, this can become SSRF.

**How to fix**

Validate as a URL and restrict accepted schemes/hosts or switch to managed uploads.

```php
'avatar_url' => ['nullable', 'url:http,https', 'max:2048'],
```

Prefer storing internal media IDs/paths from a controlled upload pipeline instead of arbitrary external URLs.

---

### Issue #4

**Severity:** Medium  
**Category:** Validation / Phone Correctness  
**Location:** app/Http/Requests/UpdateProfileRequest.php:21

**Problem**

Phone accepts any string up to 20 characters.

```php
'phone' => ['nullable', 'string', 'max:20'],
```

**Why this matters**

Invalid phone numbers can be stored and later break SMS, customer support, loyalty lookup, or identity verification flows. If phone is used for recovery or fraud checks, lack of normalization can create duplicate identities.

**How to fix**

Use a phone format rule aligned with the rest of the application and normalize before storage.

```php
'phone' => ['nullable', 'string', 'regex:/^(0|\+84)[0-9]{9,10}$/', 'max:20'],
```

Normalize to a canonical format such as `+84...`.

---

### Issue #5

**Severity:** Medium  
**Category:** Data Quality / Name Validation  
**Location:** app/Http/Requests/UpdateProfileRequest.php:20

**Problem**

`name` has no minimum length and is not trimmed.

```php
'name' => ['sometimes', 'string', 'max:255'],
```

**Why this matters**

A one-character name or whitespace-padded name can be accepted. If the service uses `$request->all()` instead of validated data, whitespace-only values can persist. Even with validated data, no normalization happens here.

**How to fix**

Add trimming in `prepareForValidation()` and enforce a minimum length.

```php
protected function prepareForValidation(): void
{
    if (is_string($this->input('name'))) {
        $this->merge(['name' => trim($this->input('name'))]);
    }
}
```

```php
'name' => ['sometimes', 'string', 'min:2', 'max:255'],
```

---

### Issue #6

**Severity:** Medium  
**Category:** Validation / Birthday Business Rules  
**Location:** app/Http/Requests/UpdateProfileRequest.php:23

**Problem**

Birthday is only validated as a generic date.

```php
'birthday' => ['nullable', 'date'],
```

**Why this matters**

Future birthdays, unrealistic historical dates, or dates that violate age-related business rules can be stored. If age affects promotions, ratings, membership segmentation, or compliance, generic `date` is not enough.

**How to fix**

Add explicit date boundaries.

```php
'birthday' => ['nullable', 'date', 'before_or_equal:today', 'after:1900-01-01'],
```

If the business has a minimum age, enforce it explicitly.

---

### Issue #7

**Severity:** Medium  
**Category:** XSS / User-Generated Content  
**Location:** app/Http/Requests/UpdateProfileRequest.php:25

**Problem**

`address` accepts arbitrary strings up to 1000 characters.

```php
'address' => ['nullable', 'string', 'max:1000'],
```

**Why this matters**

Address is user-generated content. If rendered in admin panels, invoices, booking confirmations, or emails without escaping, it can become stored XSS. Backend validation should not be the only XSS defense, but this field is currently completely unrestricted except length.

**How to fix**

Ensure all output is escaped. Consider trimming and rejecting control characters.

```php
'address' => ['nullable', 'string', 'max:1000'];
```

Add output escaping tests for every profile display surface.

---

### Issue #8

**Severity:** Medium  
**Category:** Data Consistency / Normalization  
**Location:** app/Http/Requests/UpdateProfileRequest.php:19-26

**Problem**

The request does not normalize user-entered fields before validation.

```php
'name' => ['sometimes', 'string', 'max:255'],
'phone' => ['nullable', 'string', 'max:20'],
'address' => ['nullable', 'string', 'max:1000'],
```

**Why this matters**

Profile data becomes inconsistent: duplicated phone formats, leading/trailing whitespace, inconsistent address formatting, and hard-to-search values.

**How to fix**

Implement `prepareForValidation()` for canonicalization.

```php
protected function prepareForValidation(): void
{
    $this->merge([
        'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
        'phone' => is_string($this->input('phone')) ? trim($this->input('phone')) : $this->input('phone'),
        'address' => is_string($this->input('address')) ? trim($this->input('address')) : $this->input('address'),
    ]);
}
```

---

### Issue #9

**Severity:** Low  
**Category:** API Consistency / Localization  
**Location:** app/Http/Requests/UpdateProfileRequest.php:7-28

**Problem**

No custom validation messages are defined, while several auth requests use localized Vietnamese validation messages.

```php
class UpdateProfileRequest extends FormRequest
```

**Why this matters**

The API will return inconsistent validation language and style across endpoints.

**How to fix**

Move messages to language files or define consistent localized messages for profile validation.

---

### Issue #10

**Severity:** Low  
**Category:** Maintainability / Magic Strings  
**Location:** app/Http/Requests/UpdateProfileRequest.php:24

**Problem**

Gender values are hard-coded directly in the validation rule.

```php
'gender' => ['nullable', 'in:male,female,other'],
```

**Why this matters**

If gender options are used elsewhere, hard-coding creates drift between validation, database constraints, frontend options, and API documentation.

**How to fix**

Centralize allowed values in an enum or constant.

```php
Rule::in(UserGender::values())
```

---

## Security Review

Security concerns:

- Blanket `authorize()` can become IDOR if route/controller passes a target user.
- Arbitrary `avatar_url` string should not be trusted.
- User-controlled `address` and `name` must be output-escaped wherever displayed.
- Phone normalization is missing if phone has identity/recovery significance.
- No rate limiting/audit expectations are visible for profile mutation.

No direct SQL injection or mass-assignment issue exists inside this request, but downstream code must use `$request->validated()` only.

---

## Performance Review

No heavy processing exists in this file. Main performance concerns are indirect:

- Invalid unnormalized phone/avatar data can create downstream retries or failed integrations.
- Large address values are bounded to 1000 characters, which is acceptable.

---

## Database Review

Database concerns are downstream:

- Profile columns must match validation length limits.
- Birthday column should be nullable if nullable validation is intended.
- If phone must be unique, validation and database unique indexes must enforce that.
- Gender should ideally be constrained by enum/check constraint at the database layer.

---

## Concurrency Review

No direct concurrency logic exists in this request. Profile updates should still use optimistic locking or careful partial-update semantics if multiple clients can update profile fields concurrently, otherwise one update can overwrite another.

---

## Laravel Best Practice Review

Recommended improvements:

- Use route middleware/policies to guarantee only the profile owner can update.
- Add `prepareForValidation()` for trimming/normalization.
- Use `Rule::in()` with a centralized enum/constant for gender.
- Validate avatar URL as an actual URL or replace with managed upload flow.
- Use `$request->validated()` downstream, never `$request->all()`.
- Add explicit tests for empty payload behavior.

---

## Testing Review

Recommended tests:

1. Unauthenticated profile update is rejected by route middleware.
2. User cannot update another user's profile.
3. Empty update payload is rejected or documented as no-op.
4. Name is trimmed and must satisfy minimum length.
5. Invalid phone formats are rejected.
6. Phone is normalized before persistence.
7. Invalid avatar URL schemes are rejected.
8. Future birthday is rejected.
9. Invalid gender is rejected.
10. Address/name are escaped in API consumers or rendered views.
11. Downstream controller/service only uses validated data.
12. Concurrent partial profile updates do not unintentionally wipe fields.

---

## Final Decision

🟠 **REQUEST CHANGES**

`UpdateProfileRequest` is readable but too permissive for production profile data. Add ownership enforcement at the route/policy boundary, normalize input, validate avatar/phone/birthday strictly, reject ambiguous empty updates, and keep profile API validation responses consistent.

---

_Review completed: 2026-07-14 05:30 PM_  
_File #83/137 - Phase 5: Requests (8/29 complete)_
