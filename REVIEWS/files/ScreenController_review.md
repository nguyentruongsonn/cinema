# File Review: ScreenController.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Controllers/ScreenController.php  
**Lines:** 120  
**Type:** Screen Management API Controller

---

## File Summary

`ScreenController` exposes CRUD endpoints for cinema screens. It delegates persistence and lookup behavior to `ScreenService`, validates create/update input inline, transforms `status` from API string values into boolean-like integers, and returns responses through the shared `ApiResponse` trait.

This controller manages operational cinema layout data. Screen capacity, rows, columns, format, and status directly affect showtime creation, seat layout correctness, booking availability, and revenue integrity. The implementation has production concerns around missing visible authorization, weak layout/capacity validation, unsafe exception disclosure, raw service serialization, Request coupling, unused imports, and incomplete HTTP error mapping.

---

## Overall Score

**Overall Score:** 5.8/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

- Uses constructor dependency injection for `ScreenService`.
- Uses the shared `ApiResponse` trait.
- Delegates core persistence logic to a service instead of querying directly in the controller.
- Validates basic create/update fields.
- Uses `exists` validation for `theater_id`, `format_id`, and `sound_id`.
- Uses a uniqueness rule for `code`.
- Converts public API status values into the model's expected internal boolean representation.

---

## Issues

### Issue #1

**Severity:** High  
**Category:** Security / Authorization  
**Location:** app/Http/Controllers/ScreenController.php:23-119

**Problem**

The controller exposes screen CRUD actions without any visible authorization or admin protection.

```php
public function index(Request $request)
```

```php
public function store(Request $request)
```

```php
public function update(Request $request, $id)
```

```php
public function destroy($id)
```

**Why this matters**

Screen creation, update, and deletion are administrative operations. If routes are not protected elsewhere, unauthorized users could modify theater infrastructure, disable screens, change capacity/layout, or delete screens with active showtimes. That can break bookings and cause revenue loss.

**How to fix**

Apply authorization at route/controller level and use policies for per-action control.

```php
public function __construct(
    private readonly ScreenService $screenService
) {
    $this->middleware(['auth:api', 'permission:screens.manage'])->except(['index', 'show']);
}
```

Or authorize each action:

```php
$this->authorize('create', Screen::class);
$this->authorize('update', $screen);
$this->authorize('delete', $screen);
```

---

### Issue #2

**Severity:** High  
**Category:** Security / Information Disclosure  
**Location:** app/Http/Controllers/ScreenController.php:28-30, 59-61, 103-105, 116-118

**Problem**

Most catch blocks return raw exception messages to API clients.

```php
} catch (\Exception $e) {
    return $this->errorResponse('Failed to retrieve screens: ' . $e->getMessage(), 500);
}
```

```php
} catch (\Exception $e) {
    return $this->errorResponse('Failed to create screen: ' . $e->getMessage(), 500);
}
```

```php
} catch (\Exception $e) {
    return $this->errorResponse('Failed to update screen: ' . $e->getMessage(), 500);
}
```

```php
} catch (\Exception $e) {
    return $this->errorResponse('Failed to delete screen: ' . $e->getMessage(), 500);
}
```

**Why this matters**

Raw exception messages can expose SQL errors, schema names, foreign key constraints, filesystem paths, stack internals, or business rule details. Administrative endpoints often reveal sensitive operational structure, so error disclosure is not acceptable.

**How to fix**

Log exceptions server-side and return generic API-safe errors.

```php
use Illuminate\Support\Facades\Log;

catch (\Throwable $e) {
    Log::error('Failed to create screen', [
        'exception' => $e,
        'payload' => $validated,
    ]);

    return $this->errorResponse('Failed to create screen', 500);
}
```

Prefer centralized exception handling and domain exceptions mapped to safe messages.

---

### Issue #3

**Severity:** High  
**Category:** Business Logic / Database Correctness  
**Location:** app/Http/Controllers/ScreenController.php:42-44 and 86-88

**Problem**

The controller validates `capacity`, `rows`, and `columns` independently but does not enforce consistency between them.

```php
'capacity' => 'required|integer|min:1',
'rows' => 'required|integer|min:1',
'columns' => 'required|integer|min:1',
```

```php
'capacity' => 'sometimes|integer|min:1',
'rows' => 'sometimes|integer|min:1',
'columns' => 'sometimes|integer|min:1',
```

**Why this matters**

Screen capacity must match the actual seat layout constraints. Allowing `capacity` greater than `rows * columns`, or changing rows/columns without recalculating capacity, can create impossible seating, broken seat generation, overselling risk, and inconsistent booking availability.

**How to fix**

Validate cross-field consistency in a FormRequest or service.

```php
$validator->after(function ($validator) use ($validated) {
    if (($validated['capacity'] ?? 0) > (($validated['rows'] ?? 0) * ($validated['columns'] ?? 0))) {
        $validator->errors()->add('capacity', 'Capacity cannot exceed rows multiplied by columns.');
    }
});
```

For updates, compare against the existing screen values when only one dimension is provided.

---

### Issue #4

**Severity:** High  
**Category:** Business Logic / Data Integrity  
**Location:** app/Http/Controllers/ScreenController.php:80-105 and 111-118

**Problem**

The controller allows updating or deleting screens without visible checks for active showtimes, sold tickets, seat holds, or existing bookings.

```php
$screen = $this->screenService->update((int) $id, $validated);
```

```php
$this->screenService->delete((int) $id);
```

**Why this matters**

Changing a screen's capacity/layout or deleting a screen that has scheduled showtimes can invalidate seats, tickets, and customer bookings. This can cause double booking, missing seats, incorrect occupancy, broken QR/ticket validation, and revenue reconciliation issues.

**How to fix**

Enforce domain rules before mutation:

```php
if ($screen->showtimes()->whereIn('status', ['scheduled', 'active'])->exists()) {
    return $this->errorResponse('Cannot modify a screen with active showtimes.', 409);
}
```

Prefer service-level protection inside a transaction, with the controller mapping domain exceptions to `409 Conflict`.

---

### Issue #5

**Severity:** Medium  
**Category:** Architecture / Service Layer Coupling  
**Location:** app/Http/Controllers/ScreenController.php:23-27

**Problem**

The controller passes the full HTTP `Request` object into the service.

```php
$screens = $this->screenService->getAll($request);
```

**Why this matters**

Passing `Request` couples the service layer to HTTP and allows the service to read unvalidated/unbounded request parameters. This makes the service harder to unit test and weakens the API boundary.

**How to fix**

Validate filters/pagination in a FormRequest and pass a DTO or array.

```php
$filters = $request->validate([
    'search' => ['nullable', 'string', 'max:255'],
    'theater_id' => ['nullable', 'integer', 'exists:theaters,id'],
    'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
]);

$screens = $this->screenService->getAll($filters);
```

---

### Issue #6

**Severity:** Medium  
**Category:** Validation / API Contract  
**Location:** app/Http/Controllers/ScreenController.php:23-27

**Problem**

The `index()` action has no visible validation for filter, sorting, or pagination inputs.

```php
public function index(Request $request)
{
    try {
        $screens = $this->screenService->getAll($request);
```

**Why this matters**

Unvalidated list parameters can allow invalid filters, excessive `per_page` values, unsafe sorting, unexpected query behavior, and performance degradation. API contracts should be explicit at the controller boundary.

**How to fix**

Use a list FormRequest with bounded pagination and whitelisted sorting.

```php
$request->validate([
    'search' => ['nullable', 'string', 'max:255'],
    'theater_id' => ['nullable', 'integer', 'exists:theaters,id'],
    'status' => ['nullable', 'in:active,inactive'],
    'sort_by' => ['nullable', Rule::in(['name', 'code', 'created_at'])],
    'sort_dir' => ['nullable', Rule::in(['asc', 'desc'])],
    'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
]);
```

---

### Issue #7

**Severity:** Medium  
**Category:** Laravel Best Practice / FormRequest  
**Location:** app/Http/Controllers/ScreenController.php:36-49 and 80-93

**Problem**

The controller uses inline validation for create/update operations.

```php
$validated = $request->validate([
    'theater_id' => 'required|exists:theaters,id',
    'name' => 'required|string|max:100',
    ...
]);
```

**Why this matters**

Screen validation is not trivial because it requires cross-field rules and domain checks around active showtimes. Inline validation keeps complex request semantics in the controller and makes authorization/normalization harder to test.

**How to fix**

Create dedicated FormRequests:

```php
public function store(StoreScreenRequest $request)
{
    $screen = $this->screenService->create($request->validated());
}
```

```php
public function update(UpdateScreenRequest $request, Screen $screen)
{
    $screen = $this->screenService->update($screen, $request->validated());
}
```

---

### Issue #8

**Severity:** Medium  
**Category:** API Contract / Response Serialization  
**Location:** app/Http/Controllers/ScreenController.php:57-58, 70-71, 101-102

**Problem**

The controller returns raw service results directly.

```php
return $this->successResponse($screen, 'Screen created successfully', 201);
```

```php
return $this->successResponse($screen, 'Screen retrieved successfully');
```

```php
return $this->successResponse($screen, 'Screen updated successfully');
```

**Why this matters**

If the service returns Eloquent models, the API response depends on model serialization rules and may expose internal fields, timestamps, relationships, or admin-only metadata. It also makes response shape inconsistent across endpoints.

**How to fix**

Use API Resources.

```php
return $this->successResponse(
    new ScreenResource($screen),
    'Screen created successfully',
    201
);
```

Use resource collections for paginated lists.

---

### Issue #9

**Severity:** Medium  
**Category:** Validation / Correctness  
**Location:** app/Http/Controllers/ScreenController.php:85

**Problem**

The update uniqueness rule concatenates raw `$id` into the validation rule string.

```php
'code' => 'nullable|string|max:20|unique:screens,code,' . $id,
```

**Why this matters**

Although the value is likely route-controlled, concatenating route parameters into rule strings is brittle and can behave incorrectly with non-numeric IDs or malformed route values. It also does not specify the primary key column explicitly.

**How to fix**

Use Laravel's `Rule::unique()` and route model binding.

```php
use Illuminate\Validation\Rule;

'code' => [
    'nullable',
    'string',
    'max:20',
    Rule::unique('screens', 'code')->ignore($screen->id),
],
```

---

### Issue #10

**Severity:** Medium  
**Category:** Validation / Domain Modeling  
**Location:** app/Http/Controllers/ScreenController.php:45 and 89

**Problem**

`screen_type` accepts any nullable string up to 50 characters.

```php
'screen_type' => 'nullable|string|max:50',
```

**Why this matters**

Screen type is a domain value. Accepting arbitrary strings creates inconsistent reporting, filtering, pricing, and seat-layout behavior. It also allows multiple spellings of the same concept.

**How to fix**

Validate against a domain enum/config table.

```php
'screen_type' => ['nullable', Rule::in(['standard', 'imax', 'vip', 'premium'])],
```

Use actual domain values from the application, not duplicated literals if an enum/config exists.

---

### Issue #11

**Severity:** Medium  
**Category:** API Design / Status Representation  
**Location:** app/Http/Controllers/ScreenController.php:48, 51-54, 92, 95-98

**Problem**

The public API accepts string statuses but the controller converts them into integers.

```php
'status' => 'required|in:active,inactive',
```

```php
$validated['status'] = $validated['status'] === 'active' ? 1 : 0;
```

**Why this matters**

This mixes API representation and storage representation inside the controller. It is easy for create/update/list/show responses to become inconsistent if the model returns boolean/integer `status` while clients send strings.

**How to fix**

Move status normalization to a FormRequest DTO or model cast/accessor, and return a consistent API representation through a resource.

```php
protected function prepareForValidation(): void
{
    if ($this->has('status')) {
        $this->merge(['status' => $this->input('status') === 'active']);
    }
}
```

Or store statuses as explicit enums/strings if the domain needs more than binary status.

---

### Issue #12

**Severity:** Low  
**Category:** Exception Handling / API Correctness  
**Location:** app/Http/Controllers/ScreenController.php:67-74

**Problem**

`show()` catches any exception and returns `404`.

```php
try {
    $screen = $this->screenService->getById((int) $id);
    return $this->successResponse($screen, 'Screen retrieved successfully');
} catch (\Exception $e) {
    return $this->errorResponse('Screen not found', 404);
}
```

**Why this matters**

A database outage, serialization failure, or programming error would be incorrectly returned as `404 Not Found`, hiding production failures and making monitoring inaccurate.

**How to fix**

Only map actual not-found exceptions to `404`. Let unexpected exceptions go to centralized error handling or log and return `500`.

```php
catch (ModelNotFoundException $e) {
    return $this->errorResponse('Screen not found', 404);
}
```

---

### Issue #13

**Severity:** Low  
**Category:** Type Safety / Laravel Best Practice  
**Location:** app/Http/Controllers/ScreenController.php:23, 36, 67, 80, 111

**Problem**

Controller actions do not declare return types and route parameters are untyped.

```php
public function index(Request $request)
```

```php
public function show($id)
```

**Why this matters**

Missing return types and untyped IDs reduce static analysis value and make controller contracts less explicit.

**How to fix**

Use `JsonResponse` return types and route model binding.

```php
public function show(Screen $screen): JsonResponse
{
    return $this->successResponse(new ScreenResource($screen), 'Screen retrieved successfully');
}
```

---

### Issue #14

**Severity:** Low  
**Category:** Clean Code / Unused Imports  
**Location:** app/Http/Controllers/ScreenController.php:5-6

**Problem**

The controller imports `Screen` and `Theater` but does not use them.

```php
use App\Models\Screen;
use App\Models\Theater;
```

**Why this matters**

Unused imports add noise and suggest incomplete refactoring or abandoned route-model-binding intent.

**How to fix**

Remove unused imports, or use route model binding if appropriate.

```php
use App\Models\Screen;
```

Only keep `Screen` if route model binding is introduced.

---

## Security Review

No direct SQL injection, XSS, CSRF, file upload, password handling, JWT handling, or payment logic exists in this file.

Security concerns:

- No visible authorization around administrative screen CRUD operations.
- Raw exception messages are returned to clients.
- Raw service responses may expose model fields.
- Unvalidated list inputs may allow unsafe or expensive queries depending on service implementation.

---

## Performance Review

Potential performance concerns:

- `index()` passes the full request to the service with no visible pagination bounds.
- If `ScreenService::getAll()` allows large `per_page` values or unbounded queries, screen listing can become expensive.
- Returning raw models may eager-load or serialize unintended relationships depending on service/model state.

Recommended improvements:

- Validate and cap `per_page`.
- Whitelist filters/sorts.
- Use resources to control serialization.
- Ensure service eager-loads only required relations.

---

## Database Review

Database correctness concerns:

- `capacity`, `rows`, and `columns` are not validated together.
- `update()` and `destroy()` have no visible protection against modifying/deleting screens referenced by active showtimes/bookings.
- `code` uniqueness validation should use `Rule::unique()` and should be backed by a real database unique index.
- Status storage/API representation is mixed in the controller.

---

## Concurrency Review

Concurrency-sensitive areas:

- Two concurrent create/update requests can still collide on `code`; this must be protected by a database unique constraint, not only validation.
- Updating layout while showtimes/bookings are being created can produce inconsistent seat availability if service/database protections are missing.
- Deleting a screen while showtimes are scheduled must be prevented by foreign keys/domain checks.

Required protections:

- Database unique index on `screens.code` if code must be globally unique.
- Foreign key constraints from showtimes/seats to screens.
- Transactional layout updates if seat layout records are regenerated.
- Conflict response for attempted mutation of screens with active dependencies.

---

## Laravel Best Practice Review

Recommended improvements:

- Use FormRequests for list/create/update validation and authorization.
- Use route model binding instead of manual `(int) $id` casting.
- Use API Resources for screen responses.
- Use policies/gates for admin-only mutations.
- Avoid passing full `Request` objects into services.
- Use `Rule::unique()` instead of concatenated validation strings.
- Prefer centralized exception handling.

---

## Testing Review

Recommended tests:

1. Unauthorized users cannot create, update, or delete screens.
2. `capacity` cannot exceed `rows * columns`.
3. Updating rows/columns/capacity is rejected when active showtimes or bookings exist.
4. Deleting a screen with scheduled showtimes is rejected with `409 Conflict`.
5. Duplicate screen codes are rejected and database uniqueness is enforced.
6. Invalid `screen_type` values are rejected after enum validation is added.
7. List endpoint validates and caps pagination/sorting parameters.
8. Raw exception messages are not exposed in API responses.
9. Screen responses use the documented API status representation.
10. `show()` returns 404 only for real not-found cases, not all exceptions.

---

## Final Decision

🚫 **REQUEST CHANGES**

This controller manages cinema screen infrastructure that directly impacts seat layouts and booking correctness. It is not production-ready until authorization is explicit, raw exception disclosure is removed, layout/capacity invariants are enforced, active showtime/booking mutation rules are protected, request validation is moved into FormRequests, and API serialization is made consistent.

---

_Review completed: 2026-07-14 03:13 PM_  
_File #57/137 - Phase 4: Controllers (9/34 complete)_