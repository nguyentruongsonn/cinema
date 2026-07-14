# File Review: SeatType.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Models/SeatType.php  
**Lines:** 24  
**Type:** Eloquent Model - Seat Type

---

## File Summary

`SeatType.php` is a small Eloquent model representing seat categories and their price surcharge. It exposes `name`, `surcharge`, and `color` as mass assignable, casts `surcharge` to decimal, and defines a `hasMany` relationship to seats.

Although the file is short, it participates in pricing and seat-layout correctness. Seat-type pricing must be treated as business-critical because incorrect surcharge configuration directly affects order totals.

---

## Overall Score

**Overall Score:** 6.2/10

**Decision:** REQUEST CHANGES

---

## Strengths

- Defines a clear `seats()` relationship.
- Uses a decimal cast for `surcharge`.
- The model is simple and easy to read.
- No raw SQL or dynamic query construction is present.

---

## Issues

### Issue #1

**Severity:** High  
**Category:** Business Logic / Money Integrity / Mass Assignment  
**Location:** `app/Models/SeatType.php:10-14`

**Problem**

`surcharge` is mass assignable:

```php
protected $fillable = [
    'name',
    'surcharge',
    'color',
];
```

**Why this matters**

Seat-type surcharge affects ticket pricing. If any controller or service passes request data directly into `SeatType::create()` or `$seatType->update()`, a caller with access to a weakly protected endpoint could modify pricing values.

This can cause:

- undercharging customers;
- overcharging customers;
- inconsistent ticket totals;
- reporting/reconciliation mismatches.

**How to fix**

Do not allow price-affecting fields to be updated through broad request mass assignment without explicit authorization, validation, and audit logging.

**Example**

```php
protected $guarded = ['id'];
```

Then only update pricing through a dedicated admin pricing service:

```php
$seatType->forceFill([
    'surcharge' => $validatedSurcharge,
])->save();
```

The service should validate actor permissions and write an audit log.

---

### Issue #2

**Severity:** High  
**Category:** Database Correctness / Validation  
**Location:** `app/Models/SeatType.php:10-18`

**Problem**

The model does not enforce a non-negative surcharge invariant.

```php
'surcharge',
```

```php
protected $casts = [
    'surcharge' => 'decimal:2',
];
```

**Why this matters**

A negative surcharge can reduce ticket totals and become an unintended discount path. Because pricing is financial logic, the invariant should not depend only on controller validation.

**How to fix**

Add a database constraint and application-level validation.

**Example**

```php
$table->decimal('surcharge', 10, 2)->default(0);
$table->check('surcharge >= 0');
```

Also validate in FormRequest/service:

```php
'surcharge' => ['required', 'numeric', 'min:0', 'max:999999.99'],
```

---

### Issue #3

**Severity:** Medium  
**Category:** Database Correctness / Duplicate Data  
**Location:** `app/Models/SeatType.php:10-14`

**Problem**

`name` is mass assignable, but the model does not show any normalization or uniqueness invariant.

```php
'name',
```

**Why this matters**

Seat types such as `VIP`, `Vip`, and `vip` can coexist unless the database prevents it. This creates pricing ambiguity and inconsistent admin/customer display behavior.

**How to fix**

Normalize names and add a unique database constraint.

**Example**

```php
$table->string('name')->unique();
```

Or store a normalized key:

```php
$table->string('code')->unique();
```

Use `code` for business logic and `name` for display.

---

### Issue #4

**Severity:** Medium  
**Category:** Data Integrity / Referenced Record Lifecycle  
**Location:** `app/Models/SeatType.php:20-23`

**Problem**

The model defines seats referencing a seat type:

```php
public function seats(): HasMany
{
    return $this->hasMany(Seat::class);
}
```

But there is no model-level deletion guard or documented lifecycle rule for seat types already assigned to seats.

**Why this matters**

Deleting or radically changing a seat type that is already referenced by seats can corrupt seat layouts, historical pricing interpretation, and future showtime pricing.

**How to fix**

Prevent deletion when seats exist, or use a lifecycle status such as `is_active` instead of destructive deletion.

**Example**

```php
protected static function booted(): void
{
    static::deleting(function (SeatType $seatType): void {
        if ($seatType->seats()->exists()) {
            throw new LogicException('Cannot delete a seat type that is assigned to seats.');
        }
    });
}
```

Prefer service-level policies plus database foreign-key restrictions.

---

### Issue #5

**Severity:** Medium  
**Category:** API / Data Validation / UI Safety  
**Location:** `app/Models/SeatType.php:13`

**Problem**

`color` is mass assignable with no model-level format expectation:

```php
'color',
```

**Why this matters**

If `color` is rendered by clients or templates, storing arbitrary strings can cause UI breakage. If any value is injected into inline styles without escaping, it can become a CSS injection vector.

**How to fix**

Validate color as a constrained format such as hex color.

**Example**

```php
'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
```

Consider a fixed palette if colors are business-controlled.

---

### Issue #6

**Severity:** Medium  
**Category:** Architecture / Pricing History  
**Location:** `app/Models/SeatType.php:12,16-18`

**Problem**

`SeatType` stores the current surcharge only:

```php
'surcharge',
```

```php
protected $casts = [
    'surcharge' => 'decimal:2',
];
```

There is no versioning, effective date, or historical pricing strategy visible in the model.

**Why this matters**

If a seat-type surcharge changes after tickets are sold, historical orders must still preserve the price charged at purchase time. Current surcharge must not be used to reinterpret past orders.

**How to fix**

Ensure order items/tickets store immutable price snapshots and consider versioned pricing records for future calculations.

**Example**

```php
seat_type_prices
- seat_type_id
- surcharge
- effective_from
- effective_until
```

At minimum, order items must store `unit_price`, `surcharge_snapshot`, and final calculated total.

---

### Issue #7

**Severity:** Low  
**Category:** Laravel Best Practice / Factory Support  
**Location:** `app/Models/SeatType.php:5-8`

**Problem**

The model does not use `HasFactory`.

```php
use Illuminate\Database\Eloquent\Model;
```

```php
class SeatType extends Model
```

**Why this matters**

Factories make pricing and seat-layout tests easier to write. This model affects seat and pricing scenarios, so first-class factory support is useful.

**How to fix**

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SeatType extends Model
{
    use HasFactory;
}
```

---

### Issue #8

**Severity:** Low  
**Category:** Maintainability / Static Analysis  
**Location:** `app/Models/SeatType.php:16-18`

**Problem**

The casts array is not typed.

```php
protected $casts = [
    'surcharge' => 'decimal:2',
];
```

**Why this matters**

Typed model properties improve static analysis and readability.

**How to fix**

```php
protected $casts = [
    'surcharge' => 'decimal:2',
];
```

If using Laravel versions supporting typed model properties safely, add:

```php
protected $casts = [
    'surcharge' => 'decimal:2',
];
```

This is minor; the more important fix is adding database-level financial constraints.

---

## Recommendations

### Immediate

1. Add non-negative database and validation constraints for `surcharge`.
2. Ensure only authorized admin pricing flows can change `surcharge`.
3. Add uniqueness/normalization for seat-type names or introduce a stable `code`.

### Short Term

4. Add deletion protection for seat types assigned to seats.
5. Validate `color` as a constrained UI-safe format.
6. Add audit logging for surcharge changes.

### Long Term

7. Introduce versioned seat-type pricing or immutable order/ticket price snapshots.
8. Add factories and tests around pricing calculations and seat-type lifecycle rules.

---

## Summary

`SeatType.php` is concise and readable, but it represents price-affecting domain data. The primary production risks are unrestricted mass assignment of `surcharge`, missing non-negative price invariants, weak uniqueness/normalization for seat-type identity, and no lifecycle protection for referenced seat types.

**Main concerns:**

- Price-affecting `surcharge` is mass assignable.
- No model/database invariant prevents negative surcharge values.
- No visible uniqueness/normalization for seat-type names.
- No guard against deleting seat types assigned to seats.
- `color` accepts arbitrary data unless constrained elsewhere.
- No visible strategy for historical pricing correctness.

**Status:** Request changes before production acceptance.