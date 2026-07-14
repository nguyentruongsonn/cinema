# File Review: Sound.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Models/Sound.php  
**Lines:** 18  
**Type:** Eloquent Model - Sound Format

---

## File Summary

`Sound.php` is a small Eloquent model representing a sound format/entity used by showtimes. It exposes one mass-assignable field, `name`, and defines a `hasMany` relationship to `Showtime`.

The model is readable and minimal, but it does not define uniqueness, normalization, lifecycle rules, deletion safety, factories, or query scopes. Since showtimes reference sound formats, this model still needs stricter production safeguards than currently implemented.

---

## Overall Score

**Overall Score:** 6.8/10

**Decision:** APPROVE WITH COMMENTS

---

## Strengths

- Very small and easy to understand.
- Uses an explicit `HasMany` return type for `showtimes()`.
- Defines the showtime relationship clearly.
- Limits mass assignment to `name` only, which is safer than exposing status, IDs, or operational fields.

---

## Issues

### Issue #1

**Severity:** Medium  
**Category:** Database Correctness / Data Integrity  
**Location:** `app/Models/Sound.php:10-12`

**Problem**

The model allows `name` to be mass assigned:

```php
protected $fillable = [
    'name',
];
```

There is no model-level normalization, uniqueness expectation, slug/code, or immutable identifier.

**Why this matters**

Sound formats are reference data. Duplicate or inconsistently formatted values such as `Dolby Atmos`, `dolby atmos`, `DOLBY ATMOS`, and ` Dolby Atmos ` can cause:

- duplicate filter options in APIs;
- inconsistent showtime metadata;
- admin confusion;
- incorrect frontend grouping;
- weak reporting/analytics grouping.

If uniqueness is enforced only by application validation, concurrent requests can still create duplicates unless the database has a unique constraint.

**How to fix**

Normalize and enforce uniqueness at the database level. Prefer adding a stable `code` or `slug`.

**Example**

```php
$table->string('name');
$table->string('code')->unique();
```

Then normalize before persistence in a service or mutator:

```php
public function setNameAttribute(string $value): void
{
    $this->attributes['name'] = trim($value);
}
```

---

### Issue #2

**Severity:** Medium  
**Category:** Data Integrity / Deletion Safety  
**Location:** `app/Models/Sound.php:14-17`

**Problem**

The model defines showtimes using this sound format:

```php
public function showtimes(): HasMany
{
    return $this->hasMany(Showtime::class, 'sound_id');
}
```

But the model provides no lifecycle/deletion guard for sound records referenced by showtimes.

**Why this matters**

A sound format referenced by existing or future showtimes is operational reference data. Deleting or renaming it without a lifecycle policy can:

- break historical showtime metadata;
- create foreign key constraint failures;
- corrupt reporting;
- cause frontend filters to lose expected values;
- make existing bookings harder to audit.

**How to fix**

Do not hard-delete referenced sounds. Use restrictive foreign keys and/or a lifecycle status such as active/inactive/deprecated.

**Example**

```php
public function canBeDeleted(): bool
{
    return ! $this->showtimes()->exists();
}
```

Or prefer deactivation:

```php
$table->boolean('is_active')->default(true);
```

---

### Issue #3

**Severity:** Medium  
**Category:** Business Logic / Maintainability  
**Location:** `app/Models/Sound.php:8-18`

**Problem**

The model has no active/inactive lifecycle state.

```php
class Sound extends Model
{
    ...
}
```

**Why this matters**

Reference data used by showtimes usually needs to support retirement without deleting historical records. For example, if a sound format should no longer be selectable for new showtimes, the system needs a way to hide it while retaining existing showtime history.

Without a lifecycle state, the application must either:

- keep obsolete sounds selectable forever; or
- delete them and risk breaking existing showtimes.

**How to fix**

Add an explicit lifecycle field and an active scope.

**Example**

```php
protected $fillable = [
    'name',
    'is_active',
];

protected $casts = [
    'is_active' => 'boolean',
];

public function scopeActive(Builder $query): Builder
{
    return $query->where('is_active', true);
}
```

If `is_active` should not be directly assignable from arbitrary requests, update it only through an admin service.

---

### Issue #4

**Severity:** Low  
**Category:** Laravel Best Practice / Testing  
**Location:** `app/Models/Sound.php:8-18`

**Problem**

The model does not use `HasFactory`.

```php
class Sound extends Model
{
```

**Why this matters**

Showtime tests need related sound formats. Without a factory, tests become more verbose and are more likely to hard-code database assumptions.

**How to fix**

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sound extends Model
{
    use HasFactory;
}
```

---

### Issue #5

**Severity:** Low  
**Category:** Clean Code / Relationship Convention  
**Location:** `app/Models/Sound.php:16`

**Problem**

The relationship explicitly specifies the foreign key:

```php
return $this->hasMany(Showtime::class, 'sound_id');
```

This is correct, but redundant if the database follows Laravel conventions.

**Why this matters**

Redundant convention configuration is not a bug, but it adds noise. If the foreign key is conventional, Laravel can infer it.

**How to fix**

If the foreign key is conventional, simplify:

```php
return $this->hasMany(Showtime::class);
```

Keep the explicit key only if the schema intentionally deviates or the team prefers explicit relationships.

---

### Issue #6

**Severity:** Low  
**Category:** API / Readability / Filtering  
**Location:** `app/Models/Sound.php:8-18`

**Problem**

There is no scope for deterministic ordering.

```php
class Sound extends Model
{
    ...
}
```

Sound formats are likely rendered in admin selectors and public filters. Without a named ordering scope, ordering may be duplicated across controllers/services or omitted entirely.

**Why this matters**

Reference-data API output should be deterministic. Inconsistent ordering can cause flaky frontend behavior and harder-to-test API responses.

**How to fix**

Add a typed scope.

**Example**

```php
use Illuminate\Database\Eloquent\Builder;

public function scopeOrdered(Builder $query): Builder
{
    return $query->orderBy('name');
}
```

---

## Recommendations

### Immediate

1. Enforce normalized uniqueness for `name` or introduce a unique `code`.
2. Prevent hard deletion of sounds referenced by showtimes.
3. Define whether sounds are immutable reference data or editable admin data.

### Short Term

4. Add `is_active` or explicit lifecycle states for retiring sound formats.
5. Add `HasFactory`.
6. Add deterministic ordering scope for API selectors.

### Long Term

7. Centralize reference-data management for formats/sounds/categories to avoid inconsistent lifecycle behavior.
8. Add audit logging for changes to showtime reference data.
9. Add tests for duplicate sound creation, referenced sound deletion, and inactive sound filtering.

---

## Summary

`Sound.php` is clean and minimal, but production readiness depends on stricter reference-data guarantees. The main risks are duplicate sound names, lack of lifecycle state, and unsafe deletion/renaming of records already referenced by showtimes.

**Main concerns:**

- No uniqueness/normalization for `name`.
- No lifecycle state for active/deprecated sound formats.
- No deletion guard despite `showtimes()` relationship.
- Missing factory support.
- No deterministic ordering scope for reference-data APIs.

**Status:** Approve with comments, assuming database constraints and service-layer validation enforce uniqueness and deletion safety.