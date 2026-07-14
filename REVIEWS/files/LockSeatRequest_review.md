====================================================

File:
app/Http/Requests/LockSeatRequest.php

Overall Score:
4.8/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Uses a dedicated `FormRequest` instead of raw controller validation.
- Requires `showtime_id`.
- Requires `seat_ids` to be an array with at least one entry.
- Uses `distinct` on individual seat IDs, preventing duplicate seat IDs in the same payload.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Authorization / Business Logic

Location:
app/Http/Requests/LockSeatRequest.php:9-12

Problem

The request authorizes every caller:

```php
public function authorize(): bool
{
    return true;
}
```

Why this matters

Seat locking is a money-flow operation. Allowing the request object to authorize every caller means authorization depends entirely on route middleware or controller/service code that is not visible in this file. In production booking systems, seat locks must be restricted to authenticated users or a clearly defined actor context. Otherwise, anonymous or unauthorized users may be able to lock inventory, perform denial-of-service against available seats, or create abandoned holds.

How to fix

Move explicit actor checks into `authorize()` or ensure the route uses mandatory authentication and keep `authorize()` aligned with that contract.

Example

Before

```php
public function authorize(): bool
{
    return true;
}
```

After

```php
public function authorize(): bool
{
    return $this->user() !== null;
}
```

For admin/staff-only lock paths, check a policy or permission instead.

----------------------------------------------------

### Issue #2

Severity:
Critical

Category:
Business Logic / Database Correctness / IDOR

Location:
app/Http/Requests/LockSeatRequest.php:17-19

Problem

The validation confirms that the showtime exists and that each seat exists, but it does not validate that the selected seats belong to the screen/theater for the requested showtime.

```php
'showtime_id' => 'required|integer|exists:showtimes,id',
'seat_ids' => 'required|array|min:1',
'seat_ids.*' => 'integer|distinct|exists:seats,id',
```

Why this matters

This can allow a client to submit a valid `showtime_id` and valid `seat_ids` from a different screen/theater. If downstream service code does not repeat this validation perfectly, the system can lock or book seats that do not belong to the showtime. That is a direct booking correctness issue and can cause corrupted seat inventory, customer disputes, and impossible tickets.

How to fix

Validate seat membership against the selected showtime's screen. The rule should enforce that every `seat_ids.*` belongs to the screen associated with `showtime_id`.

Example

```php
use Illuminate\Validation\Rule;
use App\Models\Showtime;

public function rules(): array
{
    $showtime = Showtime::query()->find($this->input('showtime_id'));

    return [
        'showtime_id' => ['required', 'integer', Rule::exists('showtimes', 'id')],
        'seat_ids' => ['required', 'array', 'min:1', 'max:10'],
        'seat_ids.*' => [
            'integer',
            'distinct',
            Rule::exists('seats', 'id')->where(fn ($query) =>
                $showtime ? $query->where('screen_id', $showtime->screen_id) : $query
            ),
        ],
    ];
}
```

Prefer a custom validation rule if the relationship is more complex.

----------------------------------------------------

### Issue #3

Severity:
High

Category:
Performance / Abuse Prevention

Location:
app/Http/Requests/LockSeatRequest.php:18

Problem

The request allows an unbounded number of seats:

```php
'seat_ids' => 'required|array|min:1',
```

Why this matters

A malicious or buggy client can submit thousands of seat IDs. Even with validation, this can cause large `exists` queries, memory pressure, lock contention, oversized database operations, or denial-of-service behavior. Seat locking should have a realistic upper bound based on business rules.

How to fix

Add a strict maximum number of seats per lock/order.

Example

```php
'seat_ids' => ['required', 'array', 'min:1', 'max:10'],
```

The exact maximum should come from a configuration value or booking policy, not a hard-coded magic number.

----------------------------------------------------

### Issue #4

Severity:
High

Category:
Concurrency / Idempotency

Location:
app/Http/Requests/LockSeatRequest.php:16-20

Problem

The request does not require any idempotency key or client operation identifier for seat locking.

```php
return [
    'showtime_id' => 'required|integer|exists:showtimes,id',
    'seat_ids' => 'required|array|min:1',
    'seat_ids.*' => 'integer|distinct|exists:seats,id',
];
```

Why this matters

Seat locking is vulnerable to retries, double-clicks, mobile network retries, and concurrent duplicate requests. Without an idempotency key, the backend has no request-level contract to safely deduplicate lock attempts. This can create duplicate holds, confusing expiry behavior, or repeated lock churn for the same user/session.

How to fix

Require an idempotency key for lock operations and enforce it server-side with a unique key and transactional processing.

Example

```php
'idempotency_key' => ['required', 'string', 'max:100'],
```

Then persist and enforce it atomically in the lock service.

----------------------------------------------------

### Issue #5

Severity:
Medium

Category:
Validation / Business Logic

Location:
app/Http/Requests/LockSeatRequest.php:17-19

Problem

The request does not validate showtime state, showtime timing, or seat availability.

```php
'showtime_id' => 'required|integer|exists:showtimes,id',
'seat_ids.*' => 'integer|distinct|exists:seats,id',
```

Why this matters

A valid showtime ID is not necessarily lockable. The showtime may be inactive, cancelled, already started, sold out, or closed for booking. A valid seat ID is not necessarily available. Even if availability must be enforced transactionally in the service layer, the request should express the lock contract and reject obviously invalid inputs as early as possible.

How to fix

Add validation rules or after-validation checks for lockable showtime state. Availability must still be checked inside a transaction with database locks in the service layer.

Example

```php
public function withValidator($validator): void
{
    $validator->after(function ($validator) {
        // Load showtime and reject cancelled/started/inactive showtimes.
        // Keep final availability checks transactional in the service.
    });
}
```

----------------------------------------------------

### Issue #6

Severity:
Medium

Category:
Validation / API Consistency

Location:
app/Http/Requests/LockSeatRequest.php:23-26

Problem

The class defines an empty custom messages array:

```php
public function messages(): array
{
    return [];
}
```

Why this matters

An empty `messages()` method adds noise without changing behavior. It suggests customization exists when it does not. It also misses an opportunity to provide consistent domain-specific API errors for a user-facing booking action.

How to fix

Remove the method if default messages are acceptable, or provide clear domain messages for lock-specific fields.

Example

```php
public function messages(): array
{
    return [
        'seat_ids.required' => 'Please select at least one seat.',
        'seat_ids.max' => 'Too many seats selected for one booking.',
    ];
}
```

----------------------------------------------------

### Issue #7

Severity:
Medium

Category:
Maintainability / Laravel Best Practices

Location:
app/Http/Requests/LockSeatRequest.php:17-19

Problem

Validation rules are string-based and contain important booking constraints inline.

```php
'showtime_id' => 'required|integer|exists:showtimes,id',
'seat_ids' => 'required|array|min:1',
'seat_ids.*' => 'integer|distinct|exists:seats,id',
```

Why this matters

String rules are harder to refactor and extend when the validation becomes relational and policy-driven. Booking validation is a core domain area and will likely need custom rule objects for seat/showtime compatibility, booking windows, limits, and availability pre-checks.

How to fix

Use array syntax with `Rule` objects and custom domain validation rules.

Example

```php
'showtime_id' => ['required', 'integer', Rule::exists('showtimes', 'id')],
'seat_ids' => ['required', 'array', 'min:1', 'max:' . config('booking.max_seats_per_order')],
'seat_ids.*' => ['integer', 'distinct', new SeatBelongsToShowtime($this->input('showtime_id'))],
```

----------------------------------------------------

Final Assessment

`LockSeatRequest` is too weak for a production cinema booking system. It validates only existence and basic array shape, but it does not enforce seat/showtime relationship, actor authorization, maximum seat count, idempotency, or lockability constraints. Because seat locking is a concurrency-sensitive money-flow boundary, this request should be strengthened before approval.
