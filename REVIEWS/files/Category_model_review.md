====================================================

File:
app/Models/Category.php

Overall Score:
6.4/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Uses `SoftDeletes`, which is appropriate for taxonomy data referenced by movies.
- Uses `HasFactory`, improving test setup ergonomics.
- Defines a `movies()` relationship for category/movie association.
- Casts `status` to boolean.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Data Integrity / Validation

Location:
app/Models/Category.php:13-18

Problem

`slug` is mass assignable:

```php
protected $fillable = [
    'name',
    'slug',
    'description',
    'status',
];
```

The model does not enforce slug uniqueness, normalization, or immutability.

Why this matters

Category slugs are commonly used as public identifiers in APIs and URLs. If duplicate or malformed slugs are allowed, category lookup can become ambiguous, public URLs can break, and API filtering can return incorrect data. If soft-deleted rows are present, uniqueness must be designed intentionally to avoid duplicate active slugs.

How to fix

Validate slug format and uniqueness in requests/services and enforce uniqueness at database level for active categories. Generate slugs server-side from `name` where possible instead of trusting client-provided values.

Example

```php
'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($category?->id)]
```

Database example:

```php
$table->string('slug')->unique();
```

If soft deletes require slug reuse, implement an explicit restore/reuse policy rather than accidental duplicates.

----------------------------------------------------

### Issue #2

Severity:
High

Category:
Authorization / Mass Assignment

Location:
app/Models/Category.php:13-18

Problem

`status` is mass assignable:

```php
'status',
```

Why this matters

Category visibility is business-critical. If non-admin or insufficiently authorized endpoints pass request data directly into `Category::create()` or `update()`, callers can publish, unpublish, or hide categories by changing `status`. The model makes this easy to misuse.

How to fix

Only allow `status` changes through authorized admin workflows. Split create/update DTOs or validated payloads so public endpoints cannot pass privileged fields.

Example

```php
$category->fill($request->safe()->only([
    'name',
    'slug',
    'description',
]));

if ($user->can('publish', Category::class)) {
    $category->status = $validated['status'];
}
```

----------------------------------------------------

### Issue #3

Severity:
Medium

Category:
Laravel Best Practices / Type Safety

Location:
app/Models/Category.php:27-31

Problem

The relationship method has no return type:

```php
public function movies()
{
    return $this->belongsToMany(Movie::class, 'categories_movies')
        ->withTimestamps();
}
```

Why this matters

Missing relationship return types reduce static analysis quality and IDE support. In a Laravel production application, explicit relationship types improve maintainability and make refactors safer.

How to fix

Import and declare `BelongsToMany`.

Example

```php
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

public function movies(): BelongsToMany
{
    return $this->belongsToMany(Movie::class, 'categories_movies')
        ->withTimestamps();
}
```

----------------------------------------------------

### Issue #4

Severity:
Medium

Category:
Database Correctness / Pivot Integrity

Location:
app/Models/Category.php:27-30

Problem

The pivot table name is hard-coded as `categories_movies`:

```php
return $this->belongsToMany(Movie::class, 'categories_movies')
    ->withTimestamps();
```

No related key names are specified.

Why this matters

Laravel's conventional pivot table name for `Category` and `Movie` would usually be `category_movie` in alphabetical singular form. A custom table name is valid, but relying on inferred foreign key names while using a non-conventional table increases coupling to migration details. If the pivot migration uses different column names or lacks constraints, the relationship will fail at runtime or allow orphaned pivot rows.

How to fix

Either follow Laravel conventions or specify keys explicitly and ensure the migration has foreign keys and a composite unique index.

Example

```php
return $this->belongsToMany(
    Movie::class,
    'categories_movies',
    'category_id',
    'movie_id'
)->withTimestamps();
```

Migration safeguards:

```php
$table->foreignId('category_id')->constrained()->cascadeOnDelete();
$table->foreignId('movie_id')->constrained()->cascadeOnDelete();
$table->unique(['category_id', 'movie_id']);
```

----------------------------------------------------

### Issue #5

Severity:
Medium

Category:
Database Correctness / Duplicate Data

Location:
app/Models/Category.php:27-30

Problem

The `movies()` relationship does not indicate any protection against duplicate movie/category links:

```php
return $this->belongsToMany(Movie::class, 'categories_movies')
    ->withTimestamps();
```

Why this matters

If the pivot table does not enforce uniqueness, the same movie can be attached to the same category multiple times. This can duplicate movies in API responses, inflate counts, break filtering, and create confusing admin behavior.

How to fix

Add a composite unique index to the pivot table and use `syncWithoutDetaching()` or `sync()` intentionally.

Example

```php
$table->unique(['category_id', 'movie_id']);
```

Service-level attach:

```php
$category->movies()->syncWithoutDetaching([$movieId]);
```

----------------------------------------------------

### Issue #6

Severity:
Medium

Category:
Business Logic / API Consistency

Location:
app/Models/Category.php:20-25

Problem

`status` is cast to boolean:

```php
protected function casts(): array
{
    return [
        'status' => 'boolean',
    ];
}
```

The model does not provide named scopes for active/inactive categories.

Why this matters

Without a reusable `active()` scope, every controller/service must remember to filter by `status`. This leads to inconsistent APIs where some endpoints may expose inactive categories or show movies under hidden categories.

How to fix

Add explicit query scopes and use them in read APIs.

Example

```php
public function scopeActive($query)
{
    return $query->where('status', true);
}
```

Usage:

```php
Category::active()->with('movies')->get();
```

----------------------------------------------------

### Issue #7

Severity:
Low

Category:
Clean Code / Domain Modeling

Location:
app/Models/Category.php:13-18

Problem

`status` is a generic boolean field:

```php
'status',
```

Why this matters

A generic boolean called `status` is less expressive than `is_active` or a typed enum. It forces developers to remember what `true` and `false` mean and makes APIs less self-documenting.

How to fix

Rename to `is_active` in a future migration or introduce semantic accessors/scopes while maintaining backward compatibility.

Example

```php
protected $casts = [
    'is_active' => 'boolean',
];
```

----------------------------------------------------

### Issue #8

Severity:
Low

Category:
Maintainability / Type Documentation

Location:
app/Models/Category.php:9-32

Problem

The model has no PHPDoc properties or relationship annotations.

Why this matters

Eloquent's dynamic properties make it easy to introduce mistakes in services and resources. Static analysis is weaker without property annotations, especially for fields like `status` that have business meaning.

How to fix

Add PHPDoc or adopt Laravel IDE Helper/static analysis conventions.

Example

```php
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $status
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Movie> $movies
 */
class Category extends Model
{
    // ...
}
```

----------------------------------------------------

Final Assessment

`Category` is a small model, but it controls public taxonomy and movie classification. The primary production risks are weak slug invariants, mass-assignable visibility state, and insufficiently explicit pivot relationship integrity. Before approval, slug uniqueness/normalization, pivot constraints, authorization around `status`, and typed relationship declarations should be addressed.
