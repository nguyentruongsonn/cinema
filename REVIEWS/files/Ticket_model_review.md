# File Review: Ticket.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Models/Ticket.php  
**Lines:** 132  
**Type:** Eloquent Model - Ticket

---

## File Summary

`Ticket.php` represents a ticket for a specific seat in a showtime. It defines ticket status constants, relationships to order/user/showtime/seat, basic status scopes, helper predicates, mutating methods for use/cancel/refund, and a ticket-code generator.

This is a money- and access-control-sensitive model. Tickets are the final entitlement customers receive after payment. The current implementation is too permissive and lacks the atomicity, validation, uniqueness guarantees, lifecycle controls, and audit behavior required for production ticketing.

---

## Overall Score

**Overall Score:** 4.7/10

**Decision:** REQUEST CHANGES

---

## Strengths

- Uses `HasFactory`, which supports test setup.
- Defines explicit relationships to `Order`, `User`, `Showtime`, and `Seat`.
- Uses named status constants instead of repeated raw status strings in most places.
- Casts `checked_in_at` to datetime.
- Provides helper methods such as `isValid()`, `isUsed()`, and `isCancelled()` for readability.

---

## Issues

### Issue #1

**Severity:** Critical  
**Category:** Security / Mass Assignment / Ticket Integrity  
**Location:** `app/Models/Ticket.php:23-32`

**Problem**

The model makes all ticket identity, entitlement, and lifecycle fields mass assignable:

```php
protected $fillable = [
    'order_id',
    'user_id',
    'showtime_id',
    'seat_id',
    'ticket_code',
    'qr_code',
    'status',
    'checked_in_at',
];
```

This includes:

- `order_id`
- `user_id`
- `showtime_id`
- `seat_id`
- `ticket_code`
- `qr_code`
- `status`
- `checked_in_at`

**Why this matters**

Tickets are customer entitlements. If any controller/service uses request-wide mass assignment, a caller could create or alter a ticket for another user, another order, a different seat, or mark a ticket as used/cancelled/refunded.

This can cause:

- unauthorized ticket issuance;
- ticket ownership takeover;
- QR/check-in fraud;
- refund/status manipulation;
- duplicate seat entitlements;
- audit trail corruption.

**How to fix**

Do not expose ticket identity or lifecycle fields through generic mass assignment. Create tickets only through a dedicated fulfillment service after payment confirmation and inside a transaction.

**Example**

```php
protected $guarded = [
    'id',
    'order_id',
    'user_id',
    'showtime_id',
    'seat_id',
    'ticket_code',
    'qr_code',
    'status',
    'checked_in_at',
];
```

Or keep no fillable fields and use an explicit factory/domain service:

```php
Ticket::forceCreate([
    'order_id' => $order->id,
    'user_id' => $order->user_id,
    'showtime_id' => $showtime->id,
    'seat_id' => $seat->id,
    'ticket_code' => TicketCode::generate(),
    'status' => Ticket::STATUS_VALID,
]);
```

---

### Issue #2

**Severity:** Critical  
**Category:** Concurrency / Database Correctness / Duplicate Booking  
**Location:** `app/Models/Ticket.php:23-32,59-62`

**Problem**

The model allows `showtime_id` and `seat_id` to be assigned but does not define any invariant preventing duplicate tickets for the same seat/showtime.

```php
'showtime_id',
'seat_id',
```

```php
public function seat(): BelongsTo
{
    return $this->belongsTo(Seat::class);
}
```

**Why this matters**

A cinema booking system must guarantee that one physical seat for one showtime cannot be issued to multiple customers. Without a database-level unique constraint and transactional issuance, concurrent fulfillment/payment flows can create duplicate valid tickets for the same seat/showtime.

This is a direct production revenue and customer trust issue.

**How to fix**

Add a database-level unique constraint for active ticket entitlement. Depending on refund/cancel semantics, use a composite unique key or a partial unique index equivalent.

For MySQL, a practical approach is to prevent duplicate tickets regardless of status and create explicit replacement/refund records instead:

```php
$table->unique(['showtime_id', 'seat_id']);
```

If cancelled/refunded tickets should allow reissue, model that explicitly with a separate entitlement table or generated active-key column.

Ticket creation must occur inside the same transaction that confirms order/payment and releases seat holds.

---

### Issue #3

**Severity:** Critical  
**Category:** Concurrency / Check-in Fraud / Atomicity  
**Location:** `app/Models/Ticket.php:103-109`

**Problem**

`markAsUsed()` blindly updates the ticket:

```php
public function markAsUsed(): void
{
    $this->update([
        'status' => self::STATUS_USED,
        'checked_in_at' => now(),
    ]);
}
```

It does not verify the current status in the database, does not use a conditional update, does not lock the row, and does not prevent repeated scans from overwriting `checked_in_at`.

**Why this matters**

At the gate, QR scans are concurrent and security-sensitive. A reused QR code can be accepted multiple times if multiple scanners race or if a used ticket is updated again without detecting prior use.

This can cause unauthorized entry and unreliable audit data.

**How to fix**

Use an atomic conditional update and fail if the ticket is not currently valid.

**Example**

```php
public function markAsUsed(): bool
{
    return static::whereKey($this->getKey())
        ->where('status', self::STATUS_VALID)
        ->whereNull('checked_in_at')
        ->update([
            'status' => self::STATUS_USED,
            'checked_in_at' => now(),
        ]) === 1;
}
```

Then the caller must reject scans when this returns `false`.

---

### Issue #4

**Severity:** High  
**Category:** Business Logic / Invalid State Transitions  
**Location:** `app/Models/Ticket.php:103-119`

**Problem**

The mutation methods allow status changes without checking valid state transitions:

```php
public function markAsUsed(): void
{
    $this->update([
        'status' => self::STATUS_USED,
        'checked_in_at' => now(),
    ]);
}

public function cancel(): void
{
    $this->update(['status' => self::STATUS_CANCELLED]);
}

public function refund(): void
{
    $this->update(['status' => self::STATUS_REFUNDED]);
}
```

A used ticket can be cancelled or refunded. A cancelled/refunded ticket can be marked used. A refunded ticket can be cancelled.

**Why this matters**

Ticket state transitions affect entry control, refund policy, revenue reporting, and fraud prevention. Invalid transitions can produce accounting inconsistencies and customer-service disputes.

**How to fix**

Implement an explicit state machine.

**Example**

```php
public function cancel(): bool
{
    return static::whereKey($this->getKey())
        ->where('status', self::STATUS_VALID)
        ->update(['status' => self::STATUS_CANCELLED]) === 1;
}

public function refund(): bool
{
    return static::whereKey($this->getKey())
        ->whereIn('status', [self::STATUS_VALID, self::STATUS_CANCELLED])
        ->update(['status' => self::STATUS_REFUNDED]) === 1;
}
```

Also log every transition.

---

### Issue #5

**Severity:** High  
**Category:** Security / Predictable Identifier / Race Condition  
**Location:** `app/Models/Ticket.php:124-131`

**Problem**

Ticket codes are generated using `md5(uniqid(mt_rand(), true))` and checked with a non-atomic existence query:

```php
do {
    $code = 'TKT-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 12));
} while (self::where('ticket_code', $code)->exists());
```

**Why this matters**

`uniqid()` and `mt_rand()` are not suitable for security-sensitive ticket identifiers. The existence check is also race-prone: two concurrent requests can generate the same code, both see it as available, then both insert unless a unique database constraint rejects one.

Ticket codes can be used for lookup or QR generation. Weak identifiers increase fraud and enumeration risk.

**How to fix**

Use cryptographically secure randomness and enforce a unique database constraint on `ticket_code`.

**Example**

```php
use Illuminate\Support\Str;

public static function generateTicketCode(): string
{
    return 'TKT-' . strtoupper(Str::random(16));
}
```

Then handle duplicate-key exceptions/retry during insert.

---

### Issue #6

**Severity:** High  
**Category:** Security / Sensitive Data Exposure  
**Location:** `app/Models/Ticket.php:29`

**Problem**

`qr_code` is mass assignable:

```php
'qr_code',
```

The model does not clarify whether this stores a token, payload, URL, or rendered QR content.

**Why this matters**

QR data is an access credential. If stored as plain reusable data and serialized through APIs, it can be leaked, copied, replayed, or tampered with. Making it mass assignable allows untrusted input to define a check-in credential.

**How to fix**

Do not accept QR code payloads from request data. Generate QR tokens server-side, store only a hashed verifier if possible, and expose QR content only to the ticket owner.

**Example**

```php
$plainToken = Str::random(64);

$ticket->forceFill([
    'qr_token_hash' => hash('sha256', $plainToken),
])->save();
```

The rendered QR should contain a signed, short-lived, or revocable token.

---

### Issue #7

**Severity:** Medium  
**Category:** Authorization / IDOR  
**Location:** `app/Models/Ticket.php:81-84`

**Problem**

The model has a `forUser` scope:

```php
public function scopeForUser($query, int $userId)
{
    return $query->where('user_id', $userId);
}
```

This is useful, but the model still exposes `user_id` as mass assignable and has no ownership invariant tying `user_id` to `order.user_id`.

**Why this matters**

A ticket can be assigned to a user that does not own the order. This creates inconsistent authorization behavior: APIs that authorize by `ticket.user_id` and APIs that authorize by `ticket.order.user_id` may disagree.

**How to fix**

Set `user_id` from the order during ticket creation and validate consistency.

**Example**

```php
if ($ticket->order->user_id !== $ticket->user_id) {
    throw new LogicException('Ticket user must match order user.');
}
```

Better: avoid storing redundant `user_id` unless performance requires it. Derive the owner through `order`.

---

### Issue #8

**Severity:** Medium  
**Category:** Laravel Best Practice / Static Analysis  
**Location:** `app/Models/Ticket.php:66-84`

**Problem**

All query scopes are untyped:

```php
public function scopeValid($query)
public function scopeUsed($query)
public function scopeCancelled($query)
public function scopeForUser($query, int $userId)
```

**Why this matters**

Untyped scopes reduce static analysis quality and make API filtering behavior harder to refactor safely.

**How to fix**

```php
use Illuminate\Database\Eloquent\Builder;

public function scopeValid(Builder $query): Builder
{
    return $query->where('status', self::STATUS_VALID);
}
```

Apply the same to all scopes.

---

### Issue #9

**Severity:** Medium  
**Category:** API / Data Exposure / Serialization  
**Location:** `app/Models/Ticket.php:23-36`

**Problem**

The model defines no `$hidden`, `$visible`, or API Resource protections for sensitive ticket fields.

```php
protected $fillable = [
    ...
    'ticket_code',
    'qr_code',
    ...
];
```

**Why this matters**

If controllers return raw ticket models, `ticket_code` and `qr_code` may be exposed in APIs where they are not needed. Ticket credentials should have strict audience and lifecycle controls.

**How to fix**

Use API Resources for ticket responses and consider hiding raw QR credentials by default.

**Example**

```php
protected $hidden = [
    'qr_code',
];
```

Still prefer Resource classes over model-level response shaping.

---

### Issue #10

**Severity:** Medium  
**Category:** Database Correctness / Missing Audit Fields  
**Location:** `app/Models/Ticket.php:103-119`

**Problem**

Ticket status mutations do not record who performed the action or why.

```php
$this->update(['status' => self::STATUS_CANCELLED]);
$this->update(['status' => self::STATUS_REFUNDED]);
```

**Why this matters**

Ticket cancellation, refund, and check-in are audit-sensitive operations. Production systems need to know:

- which staff member scanned the ticket;
- which admin cancelled/refunded it;
- reason codes;
- source channel;
- timestamps for every transition.

Without audit metadata, fraud investigations and customer disputes are difficult.

**How to fix**

Move these operations into services that accept actor context and write audit logs.

**Example**

```php
$ticketStatusService->markUsed($ticket, $scannerUser, $gateId);
$ticketStatusService->refund($ticket, $adminUser, $reason);
```

---

### Issue #11

**Severity:** Medium  
**Category:** Business Logic / Incomplete Status Modeling  
**Location:** `app/Models/Ticket.php:17-21`

**Problem**

Ticket statuses are string constants:

```php
const STATUS_VALID = 'valid';
const STATUS_USED = 'used';
const STATUS_CANCELLED = 'cancelled';
const STATUS_REFUNDED = 'refunded';
```

There is no enum cast, no database constraint, and no single list of valid statuses for validation.

**Why this matters**

Any typo stored in `status` breaks scopes and helper methods. Because `status` is mass assignable, invalid statuses are especially likely if upstream validation is incomplete.

**How to fix**

Use a PHP enum and database constraint where possible.

**Example**

```php
enum TicketStatus: string
{
    case Valid = 'valid';
    case Used = 'used';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
```

Then cast:

```php
protected $casts = [
    'checked_in_at' => 'datetime',
    'status' => TicketStatus::class,
];
```

---

### Issue #12

**Severity:** Low  
**Category:** Clean Code / Comments  
**Location:** `app/Models/Ticket.php:9-12,17,42,64,86,121-123`

**Problem**

The model includes phase/comment markers:

```php
/**
 * Phase 3: Ticket Model
 * Represents a ticket for a specific seat in a showtime
 */
```

and section comments:

```php
// Status constants
// Relationships
// Scopes
// Helper methods
```

**Why this matters**

Comments such as `Phase 3` are implementation/project-history noise and should not live in production domain models. Section comments add little value when method names already communicate intent.

**How to fix**

Remove stale phase comments. Keep only comments that explain non-obvious business rules.

---

## Recommendations

### Immediate

1. Remove ticket identity/status/QR fields from generic mass assignment.
2. Add a unique database constraint preventing duplicate seat/showtime tickets.
3. Replace `markAsUsed()`, `cancel()`, and `refund()` with atomic state-transition methods.
4. Generate ticket codes using cryptographically secure randomness and enforce unique database constraints.
5. Treat QR data as sensitive credential material; do not mass assign it.

### Short Term

6. Add typed scopes using `Builder`.
7. Replace string status constants with an enum-backed status model.
8. Add audit logging for check-in, cancellation, and refund transitions.
9. Ensure `ticket.user_id` always matches `ticket.order.user_id`, or remove redundant `user_id`.
10. Hide sensitive QR fields from default serialization and use API Resources.

### Long Term

11. Implement a full ticket entitlement lifecycle service.
12. Add idempotent ticket issuance tied to paid orders.
13. Add tests for duplicate seat issuance, concurrent check-in, status transition rules, QR replay, and refund/cancel policies.
14. Consider signed/revocable QR tokens with scan attempt logging.

---

## Summary

`Ticket.php` contains the core customer entitlement model, but it is not production-safe in its current form. The most serious risks are broad mass assignment of ticket entitlement fields, missing duplicate seat/showtime guarantees, non-atomic check-in, invalid lifecycle transitions, and weak ticket-code/QR handling.

**Main concerns:**

- Ticket identity, ownership, status, QR, and check-in fields are mass assignable.
- No model/database invariant preventing duplicate tickets for the same seat/showtime.
- Check-in is not atomic and can accept repeated/concurrent scans.
- Status transitions are unconstrained.
- Ticket-code generation is weak and race-prone.
- QR data is treated as a normal assignable field.
- Missing audit context for check-in/cancel/refund.

**Status:** Request changes before production acceptance.