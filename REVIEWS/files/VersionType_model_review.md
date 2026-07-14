# File Review: VersionType.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Models/VersionType.php  
**Lines:** 26  
**Type:** Eloquent Model - Movie Version Type

---

## File Summary

`VersionType.php` is an Eloquent model representing movie/showtime version metadata such as the version name, slug, and description. It uses `HasFactory`, exposes `name`, `slug`, and `description` as mass assignable, and defines a `hasMany` relationship to `Showtime`.

This is a small model, but it affects showtime filtering, schedule display, and user purchase decisions because version type metadata is part of the public showtime contract.

---

## Overall Score

**Overall Score:** 6.5/10

**Decision:** APPROVE WITH COMMENTS

---

## Strengths

- Uses `HasFactory`, improving testability.
- Defines a clear relationship to `Showtime`.
- Keeps the model focused and readable.
- No raw SQL or dynamic query construction is present.
- No sensitive data is exposed in this file.

---

## Issues

### Issue #1

**Severity:** Medium  
**Category:** Database Correctness / Duplicate Data  
**Location:** `app/Models/VersionType.php:13-17`

**Problem**

The model exposes `slug` as mass assignable, but no uniqueness or normalization invariant is visible in the model.

```php
protected $fillable = [
    'name',
    'slug',
    'description',
];
```

**Why this matters**

Version types are usually stable catalogue values such as `2d`, `3d`, `imax`, or `4dx`. If duplicate or inconsistent slugs are allowed, API filters, frontend routing, and showtime search can return inconsistent results.

Examples of problematic duplicates:

- `IMAX`
- `imax`
- `imax-2d`
- `Imax`

**How to fix**

Enforce slug normalization in the write layer and a unique database constraint.

**Example**

```php
$table->string('slug')->unique();
```

Normalize before persistence:

```php
$data['slug'] = Str::slug($data['slug']);
```

---

### Issue #2

**Severity:** Medium  
**Category:** Data Integrity / Referenced Record Lifecycle  
**Location:** `app/Models/VersionType.php:22-25`

**Problem**

The model is referenced by showtimes:

```php
public function showtimes(): HasMany
{
    return $this->hasMany(Showtime::class);
}
```

But there is no deletion or lifecycle guard in the model.

**Why this matters**

Deleting a version type used by scheduled showtimes can break public schedule display, filters, ticket metadata, and historical order/ticket context. Showtime metadata should remain stable once tickets can be sold.

**How to fix**

Use an `is_active` flag for lifecycle control and block destructive deletes while referenced.

**Example**

```php
protected static function booted(): void
{
    static::deleting(function (VersionType $versionType): void {
        if ($versionType->showtimes()->exists()) {
            throw new LogicException('Cannot delete a version type that is used by showtimes.');
        }
    });
}
```

Also enforce database foreign keys with restrictive delete behavior.

---

### Issue #3

**Severity:** Medium  
**Category:** Business Logic / Public Catalogue Integrity  
**Location:** `app/Models/VersionType.php:13-17`

**Problem**

`name`, `slug`, and `description` are all freely mass assignable.

```php
'name',
'slug',
'description',
```

**Why this matters**

Version types are public catalogue metadata. If admin or service code passes request data directly into `create()` or `update()`, public showtime labels and filters can be changed without an explicit workflow or audit point. For a cinema system, changing a version type after showtimes exist can misrepresent what customers purchased.

**How to fix**

Treat version types as controlled reference data. Limit updates to explicit validated commands and audit changes.

**Example**

```php
protected $guarded = ['id'];
```

Use a dedicated service method:

```php
public function updateVersionType(VersionType $versionType, array $validated, User $actor): VersionType
{
    // authorize actor
    // validate slug uniqueness/immutability
    // audit change
    $versionType->update($validated);

    return $versionType;
}
```

---

### Issue #4

**Severity:** Medium  
**Category:** API Consistency / Missing Active Scope  
**Location:** `app/Models/VersionType.php:13-17`

**Problem**

The model has no visible lifecycle/status field or active scope.

```php
protected $fillable = [
    'name',
    'slug',
    'description',
];
```

**Why this matters**

Reference data often needs to be hidden from new schedules without deleting historical references. Without an active flag, the application may either continue exposing obsolete version types or delete them unsafely.

**How to fix**

Add an `is_active` field and a local scope.

**Example**

```php
public function scopeActive($query)
{
    return $query->where('is_active', true);
}
```

Use the active scope for schedule creation selectors, but keep historical showtime references intact.

---

### Issue #5

**Severity:** Low  
**Category:** Maintainability / Static Analysis  
**Location:** `app/Models/VersionType.php:13-17`

**Problem**

The `$fillable` property is untyped.

```php
protected $fillable = [
    'name',
    'slug',
    'description',
];
```

**Why this matters**

Typed model metadata improves static analysis and maintainability.

**How to fix**

Where supported by the Laravel/PHP version in use:

```php
protected array $fillable = [
    'name',
    'slug',
    'description',
];
```

---

### Issue #6

**Severity:** Low  
**Category:** Clean Code / Comments  
**Location:** `app/Models/VersionType.php:19-21`

**Problem**

The relationship comment restates the method name without adding useful domain information.

```php
/**
 * Get all showtimes with this version type
 */
```

**Why this matters**

Comments that only repeat code become noise. The method name and return type already communicate this.

**How to fix**

Remove the comment or replace it with domain-specific information if needed.

---

## Recommendations

### Immediate

1. Enforce unique, normalized slugs at the database and validation layers.
2. Prevent deletion of version types referenced by showtimes.
3. Add explicit authorization/audit workflow around reference-data mutations.

### Short Term

4. Add an `is_active` lifecycle flag instead of destructive deletion.
5. Add an active scope for schedule creation selectors.
6. Type model metadata properties where supported.

### Long Term

7. Treat version types as seeded/reference data with controlled migrations.
8. Add tests for duplicate slug prevention and delete restrictions.

---

## Summary

`VersionType.php` is clean and readable, and it correctly defines factory support and the showtime relationship. The main production risks are around reference-data integrity: duplicate slugs, unsafe mutation/deletion after showtimes exist, and lack of lifecycle controls for obsolete version types.

**Main concerns:**

- No visible uniqueness/normalization invariant for `slug`.
- No deletion guard for version types referenced by showtimes.
- Public catalogue metadata is broadly mass assignable.
- No active lifecycle model.
- Minor maintainability issue from untyped properties and redundant comments.

**Status:** Approve with comments, with reference-data integrity fixes recommended before production scale.