====================================================

File:
app/Http/Requests/StoreOrderRequest.php

Overall Score:
4.2/10

Decision:
BLOCKING

----------------------------------------------------

Strengths

- Uses a dedicated `FormRequest`.
- Requires a showtime and at least one seat.
- Uses `distinct` for submitted seat IDs.
- Validates product quantities with a minimum and maximum.
- Provides a promotion code maximum length.

----------------------------------------------------

Issues

### Issue #1

Severity:
Critical

Category:
Authorization / IDOR

Location:
app/Http/Requests/StoreOrderRequest.php:9-12

Problem

The request authorizes every caller:

```php
public function authorize(): bool
{
    return true;
}
```

Why this matters

Order creation is a money-flow operation. If this request is used on a route without strict authentication middleware, unauthenticated or unauthorized clients can create orders. Even with middleware, the request does not express ownership rules around `seat_hold_id`, selected seats, or user identity. This creates IDOR risk and makes the endpoint unsafe to reuse.

How to fix

Require an authenticated actor and enforce ownership-sensitive checks in authorization or validation.

Example

```php
public function authorize(): bool
{
    return $this->user() !== null;
}
```

For `seat_hold_id`, validate that the hold belongs to the authenticated user/session.

----------------------------------------------------

### Issue #2

Severity:
Critical

Category:
Business Logic / Database Correctness / Double Booking

Location:
app/Http/Requests/StoreOrderRequest.php:17-20

Problem

The request validates that the showtime, seats, and optional seat hold exist independently, but it does not validate that the seats belong to the showtime screen or that the seat hold belongs to the same showtime and selected seats.

```php
'showtime_id' => 'required|exists:showtimes,id',
'seat_ids' => 'required|array|min:1',
'seat_ids.*' => 'integer|distinct|exists:seats,id',
'seat_hold_id' => 'nullable|integer|exists:seat_holds,id',
```

Why this matters

A client can submit a valid `showtime_id`, valid `seat_ids` from another screen, and a valid `seat_hold_id` unrelated to either. If downstream service logic does not perfectly re-check all relationships, this can create invalid orders, corrupted inventory, impossible tickets, or duplicate booking scenarios.

How to fix

Validate relational consistency:

- Every seat must belong to the showtime's screen.
- `seat_hold_id` must belong to the authenticated user/session.
- `seat_hold_id` must match the same `showtime_id`.
- The hold must include exactly the requested seats.
- The hold must not be expired or consumed.

Example

```php
'seat_hold_id' => [
    'required',
    'integer',
    Rule::exists('seat_holds', 'id')->where(fn ($query) =>
        $query->where('showtime_id', $this->input('showtime_id'))
              ->where('user_id', $this->user()->id)
              ->where('expires_at', '>', now())
    ),
],
```

Use a custom rule for exact seat/hold matching.

----------------------------------------------------

### Issue #3

Severity:
Critical

Category:
Concurrency / Idempotency / Duplicate Orders

Location:
app/Http/Requests/StoreOrderRequest.php:16-25

Problem

The request does not require an idempotency key for order creation.

```php
return [
    'showtime_id' => 'required|exists:showtimes,id',
    'seat_ids' => 'required|array|min:1',
    'seat_ids.*' => 'integer|distinct|exists:seats,id',
    'seat_hold_id' => 'nullable|integer|exists:seat_holds,id',
    'products' => 'nullable|array',
    'products.*.id' => 'required_with:products|integer|distinct|exists:products,id',
    'products.*.quantity' => 'required_with:products|integer|min:1|max:20',
    'promotion_code' => 'nullable|string|max:50',
];
```

Why this matters

Order creation is retry-prone because users double-click, mobile clients retry, payment handoffs fail, and network requests time out. Without an idempotency key at the request contract level, duplicate requests can create duplicate orders, duplicate seat reservations, duplicate product reservations, or duplicate payment attempts.

How to fix

Require an idempotency key and enforce it with a unique database constraint and transactional service handling.

Example

```php
'idempotency_key' => ['required', 'string', 'max:100'],
```

The backend must store the key atomically with the created order response.

----------------------------------------------------

### Issue #4

Severity:
High

Category:
Business Logic / Validation

Location:
app/Http/Requests/StoreOrderRequest.php:20

Problem

`seat_hold_id` is nullable:

```php
'seat_hold_id' => 'nullable|integer|exists:seat_holds,id',
```

Why this matters

If the application has a seat-locking flow, allowing order creation without a hold bypasses the lock stage. That can increase race conditions and undermine the purpose of holding seats before payment/order creation. It also weakens the invariant that a paid/created order must originate from a valid active seat hold.

How to fix

Require a valid active hold for order creation unless the system has a separate staff/admin flow with different rules.

Example

```php
'seat_hold_id' => ['required', 'integer', Rule::exists('seat_holds', 'id')],
```

Then enforce active, unexpired, owned, exact-seat matching.

----------------------------------------------------

### Issue #5

Severity:
High

Category:
Performance / Abuse Prevention

Location:
app/Http/Requests/StoreOrderRequest.php:18,21

Problem

The seat and product arrays are unbounded:

```php
'seat_ids' => 'required|array|min:1',
'products' => 'nullable|array',
```

Why this matters

A client can submit thousands of seats or products. Validation will run existence checks and downstream order creation may perform large loops, inserts, price calculations, stock checks, and locks. This is a denial-of-service and operational risk.

How to fix

Add strict business limits.

Example

```php
'seat_ids' => ['required', 'array', 'min:1', 'max:10'],
'products' => ['nullable', 'array', 'max:20'],
```

Prefer configuration-backed limits.

----------------------------------------------------

### Issue #6

Severity:
High

Category:
Validation / Product Integrity

Location:
app/Http/Requests/StoreOrderRequest.php:22

Problem

Product IDs use `distinct` on `products.*.id`:

```php
'products.*.id' => 'required_with:products|integer|distinct|exists:products,id',
```

Why this matters

This prevents duplicate product IDs, which is good, but the request does not validate that products are active, sellable, in stock, visible to customers, or available in the current theater/channel. Existing product IDs may include inactive, admin-only, deleted-by-status, or out-of-stock products.

How to fix

Use an existence rule scoped to sellable products and perform stock checks transactionally.

Example

```php
'products.*.id' => [
    'required_with:products',
    'integer',
    'distinct',
    Rule::exists('products', 'id')->where('status', 1),
],
```

Final inventory must still be reserved/decremented inside a transaction.

----------------------------------------------------

### Issue #7

Severity:
Medium

Category:
Validation / API Correctness

Location:
app/Http/Requests/StoreOrderRequest.php:17

Problem

`showtime_id` does not require an integer type:

```php
'showtime_id' => 'required|exists:showtimes,id',
```

Why this matters

The rule checks existence but does not clearly express the expected scalar type. Laravel may coerce numeric strings, but the API contract should be explicit and consistent with other ID fields in the same request.

How to fix

Add `integer`.

Example

```php
'showtime_id' => ['required', 'integer', Rule::exists('showtimes', 'id')],
```

----------------------------------------------------

### Issue #8

Severity:
Medium

Category:
Validation / Business Logic

Location:
app/Http/Requests/StoreOrderRequest.php:17-19

Problem

The request does not validate showtime lockability or booking window.

```php
'showtime_id' => 'required|exists:showtimes,id',
'seat_ids.*' => 'integer|distinct|exists:seats,id',
```

Why this matters

A showtime may exist but be cancelled, inactive, hidden, sold out, already started, or closed for booking. Orders should not be created for any showtime merely because its row exists.

How to fix

Add a scoped existence rule or custom validation rule for orderable showtimes.

Example

```php
'showtime_id' => [
    'required',
    'integer',
    Rule::exists('showtimes', 'id')->where('status', 1),
],
```

Use a custom rule if booking cutoff depends on start time.

----------------------------------------------------

### Issue #9

Severity:
Medium

Category:
Validation / Promotion Correctness

Location:
app/Http/Requests/StoreOrderRequest.php:24

Problem

`promotion_code` is only validated as a nullable string:

```php
'promotion_code' => 'nullable|string|max:50',
```

Why this matters

A promotion code may exist but be expired, inactive, usage-limited, user-ineligible, below minimum order amount, or incompatible with selected products/showtime. If the service does not atomically validate and reserve usage, promotion race conditions and incorrect discounts can occur.

How to fix

Normalize and validate the promotion code contract early, then enforce final redemption atomically in the service.

Example

```php
'promotion_code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/'],
```

Convert to uppercase in `prepareForValidation()` if codes are case-insensitive.

----------------------------------------------------

### Issue #10

Severity:
Medium

Category:
Clean Code / API Consistency

Location:
app/Http/Requests/StoreOrderRequest.php:28-31

Problem

The request defines an empty messages method:

```php
public function messages(): array
{
    return [];
}
```

Why this matters

This is dead code and adds noise. It also misses an opportunity to provide domain-specific order validation messages for a critical checkout endpoint.

How to fix

Remove the method or provide meaningful messages.

Example

```php
public function messages(): array
{
    return [
        'seat_ids.required' => 'Please select at least one seat.',
        'seat_hold_id.required' => 'Your seat hold is required to create an order.',
    ];
}
```

----------------------------------------------------

Final Assessment

`StoreOrderRequest` is not production-ready for a cinema checkout flow. It validates basic row existence but does not enforce ownership, relational consistency, active seat hold requirements, showtime lockability, product sellability, idempotency, or bounded payload sizes. Because this request is directly involved in order creation and seat inventory correctness, the current implementation should be treated as blocking until strengthened.
