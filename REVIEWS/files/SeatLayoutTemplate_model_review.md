# File Review: SeatLayoutTemplate.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Models/SeatLayoutTemplate.php  
**Lines:** 31  
**Type:** Eloquent Model - Seat Layout Template

---

## File Summary

`SeatLayoutTemplate.php` is an Eloquent model representing reusable seat layout definitions. It stores template metadata, seat matrix fields, row counts by seat type, description, and status. It exposes one active scope.

This file is small, but it is high-impact because seat layout templates drive physical seat generation and therefore booking correctness. The model currently treats layout structure as simple mass-assignable scalar fields and does not define relationships, casts for matrix data, invariants, immutability rules, or lifecycle protections.

---

## Overall Score

**Overall Score:** 4.9/10

**Decision:** REQUEST CHANGES

---

## Strengths

- The model is simple and easy to read.
- Numeric row-count fields are cast to integers.
- `status` is cast to boolean.
- An `active` scope exists for filtering enabled templates.

---

## Issues

### Issue #1

**Severity:** High  
**Category:** Business Logic / Booking Integrity / Mass Assignment  
**Location:** `app/Models/SeatLayoutTemplate.php:9-18`

**Problem**

All layout-defining fields are mass assignable:

```php
protected $fillable = [
    'template_name',
    'seat_matrix',
    'regular_seat_rows',
    'vip_seat_rows',
    'couple_seat_rows',
    'custom_matrix',
    'description',
    'status',
];
```

This includes the physical seat layout structure:

- `seat_matrix`
- `regular_seat_rows`
- `vip_seat_rows`
- `couple_seat_rows`
- `custom_matrix`
- `status`

There is no model-level protection against generic request-wide assignment changing layout definitions.

**Why this matters**

Seat layout templates are booking infrastructure, not ordinary content. If a template is changed after screens/seats have been generated from it, the system can diverge:

- template says one layout;
- generated seats represent another layout;
- screens reference stale template data;
- future showtimes may expose invalid seating maps.

This can cause wrong seat availability, broken seat maps, incorrect pricing by seat type, and booking failures.

**How to fix**

Do not mass assign layout structure through arbitrary arrays. Use a dedicated service/DTO that validates and applies layout changes transactionally.

**Example**

Before:

```php
protected $fillable = [
    'template_name',
    'seat_matrix',
    'regular_seat_rows',
    'vip_seat_rows',
    'couple_seat_rows',
    'custom_matrix',
    'description',
    'status',
];
```

After:

```php
protected $fillable = [
    'template_name',
    'description',
];

protected $guarded = [
    'id',
    'seat_matrix',
    'regular_seat_rows',
    'vip_seat_rows',
    'couple_seat_rows',
    'custom_matrix',
    'status',
];
```

Then update layout fields only through a trusted service after validation.

---

### Issue #2

**Severity:** High  
**Category:** Database Correctness / Data Modeling  
**Location:** `app/Models/SeatLayoutTemplate.php:11,15,20-25`

**Problem**

`seat_matrix` and `custom_matrix` are not cast:

```php
'seat_matrix',
'custom_matrix',
```

The casts only include row counts and status:

```php
protected $casts = [
    'regular_seat_rows' => 'integer',
    'vip_seat_rows' => 'integer',
    'couple_seat_rows' => 'integer',
    'status' => 'boolean',
];
```

If these columns store JSON or structured matrix data, the model does not cast them to arrays.

**Why this matters**

Seat matrices are structured data. Without casts, callers may receive strings in some contexts and arrays in others depending on manual encoding/decoding elsewhere. That creates hidden coupling and inconsistent behavior between controllers, services, tests, and frontend serialization.

This can break production when:

- a service expects an array but receives a JSON string;
- matrix validation is skipped because data type is inconsistent;
- API responses return encoded JSON strings instead of structured JSON;
- malformed matrix JSON is stored and only fails later during seat generation.

**How to fix**

Cast matrix columns to arrays if they are JSON columns.

**Example**

```php
protected $casts = [
    'seat_matrix' => 'array',
    'custom_matrix' => 'array',
    'regular_seat_rows' => 'integer',
    'vip_seat_rows' => 'integer',
    'couple_seat_rows' => 'integer',
    'status' => 'boolean',
];
```

If they are not JSON columns, the schema should be changed or the data model should be normalized.

---

### Issue #3

**Severity:** High  
**Category:** Business Logic / Validation / Data Integrity  
**Location:** `app/Models/SeatLayoutTemplate.php:11-15,20-25`

**Problem**

The model stores both matrix structure and row-count summary fields, but there is no invariant ensuring they match:

```php
'seat_matrix',
'regular_seat_rows',
'vip_seat_rows',
'couple_seat_rows',
'custom_matrix',
```

The row-count fields are independently assignable and only cast to integers:

```php
'regular_seat_rows' => 'integer',
'vip_seat_rows' => 'integer',
'couple_seat_rows' => 'integer',
```

There is no protection against contradictory data such as:

- `regular_seat_rows = 10` while matrix contains 2 regular rows;
- negative row counts;
- all row counts zero while matrix contains seats;
- matrix contains seat types not reflected in row counts;
- custom matrix conflicts with predefined row counts.

**Why this matters**

Seat layout data is used to generate sellable inventory. Contradictory layout metadata can lead to invalid seats, wrong seat type pricing, bad capacity, and seat maps that do not match the theater.

**How to fix**

Make one source of truth. Prefer deriving row counts from the matrix rather than storing manually editable duplicated counts.

If counts must be stored for performance, compute them inside a trusted service and prevent direct assignment.

**Example**

```php
public function recalculateRowCountsFromMatrix(): void
{
    $matrix = $this->seat_matrix ?? [];

    $this->forceFill([
        'regular_seat_rows' => $this->countRowsByType($matrix, 'regular'),
        'vip_seat_rows' => $this->countRowsByType($matrix, 'vip'),
        'couple_seat_rows' => $this->countRowsByType($matrix, 'couple'),
    ])->save();
}
```

---

### Issue #4

**Severity:** High  
**Category:** Architecture / Relationship Integrity  
**Location:** `app/Models/SeatLayoutTemplate.php:7-31`

**Problem**

The model defines no relationship to screens using the template.

```php
class SeatLayoutTemplate extends Model
{
    ...
}
```

There is no `screens()` relationship, even though `Screen.php` contains:

```php
public function seatLayoutTemplate(): BelongsTo
{
    return $this->belongsTo(SeatLayoutTemplate::class);
}
```

**Why this matters**

Template lifecycle decisions require knowing whether a template is in use. Without a relationship from template to screens, callers are more likely to delete, deactivate, or mutate a template without checking usage.

This is especially dangerous because changing a template can affect screens and future seat generation.

**How to fix**

Add the inverse relationship.

**Example**

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function screens(): HasMany
{
    return $this->hasMany(Screen::class);
}
```

Then use it in lifecycle checks:

```php
if ($template->screens()->exists()) {
    throw new DomainException('Cannot modify a template currently assigned to screens.');
}
```

---

### Issue #5

**Severity:** Medium  
**Category:** Business Logic / Lifecycle Integrity  
**Location:** `app/Models/SeatLayoutTemplate.php:17,27-30`

**Problem**

`status` is a boolean and `scopeActive()` only checks `status = 1`:

```php
'status',
```

```php
public function scopeActive($query)
{
    return $query->where('status', 1);
}
```

There is no lifecycle model for templates. A template may need distinct states such as draft, active, deprecated, locked, or archived.

**Why this matters**

A template used by existing screens should often become immutable or deprecated, not simply toggled active/inactive. A boolean status cannot express whether the template is safe for new screens, safe to edit, or retained only for historical references.

**How to fix**

Use explicit status values or an enum-backed cast.

**Example**

```php
enum SeatLayoutTemplateStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Deprecated = 'deprecated';
    case Archived = 'archived';
}
```

Then only allow new screens to use active templates, and prevent edits to active/in-use templates.

---

### Issue #6

**Severity:** Medium  
**Category:** Laravel Best Practice / Static Analysis  
**Location:** `app/Models/SeatLayoutTemplate.php:27-30`

**Problem**

The query scope is untyped:

```php
public function scopeActive($query)
{
    return $query->where('status', 1);
}
```

There is no `Builder` parameter or return type.

**Why this matters**

Untyped scopes reduce static analysis quality and make query refactoring less safe. This project already has complex booking and admin query logic; weak model contracts increase maintenance risk.

**How to fix**

```php
use Illuminate\Database\Eloquent\Builder;

public function scopeActive(Builder $query): Builder
{
    return $query->where('status', true);
}
```

---

### Issue #7

**Severity:** Medium  
**Category:** Database Correctness / Uniqueness  
**Location:** `app/Models/SeatLayoutTemplate.php:10`

**Problem**

`template_name` is mass assignable, but the model does not express a uniqueness expectation or normalized slug/key.

```php
'template_name',
```

**Why this matters**

If duplicate template names are allowed, admin users can select the wrong template, especially if templates differ only by whitespace or case. If duplicate names are not allowed, relying only on application validation without a database unique constraint is race-prone.

**How to fix**

Normalize template identity and enforce it in the database.

**Example**

```php
$table->string('template_name');
$table->string('slug')->unique();
```

or:

```php
$table->unique('template_name');
```

If names are display-only, introduce a separate immutable key.

---

### Issue #8

**Severity:** Medium  
**Category:** Data Integrity / Deletion Safety  
**Location:** `app/Models/SeatLayoutTemplate.php:5-31`

**Problem**

The model does not use `SoftDeletes`.

```php
use Illuminate\Database\Eloquent\Model;
```

For a template referenced by screens, hard deletion can break historical references unless foreign keys restrict deletion.

**Why this matters**

Layout templates are part of operational history. Even if generated seats remain, the referenced template explains how the screen was configured. Hard deletion can remove that context or fail unexpectedly depending on database constraints.

**How to fix**

Use soft deletes or enforce restrictive foreign keys and archive/deprecate instead of deleting.

**Example**

```php
use Illuminate\Database\Eloquent\SoftDeletes;

class SeatLayoutTemplate extends Model
{
    use SoftDeletes;
}
```

Then prevent deletion if screens still reference the template.

---

### Issue #9

**Severity:** Low  
**Category:** Clean Code / Magic Values  
**Location:** `app/Models/SeatLayoutTemplate.php:29`

**Problem**

The active scope uses an integer literal for a boolean column:

```php
return $query->where('status', 1);
```

**Why this matters**

The model casts `status` as boolean, so using `1` is inconsistent and less expressive.

**How to fix**

```php
return $query->where('status', true);
```

---

### Issue #10

**Severity:** Low  
**Category:** Laravel Best Practice / Factories / Testing  
**Location:** `app/Models/SeatLayoutTemplate.php:7-31`

**Problem**

The model does not use `HasFactory`.

```php
class SeatLayoutTemplate extends Model
{
```

**Why this matters**

This reduces test ergonomics. Seat layout templates are central to booking and admin tests; factories make it easier to build valid layouts and test edge cases.

**How to fix**

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SeatLayoutTemplate extends Model
{
    use HasFactory;
}
```

---

## Recommendations

### Immediate

1. Remove direct mass assignment for layout structure and lifecycle fields.
2. Cast `seat_matrix` and `custom_matrix` to arrays if they are JSON columns.
3. Make matrix data the source of truth and derive row counts from it.
4. Add the inverse `screens()` relationship.
5. Prevent mutation/deletion of templates currently used by screens or future showtimes.

### Short Term

6. Replace boolean `status` with explicit lifecycle states.
7. Type the `scopeActive()` query scope.
8. Enforce template name/key uniqueness at the database level.
9. Add soft deletes or explicit archive/deprecation workflow.
10. Add `HasFactory`.

### Long Term

11. Introduce a dedicated layout template service that validates matrix shape, seat types, dimensions, and row counts.
12. Add audit logging for template creation, update, deactivation, and deletion.
13. Add tests for malformed matrices, duplicate names, in-use template mutation, and screen generation from templates.

---

## Summary

`SeatLayoutTemplate.php` is currently too thin for a model that controls sellable seat inventory. It lacks casts for matrix data, relationships to screens, immutability/lifecycle protections, typed scopes, and data invariants between matrix and row-count fields.

**Main concerns:**

- Layout-defining fields are broadly mass assignable.
- Matrix fields are not cast to arrays.
- Row-count fields can diverge from matrix content.
- No inverse relationship to screens using the template.
- Boolean status is insufficient for template lifecycle.
- No soft delete/archive pattern.
- Weak testability due to missing factory support.

**Status:** Request changes before production acceptance.
