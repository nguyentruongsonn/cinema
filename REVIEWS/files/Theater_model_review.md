# File Review: Theater.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Models/Theater.php  
**Lines:** 48  
**Type:** Eloquent Model - Theater

---

## File Summary

`Theater.php` is an Eloquent model representing a theater belonging to a branch and containing screens. It uses soft deletes, exposes branch/screen relationships, casts `status` and `pricing_profile`, and provides `active` and `inBranch` query scopes.

The model is structurally clear, but it exposes operational fields through mass assignment and does not enforce important theater-level invariants around branch ownership, pricing profile shape, lifecycle, deletion safety, or typed query scopes.

---

## Overall Score

**Overall Score:** 5.9/10

**Decision:** REQUEST CHANGES

---

## Strengths

- Uses `SoftDeletes`, which is appropriate for operational location data.
- Defines `branch()` and `screens()` relationships.
- Provides basic query scopes for active theaters and branch filtering.
- Casts `status` to boolean.
- Keeps the model relatively small and readable.

---

## Issues

### Issue #1

**Severity:** High  
**Category:** Authorization / Business Logic / Mass Assignment  
**Location:** `app/Models/Theater.php:14-22`

**Problem**

The model exposes `branch_id`, `status`, and `pricing_profile` through mass assignment:

```php
protected $fillable = [
    'branch_id',
    'name',
    'address',
    'phone',
    'email',
    'status',
    'pricing_profile',
];
```

These are operational fields:

- `branch_id` controls tenancy/ownership within the cinema structure.
- `status` controls public/operational availability.
- `pricing_profile` can affect ticket pricing.

**Why this matters**

If controllers or services pass request-wide payloads into `Theater::create()` or `$theater->update()`, callers may reassign theaters to another branch, enable/disable theaters, or modify pricing behavior without explicit authorization checks in the model.

This can cause:

- IDOR-style branch reassignment;
- unauthorized theater activation/deactivation;
- wrong pricing for showtimes/screens;
- corrupted reporting by branch;
- hidden privilege escalation in admin APIs.

**How to fix**

Do not mass assign branch ownership or operational pricing/status fields from generic payloads. Assign them through explicit service methods after authorization and validation.

**Example**

Before:

```php
protected $fillable = [
    'branch_id',
    'name',
    'address',
    'phone',
    'email',
    'status',
    'pricing_profile',
];
```

After:

```php
protected $fillable = [
    'name',
    'address',
    'phone',
    'email',
];

protected $guarded = [
    'id',
    'branch_id',
    'status',
    'pricing_profile',
];
```

Then use explicit methods:

```php
public function assignToBranch(Branch $branch): void
{
    $this->branch()->associate($branch);
    $this->save();
}
```

---

### Issue #2

**Severity:** High  
**Category:** Business Logic / Pricing Correctness  
**Location:** `app/Models/Theater.php:21,24-27`

**Problem**

`pricing_profile` is mass assignable and cast as JSON:

```php
'pricing_profile',
```

```php
protected $casts = [
    'status' => 'boolean',
    'pricing_profile' => 'json',
];
```

There is no schema, validation, enum, DTO, or documented shape for this pricing profile.

**Why this matters**

Pricing is money-impacting logic. Storing arbitrary JSON without a strict schema makes pricing behavior fragile and difficult to validate. A malformed or partial profile can result in wrong ticket prices, lost revenue, inconsistent discounts, or runtime errors in pricing services.

**How to fix**

Use a strict cast/DTO or normalized pricing tables. If JSON is retained, validate against a defined schema before persistence.

**Example**

```php
protected $casts = [
    'status' => 'boolean',
    'pricing_profile' => 'array',
];
```

Then validate in a dedicated service:

```php
$this->validatePricingProfile($data['pricing_profile']);
```

Better: move pricing rules into normalized tables with foreign keys and versioning.

---

### Issue #3

**Severity:** High  
**Category:** Data Integrity / Deletion Safety  
**Location:** `app/Models/Theater.php:12,34-37`

**Problem**

The model uses soft deletes:

```php
use SoftDeletes;
```

and has screens:

```php
public function screens(): HasMany
{
    return $this->hasMany(Screen::class);
}
```

But the model does not define any deletion guard or lifecycle rule preventing deletion of theaters that have screens, showtimes, bookings, or future schedules.

**Why this matters**

Soft deleting a theater that still has screens and scheduled showtimes can make active inventory disappear from admin or public APIs while bookings still exist. This can create operational inconsistencies:

- customers have tickets for a hidden/deleted theater;
- active showtimes become unreachable;
- screens remain attached to a deleted parent;
- analytics exclude deleted theaters incorrectly;
- support cannot reconcile historical bookings.

**How to fix**

Prevent deletion when dependent operational records exist, especially future showtimes or bookings. Use a domain service, model observer, or policy.

**Example**

```php
public function canBeDeleted(): bool
{
    return ! $this->screens()
        ->whereHas('showtimes', fn ($query) => $query->where('start_time', '>=', now()))
        ->exists();
}
```

Prefer explicit deactivation over deletion for theaters with history.

---

### Issue #4

**Severity:** Medium  
**Category:** Lifecycle / Business Logic  
**Location:** `app/Models/Theater.php:20,24-26,39-42`

**Problem**

The model uses a boolean `status`:

```php
'status',
```

```php
'status' => 'boolean',
```

and the active scope checks:

```php
return $query->where('status', 1);
```

A theater lifecycle is more complex than active/inactive.

**Why this matters**

Theater lifecycle may include draft, active, temporarily closed, under maintenance, permanently closed, archived, or deleted. A boolean cannot express whether the theater is visible, bookable, editable, or retained for historical reporting.

This can cause business logic to spread across controllers and services using ad-hoc interpretations of `status`.

**How to fix**

Use an enum-backed status or explicit lifecycle columns.

**Example**

```php
enum TheaterStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case TemporarilyClosed = 'temporarily_closed';
    case Archived = 'archived';
}
```

---

### Issue #5

**Severity:** Medium  
**Category:** Laravel Best Practice / Casting  
**Location:** `app/Models/Theater.php:26`

**Problem**

The model casts `pricing_profile` as `json`:

```php
'pricing_profile' => 'json',
```

Laravel commonly uses `array` for JSON columns when the application wants to work with PHP arrays.

**Why this matters**

`json` is supported as a cast alias in modern Laravel versions, but `array` is clearer about the runtime type returned to application code. Ambiguous casts reduce readability for maintainers and can lead to inconsistent assumptions in services.

**How to fix**

Use `array` if the application expects a PHP array:

```php
'pricing_profile' => 'array',
```

If stricter semantics are required, use a custom cast/value object.

---

### Issue #6

**Severity:** Medium  
**Category:** Data Integrity / Uniqueness  
**Location:** `app/Models/Theater.php:15-16`

**Problem**

The model has `branch_id` and `name` but no model-level indication of uniqueness constraints:

```php
'branch_id',
'name',
```

**Why this matters**

Theater names may need to be unique per branch. Without a database-level unique constraint, duplicate theaters can be created concurrently even if request validation attempts to prevent duplicates.

This causes admin confusion, bad selectors, reporting ambiguity, and wrong screen assignments.

**How to fix**

Enforce a composite unique constraint at the database layer if business rules require it:

```php
$table->unique(['branch_id', 'name']);
```

Normalize names before validating uniqueness.

---

### Issue #7

**Severity:** Medium  
**Category:** Laravel Best Practice / Static Analysis  
**Location:** `app/Models/Theater.php:39-47`

**Problem**

Both query scopes are untyped:

```php
public function scopeActive($query)
{
    return $query->where('status', 1);
}

public function scopeInBranch($query, $branchId)
{
    return $query->where('branch_id', $branchId);
}
```

There are no `Builder`, parameter, or return types.

**Why this matters**

Untyped scopes reduce static analysis quality and make refactoring harder. This is especially relevant for filters used by admin/public APIs.

**How to fix**

```php
use Illuminate\Database\Eloquent\Builder;

public function scopeActive(Builder $query): Builder
{
    return $query->where('status', true);
}

public function scopeInBranch(Builder $query, int $branchId): Builder
{
    return $query->where('branch_id', $branchId);
}
```

---

### Issue #8

**Severity:** Medium  
**Category:** Validation / Data Quality  
**Location:** `app/Models/Theater.php:17-19`

**Problem**

Contact fields are simple mass-assignable strings:

```php
'address',
'phone',
'email',
```

There is no normalization at the model level.

**Why this matters**

Theater contact data appears in customer-facing APIs and admin interfaces. Without consistent normalization, the system may store invalid phone numbers, mixed-case emails, whitespace-only values, or malformed addresses. Validation may exist elsewhere, but this model does not enforce any invariant.

**How to fix**

Normalize low-risk fields at assignment time or in a dedicated service.

**Example**

```php
public function setEmailAttribute(?string $value): void
{
    $this->attributes['email'] = $value ? strtolower(trim($value)) : null;
}
```

Still keep validation in FormRequests/services.

---

### Issue #9

**Severity:** Low  
**Category:** Clean Code / Magic Values  
**Location:** `app/Models/Theater.php:41`

**Problem**

The active scope uses integer `1` for a boolean field:

```php
return $query->where('status', 1);
```

**Why this matters**

The model casts `status` as boolean, so querying with `true` is more expressive and consistent.

**How to fix**

```php
return $query->where('status', true);
```

---

### Issue #10

**Severity:** Low  
**Category:** Laravel Best Practice / Testing  
**Location:** `app/Models/Theater.php:10-12`

**Problem**

The model does not use `HasFactory`.

```php
class Theater extends Model
{
    use SoftDeletes;
```

**Why this matters**

Theaters are central to tests involving branches, screens, showtimes, and bookings. Missing factories increases test setup friction.

**How to fix**

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Theater extends Model
{
    use HasFactory;
    use SoftDeletes;
}
```

---

## Recommendations

### Immediate

1. Remove `branch_id`, `status`, and `pricing_profile` from generic mass assignment.
2. Add strict validation/schema for `pricing_profile`.
3. Prevent deletion/deactivation of theaters with future showtimes or active bookings.
4. Define theater lifecycle semantics beyond boolean `status`.

### Short Term

5. Use typed query scopes.
6. Replace `where('status', 1)` with `where('status', true)`.
7. Enforce theater name uniqueness per branch if required.
8. Normalize email/phone/address inputs.
9. Add `HasFactory`.

### Long Term

10. Move pricing profile behavior to versioned pricing tables or value objects.
11. Add audit logging for theater branch reassignment, status changes, pricing changes, and deletion.
12. Add tests for branch reassignment authorization, deletion safety, duplicate names, and invalid pricing profiles.

---

## Summary

`Theater.php` is readable and has useful relationships, but it is too permissive for production operational data. The main concerns are mass assignment of branch ownership, status, and pricing profile; weak pricing profile modeling; insufficient deletion/deactivation safeguards; and untyped scopes.

**Main concerns:**

- `branch_id`, `status`, and `pricing_profile` are mass assignable.
- Arbitrary JSON pricing profile can affect money calculations.
- No guard against deleting/deactivating theaters with active operational dependencies.
- Boolean status is too weak for real theater lifecycle.
- Untyped query scopes and boolean magic value.
- Missing factory support.

**Status:** Request changes before production acceptance.