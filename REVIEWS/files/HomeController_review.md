# File Review: HomeController.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Controllers/HomeController.php  
**Lines:** 140  
**Type:** Public Home Page/API Controller

---

## File Summary

`HomeController` serves the public home view and a JSON endpoint that aggregates featured movie, now-showing movies, upcoming movies, movie selector options, cinema selector options, and available dates.

The controller performs direct Eloquent queries, transforms movie data manually, and returns a JSON response through `ApiResponse`.

---

## Overall Score

**Overall Score:** 6.1/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

- Uses eager loading for movie categories on movie listing queries.
- Limits homepage movie sections to 8 records.
- Uses a consistent response helper through `ApiResponse`.
- Avoids accepting raw request input in this controller, reducing injection risk.
- Uses scoped queries such as `active()`, `available()`, `upcoming()`, and `nowShowing()` for domain readability.
- Handles missing featured movie gracefully by falling back to now-showing or upcoming movies.

---

## Issues

### Issue #1

**Severity:** Medium  
**Category:** Architecture / Fat Controller  
**Location:** app/Http/Controllers/HomeController.php:22-105

**Problem**

The `data()` method contains business/query orchestration and response-shaping logic directly inside the controller.

```php
public function data(): JsonResponse
{
    $featuredMovie = Movie::query()
        ->with('categories:id,name')
        ->active()
        ->where(function ($query) {
            $query->where('is_hot', true)
                ->orWhereHas('showtimes', function ($showtimeQuery) {
                    $showtimeQuery->available()->upcoming();
                });
        })
        ->orderByDesc('is_hot')
        ->latest('release_date')
        ->first();

    // multiple additional queries and transformations...

    return $this->successResponse([
        'featured_movie' => $this->transformMovie($featuredMovie),
        'now_showing_movies' => $nowShowingMovies->map(fn (Movie $movie) => $this->transformMovie($movie))->values(),
        'upcoming_movies' => $upcomingMovies->map(fn (Movie $movie) => $this->transformMovie($movie))->values(),
        'movie_options' => $movieOptions,
        'cinema_options' => $cinemaOptions,
        'available_dates' => $availableDates,
    ], 'Home data loaded successfully');
}
```

**Why this matters**

Controllers should stay thin. This method now owns data selection rules, homepage composition, fallback rules, serialization, and formatting. That makes the endpoint harder to test, harder to cache, and harder to reuse across web/mobile clients.

**How to fix**

Move homepage composition into a service/read model and serialization into API resources.

```php
public function data(HomePageService $homePageService): JsonResponse
{
    return $this->successResponse(
        $homePageService->getHomePageData(),
        'Home data loaded successfully'
    );
}
```

---

### Issue #2

**Severity:** Medium  
**Category:** Performance / Cache Opportunity  
**Location:** app/Http/Controllers/HomeController.php:22-104

**Problem**

The endpoint performs multiple read queries on every request and has no caching.

```php
$featuredMovie = Movie::query()...
$nowShowingMovies = Movie::query()...
$upcomingMovies = Movie::query()...
$movieOptions = Movie::query()...
$cinemaOptions = Theater::query()...
$availableDates = Showtime::query()...
```

**Why this matters**

Home page data is public, read-heavy, and changes relatively infrequently. Recomputing the full home payload for every user wastes database capacity and increases response latency under traffic spikes.

**How to fix**

Cache the composed response with clear invalidation when movies, showtimes, theaters, or branches change.

```php
$data = Cache::remember('home:data:v1', now()->addMinutes(5), function () {
    return $this->homePageService->getHomePageData();
});
```

---

### Issue #3

**Severity:** Medium  
**Category:** Performance / Database Correctness  
**Location:** app/Http/Controllers/HomeController.php:59-67

**Problem**

The controller loads all active theaters and sorts them in memory.

```php
$cinemaOptions = Theater::query()
    ->active()
    ->with('branch:id,name')
    ->get(['id', 'name', 'branch_id'])
    ->sortBy(function($theater) {
        return ($theater->branch?->name ?? '') . '_' . $theater->name;
    })
```

**Why this matters**

In-memory sorting does not scale as theater count grows. It also prevents the database from using indexes for ordering. Public endpoints should avoid loading more rows than needed without pagination or ordering constraints.

**How to fix**

Sort in SQL using a join or denormalized branch/city field.

```php
$cinemaOptions = Theater::query()
    ->select('theaters.id', 'theaters.name', 'theaters.branch_id')
    ->join('branches', 'branches.id', '=', 'theaters.branch_id')
    ->where('theaters.is_active', true)
    ->orderBy('branches.name')
    ->orderBy('theaters.name')
    ->get();
```

---

### Issue #4

**Severity:** Medium  
**Category:** Performance / Query Efficiency  
**Location:** app/Http/Controllers/HomeController.php:24-57

**Problem**

Movie queries eager load categories but do not restrict selected movie columns.

```php
$nowShowingMovies = Movie::query()
    ->with('categories:id,name')
    ->nowShowing()
    ->latest('release_date')
    ->limit(8)
    ->get();
```

**Why this matters**

The endpoint only needs a subset of movie fields, but the queries hydrate full `Movie` models. If movies contain large columns or metadata fields, the endpoint wastes memory and bandwidth between MySQL and PHP.

**How to fix**

Select only required columns.

```php
Movie::query()
    ->select([
        'id',
        'title',
        'slug',
        'description',
        'poster_url',
        'backdrops',
        'trailer_url',
        'age_rating',
        'duration',
        'release_date',
    ])
    ->with('categories:id,name')
```

---

### Issue #5

**Severity:** High  
**Category:** Security / XSS Risk / Data Exposure  
**Location:** app/Http/Controllers/HomeController.php:120-138

**Problem**

The controller returns raw rich text / user-displayable fields directly from the database.

```php
return [
    'title' => $movie->title,
    'description' => $movie->description,
    'poster_url' => $movie->poster_url,
    'backdrop_url' => !empty($backdrops) ? $backdrops[0] : $movie->poster_url,
    'backdrops' => $backdrops,
    'trailer_url' => $movie->trailer_url,
];
```

**Why this matters**

If any movie fields are admin-editable, compromised admin accounts or unsafe CMS input can inject HTML/JavaScript into clients if the frontend renders these fields unsafely. API responses should define whether fields are plain text, sanitized HTML, or trusted URLs.

**How to fix**

Validate and sanitize these fields at write time and enforce URL allowlists for media fields. Prefer API resources that explicitly document safe output.

```php
'description' => strip_tags((string) $movie->description),
```

Only strip in the response layer if the business rule is plain-text descriptions. Otherwise sanitize HTML at ingestion using a strict allowlist.

---

### Issue #6

**Severity:** Medium  
**Category:** API Consistency / Laravel Best Practice  
**Location:** app/Http/Controllers/HomeController.php:107-139

**Problem**

Movie serialization is implemented as a private controller method instead of an API Resource.

```php
private function transformMovie(?Movie $movie): ?array
{
    // ...
}
```

**Why this matters**

Manual transformations inside controllers are easy to duplicate and drift from other endpoints. Laravel API Resources provide a consistent, testable serialization layer and support conditional relationships.

**How to fix**

Use a resource class.

```php
'featured_movie' => $featuredMovie ? new MovieSummaryResource($featuredMovie) : null,
'now_showing_movies' => MovieSummaryResource::collection($nowShowingMovies),
```

---

### Issue #7

**Severity:** Medium  
**Category:** Correctness / JSON Handling  
**Location:** app/Http/Controllers/HomeController.php:113-118

**Problem**

Invalid JSON in `backdrops` is silently converted to an empty array.

```php
$decoded = is_string($movie->backdrops) ? json_decode($movie->backdrops, true) : $movie->backdrops;
$backdrops = is_array($decoded) ? $decoded : [];
```

**Why this matters**

Silently hiding malformed persisted data makes data quality problems hard to detect. If `backdrops` is invalid JSON, production should surface this through validation at write time or monitoring, not quietly degrade.

**How to fix**

Cast `backdrops` on the model and validate it on writes. If decoding fails here, log a warning with the movie ID.

```php
if (is_string($movie->backdrops)) {
    $decoded = json_decode($movie->backdrops, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        Log::warning('Invalid movie backdrops JSON', ['movie_id' => $movie->id]);
        $decoded = [];
    }
}
```

---

### Issue #8

**Severity:** Medium  
**Category:** Data Correctness / URL Validation  
**Location:** app/Http/Controllers/HomeController.php:125-128

**Problem**

Media URLs are returned without validation or normalization.

```php
'poster_url' => $movie->poster_url,
'backdrop_url' => !empty($backdrops) ? $backdrops[0] : $movie->poster_url,
'backdrops' => $backdrops,
'trailer_url' => $movie->trailer_url,
```

**Why this matters**

If these values can be admin-entered, invalid URLs, `javascript:` URLs, mixed-content HTTP URLs, or external tracking URLs may be sent to clients. This creates security, privacy, and frontend reliability issues.

**How to fix**

Validate media URLs at write time and optionally normalize them through a media storage service. Enforce scheme and domain allowlists.

---

### Issue #9

**Severity:** Medium  
**Category:** API Consistency / Type Stability  
**Location:** app/Http/Controllers/HomeController.php:101

**Problem**

`movie_options` is returned as an Eloquent collection of models instead of an explicit array/resource structure.

```php
'movie_options' => $movieOptions,
```

**Why this matters**

Returning raw Eloquent models relies on model serialization rules and hidden/visible attributes. Even though the query selected only `id` and `title`, API contracts should be explicit and stable.

**How to fix**

Map to explicit option DTO arrays.

```php
'movie_options' => $movieOptions->map(fn (Movie $movie) => [
    'id' => $movie->id,
    'title' => $movie->title,
])->values(),
```

---

### Issue #10

**Severity:** Medium  
**Category:** Performance / Query Design  
**Location:** app/Http/Controllers/HomeController.php:51-57

**Problem**

`movieOptions` returns every active movie that has upcoming available showtimes with no limit.

```php
$movieOptions = Movie::query()
    ->active()
    ->whereHas('showtimes', function ($query) {
        $query->available()->upcoming();
    })
    ->orderBy('title')
    ->get(['id', 'title']);
```

**Why this matters**

This endpoint is public and may grow unbounded as the catalog grows. Even if only active movies are returned, this should be bounded or cached.

**How to fix**

Cache the selector list or expose a dedicated searchable/paginated endpoint.

---

### Issue #11

**Severity:** Low  
**Category:** Readability / Coding Standard  
**Location:** app/Http/Controllers/HomeController.php:63 and 67

**Problem**

Closure formatting does not match common Laravel/PHP style spacing.

```php
->sortBy(function($theater) {
```

```php
->map(function($theater) {
```

**Why this matters**

Style inconsistencies reduce readability and make diffs noisy.

**How to fix**

Use standard spacing.

```php
->sortBy(function ($theater) {
```

```php
->map(function ($theater) {
```

---

### Issue #12

**Severity:** Low  
**Category:** Maintainability / Magic Values  
**Location:** app/Http/Controllers/HomeController.php:41, 48, 81

**Problem**

The method hardcodes homepage limits directly in the controller.

```php
->limit(8)
```

```php
->limit(7)
```

**Why this matters**

Magic numbers make business presentation rules harder to change and test.

**How to fix**

Move limits to named constants or config.

```php
private const HOME_MOVIE_LIMIT = 8;
private const AVAILABLE_DATE_LIMIT = 7;
```

---

### Issue #13

**Severity:** Low  
**Category:** Localization / API Consistency  
**Location:** app/Http/Controllers/HomeController.php:84-88

**Problem**

The endpoint hardcodes Vietnamese locale formatting.

```php
$parsedDate = Carbon::parse($date)->locale('vi');

return [
    'value' => $parsedDate->format('Y-m-d'),
    'label' => $parsedDate->isoFormat('dddd, DD/MM'),
];
```

**Why this matters**

A REST API should usually return locale-neutral data and let the client localize labels. Hardcoded locale makes reuse harder for multilingual clients.

**How to fix**

Return only ISO dates or accept a validated locale parameter if localized labels are required.

```php
return [
    'value' => $parsedDate->toDateString(),
];
```

---

### Issue #14

**Severity:** Low  
**Category:** Exception Handling / Observability  
**Location:** app/Http/Controllers/HomeController.php:22-105

**Problem**

The method has no explicit error boundary or contextual logging for homepage composition failures.

**Why this matters**

If one malformed movie record or bad date breaks the endpoint, operators get limited domain context unless global exception handling logs enough detail.

**How to fix**

Keep exception handling centralized, but add domain-level logging around known data-quality risks such as invalid JSON backdrops or invalid media fields.

---

## Security Review

No SQL injection was found because the controller does not use user-provided input and query builder clauses are parameterized.

Primary security concerns are output safety and public data exposure:

- Raw movie descriptions and media URLs are returned directly.
- `backdrops` can contain arbitrary values if not validated at write time.
- The response exposes home/catalog data publicly, which is expected, but fields should be deliberately curated.

---

## Performance Review

Performance risks:

- Multiple queries on every public request.
- No caching for homepage data.
- All active theaters loaded and sorted in PHP.
- Movie option list is unbounded.
- Movie queries hydrate full models instead of selecting required columns.

---

## Database Review

No write or transaction logic exists in this controller.

Database concerns are read-side only:

- Requires proper indexes on `movies.is_hot`, `movies.release_date`, movie status fields, `showtimes.scheduled_at`, showtime status fields, `theaters.branch_id`, and active/status columns.
- `DATE(scheduled_at)` in `selectRaw` can limit index usage depending on MySQL execution plan.

---

## Concurrency Review

No direct concurrency issue exists because the controller performs read-only operations.

However, stale homepage data should be expected if caching is introduced. Cache invalidation must be tied to movie/showtime/theater changes.

---

## Laravel Best Practice Review

The controller should use:

- A service/read model for homepage data composition.
- API Resources for movie and option serialization.
- Model casts for `backdrops`.
- Cache for public homepage data.
- Explicit DTO/array mapping instead of returning Eloquent models directly.

---

## Testing Review

Recommended tests:

1. Home data endpoint returns stable schema when no movies exist.
2. Featured movie fallback works when no `is_hot` movie exists.
3. Movie options only include active movies with available upcoming showtimes.
4. Cinema options include branch/city and are ordered correctly.
5. Invalid `backdrops` data does not break the endpoint and is logged.
6. Response does not expose unintended movie model attributes.
7. Locale/date labels are deterministic or removed from API response.

---

## Final Decision

🚫 **REQUEST CHANGES**

The endpoint is functional but not production-ready as currently structured. The main blockers are controller-level business composition, no caching for a public high-traffic endpoint, raw manual serialization, unbounded selector queries, and unsafe output assumptions around descriptions/media URLs.

---

_Review completed: 2026-07-14 02:50 PM_  
_File #50/137 - Phase 4: Controllers (2/34 complete)_
