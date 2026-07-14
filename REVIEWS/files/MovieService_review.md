# File Review: MovieService.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Services/MovieService.php  
**Lines:** 316  
**Type:** Service Layer - Movie Management

---

## File Information

**Path:** `app/Services/MovieService.php`  
**Type:** Laravel Service Class  
**Lines:** 316  
**Complexity:** Medium  

**Purpose:**  
Handles movie-related business logic:
- Lists movies with filters, search, sorting, and pagination
- Retrieves now-showing/upcoming movies
- Searches movies
- Creates, updates, deletes movies
- Syncs movie categories
- Caches movie details and statistics

**Business Impact:** 🟡 HIGH - Public catalog and admin movie management

---

## Overall Score

**Code Quality:** 6.6/10  
**Security:** 5.8/10  
**Performance:** 6.2/10  
**Maintainability:** 6.4/10  
**Laravel Best Practice:** 6.2/10  

**Overall Score:** 6.2/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

1. ✅ **Service Layer Exists** - Controllers can delegate movie business logic instead of becoming fat controllers
2. ✅ **Transactions Used for Create/Update** - Category sync and movie persistence are grouped atomically
3. ✅ **Basic Eager Loading Present** - `categories` and showtime relationships are eager-loaded in read paths
4. ✅ **Cache Used for Expensive Detail/Stats Reads** - Movie details and statistics are cached
5. ✅ **Structured Logging Exists** - Create/update/delete and failure paths emit logs
6. ✅ **Pagination Used** - Listing endpoint uses `paginate()` instead of loading all movies

---

## Issues Found

### Issue #1: User-Controlled Sort Column Passed Directly to `orderBy()`

**Severity:** 🔴 CRITICAL  
**Category:** Security - SQL Injection / Query Integrity  
**Location:** Lines 56-63

**Evidence:**
```php
$sortBy = $filters['sort_by'] ?? 'release_date';
$sortDir = $filters['sort_dir'] ?? 'desc';
$perPage = $filters['per_page'] ?? 12;

$movies = $query
    ->orderBy($sortBy, $sortDir)
    ->orderBy('id', 'desc')
    ->paginate($perPage)
    ->withQueryString();
```

**Problem:**
`sort_by` and `sort_dir` are taken from `$filters` and passed directly into `orderBy()` without an allowlist.

Even if Laravel wraps identifiers, accepting arbitrary column names is not production-safe. It can:
- expose internal columns through sorting behavior
- break queries with invalid columns
- allow unexpected raw-like direction values depending on framework/version behavior
- create inconsistent API behavior

**Why this matters:**
Public list endpoints are frequently called by untrusted clients. Sorting must be constrained to known, indexed, supported fields.

**How to fix:**
Use strict allowlists for sortable columns and directions.

**Example:**
```php
$allowedSortColumns = [
    'release_date',
    'title',
    'duration',
    'created_at',
    'updated_at',
];

$allowedDirections = ['asc', 'desc'];

$sortBy = in_array($filters['sort_by'] ?? null, $allowedSortColumns, true)
    ? $filters['sort_by']
    : 'release_date';

$sortDir = in_array(strtolower($filters['sort_dir'] ?? ''), $allowedDirections, true)
    ? strtolower($filters['sort_dir'])
    : 'desc';

$movies = $query
    ->orderBy($sortBy, $sortDir)
    ->orderBy('id', 'desc')
    ->paginate($perPage);
```

---

### Issue #2: Unbounded `per_page` Can Cause Performance Abuse

**Severity:** 🟠 HIGH  
**Category:** Performance / Abuse Control  
**Location:** Lines 58-63

**Evidence:**
```php
$perPage = $filters['per_page'] ?? 12;

$movies = $query
    ->orderBy($sortBy, $sortDir)
    ->orderBy('id', 'desc')
    ->paginate($perPage)
    ->withQueryString();
```

**Problem:**
`per_page` is not bounded. A client can request extremely large pages and force heavy database queries, large response payloads, and high memory usage.

**Why this matters:**
Movie listing is likely public. Unbounded pagination is a simple denial-of-service vector and can degrade database performance.

**How to fix:**
Clamp page size to a safe maximum.

**Example:**
```php
$perPage = min(
    max((int) ($filters['per_page'] ?? 12), 1),
    50
);
```

---

### Issue #3: Raw Search Keyword Used Without Escaping LIKE Wildcards

**Severity:** 🟡 MEDIUM  
**Category:** Correctness / Performance  
**Location:** Lines 41-49

**Evidence:**
```php
->when($filters['q'] ?? null, function ($query, $keyword) {
    $query->where(function ($q) use ($keyword) {
        $q->where('title', 'like', "%{$keyword}%")
            ->orWhere('original_title', 'like', "%{$keyword}%")
            ->orWhere('director', 'like', "%{$keyword}%")
            ->orWhere('cast', 'like', "%{$keyword}%")
            ->orWhere('description', 'like', "%{$keyword}%");
    });
})
```

**Problem:**
The keyword is interpolated into LIKE patterns without escaping `%`, `_`, or backslash. Parameter binding protects against classic SQL injection, but wildcard injection remains possible.

A user searching for `%` or `_` can unintentionally or intentionally match far more rows than intended.

**Why this matters:**
This can create unexpectedly expensive queries and poor search relevance. On public endpoints, wildcard abuse can amplify load.

**How to fix:**
Escape LIKE wildcards and set a maximum search length.

**Example:**
```php
$keyword = trim(mb_substr($keyword, 0, 100));
$escaped = addcslashes($keyword, '\\%_');

$q->where('title', 'like', "%{$escaped}%")
    ->orWhere('original_title', 'like', "%{$escaped}%")
    ->orWhere('director', 'like', "%{$escaped}%")
    ->orWhere('cast', 'like', "%{$escaped}%")
    ->orWhere('description', 'like', "%{$escaped}%");
```

---

### Issue #4: Search Across Multiple Text Columns With Leading Wildcards Is Expensive

**Severity:** 🟡 MEDIUM  
**Category:** Performance  
**Location:** Lines 41-49

**Evidence:**
```php
$q->where('title', 'like', "%{$keyword}%")
    ->orWhere('original_title', 'like', "%{$keyword}%")
    ->orWhere('director', 'like', "%{$keyword}%")
    ->orWhere('cast', 'like', "%{$keyword}%")
    ->orWhere('description', 'like', "%{$keyword}%");
```

**Problem:**
Leading-wildcard LIKE queries (`%keyword%`) across five columns generally cannot use normal B-tree indexes efficiently.

**Why this matters:**
As movie data grows, public search can become slow and database-heavy. Searching `description` and `cast` with leading wildcards is particularly costly.

**How to fix:**
Use a full-text index/search engine or limit wildcard search to indexed/smaller columns.

**Example:**
```php
$query->whereFullText(['title', 'original_title', 'director', 'cast'], $keyword);
```

If MySQL full-text is unavailable, restrict fields and cap keyword length.

---

### Issue #5: Create Path Mass Assigns Entire `$data` Into `Movie::create()`

**Severity:** 🟠 HIGH  
**Category:** Security - Mass Assignment / Data Integrity  
**Location:** Lines 125-138

**Evidence:**
```php
public function createMovie(array $data): Movie
{
    try {
        $movie = DB::transaction(function () use ($data) {
            // Extract category IDs
            $categoryIds = $data['category_ids'] ?? [];
            unset($data['category_ids']);

            // Generate slug if not provided
            $data['slug'] = $data['slug'] ?? Str::slug($data['title']);

            // Create movie
            $movie = Movie::create($data);
```

**Problem:**
The service accepts a generic array and passes it directly to `Movie::create()` after only removing `category_ids`.

This assumes all callers always pass validated and authorized fields. The service itself does not define an allowlist of fields it is willing to persist.

**Why this matters:**
If a controller accidentally passes `$request->all()` or an admin endpoint accepts unexpected fields, unauthorized columns can be written if the model allows them. This is a common Laravel production vulnerability.

**How to fix:**
Whitelist service-level fields even when FormRequest validation exists.

**Example:**
```php
$payload = Arr::only($data, [
    'title',
    'original_title',
    'slug',
    'description',
    'director',
    'cast',
    'duration',
    'release_date',
    'poster_url',
    'trailer_url',
    'age_rating',
    'status',
    'is_hot',
    'is_hidden',
]);

$movie = Movie::create($payload);
```

---

### Issue #6: Update Path Mass Assigns Entire `$data` Into `$movie->update()`

**Severity:** 🟠 HIGH  
**Category:** Security - Mass Assignment / Data Integrity  
**Location:** Lines 203-220

**Evidence:**
```php
public function updateMovie(int $id, array $data): Movie
{
    try {
        $movie = DB::transaction(function () use ($id, $data) {
            $movie = Movie::findOrFail($id);
            $oldSlug = $movie->slug;

            // Extract category IDs
            $categoryIds = $data['category_ids'] ?? null;
            unset($data['category_ids']);

            // Auto-generate slug if title changed but slug not provided
            if (isset($data['title']) && empty($data['slug'])) {
                $data['slug'] = Str::slug($data['title']);
            }

            // Update movie
            $movie->update($data);
```

**Problem:**
Like the create path, the update path passes the entire input array into Eloquent mass assignment.

**Why this matters:**
Update endpoints are especially sensitive because they can change publication status, hidden state, slug, release metadata, and potentially other administrative fields. The service should not depend entirely on external validation to prevent accidental writes.

**How to fix:**
Use an explicit allowlist for update fields.

**Example:**
```php
$payload = Arr::only($data, [
    'title',
    'original_title',
    'slug',
    'description',
    'director',
    'cast',
    'duration',
    'release_date',
    'poster_url',
    'trailer_url',
    'age_rating',
    'status',
    'is_hot',
    'is_hidden',
]);

$movie->update($payload);
```

---

### Issue #7: Slug Generation Does Not Ensure Uniqueness

**Severity:** 🟠 HIGH  
**Category:** Database Correctness / API Correctness  
**Location:** Lines 133-137 and 214-220

**Evidence:**
```php
$data['slug'] = $data['slug'] ?? Str::slug($data['title']);

$movie = Movie::create($data);
```

```php
if (isset($data['title']) && empty($data['slug'])) {
    $data['slug'] = Str::slug($data['title']);
}

$movie->update($data);
```

**Problem:**
The generated slug is a direct slug of the title. There is no uniqueness check, suffix generation, or duplicate handling.

**Why this matters:**
Two movies can have the same title or same slug candidate. This can cause:
- database unique constraint exceptions
- ambiguous route resolution
- cache key collisions for `movie:slug:{slug}`
- broken public URLs

**How to fix:**
Generate unique slugs and enforce a unique database index.

**Example:**
```php
private function uniqueSlug(string $title, ?int $ignoreId = null): string
{
    $base = Str::slug($title);
    $slug = $base;
    $counter = 2;

    while (
        Movie::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()
    ) {
        $slug = "{$base}-{$counter}";
        $counter++;
    }

    return $slug;
}
```

---

### Issue #8: `getMovie()` Searches by ID and Slug Simultaneously for Numeric Slugs

**Severity:** 🟡 MEDIUM  
**Category:** Correctness / API Consistency  
**Location:** Lines 171-184

**Evidence:**
```php
$cacheKey = is_numeric($idOrSlug)
    ? "movie:id:{$idOrSlug}"
    : "movie:slug:{$idOrSlug}";

return Cache::remember($cacheKey, 1800, function () use ($idOrSlug) {
    $movie = Movie::with(['categories', 'showtimes.screen.theater'])
        ->where(function ($query) use ($idOrSlug) {
            $query->where('id', $idOrSlug)
                ->orWhere('slug', $idOrSlug);
        })
        ->firstOrFail();
```

**Problem:**
For numeric input, the cache key is treated as ID, but the query still searches both `id` and `slug`.

If a movie has slug `"123"` and another movie has ID `123`, this method can return whichever row the database finds first, while caching it under `movie:id:123`.

**Why this matters:**
This can return the wrong movie and poison cache entries. Public movie detail pages may show incorrect data.

**How to fix:**
Use separate lookup paths.

**Example:**
```php
$query = Movie::with(['categories', 'showtimes.screen.theater']);

$movie = is_numeric($idOrSlug)
    ? $query->whereKey((int) $idOrSlug)->firstOrFail()
    : $query->where('slug', $idOrSlug)->firstOrFail();
```

If numeric slugs are allowed, do not infer identifier type by `is_numeric()`; accept explicit route context instead.

---

### Issue #9: Cached Movie Detail Can Become Stale When Related Showtime/Theater Data Changes

**Severity:** 🟡 MEDIUM  
**Category:** Cache Correctness  
**Location:** Lines 178-189 and 227-233

**Evidence:**
```php
return Cache::remember($cacheKey, 1800, function () use ($idOrSlug) {
    $movie = Movie::with(['categories', 'showtimes.screen.theater'])
        ...
        ->firstOrFail();
```

```php
Cache::forget("movie:id:{$id}");
Cache::forget("movie:slug:{$oldSlug}");
if ($movie->slug !== $oldSlug) {
    Cache::forget("movie:slug:{$movie->slug}");
}
Cache::forget('movies:statistics');
```

**Problem:**
`getMovie()` caches movie details including `categories`, `showtimes`, `screen`, and `theater`, but this service only invalidates the cache when the movie itself is updated/deleted.

Changes to showtimes, screens, theaters, or category names will not invalidate this cached movie detail.

**Why this matters:**
Cinema schedules are time-sensitive. Stale showtimes or theater/screen data can cause users to see incorrect availability and may affect booking decisions.

**How to fix:**
Use cache tags if supported or centralize invalidation across related services.

**Example:**
```php
Cache::tags(["movie:{$movieId}", 'movies'])->remember($cacheKey, 1800, fn () => ...);

// In ShowtimeService when showtimes change:
Cache::tags(["movie:{$movieId}"])->flush();
```

If tags are unavailable, lower TTL or explicitly forget affected movie keys in showtime/screen/theater/category services.

---

### Issue #10: Cache Invalidation Runs Inside Database Transaction

**Severity:** 🟡 MEDIUM  
**Category:** Transaction Correctness / Cache Consistency  
**Location:** Lines 206-236

**Evidence:**
```php
$movie = DB::transaction(function () use ($id, $data) {
    ...
    $movie->update($data);
    ...
    // Invalidate caches
    Cache::forget("movie:id:{$id}");
    Cache::forget("movie:slug:{$oldSlug}");
    if ($movie->slug !== $oldSlug) {
        Cache::forget("movie:slug:{$movie->slug}");
    }
    Cache::forget('movies:statistics');

    return $movie->load('categories');
});
```

**Problem:**
Cache invalidation is performed inside the transaction. If the transaction later rolls back after cache invalidation, cache state and database state can diverge. Even though there is no code after invalidation that currently throws, this is a fragile pattern.

**Why this matters:**
Production code evolves. Cache invalidation should happen after a successful commit, not before commit.

**How to fix:**
Use `DB::afterCommit()` or perform invalidation after the transaction returns.

**Example:**
```php
$movie = DB::transaction(function () use ($id, $data) {
    // update and sync only
    return $movie->load('categories');
});

Cache::forget("movie:id:{$id}");
Cache::forget("movie:slug:{$oldSlug}");
Cache::forget('movies:statistics');
```

---

### Issue #11: Delete Is Not Wrapped in a Transaction

**Severity:** 🟡 MEDIUM  
**Category:** Database Correctness / Maintainability  
**Location:** Lines 259-267

**Evidence:**
```php
public function deleteMovie(int $id): bool
{
    try {
        $movie = Movie::findOrFail($id);
        $title = $movie->title;
        $slug = $movie->slug;

        $movie->delete();
```

**Problem:**
Deletion is not transactional. The current method only deletes one model, but movie deletion can have dependent relationships such as categories, showtimes, tickets, or media depending on model/database configuration.

**Why this matters:**
If future cleanup is added, partial deletion becomes possible. For domain objects connected to bookings/showtimes, delete behavior must be deliberate and atomic.

**How to fix:**
Wrap deletion and any relationship cleanup in a transaction. Also consider whether movies with existing showtimes/orders should be soft-hidden instead of deleted.

**Example:**
```php
DB::transaction(function () use ($movie) {
    $movie->categories()->detach();
    $movie->delete();
});
```

---

### Issue #12: No Business Rule Prevents Deleting Movies With Existing Showtimes

**Severity:** 🟠 HIGH  
**Category:** Business Logic / Data Integrity  
**Location:** Lines 259-267

**Evidence:**
```php
$movie = Movie::findOrFail($id);
$title = $movie->title;
$slug = $movie->slug;

$movie->delete();
```

**Problem:**
The service deletes a movie without checking whether it has showtimes, bookings, tickets, or historical business records.

**Why this matters:**
Deleting movies that have scheduled showtimes or historical orders can break:
- booking flows
- reporting
- ticket history
- foreign key constraints
- customer order details

For a cinema booking system, movie deletion should be restricted or converted to hide/archive.

**How to fix:**
Check dependencies before deletion and prefer soft-delete/archive/hide.

**Example:**
```php
if ($movie->showtimes()->exists()) {
    throw ValidationException::withMessages([
        'movie' => 'Cannot delete a movie that has showtimes. Archive or hide it instead.',
    ]);
}

$movie->delete();
```

---

### Issue #13: Logging Includes Full Input Data on Create Failure

**Severity:** 🟡 MEDIUM  
**Category:** Security - Sensitive Data Exposure / Logging  
**Location:** Lines 156-160

**Evidence:**
```php
Log::error('Failed to create movie', [
    'data' => $data,
    'error' => $e->getMessage()
]);
```

**Problem:**
The entire `$data` payload is logged on create failure.

**Why this matters:**
Movie payloads may include large descriptions, external URLs, image paths, or future sensitive/internal fields. Logging full request-like payloads increases log volume and can leak data into centralized logs.

**How to fix:**
Log only safe identifiers and metadata.

**Example:**
```php
Log::error('Failed to create movie', [
    'title' => $data['title'] ?? null,
    'slug' => $data['slug'] ?? null,
    'error_class' => $e::class,
]);
```

---

### Issue #14: Exception Logging Loses Useful Diagnostic Context

**Severity:** 🔵 LOW  
**Category:** Observability / Maintainability  
**Location:** Lines 73-79, 156-162, 244-250, 279-285, 309-314

**Evidence:**
```php
Log::error('Failed to update movie', [
    'movie_id' => $id,
    'error' => $e->getMessage()
]);
throw $e;
```

**Problem:**
Only the exception message is logged. The exception class, code, and stack trace are not explicitly included. Laravel can include stack traces when the exception object is passed in context, but this code does not do that.

**Why this matters:**
Production debugging needs enough detail to separate validation errors, database constraint failures, cache failures, and unexpected exceptions.

**How to fix:**
Include exception metadata or pass the exception object.

**Example:**
```php
Log::error('Failed to update movie', [
    'movie_id' => $id,
    'exception' => $e,
]);
```

---

### Issue #15: Service Catches and Immediately Rethrows Exceptions Without Adding Domain Behavior

**Severity:** 🔵 LOW  
**Category:** Clean Code / Maintainability  
**Location:** Lines 28-79, 127-162, 173-193, 205-250, 261-285, 295-314

**Evidence:**
```php
try {
    ...
} catch (\Exception $e) {
    Log::error(...);
    throw $e;
}
```

**Problem:**
Most methods catch `\Exception`, log, then rethrow the same exception. This adds boilerplate and can cause duplicate logging if global exception handling also logs the exception.

**Why this matters:**
Repeated catch/rethrow blocks make the service noisy and harder to read. They also risk log duplication in production.

**How to fix:**
Only catch exceptions when translating them into domain exceptions, adding recovery, or adding highly valuable context. Otherwise rely on Laravel's centralized exception handler.

---

### Issue #16: `getMovie()` Logs “Movie retrieved (cached)” Only During Cache Miss

**Severity:** 🔵 LOW  
**Category:** Observability / Misleading Logging  
**Location:** Lines 178-187

**Evidence:**
```php
return Cache::remember($cacheKey, 1800, function () use ($idOrSlug) {
    $movie = Movie::with(['categories', 'showtimes.screen.theater'])
        ...
        ->firstOrFail();

    Log::info('Movie retrieved (cached)', ['movie_id' => $movie->id]);

    return $movie;
});
```

**Problem:**
The log is inside the cache callback, so it only runs on cache miss, not every retrieval. The message says `(cached)`, which is misleading because this is actually the database fetch used to populate cache.

**Why this matters:**
Misleading logs make production behavior harder to diagnose.

**How to fix:**
Use accurate wording.

```php
Log::info('Movie retrieved from database for cache population', [
    'movie_id' => $movie->id,
]);
```

Or log cache hits/misses explicitly outside this method using metrics instead of per-request info logs.

---

### Issue #17: Info Logging on High-Traffic Read Paths Can Create Log Noise

**Severity:** 🔵 LOW  
**Category:** Performance / Observability  
**Location:** Lines 66-70 and 305

**Evidence:**
```php
Log::info('Movies retrieved', [
    'count' => $movies->count(),
    'total' => $movies->total(),
    'filters' => $filters
]);
```

```php
Log::info('Movie statistics retrieved (cached)', $stats);
```

**Problem:**
Read methods log at `info` level on normal successful operations.

**Why this matters:**
Movie listing and statistics can be high-frequency endpoints. Excessive info logs increase storage cost and reduce signal-to-noise in production monitoring.

**How to fix:**
Use debug-level logs or metrics.

```php
Log::debug('Movies retrieved', [
    'count' => $movies->count(),
    'total' => $movies->total(),
]);
```

---

### Issue #18: Missing Strict Return/Parameter Types for `$idOrSlug`

**Severity:** 🔵 LOW  
**Category:** Code Quality / Type Safety  
**Location:** Lines 171-172

**Evidence:**
```php
public function getMovie($idOrSlug): Movie
```

**Problem:**
The method accepts mixed input despite the docblock stating `string|int`.

**Why this matters:**
This weakens static analysis and allows accidental objects/arrays/null to reach query construction.

**How to fix:**
Use a union type.

```php
public function getMovie(string|int $idOrSlug): Movie
```

---

## Recommendations

### IMMEDIATE

1. **Whitelist Sort Columns and Directions** - Fix untrusted `orderBy()` inputs
2. **Clamp `per_page`** - Prevent oversized pagination responses
3. **Whitelist Create/Update Payload Fields** - Do not pass raw arrays into `Movie::create()` / `update()`
4. **Implement Unique Slug Generation** - Prevent duplicate route/cache keys
5. **Block Deletion of Movies With Showtimes/Bookings** - Prefer archive/hide behavior

### SHORT TERM

6. **Escape LIKE Wildcards** - Prevent wildcard abuse and accidental broad searches
7. **Move Cache Invalidation After Commit** - Use after-commit-safe cache invalidation
8. **Fix Numeric Slug/ID Lookup Ambiguity** - Separate ID and slug query paths
9. **Reduce Info Logging on Read Paths** - Use debug logs or metrics
10. **Improve Exception Logging** - Include exception object/class safely

### LONG TERM

11. **Move Search to Full-Text Index/Search Engine** - Avoid multi-column leading wildcard scans
12. **Use Cache Tags or Central Cache Invalidation Strategy** - Avoid stale showtime/theater data
13. **Introduce DTOs for Movie Create/Update** - Stronger service boundary
14. **Add Tests for Slug Collisions, Delete Rules, Sorting Allowlist, Pagination Limits**

---

## Improved Version Snippet

```php
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

public function getMovies(array $filters): LengthAwarePaginator
{
    $allowedSortColumns = ['release_date', 'title', 'duration', 'created_at'];
    $allowedDirections = ['asc', 'desc'];

    $sortBy = in_array($filters['sort_by'] ?? null, $allowedSortColumns, true)
        ? $filters['sort_by']
        : 'release_date';

    $sortDir = in_array(strtolower($filters['sort_dir'] ?? ''), $allowedDirections, true)
        ? strtolower($filters['sort_dir'])
        : 'desc';

    $perPage = min(max((int) ($filters['per_page'] ?? 12), 1), 50);

    return Movie::query()
        ->with('categories')
        ->when($filters['q'] ?? null, function ($query, string $keyword): void {
            $keyword = trim(mb_substr($keyword, 0, 100));
            $escaped = addcslashes($keyword, '\\%_');

            $query->where(function ($q) use ($escaped): void {
                $q->where('title', 'like', "%{$escaped}%")
                    ->orWhere('original_title', 'like', "%{$escaped}%")
                    ->orWhere('director', 'like', "%{$escaped}%");
            });
        })
        ->orderBy($sortBy, $sortDir)
        ->orderByDesc('id')
        ->paginate($perPage)
        ->withQueryString();
}

public function createMovie(array $data): Movie
{
    return DB::transaction(function () use ($data): Movie {
        $categoryIds = $data['category_ids'] ?? [];

        $payload = Arr::only($data, [
            'title',
            'original_title',
            'slug',
            'description',
            'director',
            'cast',
            'duration',
            'release_date',
            'poster_url',
            'trailer_url',
            'age_rating',
            'status',
            'is_hot',
            'is_hidden',
        ]);

        $payload['slug'] = $payload['slug']
            ?? $this->uniqueSlug($payload['title']);

        $movie = Movie::create($payload);
        $movie->categories()->sync($categoryIds);

        DB::afterCommit(fn () => Cache::forget('movies:statistics'));

        return $movie->load('categories');
    });
}

public function deleteMovie(int $id): bool
{
    $movie = Movie::findOrFail($id);

    if ($movie->showtimes()->exists()) {
        throw ValidationException::withMessages([
            'movie' => 'Cannot delete a movie with existing showtimes.',
        ]);
    }

    DB::transaction(fn () => $movie->delete());

    Cache::forget("movie:id:{$id}");
    Cache::forget("movie:slug:{$movie->slug}");
    Cache::forget('movies:statistics');

    return true;
}
```

---

## Summary

MovieService.php is a useful service layer with basic transactions, pagination, eager loading, cache usage, and logging. However, it is not production-ready due to unsafe query input handling, unbounded pagination, broad mass assignment, fragile slug generation, and insufficient delete business rules.

**Strengths:**
- Clean service abstraction
- Uses transactions for create/update
- Eager loads common relationships
- Caches movie details/statistics
- Logs important write operations

**Main Gaps:**
1. User-controlled `sort_by`/`sort_dir` passed to `orderBy()`
2. `per_page` is unbounded
3. Create/update pass generic arrays into mass assignment
4. Slug uniqueness is not guaranteed
5. Movie deletion ignores showtime/booking dependency rules
6. Cached detail data can become stale when related schedules change

**Status:** 🚫 Request changes before production acceptance

---

*Review completed: 2026-07-14 12:09 PM*  
*File #29/137 - Phase 3: Business Logic (1/20 complete)*
