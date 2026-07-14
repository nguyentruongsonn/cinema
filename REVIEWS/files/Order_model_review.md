====================================================

File:
app/Models/Order.php

Overall Score:
4.6/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Defines explicit status constants for cancelled, pending, and confirmed/paid states.
- Uses typed relationship return types for user, showtime, promotion, order items, and payment.
- Casts monetary, JSON, integer, and datetime fields.
- Provides basic query scopes for status, order code, and user filtering.

----------------------------------------------------

Issues

### Issue #1

Severity:
Critical

Category:
Business Logic / Payment Correctness

Location:
app/Models/Order.php:17-18

Problem

`STATUS_CONFIRMED` and `STATUS_PAID` are both assigned the same value:

```php
public const STATUS_CONFIRMED = 2;
public const STATUS_PAID = 2; // Alias for confirmed in this context
```

Why this matters

Confirmed and paid are not always equivalent in a cinema booking system. An order can be confirmed before payment capture, paid after gateway confirmation, refunded after payment, or partially failed depending on provider flow. Collapsing confirmed and paid into the same integer makes state transitions ambiguous and can cause duplicate fulfillment, premature ticket issuance, incorrect revenue reporting, or inability to distinguish unpaid reservations from paid orders.

How to fix

Use a strict order state machine with distinct states, or separate order fulfillment status from payment status.

Example

```php
public const STATUS_CANCELLED = 0;
public const STATUS_PENDING = 1;
public const STATUS_CONFIRMED = 2;
public const STATUS_PAID = 3;
public const STATUS_EXPIRED = 4;
public const STATUS_REFUNDED = 5;
```

Better: use PHP enums and explicit transition methods.

----------------------------------------------------

### Issue #2

Severity:
Critical

Category:
Authorization / Mass Assignment

Location:
app/Models/Order.php:19-33

Problem

Payment and lifecycle fields are mass assignable:

```php
'status',
'payment_provider',
'payment_status',
'checkout_url',
'paid_at',
'cancelled_at',
'expired_at',
'total_amount',
'gateway_order_code',
```

Why this matters

These are privileged financial and lifecycle fields. If any controller or service uses request-driven mass assignment, a caller could mark an order as paid, alter totals, change gateway identifiers, set cancellation/expiration timestamps, or inject a checkout URL. In a booking system, this can directly cause revenue loss and unauthorized ticket issuance.

How to fix

Do not expose financial/lifecycle fields to generic mass assignment. Update them only through dedicated service methods after authorization and gateway verification.

Example

```php
protected $fillable = [
    'code',
    'user_id',
    'showtime_id',
    'payload',
];
```

Then use explicit domain methods:

```php
public function markPaid(string $provider, int $gatewayCode): void
{
    $this->forceFill([
        'status' => self::STATUS_PAID,
        'payment_provider' => $provider,
        'gateway_order_code' => $gatewayCode,
        'paid_at' => now(),
    ])->save();
}
```

----------------------------------------------------

### Issue #3

Severity:
Critical

Category:
Concurrency / Duplicate Payment

Location:
app/Models/Order.php:15-33

Problem

The model exposes mutable payment status fields but provides no idempotent state transition method:

```php
'status',
'payment_status',
'paid_at',
'gateway_order_code',
```

Why this matters

Payment webhooks and client return callbacks are commonly delivered multiple times or concurrently. Without an atomic transition method such as "mark paid only if currently pending", multiple workers can process the same order, create duplicate tickets, duplicate booking fulfillment, or double-count revenue.

How to fix

Implement idempotent, atomic state transitions in a service using a transaction and row lock.

Example

```php
DB::transaction(function () use ($orderId) {
    $order = Order::whereKey($orderId)->lockForUpdate()->firstOrFail();

    if ($order->status === Order::STATUS_PAID) {
        return;
    }

    if ($order->status !== Order::STATUS_PENDING) {
        throw new DomainException('Order cannot be paid from current state.');
    }

    $order->forceFill([
        'status' => Order::STATUS_PAID,
        'paid_at' => now(),
    ])->save();

    // fulfill seats/tickets exactly once inside the same transaction
});
```

----------------------------------------------------

### Issue #4

Severity:
High

Category:
Database Correctness / Unique Constraints

Location:
app/Models/Order.php:20-21, 75-78

Problem

The model relies on order code lookup:

```php
'code',
'gateway_order_code',
```

```php
public function scopeByOrderCode($query, $code)
{
    return $query->where('code', $code);
}
```

There is no model-level indication that `code` or `gateway_order_code` is unique.

Why this matters

Order codes and gateway order codes are identifiers. Duplicate values can cause wrong-order lookup, payment reconciliation errors, data exposure, or payment confirmation being applied to the wrong order.

How to fix

Enforce uniqueness at the database level.

Example

```php
$table->string('code')->unique();
$table->unsignedBigInteger('gateway_order_code')->nullable()->unique();
```

If gateway codes are unique only per provider:

```php
$table->unique(['payment_provider', 'gateway_order_code']);
```

----------------------------------------------------

### Issue #5

Severity:
High

Category:
Database Correctness / Relationship Mapping

Location:
app/Models/Order.php:55-58

Problem

The model defines a promotion relationship:

```php
public function promotion(): BelongsTo
{
    return $this->belongsTo(Promotion::class);
}
```

but `promotion_id` is not present in `$fillable`:

```php
protected $fillable = [
    'code',
    'gateway_order_code',
    'user_id',
    'showtime_id',
    ...
];
```

Why this matters

This is inconsistent model design. Either the order has a `promotion_id` foreign key and it should be deliberately managed, or the relationship is stale/dead code. Inconsistent relationships create confusion in services and resources and can hide bugs in promotion/revenue calculations.

How to fix

If orders store a promotion foreign key, add a controlled assignment path and database foreign key. If not, remove the relationship.

Example

```php
'promotion_id',
```

with:

```php
$table->foreignId('promotion_id')->nullable()->constrained()->nullOnDelete();
```

----------------------------------------------------

### Issue #6

Severity:
High

Category:
Business Logic / Monetary Correctness

Location:
app/Models/Order.php:24, 35-38

Problem

`total_amount` is mass assignable and only cast to decimal:

```php
'total_amount',
```

```php
'total_amount' => 'decimal:2',
```

There is no invariant preventing negative totals or ensuring the total matches order items, seats, combos, fees, discounts, and promotions.

Why this matters

Order totals are financial data. If invalid totals are persisted, the system can undercharge, overcharge, misreport revenue, or fail payment gateway reconciliation.

How to fix

Calculate totals server-side from authoritative pricing data and enforce database constraints.

Example

```php
$table->decimal('total_amount', 12, 2);
$table->check('total_amount >= 0');
```

Do not trust request payload totals. Compute through pricing services inside the order transaction.

----------------------------------------------------

### Issue #7

Severity:
High

Category:
Security / Sensitive Data Exposure

Location:
app/Models/Order.php:25, 35-39

Problem

The model stores arbitrary JSON payload:

```php
'payload',
```

```php
'payload' => 'json',
```

Why this matters

A generic order payload can accidentally persist PII, payment gateway data, signed callback payloads, customer contact details, seat details, internal pricing logic, or tokens. If returned by API resources or logs, this can leak sensitive data.

How to fix

Replace generic `payload` storage with explicit columns or a strict value object schema. Never store raw payment secrets or unbounded request payloads.

Example

```php
'payload' => [
    'selected_seats' => [...],
    'pricing_snapshot' => [...],
]
```

Validate and redact before persistence.

----------------------------------------------------

### Issue #8

Severity:
High

Category:
State Machine / Correctness

Location:
app/Models/Order.php:15-18, 26-32

Problem

The model defines only three order states and timestamp fields:

```php
public const STATUS_CANCELLED = 0;
public const STATUS_PENDING = 1;
public const STATUS_CONFIRMED = 2;
```

```php
'paid_at',
'cancelled_at',
'expired_at',
```

There are no domain methods ensuring timestamps match status.

Why this matters

The model allows impossible states such as:
- `status = pending` with `paid_at` set.
- `status = paid` with `cancelled_at` set.
- `status = cancelled` with no `cancelled_at`.
- `status = paid` and `expired_at` set.

Impossible states break booking cleanup, revenue reports, user order history, seat release logic, and customer support workflows.

How to fix

Add explicit transition methods and validate transitions in services.

Example

```php
public function cancel(): void
{
    if ($this->status === self::STATUS_PAID) {
        throw new DomainException('Paid orders cannot be cancelled without refund.');
    }

    $this->forceFill([
        'status' => self::STATUS_CANCELLED,
        'cancelled_at' => now(),
    ])->save();
}
```

----------------------------------------------------

### Issue #9

Severity:
Medium

Category:
Database Correctness / Referential Integrity

Location:
app/Models/Order.php:45-68

Problem

The model defines critical relationships:

```php
public function user(): BelongsTo
public function showtime(): BelongsTo
public function orderItems(): HasMany
public function payment(): HasOne
```

The model does not document deletion behavior or lifecycle policy for related rows.

Why this matters

Orders are financial/audit records. Deleting users, showtimes, order items, or payments without a clear policy can destroy auditability or leave orphaned financial data. In production, orders should generally be immutable records with explicit retention rules.

How to fix

Define database foreign key behavior intentionally:
- user deletion should likely `restrict` or `nullOnDelete` with immutable customer snapshot;
- showtime deletion should likely be restricted if orders exist;
- payment deletion should be restricted;
- order items should cascade only if deleting draft orders is allowed.

----------------------------------------------------

### Issue #10

Severity:
Medium

Category:
Performance / Database Indexing

Location:
app/Models/Order.php:70-83

Problem

The model exposes scopes filtering by status, code, and user:

```php
public function scopeByStatus($query, $status)
public function scopeByOrderCode($query, $code)
public function scopeByUser($query, $userId)
```

There is no indication of supporting indexes.

Why this matters

Orders grow quickly in a cinema booking platform. Admin dashboards, user order history, cleanup jobs, and payment reconciliation will frequently filter by status, user, code, gateway code, and expiration time. Missing indexes will create slow queries and table scans.

How to fix

Add indexes aligned with query patterns.

Example

```php
$table->index(['user_id', 'created_at']);
$table->index(['status', 'expired_at']);
$table->index(['payment_status', 'created_at']);
$table->unique('code');
$table->unique(['payment_provider', 'gateway_order_code']);
```

----------------------------------------------------

### Issue #11

Severity:
Medium

Category:
API / Security

Location:
app/Models/Order.php:29

Problem

`checkout_url` is mass assignable:

```php
'checkout_url',
```

Why this matters

Checkout URLs redirect users into payment flow. If this value can be influenced by input or compromised internal code, users can be redirected to phishing or attacker-controlled payment pages.

How to fix

Only set checkout URLs from trusted payment gateway responses after verifying the provider and expected host.

Example

```php
if (! str_starts_with($checkoutUrl, config('payments.payos.checkout_host'))) {
    throw new UnexpectedValueException('Untrusted checkout URL.');
}
```

----------------------------------------------------

### Issue #12

Severity:
Medium

Category:
Laravel Best Practices / Type Safety

Location:
app/Models/Order.php:70-83

Problem

Query scopes do not type-hint their query parameter or return type:

```php
public function scopeByStatus($query, $status)
```

Why this matters

Untyped scopes reduce static analysis support and make query composition easier to misuse.

How to fix

Use `Builder` type hints and stricter parameter types.

Example

```php
use Illuminate\Database\Eloquent\Builder;

public function scopeByStatus(Builder $query, int $status): Builder
{
    return $query->where('status', $status);
}
```

----------------------------------------------------

### Issue #13

Severity:
Medium

Category:
Validation / Input Correctness

Location:
app/Models/Order.php:70-83

Problem

Scopes accept untyped arbitrary inputs:

```php
public function scopeByStatus($query, $status)
public function scopeByOrderCode($query, $code)
public function scopeByUser($query, $userId)
```

Why this matters

Although Eloquent parameter binding prevents SQL injection here, arbitrary values can still cause invalid queries, inconsistent API behavior, and hard-to-debug bugs. For example, non-integer user IDs or invalid status values should be rejected at validation boundaries.

How to fix

Validate before calling scopes and use stricter parameter types.

Example

```php
public function scopeByUser(Builder $query, int $userId): Builder
{
    return $query->where('user_id', $userId);
}
```

----------------------------------------------------

### Issue #14

Severity:
Low

Category:
Clean Code / Naming

Location:
app/Models/Order.php:18

Problem

The comment explains that paid is an alias for confirmed:

```php
public const STATUS_PAID = 2; // Alias for confirmed in this context
```

Why this matters

This comment documents a dangerous domain shortcut rather than fixing it. Payment and confirmation are separate business concepts. Comments should not normalize ambiguous state modeling in financial code.

How to fix

Remove the alias and model payment status explicitly.

----------------------------------------------------

### Issue #15

Severity:
Low

Category:
Maintainability / Type Documentation

Location:
app/Models/Order.php:11-84

Problem

The model has no PHPDoc annotations for financial fields and relationships.

Why this matters

Orders are central financial records. Static analysis should understand dynamic fields such as `total_amount`, `status`, `payment_status`, `paid_at`, `expired_at`, and relationships to reduce accidental misuse.

How to fix

Add PHPDoc or use Laravel IDE Helper/static analysis conventions.

Example

```php
/**
 * @property int $id
 * @property string $code
 * @property int $user_id
 * @property int $showtime_id
 * @property string $total_amount
 * @property int $status
 * @property string|null $payment_status
 * @property \Illuminate\Support\Carbon|null $paid_at
 */
class Order extends Model
{
    // ...
}
```

----------------------------------------------------

Final Assessment

`Order` is under-modeled for a production cinema booking/payment domain. The current model exposes critical financial and lifecycle fields through mass assignment, conflates confirmed and paid states, lacks atomic/idempotent state transitions, and does not express invariants required to prevent duplicate payment fulfillment or inconsistent order states. This should be redesigned around explicit state transitions, database constraints, and payment-safe service methods before production use.
