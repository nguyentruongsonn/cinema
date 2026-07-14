# File Review: ShowtimeController.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Controllers/ShowtimeController.php  
**Lines:** 230  
**Type:** Showtime Management API Controller

---

## File Summary

`ShowtimeController` exposes CRUD and bulk creation endpoints for showtimes. It delegates some operations to `ShowtimeService`, but bulk creation directly queries and writes `Showtime` models inside the controller. The file mutates incoming request data, validates create/update payloads inline, returns raw service/model responses, and exposes raw exception details in multiple API errors.

Showtime scheduling is a critical domain area for a cinema booking system. Incorrect showtime creation can create overlapping schedules, duplicate screenings, impossible seat availability, invalid ticket sales, duplicate bookings, payment reconciliation problems, and customer-facing operational failures. This controller is not production-ready due to race-prone duplicate checks, missing transactions, weak schedule conflict validation, raw exception disclosure, fat-controller business logic, missing visible authorization, inconsistent API behavior, and direct model writes bypassing the service layer.

---

## Overall Score

**Overall Score:** 4.7/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

- Uses constructor dependency injection for `ShowtimeService`.
- Uses the shared `ApiResponse` trait.
- Uses `exists` validation for foreign keys in create/update/bulk endpoints.
- Uses basic date/time validation for scheduled times.
- Provides bulk scheduling capabilities needed by cinema operations.
- Handles `ModelNotFoundException` explicitly in `getMovieShowtimes()`.

---

## Issues

### Issue #1

**Severity:** Critical  
**Category:** Concurrency / Duplicate Showtime / Business Logic  
**Location:** app/Http/Controllers/ShowtimeController.php:130-138 and 176-189

**Problem**

Bulk creation checks for duplicates with `exists()` and then inserts with `create()` outside a transaction and without a database lock.

```php
$exists = \App\Models\Showtime::where('screen_id', $base['screen_id'])
    ->where('scheduled_at', $scheduledAt)
    ->exists();

if ($exists) { $skipped++; continue; }

\App\Models\Showtime::create(array_merge($base, ['scheduled_at' => $scheduledAt]));
```

```php
$exists = \App\Models\Showtime::where('screen_id', $slot['screen_id'])
    ->where('scheduled_at', $scheduledAt)
    ->exists();

if ($exists) { $skipped++; continue; }

\App\Models\Showtime::create([
```

**Why this matters**

This is a classic check-then-insert race condition. Two concurrent admin requests can both see no existing record and both insert the same showtime. Duplicate showtimes for the same screen/time can lead to duplicate seat maps, double booking, incorrect revenue allocation, and operational confusion.

**How to fix**

Enforce a database unique constraint and use transaction-safe creation.

```php
Schema::table('showtimes', function (Blueprint $table) {
    $table->unique(['screen_id', 'scheduled_at']);
});
```

Then use `firstOrCreate()`/`upsert()` with exception handling, or perform the operation in a transaction and rely on the unique index.

```php
try {
    Showtime::create($payload);
    $created++;
} catch (QueryException $e) {
    if ($this->isDuplicateKey($e)) {
        $skipped++;
        continue;
    }

    throw $e;
}
```

---

### Issue #2

**Severity:** Critical  
**Category:** Business Logic / Scheduling Correctness  
**Location:** app/Http/Controllers/ShowtimeController.php:42-49, 77-84, 100-109, 158-167

**Problem**

Showtime validation only checks exact duplicate screen/time in bulk paths and does not validate movie duration, cleaning buffer, end time, or overlapping showtimes.

```php
'scheduled_at' => 'required|date_format:Y-m-d H:i:s|after:now',
```

```php
'scheduled_at' => 'sometimes|date_format:Y-m-d H:i:s',
```

```php
'times.*'     => 'required|date_format:H:i',
```

```php
'slots.*.time'   => 'required|date_format:H:i',
```

**Why this matters**

Cinema scheduling requires overlap prevention, not only identical start-time prevention. A screen cannot run two movies at overlapping times. Without validating movie duration and cleanup buffer, the system can schedule a 120-minute movie at 10:00 and another at 10:30 in the same screen. This can break seat availability and ticket sales.

**How to fix**

Move scheduling validation into a domain service that calculates end time and checks overlaps.

```php
$start = Carbon::parse($scheduledAt);
$end = $start->copy()->addMinutes($movie->duration + config('cinema.cleanup_buffer_minutes'));

$conflict = Showtime::where('screen_id', $screenId)
    ->where(function ($query) use ($start, $end) {
        $query->whereBetween('scheduled_at', [$start, $end])
            ->orWhere(function ($query) use ($start, $end) {
                $query->where('scheduled_at', '<=', $start)
                    ->where('ends_at', '>', $start);
            });
    })
    ->exists();

if ($conflict) {
    throw new ShowtimeConflictException();
}
```

Persist `ends_at` or derive it consistently from movie duration.

---

### Issue #3

**Severity:** High  
**Category:** Security / Authorization  
**Location:** app/Http/Controllers/ShowtimeController.php:22-230

**Problem**

The controller exposes showtime creation, update, bulk creation, and deletion without any visible authorization or admin protection.

```php
public function store(Request $request)
```

```php
public function update(Request $request, $id)
```

```php
public function bulkCreate(Request $request)
```

```php
public function bulkSingleDay(Request $request)
```

```php
public function destroy($id)
```

**Why this matters**

Showtime management is an administrative capability. If route middleware is missing or misconfigured, unauthorized users could create fake showtimes, cancel active showtimes, alter ticket inventory, or delete screenings with paid orders.

**How to fix**

Enforce authorization at the controller, route, and policy layers.

```php
public function __construct(
    private readonly ShowtimeService $showtimeService
) {
    $this->middleware(['auth:api', 'permission:showtimes.manage'])
        ->except(['index', 'show', 'getMovieShowtimes']);
}
```

Use policies for mutation actions:

```php
$this->authorize('create', Showtime::class);
$this->authorize('update', $showtime);
$this->authorize('delete', $showtime);
```

---

### Issue #4

**Severity:** High  
**Category:** Security / Information Disclosure  
**Location:** app/Http/Controllers/ShowtimeController.php:32-34, 54-56, 89-91, 147-149, 198-200, 211-213, 226-228

**Problem**

The controller returns raw exception messages to API clients. `getMovieShowtimes()` is especially severe because it exposes file path and line number.

```php
return $this->errorResponse('Failed to retrieve showtimes: ' . $e->getMessage(), 500);
```

```php
return $this->errorResponse('Bulk create failed: ' . $e->getMessage(), 500);
```

```php
return $this->errorResponse('Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 500);
```

**Why this matters**

This can expose SQL errors, table names, filesystem paths, line numbers, framework internals, and production infrastructure details. The file/line disclosure at line 227 is a direct production information leak.

**How to fix**

Log full exceptions server-side and return generic errors.

```php
catch (\Throwable $e) {
    Log::error('Failed to retrieve movie showtimes', [
        'exception' => $e,
        'slug_or_id' => $slugOrId,
    ]);

    return $this->errorResponse('Failed to retrieve showtimes', 500);
}
```

Prefer centralized exception handling.

---

### Issue #5

**Severity:** High  
**Category:** Architecture / Fat Controller / Service Bypass  
**Location:** app/Http/Controllers/ShowtimeController.php:111-140 and 169-191

**Problem**

The bulk creation methods implement business logic and persistence directly in the controller using `\App\Models\Showtime::where()` and `\App\Models\Showtime::create()`.

```php
$exists = \App\Models\Showtime::where('screen_id', $base['screen_id'])
```

```php
\App\Models\Showtime::create(array_merge($base, ['scheduled_at' => $scheduledAt]));
```

```php
\App\Models\Showtime::create([
```

**Why this matters**

This bypasses `ShowtimeService::create()`, so any domain rules, transactions, logging, seat generation, event dispatching, or schedule conflict checks inside the service are skipped. Business logic is duplicated across controller and service layers and becomes harder to test.

**How to fix**

Move bulk scheduling into `ShowtimeService`.

```php
$result = $this->showtimeService->bulkCreate($validated);

return $this->successResponse($result, 'Showtimes created successfully', 201);
```

The service should own conflict detection, transaction handling, event dispatching, and audit logging.

---

### Issue #6

**Severity:** High  
**Category:** Database / Transactions / Partial Failure  
**Location:** app/Http/Controllers/ShowtimeController.php:126-140 and 173-191

**Problem**

Bulk create loops insert records one-by-one with no transaction or failure strategy.

```php
for ($d = clone $from; $d < $to; $d->modify('+1 day')) {
    foreach ($validated['times'] as $time) {
        ...
        \App\Models\Showtime::create(array_merge($base, ['scheduled_at' => $scheduledAt]));
        $created++;
    }
}
```

```php
foreach ($validated['slots'] as $slot) {
    ...
    \App\Models\Showtime::create([
```

**Why this matters**

If an exception occurs halfway through a large bulk request, the API returns a 500 while some showtimes were already created. Retrying the request may create more duplicates or inconsistent skipped/created counts. This is not atomic and not retry-safe.

**How to fix**

Wrap bulk operations in a transaction and define expected behavior: all-or-nothing, or explicitly partial success with idempotency.

```php
$result = DB::transaction(function () use ($payloads) {
    return $this->showtimeRepository->bulkCreateSafely($payloads);
});
```

If partial success is desired, return `207 Multi-Status`-style details and make the endpoint idempotent.

---

### Issue #7

**Severity:** High  
**Category:** Business Logic / Data Integrity  
**Location:** app/Http/Controllers/ShowtimeController.php:75-91 and 206-213

**Problem**

The controller allows updating and deleting showtimes without visible checks for existing bookings, paid orders, seat holds, tickets, or showtime start time.

```php
$showtime = $this->showtimeService->update((int) $id, $validated);
```

```php
$this->showtimeService->delete((int) $id);
```

**Why this matters**

Changing or deleting a showtime after tickets have been sold can invalidate orders, payments, seats, QR tickets, and customer notifications. This can cause direct revenue loss and customer support incidents.

**How to fix**

Enforce state-transition rules in the service/policy layer.

```php
if ($showtime->orders()->whereIn('status', ['paid', 'confirmed'])->exists()) {
    throw new DomainException('Cannot modify a showtime with paid bookings.');
}
```

Return `409 Conflict` for domain conflicts.

---

### Issue #8

**Severity:** Medium  
**Category:** Validation / Date Correctness  
**Location:** app/Http/Controllers/ShowtimeController.php:82

**Problem**

`update()` allows `scheduled_at` to be changed to a past datetime.

```php
'scheduled_at' => 'sometimes|date_format:Y-m-d H:i:s',
```

**Why this matters**

A showtime can be moved into the past, which can break ticket availability, booking windows, analytics, and status transitions.

**How to fix**

Require future dates for schedule updates unless the business explicitly allows historical correction.

```php
'scheduled_at' => ['sometimes', 'date_format:Y-m-d H:i:s', 'after:now'],
```

Also block updates for showtimes with bookings or elapsed start time.

---

### Issue #9

**Severity:** Medium  
**Category:** Validation / Abuse Resistance  
**Location:** app/Http/Controllers/ShowtimeController.php:103-106 and 164-166

**Problem**

Bulk creation input is unbounded. There is no maximum date range, maximum number of times, or maximum number of slots.

```php
'date_from'   => 'required|date|before_or_equal:date_to',
'date_to'     => 'required|date',
'times'       => 'required|array|min:1',
```

```php
'slots'          => 'required|array|min:1',
```

**Why this matters**

A user can submit a huge date range and many times/slots, causing thousands of insert attempts in a single request. This can exhaust CPU, database connections, memory, and lock resources.

**How to fix**

Add strict limits.

```php
'date_from' => ['required', 'date', 'before_or_equal:date_to', 'after_or_equal:today'],
'date_to' => ['required', 'date', 'after_or_equal:date_from', 'before_or_equal:' . now()->addMonths(3)->toDateString()],
'times' => ['required', 'array', 'min:1', 'max:10'],
'slots' => ['required', 'array', 'min:1', 'max:100'],
```

Also validate generated showtime count before inserting.

---

### Issue #10

**Severity:** Medium  
**Category:** Architecture / Request Mutation  
**Location:** app/Http/Controllers/ShowtimeController.php:25-30

**Problem**

`index()` mutates the incoming `Request` object before passing it to the service.

```php
$request->merge([
    'status' => $request->query('status', 'all'),
    'upcoming' => false,
]);
$showtimes = $this->showtimeService->getAll($request);
```

**Why this matters**

Mutating request input hides behavior, couples controller/service semantics, and can override user-supplied values. It also makes tests harder to reason about because the request no longer represents the original client input.

**How to fix**

Build explicit validated filters.

```php
$filters = $request->validate([
    'status' => ['nullable', Rule::in(['all', '0', '1'])],
    'movie_id' => ['nullable', 'integer', 'exists:movies,id'],
    'screen_id' => ['nullable', 'integer', 'exists:screens,id'],
    'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
]);

$filters['status'] = $filters['status'] ?? 'all';
$filters['upcoming'] = false;

$showtimes = $this->showtimeService->getAll($filters);
```

---

### Issue #11

**Severity:** Medium  
**Category:** Architecture / Service Layer Coupling  
**Location:** app/Http/Controllers/ShowtimeController.php:30

**Problem**

The controller passes the full HTTP `Request` into the service.

```php
$showtimes = $this->showtimeService->getAll($request);
```

**Why this matters**

Services should not depend on HTTP request objects. This weakens testability, encourages unvalidated input usage, and creates hidden coupling between API query parameters and business logic.

**How to fix**

Pass validated arrays or DTOs.

```php
$showtimes = $this->showtimeService->getAll($filters);
```

---

### Issue #12

**Severity:** Medium  
**Category:** API Consistency / Status Modeling  
**Location:** app/Http/Controllers/ShowtimeController.php:48, 83, 123, 163, 188

**Problem**

`status` is modeled as `0`/`1` in API validation and creation.

```php
'status' => 'required|in:0,1',
```

```php
'status'      => 1,
```

```php
'status'         => 'nullable|in:0,1',
```

**Why this matters**

Binary numeric status is too weak for showtimes. Real showtime lifecycle usually includes scheduled, selling, sold_out, cancelled, completed, hidden, and delayed. Numeric API statuses are not self-documenting and invite inconsistent client behavior.

**How to fix**

Use named status enums and a controlled state machine.

```php
'status' => ['required', Rule::in(['scheduled', 'active', 'cancelled'])],
```

Persist as enum/string or map in a FormRequest/resource layer consistently.

---

### Issue #13

**Severity:** Medium  
**Category:** API Contract / Response Serialization  
**Location:** app/Http/Controllers/ShowtimeController.php:52-53, 65-66, 87-88, 222-223

**Problem**

The controller returns raw service data/model data directly.

```php
return $this->successResponse($showtime, 'Showtime created successfully', 201);
```

```php
return $this->successResponse($showtime, 'Showtime retrieved successfully');
```

```php
return $this->successResponse($data, 'Showtimes retrieved successfully');
```

**Why this matters**

Raw Eloquent serialization can expose internal fields and produce inconsistent response shapes depending on loaded relationships. Showtime payloads can include operational schedule data that should be deliberately shaped.

**How to fix**

Use API Resources.

```php
return $this->successResponse(
    new ShowtimeResource($showtime),
    'Showtime created successfully',
    201
);
```

Use `ShowtimeResource::collection()` for lists.

---

### Issue #14

**Severity:** Medium  
**Category:** Validation / Data Quality  
**Location:** app/Http/Controllers/ShowtimeController.php:45-46, 80-81, 107-108, 161-162

**Problem**

The controller validates `format_id` and `version_type_id` independently but does not verify compatibility with the selected movie, screen, or each other.

```php
'format_id' => 'nullable|exists:formats,id',
'version_type_id' => 'nullable|exists:version_types,id',
```

**Why this matters**

A showtime can be created with a format/version unsupported by the movie or screen. This can break pricing, customer display, and auditorium capability assumptions.

**How to fix**

Add domain validation in the service.

```php
if (! $screen->supportsFormat($formatId)) {
    throw new DomainException('Selected screen does not support this format.');
}

if (! $movie->supportsVersionType($versionTypeId)) {
    throw new DomainException('Selected movie does not support this version type.');
}
```

---

### Issue #15

**Severity:** Medium  
**Category:** Clean Code / Maintainability  
**Location:** app/Http/Controllers/ShowtimeController.php:131, 137, 176, 182

**Problem**

The controller uses fully-qualified model calls even though `Showtime` is already imported.

```php
use App\Models\Showtime;
```

But later:

```php
\App\Models\Showtime::where('screen_id', $base['screen_id'])
```

```php
\App\Models\Showtime::create(array_merge($base, ['scheduled_at' => $scheduledAt]));
```

**Why this matters**

This is inconsistent and suggests rushed implementation. It also makes imports misleading and lowers readability.

**How to fix**

Use the imported class, or better, remove direct model usage from the controller entirely and delegate to the service.

```php
Showtime::create($payload);
```

---

### Issue #16

**Severity:** Low  
**Category:** Exception Handling / API Correctness  
**Location:** app/Http/Controllers/ShowtimeController.php:62-69

**Problem**

`show()` catches any exception and returns `404`.

```php
try {
    $showtime = $this->showtimeService->getById((int) $id);
    return $this->successResponse($showtime, 'Showtime retrieved successfully');
} catch (\Exception $e) {
    return $this->errorResponse('Showtime not found', 404);
}
```

**Why this matters**

Database errors, serialization errors, and programming errors will be hidden as not-found responses. This breaks observability and makes production incidents harder to detect.

**How to fix**

Catch only `ModelNotFoundException` for 404 and let unexpected exceptions be handled centrally.

```php
catch (ModelNotFoundException $e) {
    return $this->errorResponse('Showtime not found', 404);
}
```

---

### Issue #17

**Severity:** Low  
**Category:** Laravel Best Practice / FormRequest  
**Location:** app/Http/Controllers/ShowtimeController.php:40-49, 75-84, 98-109, 156-167

**Problem**

All validation is inline inside controller methods.

```php
$validated = $request->validate([
```

**Why this matters**

Showtime validation is complex and includes authorization, schedule conflict detection, date windows, domain compatibility, and bulk limits. Inline arrays are not maintainable at this complexity.

**How to fix**

Use dedicated FormRequests:

```php
public function bulkCreate(BulkCreateShowtimeRequest $request)
{
    $result = $this->showtimeService->bulkCreate($request->validated());
}
```

---

### Issue #18

**Severity:** Low  
**Category:** Type Safety / Laravel Best Practice  
**Location:** app/Http/Controllers/ShowtimeController.php:22, 40, 62, 75, 98, 156, 206, 219

**Problem**

Controller actions do not declare return types and route parameters are untyped.

```php
public function show($id)
```

```php
public function getMovieShowtimes($slugOrId)
```

**Why this matters**

Missing return types and untyped route parameters reduce static analysis and make controller contracts less clear.

**How to fix**

Use `JsonResponse` return types and route model binding where appropriate.

```php
public function show(Showtime $showtime): JsonResponse
```

---

## Security Review

Security concerns:

- No visible authorization for administrative showtime mutation endpoints.
- Raw exception messages are returned to clients.
- `getMovieShowtimes()` exposes exception file path and line number.
- Raw model/service responses may expose internal fields.
- Bulk endpoints are vulnerable to resource exhaustion because request size/range is not capped.

No direct SQL injection is visible in this file because Eloquent query builder is used. No password/JWT/webhook handling exists in this file.

---

## Performance Review

Performance concerns:

- Bulk endpoints perform one `exists()` query per generated showtime plus one insert per created row.
- Large date ranges and slot arrays are unbounded.
- No batching/upsert is used.
- No transaction strategy exists for bulk operations.
- `index()` passes unvalidated request data to the service, so pagination bounds are not visible.
- Raw serialization may include unnecessary relationships depending on service output.

Recommended improvements:

- Validate and cap generated showtime count.
- Use bulk conflict detection queries instead of per-row `exists()`.
- Use `upsert()` with a unique key if partial skip behavior is desired.
- Use API Resources and controlled eager loading.
- Add database indexes on `screen_id`, `scheduled_at`, and likely `movie_id`.

---

## Database Review

Database correctness concerns:

- Duplicate showtimes are possible without a unique database constraint.
- Bulk operations are not transactional.
- Schedule overlap validation is absent.
- Update/delete operations have no visible booking/ticket dependency checks.
- Status lifecycle is represented as numeric values only.
- Format/version compatibility is not validated.
- There is no visible audit log for creating, changing, or deleting showtimes.

---

## Concurrency Review

High-risk concurrency issues:

- `exists()` followed by `create()` is race-prone.
- Bulk create is not idempotent.
- Partial failure makes retries unsafe.
- Updating/deleting showtimes can race with booking creation unless service/database locking exists.
- No lock or transaction is visible around schedule mutation.

Required protections:

- Unique index for exact duplicate prevention.
- Overlap prevention implemented transactionally.
- Domain locks around showtime mutation when bookings/seat holds exist.
- Idempotency key or deterministic bulk operation key for retries.
- Audit log for all schedule mutations.

---

## Laravel Best Practice Review

Recommended improvements:

- Move validation and authorization into FormRequests.
- Use route model binding.
- Use policies/gates for showtime mutations.
- Use API Resources.
- Do not pass `Request` objects into services.
- Do not mutate the request object.
- Do not put domain scheduling loops in the controller.
- Avoid fully-qualified model references when imports exist.
- Prefer centralized exception handling and safe error responses.

---

## Testing Review

Recommended tests:

1. Unauthorized users cannot create, update, bulk-create, or delete showtimes.
2. Concurrent bulk create requests cannot create duplicate showtimes.
3. Exact duplicate screen/time is blocked by a database unique constraint.
4. Overlapping showtimes for the same screen are rejected.
5. Bulk create is atomic or explicitly returns safe partial-success details.
6. Retrying the same bulk request is idempotent or safely skipped.
7. Updating/deleting showtimes with paid bookings is rejected with `409 Conflict`.
8. Updating `scheduled_at` into the past is rejected.
9. Huge date ranges and slot arrays are rejected.
10. Raw exception details, file paths, and line numbers are never returned.
11. Format/version incompatibility is rejected.
12. API responses use stable `ShowtimeResource` shapes.
13. `show()` returns `404` only for actual not-found cases.

---

## Final Decision

🚫 **REQUEST CHANGES**

This controller contains critical scheduling correctness and concurrency risks. Bulk creation is race-prone, non-transactional, not idempotent, and bypasses the service layer. The controller also exposes raw exception details, lacks visible authorization, permits weak showtime state changes, and does not validate schedule overlaps. These issues must be fixed before this code can be considered production-ready for a cinema booking system.

---

_Review completed: 2026-07-14 03:18 PM_  
_File #58/137 - Phase 4: Controllers (10/34 complete)_
