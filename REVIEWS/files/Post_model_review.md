====================================================

File:
app/Models/Post.php

Overall Score:
5.6/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Uses `HasFactory`, which supports test data creation.
- Defines casts for publication state, publication date, and view count.
- Provides basic scopes for published posts and category filtering.
- Automatically generates a slug when one is not provided.
- Defines an author relationship to `User`.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Authorization / Mass Assignment

Location:
app/Models/Post.php:13-24

Problem

The model allows publication, author ownership, slug, image path, and analytics fields to be mass assigned:

```php
protected $fillable = [
    'title',
    'slug',
    'content',
    'excerpt',
    'author_id',
    'category',
    'featured_image',
    'is_published',
    'published_at',
    'view_count',
];
```

Why this matters

These are privileged fields. If any controller or service uses request-driven mass assignment, a caller can assign posts to another author, publish content without approval, backdate publication, manipulate slugs, alter image paths, or forge view counts. For public CMS content, this can cause unauthorized publication and integrity issues.

How to fix

Separate user-editable content from system-controlled fields. Set `author_id`, publication fields, and `view_count` through explicit service methods after authorization.

Example

```php
protected $fillable = [
    'title',
    'content',
    'excerpt',
    'category',
    'featured_image',
];
```

Use explicit methods:

```php
public function publish(): void
{
    $this->forceFill([
        'is_published' => true,
        'published_at' => now(),
    ])->save();
}
```

----------------------------------------------------

### Issue #2

Severity:
High

Category:
Security / Stored XSS

Location:
app/Models/Post.php:16-17, 83-89

Problem

The model stores raw content and excerpt:

```php
'content',
'excerpt',
```

The accessor only strips tags when deriving an excerpt from content:

```php
return Str::limit(strip_tags($this->content), 150);
```

It does not sanitize stored `content` or an explicitly provided `excerpt`.

Why this matters

Posts are public-facing content. If HTML or script payloads are stored and later rendered without strict escaping/sanitization, this becomes stored XSS. The accessor can also return a manually supplied `excerpt` without stripping tags.

How to fix

Sanitize rich text before persistence using an HTML purifier with an allowlist, or store markdown/plain text and escape at render time. Also sanitize explicitly provided excerpts.

Example

```php
public function setExcerptAttribute(?string $value): void
{
    $this->attributes['excerpt'] = $value ? strip_tags($value) : null;
}
```

For rich content, use a dedicated sanitizer instead of simple `strip_tags`.

----------------------------------------------------

### Issue #3

Severity:
High

Category:
Database Correctness / Slug Uniqueness

Location:
app/Models/Post.php:35-44

Problem

The model generates slugs directly from title without checking uniqueness:

```php
static::creating(function ($post) {
    if (empty($post->slug)) {
        $post->slug = Str::slug($post->title);
    }
});
```

Why this matters

Two posts with the same title will generate the same slug. If slugs are used for public routing, this can cause ambiguous routes, wrong post resolution, or database unique constraint failures at runtime.

How to fix

Enforce a unique slug at the database level and generate collision-safe slugs in the service/model.

Example

```php
$table->string('slug')->unique();
```

```php
$base = Str::slug($post->title);
$slug = $base;
$counter = 2;

while (static::where('slug', $slug)->exists()) {
    $slug = "{$base}-{$counter}";
    $counter++;
}

$post->slug = $slug;
```

For correctness under concurrency, still rely on a database unique index and retry on duplicate-key errors.

----------------------------------------------------

### Issue #4

Severity:
High

Category:
Business Logic / Publication Workflow

Location:
app/Models/Post.php:21-22, 57-62

Problem

Publication state is represented by two independently mutable fields:

```php
'is_published',
'published_at',
```

The published scope requires both:

```php
return $query->where('is_published', true)
             ->whereNotNull('published_at')
             ->where('published_at', '<=', now());
```

There is no invariant ensuring the fields remain consistent.

Why this matters

The model allows invalid publication states:
- `is_published = true` with `published_at = null`
- `is_published = false` with a past `published_at`
- scheduled future posts with unclear state
- published posts backdated through mass assignment

This creates inconsistent API results and admin behavior.

How to fix

Use explicit workflow methods and validate state transitions.

Example

```php
public function publish(?CarbonInterface $publishedAt = null): void
{
    $this->forceFill([
        'is_published' => true,
        'published_at' => $publishedAt ?? now(),
    ])->save();
}

public function unpublish(): void
{
    $this->forceFill([
        'is_published' => false,
        'published_at' => null,
    ])->save();
}
```

----------------------------------------------------

### Issue #5

Severity:
Medium

Category:
Security / Unsafe File Reference

Location:
app/Models/Post.php:20

Problem

`featured_image` is mass assignable:

```php
'featured_image',
```

Why this matters

If this field accepts arbitrary paths or URLs, clients can persist external tracking URLs, malicious content URLs, or references to files they should not control. Public-facing image fields should be constrained to trusted storage paths or sanitized URLs.

How to fix

Only set `featured_image` from a validated upload/storage service. Store a normalized storage path, not arbitrary user input.

Example

```php
$post->forceFill([
    'featured_image' => $storedPath,
])->save();
```

Validate MIME type, size, extension, and storage disk in request/service code.

----------------------------------------------------

### Issue #6

Severity:
Medium

Category:
Data Integrity / Analytics Correctness

Location:
app/Models/Post.php:23, 75-78

Problem

`view_count` is mass assignable while also mutated by `incrementViews()`:

```php
'view_count',
```

```php
public function incrementViews()
{
    $this->increment('view_count');
}
```

Why this matters

View counts are analytics data. Allowing direct mass assignment makes analytics unreliable and allows accidental or malicious tampering.

How to fix

Remove `view_count` from `$fillable` and only mutate it through controlled code paths.

Example

```php
protected $fillable = [
    'title',
    'slug',
    'content',
    'excerpt',
    'category',
    'featured_image',
];
```

----------------------------------------------------

### Issue #7

Severity:
Medium

Category:
Performance / Database Indexing

Location:
app/Models/Post.php:57-69

Problem

The model exposes scopes that filter by publication fields and category:

```php
public function scopePublished($query)
{
    return $query->where('is_published', true)
                 ->whereNotNull('published_at')
                 ->where('published_at', '<=', now());
}

public function scopeCategory($query, $category)
{
    return $query->where('category', $category);
}
```

There is no indication of indexes supporting these query patterns.

Why this matters

Public post listing and category pages can become slow as posts grow. Without indexes, filtering published posts by date and category can require table scans.

How to fix

Add indexes aligned with read patterns.

Example

```php
$table->index(['is_published', 'published_at']);
$table->index(['category', 'is_published', 'published_at']);
$table->unique('slug');
```

----------------------------------------------------

### Issue #8

Severity:
Medium

Category:
Laravel Best Practices / Type Safety

Location:
app/Models/Post.php:35, 49-51, 57-69, 75-88

Problem

The model methods lack return types and relationship return types:

```php
protected static function boot()
public function author()
public function scopePublished($query)
public function scopeCategory($query, $category)
public function incrementViews()
public function getExcerptAttribute($value)
```

Why this matters

Untyped model APIs reduce static analysis support and increase maintenance risk.

How to fix

Add concrete return types and imports.

Example

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

protected static function boot(): void
{
    // ...
}

public function author(): BelongsTo
{
    return $this->belongsTo(User::class, 'author_id');
}

public function scopePublished(Builder $query): Builder
{
    return $query->where('is_published', true)
        ->whereNotNull('published_at')
        ->where('published_at', '<=', now());
}
```

----------------------------------------------------

### Issue #9

Severity:
Medium

Category:
Validation / Input Correctness

Location:
app/Models/Post.php:67-70

Problem

The category scope accepts arbitrary untyped input:

```php
public function scopeCategory($query, $category)
{
    return $query->where('category', $category);
}
```

Why this matters

Although query binding prevents SQL injection, arbitrary categories create inconsistent URLs, filters, and admin/public behavior. Category values should be normalized and constrained.

How to fix

Validate and normalize categories at request/service boundaries. Prefer a category enum/reference table if categories are controlled.

Example

```php
public function scopeCategory(Builder $query, string $category): Builder
{
    return $query->where('category', Str::slug($category));
}
```

----------------------------------------------------

### Issue #10

Severity:
Medium

Category:
Correctness / Slug Lifecycle

Location:
app/Models/Post.php:39-43

Problem

Slug generation only runs on create:

```php
static::creating(function ($post) {
    if (empty($post->slug)) {
        $post->slug = Str::slug($post->title);
    }
});
```

Why this matters

If a title is updated and slug is intentionally expected to follow the title, it will not update. If slug should be permanent for SEO, the model does not document or enforce that policy. This ambiguity causes routing and SEO inconsistencies.

How to fix

Define slug lifecycle explicitly:
- immutable slug after create; or
- controlled slug updates with redirect history.

Example

```php
// Keep slug immutable unless changed explicitly by an authorized admin.
```

If changing slugs, preserve redirects from old slugs.

----------------------------------------------------

### Issue #11

Severity:
Low

Category:
Clean Code / Formatting

Location:
app/Models/Post.php:59-61

Problem

The chained query indentation is inconsistent with common Laravel formatting:

```php
return $query->where('is_published', true)
             ->whereNotNull('published_at')
             ->where('published_at', '<=', now());
```

Why this matters

Minor formatting inconsistencies reduce readability and make diffs noisier.

How to fix

Use project-standard Laravel/Pint formatting.

Example

```php
return $query->where('is_published', true)
    ->whereNotNull('published_at')
    ->where('published_at', '<=', now());
```

----------------------------------------------------

### Issue #12

Severity:
Low

Category:
Maintainability / Type Documentation

Location:
app/Models/Post.php:9-90

Problem

The model has no PHPDoc annotations for dynamic properties and relationships.

Why this matters

Static analysis cannot infer important CMS fields such as `slug`, `author_id`, `is_published`, `published_at`, and `view_count` without helper metadata.

How to fix

Add PHPDoc or Laravel IDE helper metadata.

Example

```php
/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $content
 * @property int $author_id
 * @property bool $is_published
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property int $view_count
 */
class Post extends Model
{
    // ...
}
```

----------------------------------------------------

Final Assessment

`Post` is functional but too permissive for production CMS content. The model allows mass assignment of author, publication workflow, slugs, image references, and analytics fields; stores public-facing content without model-level sanitization guarantees; and generates slugs without uniqueness handling. The publication workflow and slug lifecycle need explicit invariants before this model is safe for a public production application.