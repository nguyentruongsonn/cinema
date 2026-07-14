====================================================

File:
app/Models/Movie.php

Overall Score:
5.4/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Uses `HasFactory` and `SoftDeletes`.
- Defines relationships for showtimes, categories, formats, and subtitles.
- Provides useful query scopes for active, now showing, upcoming, slug, and category filtering.
- Casts several fields to appropriate primitive/date types.
- Automatically generates slugs on create when missing.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Concurrency / Database Correctness

Location:
app/Models/Movie.php:33-45

Problem

Slug uniqueness is generated with a read-then-write loop:

```php
protected static function generateUniqueSlug(string $title): string
{
    $slug = Str::slug($title);
    $originalSlug = $slug;
    $counter = 1;

    while (static::where('slug', $slug)->exists()) {
        $slug = $originalSlug . '-' . $counter;
        $counter++;
    }

    return $slug;
}
```

Why this matters

Two concurrent requests creating movies with the same title can both observe that the slug does not exist and then both write the same slug. This creates duplicate slugs unless a database unique constraint exists. Duplicate slugs break route lookup, public movie detail pages, SEO, and API consistency.

How to fix

Enforce a database unique index on `slug` and handle duplicate-key retry at creation time.

Example

```php
$table->string('slug')->unique();
```

Creation should retry when the database rejects a duplicate slug, not rely only on `exists()`.

----------------------------------------------------

### Issue #2

Severity:
High

Category:
Business Logic / API Correctness

Location:
app/Models/Movie.php:26-30

Problem

Slug regeneration on update only happens when the title is dirty and the slug is empty:

```php
static::updating(function ($movie) {
    if ($movie->isDirty('title') && empty($movie->slug)) {
        $movie->slug = static::generateUniqueSlug($movie->title);
    }
});
```

Why this matters

If a movie title changes and the old slug remains populated, the slug will not update. If this is intentional for SEO stability, it should be explicit and documented. If not intentional, users and APIs will see stale slugs that no longer match the title.

How to fix

Define a clear slug policy:
- immutable slugs after creation, documented and enforced; or
- regenerate slugs when titles change, with redirects/aliases if public URLs matter.

Example immutable policy:

```php
static::updating(function ($movie) {
    if ($movie->isDirty('slug')) {
        // require explicit admin permission/service path
    }
});
```

----------------------------------------------------

### Issue #3

Severity:
High

Category:
Authorization / Mass Assignment

Location:
app/Models/Movie.php:47-68

Problem

Sensitive operational fields are mass assignable:

```php
'status',
'is_hidden',
'manual_override_status',
'is_hot',
'surcharge',
```

Why this matters

These fields control movie visibility, business promotion, and pricing. If any controller uses request-driven mass assignment without strict authorization, unauthorized users or lower-privileged admins can publish/hide movies, mark movies as hot, override lifecycle status, or alter ticket surcharge.

How to fix

Move privileged updates into explicit admin-only service methods and validate each field. Consider separating public content updates from operational status/pricing updates.

Example

```php
$movie->update([
    'title' => $validated['title'],
    'description' => $validated['description'],
]);

// separate authorized operation
$this->authorize('manageMovieStatus', $movie);
$movie->forceFill([
    'status' => $validated['status'],
    'is_hidden' => $validated['is_hidden'],
])->save();
```

----------------------------------------------------

### Issue #4

Severity:
High

Category:
Business Logic / Pricing Correctness

Location:
app/Models/Movie.php:47-68

Problem

`surcharge` is mass assignable and only cast as decimal:

```php
'surcharge',
```

There is no model-level invariant preventing negative values.

Why this matters

Movie surcharge affects ticket pricing and revenue. A negative surcharge can reduce ticket totals. This can lose money if malformed admin input or compromised privileged endpoints persist negative values.

How to fix

Validate and enforce non-negative values in FormRequests/services and the database.

Example

```php
'surcharge' => ['required', 'numeric', 'min:0', 'decimal:0,2']
```

Database:

```php
$table->decimal('surcharge', 10, 2)->default(0);
$table->check('surcharge >= 0');
```

----------------------------------------------------

### Issue #5

Severity:
High

Category:
Security / Stored XSS

Location:
app/Models/Movie.php:47-68

Problem

Several user/admin-controlled content fields are mass assignable:

```php
'title',
'original_title',
'description',
'poster_url',
'trailer_url',
'director',
'cast',
'backdrops',
```

The model does not sanitize or constrain content.

Why this matters

Movie metadata is likely rendered in frontend pages and admin dashboards. If HTML/script content is stored in fields such as `description`, `title`, `director`, or `cast`, it can become stored XSS if any consumer renders it unsafely. URLs can also point to unsafe schemes if not validated.

How to fix

Validate content length and URL schemes in FormRequests. Escape on output. For rich text, sanitize with an allowlist HTML purifier before persistence.

Example

```php
'description' => ['nullable', 'string', 'max:5000'],
'poster_url' => ['nullable', 'url', 'max:2048', 'starts_with:https://'],
'trailer_url' => ['nullable', 'url', 'max:2048'],
```

----------------------------------------------------

### Issue #6

Severity:
Medium

Category:
Security / Path Handling

Location:
app/Models/Movie.php:73-90

Problem

Display URL accessors concatenate storage paths directly:

```php
return asset('storage/' . $this->poster_path);
```

```php
return asset('storage/' . $this->banner_path);
```

Why this matters

If `poster_path` or `banner_path` is ever persisted from insufficiently validated input, the application may generate URLs for unintended storage paths. This is not direct filesystem access here, but it can expose internal path structure and create broken or misleading public URLs.

How to fix

Validate uploaded paths at the storage boundary and generate URLs through Laravel Storage.

Example

```php
return $this->poster_path
    ? Storage::disk('public')->url($this->poster_path)
    : ($this->poster_url ?: null);
```

Also restrict stored paths to expected directories such as `movies/posters/*`.

----------------------------------------------------

### Issue #7

Severity:
Medium

Category:
API Design / Model Responsibility

Location:
app/Models/Movie.php:73-90

Problem

The model generates absolute asset URLs:

```php
public function getPosterDisplayUrlAttribute(): ?string
```

```php
public function getBannerDisplayUrlAttribute(): ?string
```

Why this matters

Eloquent models should primarily represent persistence/domain state. API presentation concerns such as absolute URLs are better handled by API Resources/DTOs. Putting URL formatting in the model couples persistence to HTTP context and makes CLI/tests/background jobs more environment-dependent.

How to fix

Move display URL formatting to an API Resource or presenter.

Example

```php
'poster_display_url' => $this->poster_path
    ? Storage::disk('public')->url($this->poster_path)
    : $this->poster_url,
```

----------------------------------------------------

### Issue #8

Severity:
Medium

Category:
Performance / Database Indexing

Location:
app/Models/Movie.php:127-147

Problem

The scopes filter by status, visibility, release date, and end date:

```php
return $query->where('status', 1)
    ->where('is_hidden', 0);
```

```php
->where('release_date', '<=', now())
->where(function ($q) {
    $q->whereNull('end_date')
      ->orWhere('end_date', '>=', now());
});
```

There is no model indication of expected database indexes.

Why this matters

Movie listing endpoints are high-traffic. Without indexes on visibility/status/date columns, now-showing and upcoming pages can degrade as the movie table grows.

How to fix

Add composite indexes aligned with common filters.

Example

```php
$table->index(['status', 'is_hidden', 'release_date']);
$table->index(['status', 'is_hidden', 'end_date']);
```

----------------------------------------------------

### Issue #9

Severity:
Medium

Category:
Correctness / Date Handling

Location:
app/Models/Movie.php:133-147

Problem

The scopes compare date-cast fields to `now()`:

```php
->where('release_date', '<=', now())
```

```php
->where('release_date', '>', now());
```

`release_date` and `end_date` are cast as dates:

```php
'release_date' => 'date',
'end_date' => 'date',
```

Why this matters

Date-only columns compared to a full timestamp can produce boundary inconsistencies depending on database type and timezone. A movie with `release_date` equal to today should be considered active for the full day, but timestamp comparisons can be ambiguous.

How to fix

Use `today()`/`whereDate()` when the field represents a calendar date.

Example

```php
->whereDate('release_date', '<=', today())
```

```php
->whereDate('release_date', '>', today())
```

----------------------------------------------------

### Issue #10

Severity:
Medium

Category:
Database Correctness / Soft Deletes

Location:
app/Models/Movie.php:14

Problem

The model uses soft deletes:

```php
use HasFactory, SoftDeletes;
```

Slug generation checks only default scoped rows:

```php
while (static::where('slug', $slug)->exists()) {
```

Why this matters

Because `SoftDeletes` applies a global scope, this query ignores soft-deleted movies. A new movie can reuse the slug of a soft-deleted movie unless the database unique constraint prevents it. If the old movie is later restored, slug collisions can occur.

How to fix

Include trashed rows in slug uniqueness checks and enforce database uniqueness according to the product policy.

Example

```php
while (static::withTrashed()->where('slug', $slug)->exists()) {
    $slug = $originalSlug . '-' . $counter++;
}
```

----------------------------------------------------

### Issue #11

Severity:
Medium

Category:
Maintainability / Magic Values

Location:
app/Models/Movie.php:64-67, 92-102, 127-130

Problem

The model uses weakly documented status fields:

```php
'status',
'is_hidden',
'manual_override_status',
'is_hot',
```

and filters with raw values:

```php
return $query->where('status', 1)
    ->where('is_hidden', 0);
```

Why this matters

It is unclear what `status` means as a boolean, how it interacts with `is_hidden`, and what integer values `manual_override_status` can contain. Hidden coupling between these fields makes movie visibility rules hard to reason about and easy to break.

How to fix

Use enums/constants and centralize visibility decisions.

Example

```php
enum MovieStatus: int
{
    case Inactive = 0;
    case Active = 1;
}
```

or constants:

```php
public const STATUS_ACTIVE = 1;
public const STATUS_INACTIVE = 0;
```

----------------------------------------------------

### Issue #12

Severity:
Medium

Category:
Validation / Data Integrity

Location:
app/Models/Movie.php:47-68

Problem

The model allows mass assignment for fields requiring strong validation:

```php
'duration',
'release_date',
'end_date',
'age_rating',
'backdrops',
```

The model does not enforce invariants such as positive duration, valid date ordering, or bounded backdrop structure.

Why this matters

Invalid movie data can break scheduling and public display. For example:
- `duration <= 0` makes showtime scheduling invalid.
- `end_date < release_date` causes movies to disappear incorrectly.
- malformed `backdrops` JSON can break frontend assumptions.

How to fix

Validate these fields in FormRequests/services and add database constraints where possible.

Example

```php
'duration' => ['required', 'integer', 'min:1', 'max:600'],
'release_date' => ['required', 'date'],
'end_date' => ['nullable', 'date', 'after_or_equal:release_date'],
'backdrops' => ['nullable', 'array'],
'backdrops.*' => ['string', 'max:2048'],
```

----------------------------------------------------

### Issue #13

Severity:
Medium

Category:
Performance / N+1 Queries

Location:
app/Models/Movie.php:104-124

Problem

The model defines multiple relationships:

```php
public function showtimes(): HasMany
public function categories(): BelongsToMany
public function formats(): BelongsToMany
public function subtitles(): BelongsToMany
```

There are no default eager-loading decisions or dedicated query scopes for common API listing use cases.

Why this matters

Movie listing/detail endpoints commonly need categories, formats, subtitles, and showtimes. If controllers/resources access these relationships without eager loading, this model can contribute to N+1 queries.

How to fix

Define explicit scopes for common API views or require controllers/services to eager load relationships.

Example

```php
public function scopeForListing($query)
{
    return $query->with(['categories', 'formats', 'subtitles']);
}
```

----------------------------------------------------

### Issue #14

Severity:
Low

Category:
Laravel Best Practices / Type Safety

Location:
app/Models/Movie.php:16-31

Problem

The model overrides `boot()` without a return type:

```php
protected static function boot()
```

Why this matters

Modern PHP/Laravel code should use explicit return types where possible for readability and static analysis.

How to fix

Add a `void` return type.

Example

```php
protected static function boot(): void
{
    parent::boot();
    // ...
}
```

----------------------------------------------------

### Issue #15

Severity:
Low

Category:
Laravel Best Practices / Query Scopes

Location:
app/Models/Movie.php:127-158

Problem

Query scopes do not type-hint their query parameter or return type:

```php
public function scopeActive($query)
```

Why this matters

Untyped scopes reduce IDE/static analysis support and make query composition less self-documenting.

How to fix

Type scopes with `Builder` and return `Builder`.

Example

```php
use Illuminate\Database\Eloquent\Builder;

public function scopeActive(Builder $query): Builder
{
    return $query->where('status', true)
        ->where('is_hidden', false);
}
```

----------------------------------------------------

### Issue #16

Severity:
Low

Category:
Maintainability / Type Documentation

Location:
app/Models/Movie.php:12-160

Problem

The model has no PHPDoc annotations for dynamic Eloquent properties and relationships.

Why this matters

This model is central to the domain and has many fields with business meaning. Missing annotations make static analysis weaker and increase accidental misuse of dynamic properties.

How to fix

Add PHPDoc or adopt Laravel IDE Helper/static analysis conventions.

Example

```php
/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property int $duration
 * @property \Illuminate\Support\Carbon|null $release_date
 * @property string $surcharge
 * @property bool $status
 * @property bool $is_hidden
 */
class Movie extends Model
{
    // ...
}
```

----------------------------------------------------

Final Assessment

`Movie` is functional but not production-ready for a high-traffic booking platform without stronger data integrity and authorization safeguards. The most serious risks are race-prone slug generation, mass assignment of pricing/visibility fields, missing pricing and scheduling invariants, weak date semantics, and unclear status modeling.
