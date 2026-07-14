# File Review: Subtitle.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Models/Subtitle.php  
**Lines:** 25  
**Type:** Eloquent Model - Subtitle

---

## File Summary

`Subtitle.php` is a small Eloquent model for subtitle metadata. It exposes `name` as mass assignable and defines relationships to movies through the `movie_subtitle` pivot table and to showtimes through `subtitle_id`.

The file is readable, but subtitle records are referenced by movies and showtimes, so data normalization, uniqueness, and deletion lifecycle rules matter for API consistency and schedule correctness.

---

## Overall Score

**Overall Score:** 6.7/10

**Decision:** APPROVE WITH COMMENTS

---

## Strengths

- Simple, focused model.
- Defines both movie and showtime relationships.
- Uses explicit pivot table name for the movie relationship.
- No raw SQL or dynamic query construction is present.
- No sensitive fields are exposed.

---

## Issues

### Issue #1

**Severity:** Medium  
**Category:** Database Correctness / Duplicate Data  
**Location:** `app/Models/Subtitle.php:11-13`

**Problem**

`name` is mass assignable, but there is no visible uniqueness or normalization invariant in the model.

```php
protected $fillable = [
    'name',
];
```

**Why this matters**

Subtitle options such as `English`, `english`, `EN`, and `Eng Sub` can become duplicated unless constrained elsewhere. Duplicate subtitle records create inconsistent filtering, movie metadata, showtime display, and admin behavior.

**How to fix**

Use a stable normalized code and enforce uniqueness at the database level.

**Example**

```php
$table->string('code')->unique();
$table->string('name');
```

Application code should use `code` for identity and `name` for display.

---

### Issue #2

**Severity:** Medium  
**Category:** Data Integrity / Referenced Record Lifecycle  
**Location:** `app/Models/Subtitle.php:15-24`

**Problem**

The model is referenced by movies and showtimes:

```php
public function movies(): BelongsToMany
{
    return $this->belongsToMany(Movie::class, 'movie_subtitle')
        ->withTimestamps();
}

public function showtimes(): HasMany
{
    return $this->hasMany(Showtime::class, 'subtitle_id');
}
```

But the model does not define any deletion guard or lifecycle rule.

**Why this matters**

Deleting a subtitle record that is already used by movies or scheduled showtimes can break historical showtime data, public movie metadata, filters, and ticket/order display.

**How to fix**

Prefer an `is_active` lifecycle flag over destructive deletion, and block deletion while references exist.

**Example**

```php
protected static function booted(): void
{
    static::deleting(function (Subtitle $subtitle): void {
        if ($subtitle->movies()->exists() || $subtitle->showtimes()->exists()) {
            throw new LogicException('Cannot delete a subtitle that is in use.');
        }
    });
}
```

Also enforce foreign-key constraints with restrictive delete behavior.

---

### Issue #3

**Severity:** Medium  
**Category:** API Consistency / Business Semantics  
**Location:** `app/Models/Subtitle.php:11-13`

**Problem**

The model stores only `name`.

```php
'name',
```

**Why this matters**

A display name alone is weak as a domain identifier. Subtitle language/format usually needs stable semantics such as language code, subtitle type, or whether it is dubbed/subtitled. Relying on free-form names makes filtering and integrations fragile.

**How to fix**

Introduce explicit fields for stable identity.

**Example**

```php
$table->string('code')->unique(); // vi, en, ja
$table->string('name');
$table->boolean('is_active')->default(true);
```

If subtitle type matters, model it explicitly:

```php
$table->enum('type', ['subtitle', 'dubbed', 'none']);
```

---

### Issue #4

**Severity:** Low  
**Category:** Laravel Best Practice / Factory Support  
**Location:** `app/Models/Subtitle.php:5-9`

**Problem**

The model does not use `HasFactory`.

```php
use Illuminate\Database\Eloquent\Model;
```

```php
class Subtitle extends Model
```

**Why this matters**

Factories make movie metadata and showtime scheduling tests easier to write.

**How to fix**

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subtitle extends Model
{
    use HasFactory;
}
```

---

### Issue #5

**Severity:** Low  
**Category:** Maintainability / Static Analysis  
**Location:** `app/Models/Subtitle.php:11-13`

**Problem**

The model property is untyped.

```php
protected $fillable = [
    'name',
];
```

**Why this matters**

Typed model metadata improves static analysis and readability.

**How to fix**

Where supported by the Laravel/PHP version in use:

```php
protected array $fillable = [
    'name',
];
```

---

### Issue #6

**Severity:** Low  
**Category:** Eloquent Relationship Correctness / Pivot Integrity  
**Location:** `app/Models/Subtitle.php:15-19`

**Problem**

The pivot relationship uses the table name but does not specify pivot keys.

```php
return $this->belongsToMany(Movie::class, 'movie_subtitle')
    ->withTimestamps();
```

**Why this matters**

Laravel will infer `subtitle_id` and `movie_id`. This is fine only if the pivot table follows exactly that convention. Explicit pivot keys improve maintainability and reduce breakage if naming differs.

**How to fix**

```php
return $this->belongsToMany(Movie::class, 'movie_subtitle', 'subtitle_id', 'movie_id')
    ->withTimestamps();
```

Also ensure a unique database constraint exists:

```php
$table->unique(['movie_id', 'subtitle_id']);
```

---

## Recommendations

### Immediate

1. Add a database unique constraint or stable `code` for subtitle identity.
2. Ensure referenced subtitle rows cannot be deleted while used by movies or showtimes.
3. Add validation/normalization for subtitle names or codes.

### Short Term

4. Add `is_active` instead of deleting subtitle rows.
5. Specify pivot keys explicitly in the relationship.
6. Add a unique constraint on the movie-subtitle pivot pair.

### Long Term

7. Add factories and tests for movie filtering and showtime metadata.
8. Model subtitle language/type semantics explicitly instead of relying on free-form display names.

---

## Summary

`Subtitle.php` is clean and simple, but it relies on a free-form `name` as the only domain field and does not enforce lifecycle safety for referenced records. The production risks are mainly duplicate subtitle metadata, inconsistent filtering/display behavior, and unsafe deletion while subtitles are used by movies or showtimes.

**Main concerns:**

- No visible uniqueness/normalization for subtitle identity.
- No deletion guard for subtitles used by movies or showtimes.
- Free-form `name` is weak for domain semantics.
- Missing factory support.
- Pivot relationship relies on inferred keys.

**Status:** Approve with comments, with data integrity improvements recommended before scale.