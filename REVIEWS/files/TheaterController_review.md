# File Review: TheaterController.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Controllers/TheaterController.php  
**Lines:** 133  
**Type:** Theater Management API Controller

---

## File Summary

`TheaterController` exposes theater listing, city listing, create, show, screens, update, and delete endpoints. Compared with several other controllers, it uses dedicated `StoreTheaterRequest` and `UpdateTheaterRequest` for mutation validation, and it validates listing filters inline with pagination bounds. However, the controller still has production-readiness issues: no visible authorization on theater mutation endpoints, raw exception details returned to clients, broad exception handling that converts unrelated failures into incorrect responses, no route model binding, raw service/model serialization, and no visible protection against deleting or modifying theaters that have screens, showtimes, bookings, or paid orders.

Theater data is operationally important because screens, showtimes, seats, bookings, analytics, and customer-facing browsing depend on it. Theater mutation must be protected by authorization, domain constraints, audit logging, and safe error handling.

---

## Overall Score

**Overall Score:** 6.4/10

**Decision:** ⚠️ **REQUEST CHANGES**

---

## Strengths

- Uses dependency injection for `TheaterService`.
- Uses dedicated `StoreTheaterRequest` and `UpdateTheaterRequest` for create/update validation.
- Validates index filters with explicit allowlists for `status`, `sort_by`, and `sort_dir`.
- Caps `per_page` to `max:50` in listing and screens endpoints.
- Keeps most business logic in `TheaterService` rather than directly querying models in the controller.
- Uses shared `ApiResponse` response helpers.

---

## Issues

### Issue #1

**Severity:** High  
**Category:** Security / Authorization  
**Location:** app/Http/Controllers/TheaterController.php:62-70, 109-117, 123-131

**Problem**

The controller exposes theater create, update, and delete operations without any visible authorization checks or admin middleware.

```php
public function store(StoreTheaterRequest $request)
```

```php
public function update(UpdateTheaterRequest $request, $id)
```

```php
public function destroy($id)
```

**Why this matters**

Theater management is an administrative operation. If route-level middleware is missing or misconfigured, an unauthorized user could create fake theaters, change active theater data, or delete operational theaters. This impacts customer browsing, showtimes, bookings, analytics, and revenue reporting.

**How to fix**

Enforce authorization in addition to route middleware. Use policies or permissions.

```php
public function store(StoreTheaterRequest $request)
{
    $this->authorize('create', Theater::class);

    $theater = $this->theaterService->createTheater($request->validated());

    return $this->successResponse(new TheaterResource($theater), 'Theater created successfully', 201);
}
```

Also protect the controller constructor where appropriate:

```php
$this->middleware(['auth:api', 'permission:theaters.manage'])
    ->except(['index', 'cities', 'show', 'screens']);
```

---

### Issue #2

**Severity:** High  
**Category:** Security / Information Disclosure  
**Location:** app/Http/Controllers/TheaterController.php:40-42, 54-56, 68-70, 101-103, 115-117, 129-131

**Problem**

The controller returns raw exception messages to API clients.

```php
return $this->errorResponse('Failed to retrieve theaters: ' . $e->getMessage(), 500);
```

```php
return $this->errorResponse('Failed to create theater: ' . $e->getMessage(), 500);
```

```php
return $this->errorResponse('Failed to delete theater: ' . $e->getMessage(), 500);
```

**Why this matters**

Raw exception messages can expose SQL errors, table names, validation internals, filesystem paths, model names, and infrastructure details. In production APIs, internal errors must be logged server-side and clients should receive stable generic messages.

**How to fix**

Log exception details internally and return generic errors.

```php
catch (\Throwable $e) {
    Log::error('Failed to create theater', [
        'exception' => $e,
        'payload' => $request->validated(),
    ]);

    return $this->errorResponse('Failed to create theater', 500);
}
```

Better: remove broad try/catch blocks and use centralized exception rendering.

---

### Issue #3

**Severity:** High  
**Category:** Business Logic / Data Integrity  
**Location:** app/Http/Controllers/TheaterController.php:109-117 and 123-131

**Problem**

The controller allows updating and deleting theaters without visible checks for dependent screens, showtimes, bookings, paid orders, or active schedules.

```php
$theater = $this->theaterService->updateTheater($id, $request->validated());
```

```php
$this->theaterService->deleteTheater($id);
```

**Why this matters**

Deleting or modifying a theater that has active screens/showtimes/bookings can break customer reservations, seat maps, reporting, and order history. A theater with sold tickets should not be physically deleted or materially changed without a controlled lifecycle and audit trail.

**How to fix**

Enforce domain rules in the service and return conflict responses for unsafe operations.

```php
if ($theater->screens()
    ->whereHas('showtimes.orders', fn ($query) => $query->whereIn('status', ['paid', 'confirmed']))
    ->exists()) {
    throw new DomainException('Cannot delete a theater with paid bookings.');
}
```

Prefer soft-delete/deactivation for operational entities.

---

### Issue #4

**Severity:** Medium  
**Category:** Exception Handling / API Correctness  
**Location:** app/Http/Controllers/TheaterController.php:76-84

**Problem**

`show()` catches any exception and converts it into a `404`.

```php
try {
    $theater = $this->theaterService->getTheater($id);

    return $this->successResponse($theater, 'Theater retrieved successfully');
} catch (\Exception $e) {
    return $this->errorResponse('Theater not found', 404);
}
```

**Why this matters**

Database outages, serialization bugs, programming errors, and permission failures would be incorrectly returned as "not found." This hides production incidents, breaks monitoring, and gives clients incorrect semantics.

**How to fix**

Catch only `ModelNotFoundException` for 404 and allow unexpected exceptions to be handled centrally.

```php
catch (ModelNotFoundException $e) {
    return $this->errorResponse('Theater not found', 404);
}
```

---

### Issue #5

**Severity:** Medium  
**Category:** API Contract / Response Serialization  
**Location:** app/Http/Controllers/TheaterController.php:37-39, 51-53, 65-67, 79-81, 98-100, 112-114

**Problem**

The controller returns raw service results directly instead of API Resources.

```php
return $this->paginatedResponse($theaters, 'Theaters retrieved successfully');
```

```php
return $this->successResponse($theater, 'Theater created successfully', 201);
```

```php
return $this->successResponse($theater, 'Theater retrieved successfully');
```

**Why this matters**

Raw Eloquent serialization can expose internal fields and creates unstable response shapes depending on loaded relationships. Public theater responses and admin theater responses may need different fields.

**How to fix**

Use API Resources.

```php
return $this->paginatedResponse(
    TheaterResource::collection($theaters),
    'Theaters retrieved successfully'
);
```

Use separate resources for public/admin payloads if needed.

---

### Issue #6

**Severity:** Medium  
**Category:** Laravel Best Practice / Route Model Binding  
**Location:** app/Http/Controllers/TheaterController.php:76, 90, 109, 123

**Problem**

The controller accepts raw IDs instead of using route model binding.

```php
public function show($id)
```

```php
public function screens($theaterId, Request $request)
```

```php
public function update(UpdateTheaterRequest $request, $id)
```

```php
public function destroy($id)
```

**Why this matters**

Raw IDs push model resolution into services, reduce type safety, and make authorization/policies less ergonomic. Route model binding provides consistent 404 behavior and cleaner controller contracts.

**How to fix**

Use route model binding.

```php
public function show(Theater $theater): JsonResponse
{
    return $this->successResponse(new TheaterResource($theater), 'Theater retrieved successfully');
}
```

For screens:

```php
public function screens(Theater $theater, Request $request): JsonResponse
{
    $screens = $this->theaterService->getTheaterScreens($theater, $filters);
}
```

---

### Issue #7

**Severity:** Medium  
**Category:** API Design / Public Data Exposure  
**Location:** app/Http/Controllers/TheaterController.php:48-56

**Problem**

`cities()` exposes all unique theater cities without visible status filtering.

```php
$cities = $this->theaterService->getCities();

return $this->successResponse($cities, 'Cities retrieved successfully');
```

**Why this matters**

If inactive, internal, test, draft, or soft-deleted theaters exist, their cities may be exposed publicly. For a public API, city lists should normally include only active/public theaters.

**How to fix**

Make the intended scope explicit.

```php
$cities = $this->theaterService->getCities(status: 'active');
```

If this endpoint is admin-only, protect it with authorization.

---

### Issue #8

**Severity:** Medium  
**Category:** Validation / Identifier Handling  
**Location:** app/Http/Controllers/TheaterController.php:76, 90, 109, 123

**Problem**

Route IDs are not typed or validated at the controller boundary.

```php
public function show($id)
```

```php
public function screens($theaterId, Request $request)
```

**Why this matters**

Invalid IDs such as strings can reach service methods. Depending on service implementation, this can cause inconsistent errors, accidental broad queries, or unexpected 500 responses.

**How to fix**

Use route model binding or typed integer parameters.

```php
public function show(int $id): JsonResponse
```

Route constraints should also be used:

```php
Route::get('/theaters/{theater}', ...)->whereNumber('theater');
```

---

### Issue #9

**Severity:** Low  
**Category:** Type Safety / Readability  
**Location:** app/Http/Controllers/TheaterController.php:25, 48, 62, 76, 90, 109, 123

**Problem**

Controller methods do not declare return types.

```php
public function index(Request $request)
```

```php
public function cities()
```

```php
public function destroy($id)
```

**Why this matters**

Missing return types reduce static analysis effectiveness and make controller contracts less explicit.

**How to fix**

Declare `JsonResponse` return types.

```php
use Illuminate\Http\JsonResponse;

public function index(Request $request): JsonResponse
```

---

### Issue #10

**Severity:** Low  
**Category:** Clean Code / Dependency Injection Style  
**Location:** app/Http/Controllers/TheaterController.php:15-20

**Problem**

The controller uses a mutable protected property instead of constructor property promotion and readonly injection.

```php
protected TheaterService $theaterService;

public function __construct(TheaterService $theaterService)
{
    $this->theaterService = $theaterService;
}
```

**Why this matters**

The dependency is not intended to change after construction. A readonly promoted property is shorter, safer, and consistent with modern PHP 8+ style.

**How to fix**

```php
public function __construct(
    private readonly TheaterService $theaterService
) {
}
```

---

### Issue #11

**Severity:** Low  
**Category:** Exception Handling / Observability  
**Location:** app/Http/Controllers/TheaterController.php:40-42, 54-56, 68-70, 82-84, 101-103, 115-117, 129-131

**Problem**

The controller catches exceptions but does not log them.

```php
catch (\Exception $e) {
    return $this->errorResponse('Failed to retrieve theaters: ' . $e->getMessage(), 500);
}
```

**Why this matters**

If errors are swallowed and only returned to the client, production observability depends on client reports. Operational failures should be logged with context.

**How to fix**

Use centralized exception handling or log with context.

```php
Log::error('Failed to retrieve theater screens', [
    'exception' => $e,
    'theater_id' => $theaterId,
    'filters' => $filters ?? [],
]);
```

---

### Issue #12

**Severity:** Low  
**Category:** API Consistency / HTTP Semantics  
**Location:** app/Http/Controllers/TheaterController.php:123-131

**Problem**

`destroy()` returns a success response with `null` data and default success status instead of using a consistent delete contract.

```php
return $this->successResponse(null, 'Theater deleted successfully');
```

**Why this matters**

APIs should consistently choose between `204 No Content` for deletes or a standard JSON success envelope. Returning `null` data is acceptable only if this is a documented API convention across the project.

**How to fix**

Either return `204`:

```php
return response()->json(null, 204);
```

Or standardize in `ApiResponse`:

```php
return $this->successResponse(null, 'Theater deleted successfully', 200);
```

---

## Security Review

Security concerns:

- No visible authorization for theater create/update/delete.
- Raw exception messages are returned to clients.
- Raw service/model data may expose internal fields.
- City list may expose inactive/internal theater locations depending on service behavior.
- IDs are passed untyped into service methods.

No direct SQL injection is visible in this file because the controller itself does not build raw SQL. No password/JWT/webhook handling exists in this file.

---

## Performance Review

Positive points:

- Listing endpoints cap `per_page` to 50.
- Sorting fields are allowlisted.
- Filters are validated before being passed to the service.

Performance concerns:

- Response serialization is not controlled through resources.
- `cities()` may become expensive if service does not cache or scope active theaters.
- `screens()` performance depends on service eager loading and indexing.
- No caching is visible for relatively static theater/city data.

Recommended improvements:

- Cache public city lists and active theater lists.
- Use resources to prevent accidental heavy relationship serialization.
- Ensure database indexes exist for `city`, `status`, and theater foreign keys used by screens/showtimes.

---

## Database Review

Database correctness concerns:

- Unsafe update/delete behavior depends entirely on service implementation.
- No visible protection against deleting theaters with screens/showtimes/bookings.
- No visible soft-delete or deactivation lifecycle at controller level.
- No visible audit logging for theater mutation.

Recommended protections:

- Use soft delete or status deactivation instead of hard delete.
- Block deletion when dependent paid bookings or future showtimes exist.
- Add database-level foreign keys and restrict/cascade policies deliberately.
- Audit all create/update/delete operations with actor ID.

---

## Concurrency Review

No direct transaction or locking logic exists in this controller. Theater mutation can still cause concurrency problems if:

- A theater is deleted while screens/showtimes are being created.
- A theater is deactivated while customers are booking active showtimes.
- Multiple admins update the same theater concurrently.

Recommended protections:

- Use transactions in the service for update/delete workflows.
- Use optimistic locking or updated-at conflict checks for admin edits.
- Block state changes when active showtimes/bookings exist.
- Emit audit events after committed transactions.

---

## Laravel Best Practice Review

Recommended improvements:

- Add explicit authorization through policies/gates or middleware.
- Use route model binding for theater IDs.
- Use API Resources for all theater and screen responses.
- Avoid broad `catch (\Exception)` in controllers.
- Use centralized exception handling.
- Add explicit `JsonResponse` return types.
- Prefer constructor property promotion with `readonly`.
- Use dedicated FormRequests for index/screens filters if validation grows.

---

## Testing Review

Recommended tests:

1. Guests/non-admins cannot create, update, or delete theaters.
2. Admins can create theaters with valid `StoreTheaterRequest` data.
3. Invalid filters in `index()` return validation errors.
4. `per_page > 50` is rejected.
5. `show()` returns 404 only for missing theaters, not generic service failures.
6. Raw exception details are not exposed in production responses.
7. Deleting a theater with screens/future showtimes/paid bookings is rejected.
8. Updating a theater with active showtimes follows business rules.
9. `cities()` returns only active/public theater cities if used publicly.
10. Responses are stable through API Resources.
11. Concurrent updates do not silently overwrite each other.
12. Theater mutation creates audit logs with actor identity.

---

## Final Decision

⚠️ **REQUEST CHANGES**

`TheaterController` is cleaner than many controllers in the project because it uses FormRequests for mutations and validates list filters. It is still not production-ready because administrative mutations have no visible authorization, raw exception details are exposed, broad exception handling hides real failures, raw service data is returned, and destructive operations do not visibly protect dependent screens, showtimes, bookings, or paid orders.

---

_Review completed: 2026-07-14 03:24 PM_  
_File #59/137 - Phase 4: Controllers (11/34 complete)_
