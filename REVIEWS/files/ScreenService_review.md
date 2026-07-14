# File Review: ScreenService.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Services/ScreenService.php  
**Lines:** 117  
**Type:** Service Layer - Screen Management

---

## File Information

**Path:** `app/Services/ScreenService.php`  
**Type:** Laravel Service Class  
**Lines:** 117  
**Complexity:** Low  

**Purpose:**  
Handles screen-related business logic:
- Lists screens with filters, sorting, pagination, and relationships
- Retrieves screen details with seats
- Creates, updates, and deletes screens
- Applies request-based filtering and sorting

**Business Impact:** 🔴 CRITICAL - Screens are core scheduling and seat inventory entities. Incorrect handling can break showtimes, bookings, seat maps, and theater operations.

---

## Overall Score

**Code Quality:** 6.2/10  
**Security:** 5.8/10  
**Performance:** 6.0/10  
**Maintainability:** 5.9/10  
**Laravel Best Practice:** 5.6/10  

**Overall Score:** 5.9/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

1. ✅ **Small and Readable Class** - The service is short and easy to scan
2. ✅ **Relationship Eager Loading Exists** - Main queries eager-load theater, format, sound, and seats where needed
3. ✅ **Sort Column Allowlist Exists** - `applySorting()` restricts sorting to `name`, `capacity`, and `created_at`
4. ✅ **Invalid Sort Field Falls Back Safely** - Invalid `sort_by` values default to `name`
5. ✅ **Strict `in_array()` Used** - Sort allowlist uses strict comparison
6. ✅ **Typed Public Return Values** - Public methods declare return types

---

## Issues Found

### Issue #1: Delete Allows Removing a Screen Without Checking Showtimes, Seats, Bookings, or Orders

**Severity:** 🔴 CRITICAL  
**Category:** Business Logic / Database Correctness  
**Location:** Lines 61-65

**Evidence:**
```php
public function delete(int $id): bool
{
    $screen = Screen::findOrFail($id);

    return (bool) $screen->delete();
}
```

**Problem:**
The service deletes a screen without checking whether the screen has seats, showtimes, bookings, paid orders, tickets, or historical records.

**Why this matters:**
A screen is a core operational entity. Deleting it can:
- orphan showtimes or seats
- invalidate existing bookings
- break ticket history
- violate foreign keys
- remove audit/reporting context for past sessions

In a cinema booking system, screens with operational history should be deactivated, not blindly deleted.

**How to fix:**
Block deletion if dependencies exist and use a deactivation workflow.

**Example:**
```php
if ($screen->showtimes()->exists() || $screen->seats()->exists()) {
    throw ValidationException::withMessages([
        'screen' => 'Cannot delete a screen with seats or showtimes. Deactivate it instead.',
    ]);
}

$screen->delete();
```

For screens with history:
```php
$screen->update(['status' => 0]);
```

---

### Issue #2: Create Path Mass Assigns Raw `$data`

**Severity:** 🟠 HIGH  
**Category:** Security - Mass Assignment / Data Integrity  
**Location:** Lines 38-41

**Evidence:**
```php
public function create(array $data): Screen
{
    $screen = Screen::create($data);
    $screen->load(['theater', 'format', 'sound']);
```

**Problem:**
The service accepts a generic array and passes it directly to `Screen::create()`.

**Why this matters:**
If a controller passes `$request->all()` or validation changes later, unintended model columns may be written if the model permits them. Screen records control capacity, theater association, and seat inventory behavior.

**How to fix:**
Whitelist fields at the service boundary.

**Example:**
```php
use Illuminate\Support\Arr;

$payload = Arr::only($data, [
    'theater_id',
    'name',
    'code',
    'capacity',
    'format_id',
    'sound_id',
    'screen_type',
    'status',
]);

$screen = Screen::create($payload);
```

---

### Issue #3: Update Path Mass Assigns Raw `$data`

**Severity:** 🟠 HIGH  
**Category:** Security - Mass Assignment / Data Integrity  
**Location:** Lines 49-53

**Evidence:**
```php
public function update(int $id, array $data): Screen
{
    $screen = Screen::findOrFail($id);
    $screen->update($data);
    $screen->load(['theater', 'format', 'sound']);
```

**Problem:**
The update method passes the entire input array to Eloquent.

**Why this matters:**
Updating `theater_id`, `capacity`, `status`, or format/sound fields without strict rules can break active showtimes and seat layouts. This is especially risky if the screen already has scheduled showtimes.

**How to fix:**
Use an explicit allowlist and apply domain rules before allowing changes.

```php
$payload = Arr::only($data, [
    'name',
    'code',
    'format_id',
    'sound_id',
    'screen_type',
    'status',
]);

$screen->update($payload);
```

Changes to `capacity` or `theater_id` should require additional business validation.

---

### Issue #4: Updating Screen Capacity Has No Seat Layout Consistency Check

**Severity:** 🔴 CRITICAL  
**Category:** Business Logic / Database Correctness  
**Location:** Lines 49-53

**Evidence:**
```php
$screen = Screen::findOrFail($id);
$screen->update($data);
```

**Problem:**
The service allows arbitrary updates without checking whether `capacity` matches the actual number of seats or whether existing showtimes rely on the current layout.

**Why this matters:**
Screen capacity and seats must stay consistent. If capacity is changed independently:
- available seat counts can become incorrect
- booking UI can show wrong inventory
- revenue/capacity reports become wrong
- seat selection can break for existing showtimes

**How to fix:**
Treat capacity as derived from seats or enforce consistency inside a transaction.

```php
if (array_key_exists('capacity', $payload) && $screen->seats()->exists()) {
    throw ValidationException::withMessages([
        'capacity' => 'Cannot update capacity directly when seats already exist.',
    ]);
}
```

---

### Issue #5: Updating Screen Format/Sound/Theater Can Break Existing Future Showtimes

**Severity:** 🟠 HIGH  
**Category:** Business Logic / Data Integrity  
**Location:** Lines 49-53

**Evidence:**
```php
$screen->update($data);
```

**Problem:**
The service allows arbitrary changes to screen attributes without checking active/future showtimes.

**Why this matters:**
If `theater_id`, `format_id`, `sound_id`, or `screen_type` changes while future showtimes exist, customers may see inconsistent showtime metadata or tickets may no longer match the actual room configuration.

**How to fix:**
Prevent structural changes when future showtimes exist.

```php
if ($screen->showtimes()->where('scheduled_at', '>=', now())->exists()) {
    throw ValidationException::withMessages([
        'screen' => 'Cannot modify screen configuration while future showtimes exist.',
    ]);
}
```

---

### Issue #6: Create/Update/Delete Are Not Transactional

**Severity:** 🟠 HIGH  
**Category:** Database Correctness / Transaction Safety  
**Location:** Lines 38-65

**Evidence:**
```php
$screen = Screen::create($data);
```

```php
$screen->update($data);
```

```php
return (bool) $screen->delete();
```

**Problem:**
Screen mutations are not wrapped in transactions.

**Why this matters:**
Screen changes often need to be atomic with seat layout creation/update, audit logging, cache invalidation, and dependency checks. Without transactions, partial updates can leave inconsistent state.

**How to fix:**
Use `DB::transaction()` for screen writes.

---

### Issue #7: `per_page` Is Unbounded

**Severity:** 🟠 HIGH  
**Category:** Performance / Abuse Control  
**Location:** Lines 22-24

**Evidence:**
```php
$perPage = (int) $request->query('per_page', 15);

return $query->paginate($perPage);
```

**Problem:**
The client controls pagination size without a maximum.

**Why this matters:**
The listing eager-loads three relationships. Large `per_page` values can cause high memory usage and oversized responses.

**How to fix:**
Clamp page size.

```php
$perPage = min(max((int) $request->query('per_page', 15), 1), 50);
```

---

### Issue #8: Service Layer Depends Directly on HTTP Request

**Severity:** 🟡 MEDIUM  
**Category:** Architecture / Laravel Best Practice / Testability  
**Location:** Lines 8, 15, 71, 105

**Evidence:**
```php
use Illuminate\Http\Request;
```

```php
public function getAll(Request $request): LengthAwarePaginator
```

```php
private function applyFilters(Builder $query, Request $request): void
```

**Problem:**
The service is coupled to the HTTP layer. Service classes should receive validated arrays, DTOs, or typed filter objects.

**Why this matters:**
This makes the service harder to reuse from jobs, commands, tests, or internal workflows. It also shifts input normalization responsibility into the service inconsistently.

**How to fix:**
Accept validated filters.

```php
public function getAll(array $filters): LengthAwarePaginator
```

Controllers/FormRequests should validate and pass `$request->validated()` or selected query parameters.

---

### Issue #9: Filters Use Raw Request Values Without Type Normalization

**Severity:** 🟡 MEDIUM  
**Category:** Validation / Data Correctness  
**Location:** Lines 73-83 and 94-99

**Evidence:**
```php
$query->where('theater_id', $request->input('theater_id'));
$query->where('format_id', $request->input('format_id'));
$query->where('screen_type', $request->input('screen_type'));
```

```php
$status = $request->input('status', 'active');
```

**Problem:**
The service assumes request parameters are valid and normalized.

**Why this matters:**
If controller validation is missed or reused incorrectly, invalid IDs, status values, or screen types can produce incorrect results.

**How to fix:**
Validate in FormRequest and pass typed/normalized values into the service.

---

### Issue #10: Unsupported Status Values Silently Return All Statuses

**Severity:** 🟡 MEDIUM  
**Category:** API Consistency / Validation  
**Location:** Lines 94-99

**Evidence:**
```php
$status = $request->input('status', 'active');
if ($status === 'active') {
    $query->active();
} elseif ($status === 'inactive') {
    $query->where('status', 0);
}
```

**Problem:**
If `status` is neither `active` nor `inactive`, no status condition is applied.

**Why this matters:**
Invalid client input should not silently broaden the result set. This can expose inactive screens unexpectedly.

**How to fix:**
Reject unsupported values in validation or explicitly support `all`.

```php
if (!in_array($status, ['active', 'inactive', 'all'], true)) {
    throw ValidationException::withMessages([
        'status' => 'Invalid screen status filter.',
    ]);
}
```

---

### Issue #11: Search Keyword Is Used in LIKE Without Escaping Wildcards

**Severity:** 🟡 MEDIUM  
**Category:** Performance / Query Correctness  
**Location:** Lines 85-91

**Evidence:**
```php
$search = $request->input('q');

$query->where(function (Builder $subQuery) use ($search) {
    $subQuery->where('name', 'like', "%{$search}%")
        ->orWhere('code', 'like', "%{$search}%");
});
```

**Problem:**
The search keyword is embedded in LIKE patterns without escaping `%`, `_`, or backslash.

**Why this matters:**
Parameter binding prevents classic SQL injection, but wildcard injection can broaden searches and force expensive scans.

**How to fix:**
Escape LIKE wildcards and cap search length.

```php
$search = addcslashes(trim(mb_substr($request->input('q'), 0, 100)), '\\%_');
```

---

### Issue #12: Search Uses Leading Wildcards on Multiple Columns

**Severity:** 🟡 MEDIUM  
**Category:** Performance  
**Location:** Lines 88-91

**Evidence:**
```php
$subQuery->where('name', 'like', "%{$search}%")
    ->orWhere('code', 'like', "%{$search}%");
```

**Problem:**
Leading wildcard searches cannot efficiently use normal indexes.

**Why this matters:**
Screen listing may be called by admin interfaces frequently. Leading wildcard search can become slow as data grows.

**How to fix:**
Use prefix search where acceptable, full-text search, or enforce minimum keyword length and max length.

---

### Issue #13: Pagination Lacks `withQueryString()`

**Severity:** 🔵 LOW  
**Category:** API Consistency  
**Location:** Line 24

**Evidence:**
```php
return $query->paginate($perPage);
```

**Problem:**
The paginator does not preserve current query parameters in generated pagination links.

**Why this matters:**
Other services use `withQueryString()`. Missing it can create inconsistent pagination behavior for API consumers.

**How to fix:**
```php
return $query->paginate($perPage)->withQueryString();
```

---

### Issue #14: Sorting Is Not Fully Deterministic

**Severity:** 🟡 MEDIUM  
**Category:** Pagination Correctness  
**Location:** Line 115

**Evidence:**
```php
$query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc');
```

**Problem:**
Sorting only by `name`, `capacity`, or `created_at` can produce unstable ordering when multiple rows share the same value.

**Why this matters:**
Paginated results can show duplicate or missing records across pages when data changes or ties exist.

**How to fix:**
Add a deterministic secondary sort.

```php
$query->orderBy($sortBy, $direction)->orderBy('id');
```

---

### Issue #15: `getById()` Loads All Seats Without Pagination or Column Selection

**Severity:** 🟡 MEDIUM  
**Category:** Performance / API Design  
**Location:** Lines 30-32

**Evidence:**
```php
return Screen::with(['theater', 'format', 'sound', 'seats'])->findOrFail($id);
```

**Problem:**
The detail endpoint loads every seat for the screen.

**Why this matters:**
Large screens may have hundreds of seats. If seat relationship includes unnecessary columns or nested relationships later, the payload grows quickly. It also mixes screen metadata with full seat inventory.

**How to fix:**
Use selective columns or a dedicated seat layout endpoint.

```php
Screen::with([
    'theater:id,name',
    'format:id,name',
    'sound:id,name',
    'seats:id,screen_id,row_label,seat_number,type,status',
])->findOrFail($id);
```

---

### Issue #16: No Logging or Audit Trail for Screen Mutations

**Severity:** 🟡 MEDIUM  
**Category:** Logging / Auditability  
**Location:** Lines 38-65

**Evidence:**
```php
public function create(array $data): Screen
...
public function update(int $id, array $data): Screen
...
public function delete(int $id): bool
```

**Problem:**
Create, update, and delete operations do not emit any audit log.

**Why this matters:**
Screen configuration affects seat availability, showtime assignment, and booking behavior. Production systems need traceability for who changed screen metadata or removed/deactivated a screen.

**How to fix:**
Add structured audit logging around state changes. Prefer domain/audit events over ad-hoc logs.

---

### Issue #17: No Authorization Boundary in Service

**Severity:** 🟡 MEDIUM  
**Category:** Authorization / Architecture  
**Location:** Lines 38-65

**Evidence:**
```php
public function create(array $data): Screen
public function update(int $id, array $data): Screen
public function delete(int $id): bool
```

**Problem:**
The service performs administrative mutations without accepting an actor or enforcing policy checks.

**Why this matters:**
Authorization may exist in controllers, but the service itself is reusable. If called from another path, screen mutations can bypass authorization.

**How to fix:**
Ensure controllers enforce policies consistently, or pass the actor/context and use policy checks before mutation.

```php
Gate::authorize('update', $screen);
```

---

### Issue #18: Business Logic Is Too Thin for a Critical Domain Entity

**Severity:** 🟡 MEDIUM  
**Category:** Architecture / Business Logic  
**Location:** Lines 38-65

**Evidence:**
```php
$screen = Screen::create($data);
$screen->update($data);
return (bool) $screen->delete();
```

**Problem:**
The service is mostly a CRUD wrapper and does not enforce screen-domain invariants.

**Why this matters:**
Screens are not simple records. They are tied to seats, layouts, showtimes, booking capacity, and theater operations. A production service should enforce invariants around capacity, layout changes, active showtimes, and deletion.

**How to fix:**
Introduce explicit domain methods:
- `createScreenWithLayout()`
- `updateScreenConfiguration()`
- `deactivateScreen()`
- `assertNoFutureShowtimesForStructuralChange()`

---

## Recommendations

### IMMEDIATE

1. **Block Unsafe Screen Deletion** - Do not delete screens with seats/showtimes/bookings; use deactivate/archive workflow
2. **Whitelist Create/Update Payloads** - Avoid raw mass assignment
3. **Add Business Rules for Capacity/Layout Changes** - Keep screen capacity and seats consistent
4. **Prevent Structural Changes When Future Showtimes Exist**
5. **Clamp `per_page`** - Prevent oversized eager-loaded responses

### SHORT TERM

6. **Remove HTTP Request Dependency from Service** - Accept validated filter arrays or DTOs
7. **Escape LIKE Wildcards** - Prevent wildcard abuse
8. **Reject Invalid Status Values** - Do not silently broaden results
9. **Add Deterministic Secondary Sort**
10. **Add Audit Logging for Create/Update/Delete**

### LONG TERM

11. **Split Screen Metadata and Seat Layout APIs** - Avoid loading full seats in generic detail responses
12. **Introduce Screen Domain Service Rules** - Model structural changes explicitly
13. **Add Tests for Delete Dependencies, Capacity/Seat Consistency, Future Showtime Restrictions, Pagination Limits**
14. **Use Policies or Actor Context for Admin Mutations**

---

## Improved Version Snippet

```php
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

public function getAll(array $filters): LengthAwarePaginator
{
    $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 50);

    $status = $filters['status'] ?? 'active';
    if (!in_array($status, ['active', 'inactive', 'all'], true)) {
        throw ValidationException::withMessages([
            'status' => 'Invalid screen status filter.',
        ]);
    }

    return Screen::query()
        ->with(['theater:id,name', 'format:id,name', 'sound:id,name'])
        ->when(!empty($filters['theater_id']), fn ($q) => $q->where('theater_id', (int) $filters['theater_id']))
        ->when(!empty($filters['format_id']), fn ($q) => $q->where('format_id', (int) $filters['format_id']))
        ->when($status === 'active', fn ($q) => $q->active())
        ->when($status === 'inactive', fn ($q) => $q->where('status', 0))
        ->orderBy($filters['sort_by'] ?? 'name', ($filters['sort_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc')
        ->orderBy('id')
        ->paginate($perPage)
        ->withQueryString();
}

public function update(int $id, array $data): Screen
{
    return DB::transaction(function () use ($id, $data): Screen {
        $screen = Screen::query()->lockForUpdate()->findOrFail($id);

        $payload = Arr::only($data, [
            'name',
            'code',
            'format_id',
            'sound_id',
            'screen_type',
            'status',
        ]);

        $structuralFields = array_intersect(array_keys($payload), ['format_id', 'sound_id', 'screen_type']);
        if ($structuralFields && $screen->showtimes()->where('scheduled_at', '>=', now())->exists()) {
            throw ValidationException::withMessages([
                'screen' => 'Cannot modify screen configuration while future showtimes exist.',
            ]);
        }

        $screen->update($payload);

        return $screen->load(['theater', 'format', 'sound']);
    });
}

public function delete(int $id): bool
{
    return DB::transaction(function () use ($id): bool {
        $screen = Screen::query()->lockForUpdate()->findOrFail($id);

        if ($screen->seats()->exists() || $screen->showtimes()->exists()) {
            throw ValidationException::withMessages([
                'screen' => 'Cannot delete a screen with seats or showtimes. Deactivate it instead.',
            ]);
        }

        return (bool) $screen->delete();
    });
}
```

---

## Summary

ScreenService.php is readable and has basic filtering/sorting, but it is too thin for a production cinema booking domain. Screens directly affect seat inventory, showtimes, bookings, and historical records. The current implementation treats screens as simple CRUD records and does not enforce important domain invariants.

**Strengths:**
- Simple and readable
- Eager loading used
- Sort allowlist exists
- Invalid sort field falls back safely
- Public methods are typed

**Main Gaps:**
1. Unsafe screen deletion with no dependency checks
2. Raw mass assignment in create/update
3. No validation for capacity/seat layout consistency
4. No protection against changing screen configuration with future showtimes
5. No transactions around mutations
6. Unbounded pagination
7. Service is coupled to HTTP Request
8. Invalid status filters silently broaden results
9. No audit logging for screen mutations

**Status:** 🚫 Request changes before production acceptance

---

*Review completed: 2026-07-14 12:30 PM*  
*File #32/137 - Phase 3: Business Logic (4/20 complete)*
