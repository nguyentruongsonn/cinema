# File Review: Admin/TheaterController.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Controllers/Admin/TheaterController.php  
**Lines:** 70  
**Type:** Admin Theater Management Controller

---

## File Summary

`Admin\TheaterController` provides admin CRUD-style operations for theaters: list, create, update, status toggle, and delete. It uses `StoreTheaterRequest` and `UpdateTheaterRequest` for create/update, but uses raw `Request` for listing, direct Eloquent operations, and raw JSON responses.

This controller is not production-ready for admin theater management. It has no visible authorization, deletes theaters without dependency checks, toggles operational status with race-prone read-modify-write logic, returns raw Eloquent models, lacks audit logging, and has weak query validation around search.

---

## Overall Score

**Overall Score:** 5.0/10

**Decision:** 🟠 **REQUEST CHANGES**

---

## Strengths

- Uses FormRequest classes for create and update.
- Uses route model binding for `Theater $theater`.
- Eager-loads `branch` in `index()`.
- Uses pagination for theater listing.
- Keeps the controller relatively small.

---

## Issues

### Issue #1

**Severity:** Critical  
**Category:** Security / Authorization  
**Location:** app/Http/Controllers/Admin/TheaterController.php:14-68

**Problem**

No method shows authentication, authorization middleware, policy, gate, or permission check.

```php
public function index(Request $request)
```

```php
public function store(StoreTheaterRequest $request)
```

```php
public function destroy(Theater $theater)
```

**Why this matters**

Theater management is an admin-only capability. Unauthorized mutation can create fake theaters, alter operational locations, disable theaters, or delete records used by screens/showtimes/orders.

**How to fix**

Add explicit middleware and policies.

```php
public function __construct()
{
    $this->middleware(['auth', 'permission:theaters.manage']);
}
```

For destructive operations:

```php
$this->authorize('delete', $theater);
```

---

### Issue #2

**Severity:** High  
**Category:** Business Logic / Data Integrity  
**Location:** app/Http/Controllers/Admin/TheaterController.php:65-68

**Problem**

The controller deletes a theater without checking dependent screens, showtimes, bookings, or historical orders.

```php
public function destroy(Theater $theater)
{
    $theater->delete();
    return response()->json(['success' => true, 'message' => 'Xóa rạp chiếu thành công.']);
}
```

**Why this matters**

Theaters are operational and historical entities. Deleting a theater can break screen/showtime relationships and historical reports. If foreign keys exist, this may fail at runtime. If cascade rules exist, it can destroy large parts of the schedule hierarchy.

**How to fix**

Block deletion when dependencies exist and prefer deactivation/soft delete.

```php
if ($theater->screens()->exists()) {
    return response()->json([
        'success' => false,
        'message' => 'Cannot delete theater with screens.'
    ], 422);
}

$theater->delete();
```

---

### Issue #3

**Severity:** High  
**Category:** Business Logic / Operational Integrity  
**Location:** app/Http/Controllers/Admin/TheaterController.php:59-62

**Problem**

A theater can be toggled active/inactive without checking active screens, future showtimes, or sold tickets.

```php
$theater->update(['status' => !$theater->status]);
```

**Why this matters**

Disabling an active theater while showtimes are scheduled can leave sellable sessions in an inconsistent state. Re-enabling/deactivating theaters should be controlled by business rules.

**How to fix**

Require an explicit target status and validate dependencies before deactivation.

```php
if ($request->boolean('status') === false && $theater->showtimes()->future()->exists()) {
    return response()->json([
        'success' => false,
        'message' => 'Cannot deactivate theater with future showtimes.'
    ], 422);
}
```

---

### Issue #4

**Severity:** High  
**Category:** Concurrency / Lost Update  
**Location:** app/Http/Controllers/Admin/TheaterController.php:59-62

**Problem**

Status toggling uses read-modify-write without locking.

```php
$theater->update(['status' => !$theater->status]);
```

**Why this matters**

Concurrent toggle requests can produce an unexpected final state. Toggle endpoints are inherently race-prone and should be replaced with explicit state-setting.

**How to fix**

Use an explicit `status` payload.

```php
$validated = $request->validate([
    'status' => ['required', 'boolean'],
]);

$theater->update(['status' => $validated['status']]);
```

---

### Issue #5

**Severity:** Medium  
**Category:** Validation / Search Abuse  
**Location:** app/Http/Controllers/Admin/TheaterController.php:14-24

**Problem**

The search value is taken from raw request input without validation or length limits.

```php
$search = $request->input('search');

$query->where('name', 'like', "%{$search}%")
      ->orWhere('address', 'like', "%{$search}%")
      ->orWhere('email', 'like', "%{$search}%");
```

**Why this matters**

Leading-wildcard `LIKE` queries can be expensive, and long search strings can degrade database performance.

**How to fix**

Use a dedicated list request.

```php
'search' => ['nullable', 'string', 'max:100'],
'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
```

---

### Issue #6

**Severity:** Medium  
**Category:** Query Logic / Maintainability  
**Location:** app/Http/Controllers/Admin/TheaterController.php:20-24

**Problem**

Search `orWhere` clauses are not grouped.

```php
->when($search, function ($query) use ($search) {
    $query->where('name', 'like', "%{$search}%")
          ->orWhere('address', 'like', "%{$search}%")
          ->orWhere('email', 'like', "%{$search}%");
})
```

**Why this matters**

If future filters are added, ungrouped `OR` conditions can break filter semantics and return records outside the intended scope.

**How to fix**

Group search conditions.

```php
->when($search, function ($query) use ($search) {
    $query->where(function ($query) use ($search) {
        $query->where('name', 'like', "%{$search}%")
            ->orWhere('address', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%");
    });
})
```

---

### Issue #7

**Severity:** Medium  
**Category:** API Consistency  
**Location:** app/Http/Controllers/Admin/TheaterController.php:28,38-42,52-56,62,68

**Problem**

The controller returns inconsistent raw JSON shapes.

```php
return response()->json($theaters);
```

```php
return response()->json([
    'success' => true, 
    'message' => 'Tạo rạp chiếu thành công.',
    'data' => $theater
], 201);
```

```php
return response()->json(['success' => true, 'status' => $theater->status]);
```

**Why this matters**

Clients must special-case response parsing for each endpoint. This hurts API maintainability and error handling consistency.

**How to fix**

Use a shared API response envelope and resources consistently.

```php
return $this->successResponse(TheaterResource::collection($theaters), 'Theaters retrieved successfully');
```

---

### Issue #8

**Severity:** Medium  
**Category:** API Serialization / Data Exposure  
**Location:** app/Http/Controllers/Admin/TheaterController.php:28,41,55

**Problem**

Raw Eloquent models and paginators are returned directly.

```php
return response()->json($theaters);
```

```php
'data' => $theater
```

**Why this matters**

Raw serialization couples the API to database columns and can accidentally expose internal fields.

**How to fix**

Use API Resources.

```php
return new TheaterResource($theater);
```

For paginated lists:

```php
return TheaterResource::collection($theaters);
```

---

### Issue #9

**Severity:** Medium  
**Category:** Mass Assignment / Input Boundary  
**Location:** app/Http/Controllers/Admin/TheaterController.php:36,50

**Problem**

Validated arrays are passed directly into `create()` and `update()`.

```php
$theater = Theater::create($validated);
```

```php
$theater->update($validated);
```

**Why this matters**

This is only safe if the FormRequests and model `$fillable` are perfectly restrictive. For admin endpoints, explicit field mapping is safer and more maintainable.

**How to fix**

Map allowed fields explicitly.

```php
$theater->fill([
    'branch_id' => $validated['branch_id'],
    'name' => $validated['name'],
    'address' => $validated['address'],
    'email' => $validated['email'],
    'status' => $validated['status'],
])->save();
```

---

### Issue #10

**Severity:** Medium  
**Category:** Boolean Handling / API Semantics  
**Location:** app/Http/Controllers/Admin/TheaterController.php:34,48

**Problem**

Status is derived from field presence instead of a validated boolean value.

```php
$validated['status'] = $request->has('status') ? 1 : 0;
```

**Why this matters**

For JSON APIs, `has('status')` treats `"status": false` as present and therefore sets status to `1`. This is a correctness bug for API clients.

**How to fix**

Use validated boolean input.

```php
$validated['status'] = $request->boolean('status');
```

Better: let the FormRequest validate and normalize it.

---

### Issue #11

**Severity:** Medium  
**Category:** Observability / Audit Logging  
**Location:** app/Http/Controllers/Admin/TheaterController.php:31-68

**Problem**

No admin mutation is audited.

```php
$theater = Theater::create($validated);
```

```php
$theater->update($validated);
```

```php
$theater->delete();
```

**Why this matters**

Theater changes affect schedules, customer-facing location data, and business reports. Production systems need actor, old values, new values, reason, and timestamp.

**How to fix**

Create audit events for create/update/status/delete operations.

```php
AuditLog::record('theater.updated', [
    'actor_id' => auth()->id(),
    'theater_id' => $theater->id,
    'changes' => $theater->getChanges(),
]);
```

---

### Issue #12

**Severity:** Medium  
**Category:** Exception Handling  
**Location:** app/Http/Controllers/Admin/TheaterController.php:31-68

**Problem**

Create, update, toggle, and delete have no exception handling or domain-specific failure responses.

```php
$theater = Theater::create($validated);
```

```php
$theater->delete();
```

**Why this matters**

Database constraint failures and unexpected exceptions will fall through to the global exception handler. That may be acceptable if globally standardized, but this controller itself does not provide clear domain responses for expected failures like "theater in use".

**How to fix**

Handle expected domain failures explicitly and let unexpected exceptions be logged by the global handler.

```php
if ($theater->screens()->exists()) {
    return response()->json(['message' => 'Theater is in use.'], 422);
}
```

---

### Issue #13

**Severity:** Low  
**Category:** Dead Code / Unused Import  
**Location:** app/Http/Controllers/Admin/TheaterController.php:7

**Problem**

`Branch` is imported but never used.

```php
use App\Models\Branch;
```

**Why this matters**

Unused imports add noise and indicate missing static analysis/style enforcement.

**How to fix**

Remove the unused import.

---

### Issue #14

**Severity:** Low  
**Category:** Type Safety  
**Location:** app/Http/Controllers/Admin/TheaterController.php:14,31,45,59,65

**Problem**

No controller method declares a return type.

```php
public function index(Request $request)
```

**Why this matters**

Return types improve static analysis and refactoring safety.

**How to fix**

```php
use Illuminate\Http\JsonResponse;

public function index(Request $request): JsonResponse
```

---

### Issue #15

**Severity:** Low  
**Category:** Formatting / Code Style  
**Location:** app/Http/Controllers/Admin/TheaterController.php:39,53

**Problem**

There is trailing whitespace after array entries.

```php
'success' => true, 
```

**Why this matters**

Style issues should be caught automatically by Laravel Pint/CI.

**How to fix**

Run Laravel Pint and enforce it in CI.

---

## Security Review

Security concerns:

- No visible authorization for admin-only endpoints.
- Raw Eloquent serialization may expose internal fields.
- Theater deletion/toggling can be abused to disrupt operations.
- No audit trail for admin mutations.

No direct SQL injection is visible because Eloquent parameter binding is used.

---

## Performance Review

Performance concerns:

- Wildcard search without validation or length limits.
- Fixed pagination size is acceptable, but not configurable under validation.
- Raw model serialization may send unnecessary fields.

---

## Database Review

Database/data correctness concerns:

- Theater deletion has no dependency checks.
- No soft-delete/deactivation strategy is visible.
- Status changes do not validate active/future operational dependencies.
- Direct mass assignment relies entirely on FormRequest and model fillable correctness.

---

## Concurrency Review

Concurrency concerns:

- `toggleActive()` is race-prone.
- Delete can race with screen/showtime creation if not guarded by transaction/constraints.
- No locks or explicit state transitions are used for operational status changes.

---

## Laravel Best Practice Review

Recommended improvements:

- Add authorization middleware/policies.
- Use a dedicated index/list FormRequest.
- Use API Resources.
- Use shared response helpers.
- Replace toggle with explicit state update.
- Remove unused imports.
- Add return types.
- Move business rules to a service/action.
- Use audit logging for admin mutations.

---

## Testing Review

Recommended tests:

1. Guest cannot access admin theater endpoints.
2. Non-admin/non-permissioned user cannot create/update/delete theaters.
3. `status: false` in JSON creates an inactive theater.
4. Theater with screens cannot be deleted.
5. Theater with future showtimes cannot be deactivated.
6. Search input over max length is rejected.
7. Search OR conditions remain scoped when additional filters are added.
8. Raw model fields are not exposed through API resources.
9. Concurrent toggle requests do not produce unexpected state.
10. Admin create/update/delete actions produce audit records.

---

## Final Decision

🟠 **REQUEST CHANGES**

`Admin\TheaterController` is simple but not production-ready. It needs explicit authorization, safe dependency-aware deletion/deactivation, proper boolean handling, stable API resources, audit logging, and improved validation before it can be accepted.

---

_Review completed: 2026-07-14 04:35 PM_  
_File #72/137 - Phase 4: Controllers (24/34 complete)_
