# File Review: MovieController.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Controllers/MovieController.php  
**Lines:** 206  
**Type:** Movie API Controller

---

## File Summary

`MovieController` exposes listing, filtering, search, create, update, show, delete, and status-toggle operations for movies. It uses `MovieService` for some operations, but also performs direct model access, file storage/deletion, request mutation, validation, exception handling, and response shaping inside the controller.

---

## Overall Score

**Overall Score:** 4.8/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

- Uses dependency injection for `MovieService`.
- Public listing filters are validated in `index()`.
- Pagination limit is bounded to `max:200`.
- Uses `ApiResponse` for response shape.
- Uses Laravel `Storage` facade instead of raw filesystem paths.
- Uses `Rule::in()` for supported list filters.

---

## Issues

### Issue #1

**Severity:** Critical  
**Category:** Security / Validation / Mass Assignment  
**Location:** app/Http/Controllers/MovieController.php:86-102 and 131-154

**Problem**

`store()` and `update()` accept raw request data without validation and pass it directly into the service.

```php
public function store(Request $request)
{
    try {
        $data = $request->except(['poster_file', 'banner_file']);
        // ...
        $movie = $this->movieService->createMovie($data);
```

```php
public function update(Request $request, $id)
{
    try {
        $movie = \App\Models\Movie::findOrFail($id);
        $data = $request->except(['poster_file', 'banner_file']);
        // ...
        $movie = $this->movieService->updateMovie($id, $data);
```

The file imports dedicated request classes but does not use them.

```php
use App\Http\Requests\StoreMovieRequest;
use App\Http\Requests\UpdateMovieRequest;
```

**Why this matters**

This allows unvalidated and potentially unauthorized fields to reach the service/model layer. If the model or service permits mass assignment of sensitive fields, clients can modify fields such as status, hot flag, slug, pricing-related metadata, visibility, or other administrative attributes without the intended validation rules.

**How to fix**

Use the existing FormRequest classes and only pass validated data.

```php
public function store(StoreMovieRequest $request)
{
    $data = $request->validated();

    $movie = $this->movieService->createMovie($data);

    return $this->successResponse($movie, 'Movie created successfully', 201);
}

public function update(UpdateMovieRequest $request, int $id)
{
    $data = $request->validated();

    $movie = $this->movieService->updateMovie($id, $data);

    return $this->successResponse($movie, 'Movie updated successfully');
}
```

---

### Issue #2

**Severity:** High  
**Category:** Security / File Upload Validation  
**Location:** app/Http/Controllers/MovieController.php:91-100 and 137-152

**Problem**

Uploaded files are stored without controller-level validation of MIME type, extension, size, image dimensions, or content.

```php
if ($request->hasFile('poster_file') && $request->file('poster_file')->isValid()) {
    $data['poster_path'] = $request->file('poster_file')->store('movies/posters', 'public');
}
```

```php
if ($request->hasFile('banner_file') && $request->file('banner_file')->isValid()) {
    $data['banner_path'] = $request->file('banner_file')->store('movies/banners', 'public');
}
```

**Why this matters**

`isValid()` only confirms upload success. It does not guarantee that the file is an image, safe, within size limits, or acceptable for public storage. Storing unvalidated files on a public disk can lead to malware hosting, storage abuse, content-type confusion, and frontend breakage.

**How to fix**

Validate uploads through `StoreMovieRequest` / `UpdateMovieRequest`.

```php
'poster_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
'banner_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
```

---

### Issue #3

**Severity:** High  
**Category:** Data Loss / File Consistency  
**Location:** app/Http/Controllers/MovieController.php:137-154

**Problem**

`update()` deletes the old poster/banner before the database update succeeds.

```php
if ($movie->poster_path) {
    Storage::disk('public')->delete($movie->poster_path);
}
$data['poster_path'] = $request->file('poster_file')->store('movies/posters', 'public');
```

```php
$movie = $this->movieService->updateMovie($id, $data);
```

**Why this matters**

If the database update fails after the old file is deleted, the existing movie record may still reference a now-deleted file or lose its media unexpectedly. This is a production data consistency issue.

**How to fix**

Store the new file first, update the database in a transaction, and delete the old file only after successful commit.

```php
$newPosterPath = $request->file('poster_file')->store('movies/posters', 'public');
$oldPosterPath = $movie->poster_path;

DB::transaction(function () use ($id, $data, $newPosterPath) {
    $data['poster_path'] = $newPosterPath;
    $this->movieService->updateMovie($id, $data);
});

Storage::disk('public')->delete($oldPosterPath);
```

Prefer `DB::afterCommit()` for cleanup.

---

### Issue #4

**Severity:** High  
**Category:** Security / Information Disclosure  
**Location:** app/Http/Controllers/MovieController.php:46-48, 109-111, 160-162, 174-176, 188-190, 202-204

**Problem**

The controller exposes raw exception messages in API responses.

```php
return $this->errorResponse('Failed to retrieve movies: ' . $e->getMessage(), 500);
```

```php
return $this->errorResponse('Failed to create movie: ' . $e->getMessage(), 500);
```

```php
return $this->errorResponse('Failed to update movie: ' . $e->getMessage(), 500);
```

**Why this matters**

Exception messages can reveal table names, SQL errors, filesystem paths, validation internals, configuration, or implementation details. Public APIs should not return raw exception messages.

**How to fix**

Log the exception server-side and return a generic message.

```php
Log::error('Failed to create movie', ['exception' => $e]);

return $this->errorResponse('Failed to create movie', 500);
```

---

### Issue #5

**Severity:** High  
**Category:** Authorization / Access Control  
**Location:** app/Http/Controllers/MovieController.php:86-205

**Problem**

The controller contains administrative write operations but performs no visible authorization checks.

```php
public function store(Request $request)
```

```php
public function update(Request $request, $id)
```

```php
public function destroy($id)
```

```php
public function toggleActive($id)
```

```php
public function toggleHot($id)
```

**Why this matters**

If route middleware is missing or misconfigured, any authenticated or public client could create, update, delete, or feature movies. Controllers should enforce policies or middleware explicitly for high-impact operations.

**How to fix**

Use policies or middleware.

```php
public function store(StoreMovieRequest $request)
{
    $this->authorize('create', Movie::class);
}
```

```php
public function update(UpdateMovieRequest $request, Movie $movie)
{
    $this->authorize('update', $movie);
}
```

---

### Issue #6

**Severity:** High  
**Category:** Business Logic / Correctness  
**Location:** app/Http/Controllers/MovieController.php:182-190

**Problem**

`toggleActive()` toggles the `status` column as if it were boolean.

```php
$movie = \App\Models\Movie::findOrFail($id);
$movie->update(['status' => !$movie->status]);
```

**Why this matters**

Elsewhere in this controller, `status` is treated as a domain enum/string with values such as `active`, `now_showing`, `upcoming`, `hidden`, and `all`.

```php
'status' => ['nullable', Rule::in(['active', 'now_showing', 'upcoming', 'hidden', 'all'])],
```

Applying boolean negation to a string status is logically incorrect. In PHP, non-empty strings are truthy, so `!$movie->status` becomes `false`. This can persist an invalid status value and break movie filtering/business rules.

**How to fix**

Use explicit status transitions.

```php
$movie->update([
    'status' => $movie->status === 'hidden' ? 'active' : 'hidden',
]);
```

Better: move status transitions into the service/domain layer with validation.

---

### Issue #7

**Severity:** Medium  
**Category:** Architecture / Service Layer Bypass  
**Location:** app/Http/Controllers/MovieController.php:134, 185, 199

**Problem**

The controller bypasses `MovieService` and directly queries the `Movie` model.

```php
$movie = \App\Models\Movie::findOrFail($id);
```

This occurs in `update()`, `toggleActive()`, and `toggleHot()`.

**Why this matters**

The controller now owns part of the movie domain workflow and bypasses service-level rules, logging, authorization hooks, cache invalidation, and invariants. It also creates hidden coupling to the model.

**How to fix**

Inject/import `Movie` only for route model binding and keep business transitions in `MovieService`.

```php
$movie = $this->movieService->toggleHot($id);
```

---

### Issue #8

**Severity:** Medium  
**Category:** API Correctness / Validation Flow  
**Location:** app/Http/Controllers/MovieController.php:74-80

**Problem**

`search()` validates that `q` exists, then delegates to `index()` which validates the full request again.

```php
$request->validate([
    'q' => ['required', 'string', 'max:255'],
]);

return $this->index($request);
```

**Why this matters**

Double validation is unnecessary and makes control flow harder to reason about. It also couples `search()` behavior tightly to `index()` implementation details.

**How to fix**

Create a dedicated search method in the service or use one validated request path.

```php
$filters = $request->validate([
    'q' => ['required', 'string', 'max:255'],
    'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
]);

$movies = $this->movieService->searchMovies($filters);
```

---

### Issue #9

**Severity:** Medium  
**Category:** Maintainability / Request Mutation  
**Location:** app/Http/Controllers/MovieController.php:54-68

**Problem**

`nowShowing()` and `comingSoon()` mutate the incoming request before delegating to `index()`.

```php
$request->merge(['status' => 'now_showing']);

return $this->index($request);
```

```php
$request->merge(['status' => 'upcoming']);

return $this->index($request);
```

**Why this matters**

Mutating request input hides behavior and can overwrite client-provided fields. It also makes tests and logs less clear because the request no longer reflects the original client input.

**How to fix**

Build filter arrays explicitly.

```php
$filters = array_merge($request->validate([...]), ['status' => 'now_showing']);
$movies = $this->movieService->getMovies($filters);
```

---

### Issue #10

**Severity:** Medium  
**Category:** API Consistency / HTTP Status Codes  
**Location:** app/Http/Controllers/MovieController.php:117-125

**Problem**

`show()` catches all exceptions and converts them to `404`.

```php
try {
    $movie = $this->movieService->getMovie($idOrSlug);

    return $this->successResponse($movie, 'Movie retrieved successfully');
} catch (\Throwable $e) {
    return $this->errorResponse('Movie not found', 404);
}
```

**Why this matters**

Real server errors, database failures, serialization bugs, or service exceptions will be incorrectly reported as "not found." This hides production incidents and makes monitoring inaccurate.

**How to fix**

Only convert not-found exceptions to 404. Let unexpected exceptions bubble to centralized exception handling or log them and return 500.

```php
catch (ModelNotFoundException $e) {
    return $this->errorResponse('Movie not found', 404);
}
```

---

### Issue #11

**Severity:** Medium  
**Category:** Resource Leakage / Orphan Files  
**Location:** app/Http/Controllers/MovieController.php:91-102 and 137-154

**Problem**

If file storage succeeds but `createMovie()` or `updateMovie()` fails, newly uploaded files are not deleted.

```php
$data['poster_path'] = $request->file('poster_file')->store('movies/posters', 'public');
// ...
$movie = $this->movieService->createMovie($data);
```

**Why this matters**

Failed writes can leave orphan files in public storage. Over time this wastes storage and may expose files that are not referenced by any movie record.

**How to fix**

Track uploaded paths and delete them in the failure path, or move media handling into a transactional service with cleanup.

```php
$uploadedPaths = [];

try {
    $path = $request->file('poster_file')->store('movies/posters', 'public');
    $uploadedPaths[] = $path;
    // create movie
} catch (\Throwable $e) {
    foreach ($uploadedPaths as $path) {
        Storage::disk('public')->delete($path);
    }

    throw $e;
}
```

---

### Issue #12

**Severity:** Medium  
**Category:** Laravel Best Practice / Route Model Binding  
**Location:** app/Http/Controllers/MovieController.php:117, 131, 168, 182, 196

**Problem**

The controller uses raw IDs and manual lookups instead of route model binding.

```php
public function update(Request $request, $id)
```

```php
public function destroy($id)
```

```php
public function toggleHot($id)
```

**Why this matters**

Route model binding reduces repeated lookup code, standardizes 404 behavior, and works cleanly with policies.

**How to fix**

Use route model binding where possible.

```php
public function update(UpdateMovieRequest $request, Movie $movie)
{
    $this->authorize('update', $movie);
}
```

For slug-or-ID lookup, define explicit route binding logic rather than accepting an untyped `$idOrSlug`.

---

### Issue #13

**Severity:** Medium  
**Category:** API Consistency / Response Serialization  
**Location:** app/Http/Controllers/MovieController.php:104-108, 120-122, 156-159, 187, 201

**Problem**

The controller returns raw Eloquent models and appended attributes directly.

```php
return $this->successResponse(
    $movie->append(['poster_display_url', 'banner_display_url']),
    'Movie created successfully',
    201
);
```

```php
return $this->successResponse($movie, 'Movie retrieved successfully');
```

**Why this matters**

Raw model serialization can expose unintended fields depending on `$hidden`, `$visible`, casts, relations, and appended attributes. API contracts should be explicit.

**How to fix**

Use API Resources.

```php
return $this->successResponse(
    new MovieResource($movie),
    'Movie created successfully',
    201
);
```

---

### Issue #14

**Severity:** Medium  
**Category:** Maintainability / Unused Imports  
**Location:** app/Http/Controllers/MovieController.php:5-6

**Problem**

`StoreMovieRequest` and `UpdateMovieRequest` are imported but not used.

```php
use App\Http\Requests\StoreMovieRequest;
use App\Http\Requests\UpdateMovieRequest;
```

**Why this matters**

Unused imports indicate incomplete refactoring and directly correlate with the missing validation issue in this file.

**How to fix**

Use the FormRequests or remove the imports. In this case, use them.

---

### Issue #15

**Severity:** Low  
**Category:** Clean Code / Naming  
**Location:** app/Http/Controllers/MovieController.php:64

**Problem**

The method name `comingSoon()` maps to the status value `upcoming`.

```php
public function comingSoon(Request $request)
{
    $request->merge(['status' => 'upcoming']);
```

**Why this matters**

Using multiple terms for the same domain state increases cognitive load and can cause API confusion.

**How to fix**

Standardize on one public/domain term, or document alias behavior explicitly.

---

### Issue #16

**Severity:** Low  
**Category:** Readability / Localization  
**Location:** app/Http/Controllers/MovieController.php:91 and 97

**Problem**

Inline comments use Vietnamese in an otherwise English codebase.

```php
// Xử lý upload poster
```

```php
// Xử lý upload banner
```

**Why this matters**

Mixed-language comments reduce maintainability for international teams. Comments are also unnecessary because the code is self-explanatory.

**How to fix**

Remove the comments or translate them to English only when they add context.

---

## Security Review

Major security concerns exist:

- Create/update accept raw unvalidated request data.
- Public-disk uploads are not validated for file type, size, or content.
- Raw exception messages are returned to clients.
- Administrative methods do not enforce visible authorization.
- Raw model responses may expose unintended attributes.

No direct SQL injection was found in this file because query construction is delegated to Eloquent/service code and filter values are validated in `index()`.

---

## Performance Review

Performance concerns:

- `per_page` allows up to 200, which may be acceptable for admin but high for public endpoints.
- File upload operations are performed inside the controller and not queued/processed.
- No cache invalidation is visible after create/update/delete/toggle operations.
- Returning full Eloquent models can over-serialize data.

---

## Database / Transaction Review

Transactional concerns are significant:

- File deletion and database update are not atomic.
- New files can become orphaned when database writes fail.
- Old files can be deleted before a failed update.
- Status toggles are direct writes with no domain validation.

---

## Concurrency Review

Concurrency concerns:

- `toggleHot()` and `toggleActive()` perform read-modify-write operations without locking.
- Concurrent toggles can produce lost updates.
- Status transitions are not centralized and not guarded by a state machine.

For boolean toggles, prefer atomic SQL updates or explicit state-setting endpoints. For domain status, do not use generic toggles.

---

## Laravel Best Practice Review

The controller violates several Laravel best practices:

- Imports FormRequests but uses raw `Request`.
- Does not use policies or explicit authorization.
- Does not use API Resources.
- Does not use route model binding.
- Catches `Throwable` broadly and returns raw exception messages.
- Bypasses the service layer with direct model lookups and updates.
- Handles file lifecycle in the controller instead of a dedicated service.

---

## Testing Review

Recommended tests:

1. Store rejects invalid file type, oversized files, and non-image uploads.
2. Store rejects unauthorized users.
3. Store only persists validated fields.
4. Update does not delete old media if database update fails.
5. Failed create/update cleans up newly uploaded files.
6. `toggleActive()` never writes boolean values into string status fields.
7. Unexpected service exception in `show()` returns 500, not 404.
8. Response does not expose unintended model attributes.
9. Concurrent toggle requests do not produce inconsistent state.

---

## Final Decision

🚫 **REQUEST CHANGES**

This controller is not production-ready. The most serious issues are unvalidated create/update payloads, unsafe public file uploads, raw exception disclosure, missing visible authorization, and a broken `toggleActive()` implementation that can corrupt the movie status field.

---

_Review completed: 2026-07-14 02:55 PM_  
_File #51/137 - Phase 4: Controllers (3/34 complete)_
