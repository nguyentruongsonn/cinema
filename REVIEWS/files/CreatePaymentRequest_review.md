# File Review: CreatePaymentRequest.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Requests/CreatePaymentRequest.php  
**Lines:** 26  
**Type:** FormRequest Validation

---

## File Summary

`CreatePaymentRequest` validates the payload used to create a payment/order for a showtime with selected items, optional voucher code, and optional points usage.

This is a high-risk money-flow request. The validation covers basic shape but leaves major production gaps around authentication, item existence by type, seat/showtime consistency, duplicate items, product limits, idempotency, and abuse prevention.

---

## Overall Score

**Overall Score:** 4.6/10

**Decision:** 🔴 **BLOCKING**

---

## Strengths

- Uses Laravel `FormRequest`.
- Requires `showtime_id`.
- Requires at least one item.
- Restricts item type to `seat` or `product`.
- Enforces positive integer quantity.
- Bounds `voucher_code` length.
- Prevents negative `points_used`.

---

## Issues

### Issue #1

**Severity:** Critical  
**Category:** Authentication / Authorization  
**Location:** app/Http/Requests/CreatePaymentRequest.php:9-12

**Problem**

The request authorizes every caller and explicitly delegates authentication to the controller.

```php
public function authorize(): bool
{
    return true; // We check Auth::user() in controller
}
```

**Why this matters**

Payment creation is a sensitive money-flow operation. A `FormRequest` should not rely on controller code to remember authentication checks. If this request is reused or the controller changes, unauthenticated or unauthorized users may reach payment creation logic.

**How to fix**

Require an authenticated user in the request and keep route middleware as defense-in-depth.

```php
public function authorize(): bool
{
    return $this->user() !== null;
}
```

If guests are allowed to purchase, that should be explicit and covered by a separate guest checkout request with stricter identity validation.

---

### Issue #2

**Severity:** Critical  
**Category:** Concurrency / Payment Idempotency  
**Location:** app/Http/Requests/CreatePaymentRequest.php:14-25

**Problem**

The request does not require an idempotency key.

```php
public function rules(): array
{
    return [
        'showtime_id' => 'required|integer|exists:showtimes,id',
        'items' => 'required|array|min:1',
        ...
    ];
}
```

**Why this matters**

Payment creation is highly vulnerable to retries, double-clicks, mobile network retries, and gateway callback timing. Without an idempotency requirement, duplicate orders/payments can be created for the same cart.

**How to fix**

Require an `Idempotency-Key` header and enforce it server-side with a unique database constraint.

```php
public function withValidator($validator): void
{
    $validator->after(function ($validator): void {
        if (! $this->header('Idempotency-Key')) {
            $validator->errors()->add('Idempotency-Key', 'The Idempotency-Key header is required.');
        }
    });
}
```

---

### Issue #3

**Severity:** High  
**Category:** Validation / Seat Integrity  
**Location:** app/Http/Requests/CreatePaymentRequest.php:19-21

**Problem**

For `seat` items, the request validates only that `id` is an integer. It does not validate that the seat exists or belongs to the selected showtime's screen.

```php
'items.*.type' => 'required|string|in:seat,product',
'items.*.id' => 'required|integer',
'items.*.quantity' => 'required|integer|min:1',
```

**Why this matters**

A client can submit arbitrary seat IDs, including seats from another screen/theater. If downstream checks are incomplete, this can create invalid bookings, pricing errors, or seat availability corruption.

**How to fix**

Use conditional validation and an after-validation check that verifies each seat belongs to the screen for the requested showtime.

```php
public function withValidator($validator): void
{
    $validator->after(function ($validator): void {
        // Resolve showtime and verify all seat item IDs belong to showtime->screen_id.
    });
}
```

---

### Issue #4

**Severity:** High  
**Category:** Validation / Product Integrity  
**Location:** app/Http/Requests/CreatePaymentRequest.php:19-21

**Problem**

For `product` items, the request validates only that `id` is an integer. It does not validate product existence, active status, stock availability, or sellability.

```php
'items.*.id' => 'required|integer',
```

**Why this matters**

A client can submit non-existent, disabled, deleted, or out-of-stock products. If service logic misses any of these checks, payment totals and inventory can become inconsistent.

**How to fix**

Add conditional product validation in an after-validation hook or split the request shape into explicit `seats` and `products` arrays with independent validation rules.

---

### Issue #5

**Severity:** High  
**Category:** Business Logic / Seat Quantity  
**Location:** app/Http/Requests/CreatePaymentRequest.php:19-21

**Problem**

The same `quantity` rule applies to both seats and products.

```php
'items.*.quantity' => 'required|integer|min:1',
```

**Why this matters**

A seat is normally a unique booking unit and should not have quantity greater than 1. Allowing `quantity > 1` for a seat item can produce incorrect ticket counts, totals, duplicate booking attempts, or unclear downstream behavior.

**How to fix**

Use conditional validation:

```php
'items.*.quantity' => ['required', 'integer', 'min:1'],
```

Then enforce:

```php
if ($item['type'] === 'seat' && (int) $item['quantity'] !== 1) {
    $validator->errors()->add("items.$index.quantity", 'Seat item quantity must be 1.');
}
```

---

### Issue #6

**Severity:** High  
**Category:** Business Logic / Duplicate Items  
**Location:** app/Http/Requests/CreatePaymentRequest.php:18-21

**Problem**

The request does not reject duplicate item entries.

```php
'items' => 'required|array|min:1',
```

**Why this matters**

Duplicate seat entries can cause double booking attempts or duplicate ticket lines. Duplicate product entries can bypass per-item quantity caps or stock checks if downstream logic processes them independently.

**How to fix**

Normalize and validate unique composite keys by `type:id`.

```php
$keys = collect($this->input('items', []))
    ->map(fn ($item) => ($item['type'] ?? '').':'.($item['id'] ?? ''));

if ($keys->duplicates()->isNotEmpty()) {
    $validator->errors()->add('items', 'Duplicate items are not allowed.');
}
```

---

### Issue #7

**Severity:** High  
**Category:** Abuse Protection / Resource Exhaustion  
**Location:** app/Http/Requests/CreatePaymentRequest.php:18

**Problem**

The `items` array has no maximum size.

```php
'items' => 'required|array|min:1',
```

**Why this matters**

A client can submit a very large item list, causing heavy database lookups, pricing calculations, locking attempts, or memory usage in payment creation logic.

**How to fix**

Set a business-specific maximum.

```php
'items' => 'required|array|min:1|max:20',
```

Use a lower limit for seats if appropriate.

---

### Issue #8

**Severity:** High  
**Category:** Business Logic / Showtime State  
**Location:** app/Http/Requests/CreatePaymentRequest.php:17

**Problem**

The request validates only that the showtime ID exists.

```php
'showtime_id' => 'required|integer|exists:showtimes,id',
```

**Why this matters**

Payment creation should not be allowed for inactive, cancelled, past, sold-out, hidden, or not-yet-open showtimes. `exists` does not enforce any business state.

**How to fix**

Use a custom rule or after-validation hook to verify the showtime is sellable.

```php
Rule::exists('showtimes', 'id')->where(fn ($query) => $query->where('status', 'active'))
```

Also validate that the start time is still bookable according to business cut-off rules.

---

### Issue #9

**Severity:** Medium  
**Category:** Validation / Voucher Normalization  
**Location:** app/Http/Requests/CreatePaymentRequest.php:22

**Problem**

`voucher_code` is accepted as a nullable string with max length only.

```php
'voucher_code' => 'nullable|string|max:50',
```

**Why this matters**

Voucher codes are usually case-insensitive and format-constrained. Without normalization and format validation, duplicate code representations and confusing failures occur.

**How to fix**

Normalize and validate format.

```php
protected function prepareForValidation(): void
{
    if ($this->filled('voucher_code')) {
        $this->merge(['voucher_code' => strtoupper(trim($this->voucher_code))]);
    }
}
```

```php
'voucher_code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/'],
```

---

### Issue #10

**Severity:** Medium  
**Category:** Business Logic / Loyalty Abuse  
**Location:** app/Http/Requests/CreatePaymentRequest.php:23

**Problem**

`points_used` has no upper bound and no validation against the authenticated user's balance.

```php
'points_used' => 'nullable|integer|min:0',
```

**Why this matters**

A client can submit extremely high points usage. Service logic must reject this, but request validation should still enforce sane bounds and ownership-related checks where possible.

**How to fix**

Add a maximum and validate available balance downstream atomically.

```php
'points_used' => ['nullable', 'integer', 'min:0', 'max:100000'],
```

Then enforce balance inside a transaction with row locking.

---

### Issue #11

**Severity:** Medium  
**Category:** API Contract / Input Shape  
**Location:** app/Http/Requests/CreatePaymentRequest.php:18-23

**Problem**

The mixed `items` array combines seats and products in one polymorphic structure.

```php
'items.*.type' => 'required|string|in:seat,product',
'items.*.id' => 'required|integer',
'items.*.quantity' => 'required|integer|min:1',
```

**Why this matters**

Different item types require different validation rules. A polymorphic request shape makes validation weaker and pushes type-specific rules into services.

**How to fix**

Use explicit arrays:

```php
'seat_ids' => ['required', 'array', 'min:1', 'max:10'],
'seat_ids.*' => ['integer', 'distinct'],
'products' => ['sometimes', 'array', 'max:10'],
'products.*.product_id' => ['required', 'integer'],
'products.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
```

---

### Issue #12

**Severity:** Medium  
**Category:** Laravel Best Practices / Rule Syntax  
**Location:** app/Http/Requests/CreatePaymentRequest.php:17-23

**Problem**

Validation rules are defined as pipe strings.

```php
'showtime_id' => 'required|integer|exists:showtimes,id',
```

**Why this matters**

Pipe strings are valid Laravel syntax, but array syntax is more maintainable for complex production validation, especially with `Rule::exists`, custom rules, conditional rules, and constants.

**How to fix**

Use array rules and `Rule` objects.

```php
use Illuminate\Validation\Rule;

'showtime_id' => ['required', 'integer', Rule::exists('showtimes', 'id')],
```

---

## Security Review

Security concerns:

- Payment creation request authorizes all callers.
- Authentication is delegated to controller implementation.
- No idempotency key requirement.
- Arbitrary seat/product IDs are accepted at the boundary.
- No maximum item count creates request amplification risk.
- Loyalty points input has no upper bound.

No SQL injection is directly visible in this file because it uses Laravel validation rules and no raw SQL.

---

## Performance Review

Performance concerns:

- Unbounded item arrays can trigger excessive downstream queries.
- Missing item `distinct` validation allows redundant processing.
- Mixed item structure forces repeated type branching downstream.
- `exists:showtimes,id` alone may allow unnecessary processing for inactive/past showtimes.

---

## Database Review

Data correctness concerns:

- No validation that seats belong to the showtime's screen.
- No validation that products are active/sellable.
- No duplicate item prevention.
- No request-level support for atomic points redemption.
- No idempotency contract for unique payment/order creation.

---

## Concurrency Review

Concurrency concerns:

- Duplicate payment/order creation is possible without idempotency.
- Duplicate seat IDs or repeated submissions can amplify seat-lock contention.
- Points usage requires transactional balance locking, but request contract does not make this explicit.
- Seat availability still requires database locking downstream.

---

## Laravel Best Practice Review

Recommended improvements:

- Use `$this->user() !== null` in `authorize()`.
- Replace pipe strings with array rules and `Rule` objects for complex conditions.
- Add `withValidator()` for cross-field business validation.
- Use explicit `seat_ids` and `products` arrays rather than polymorphic `items`.
- Require `Idempotency-Key` for payment creation.
- Bound array sizes and quantities.

---

## Testing Review

Recommended tests:

1. Unauthenticated requests are rejected.
2. Missing idempotency key is rejected.
3. Past/inactive showtimes are rejected.
4. Seat IDs from another screen are rejected.
5. Non-existent/disabled products are rejected.
6. Seat quantity greater than 1 is rejected.
7. Duplicate seat/product entries are rejected.
8. Excessive item arrays are rejected.
9. Excessive points usage is rejected.
10. Voucher code is normalized and invalid characters are rejected.
11. Valid mixed cart passes validation.

---

## Final Decision

🔴 **BLOCKING**

`CreatePaymentRequest` is not production-ready for a payment creation endpoint. It validates basic shape but misses core money-flow requirements: authenticated authorization, idempotency, seat/showtime consistency, product sellability, duplicate item prevention, and bounded input sizes.

---

_Review completed: 2026-07-14 05:05 PM_  
_File #78/137 - Phase 5: Requests (3/29 complete)_