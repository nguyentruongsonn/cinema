# File Review: TheaterService.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Services/TheaterService.php  
**Lines:** 257  
**Type:** Service Layer - Theater Management

---

## File Information

**Path:** `app/Services/TheaterService.php`  
**Type:** Laravel Service Class  
**Lines:** 257  
**Complexity:** Low-Medium  

**Purpose:**  
Handles theater-related business logic:
- Lists theaters with search, branch/city/status filters, sorting, and pagination
- Retrieves branch/city values from active theaters
- Creates, updates, and deletes theaters
- Retrieves theater details and theater screens

**Business Impact:** 🟠 HIGH - Affects cinema locations, screens, scheduling dependencies, and public catalog availability

---

## Overall Score

**Code Quality:** 6.1/10  
**Security:** 5.7/10  
**Performance:** 5.8/10  
**Maintainability:** 6.0/10  
**Laravel Best Practice:** 5.8/10  

**Overall Score:** 5.9/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

1. ✅ **Service Layer Exists** - Theater-related logic is separated from controllers
2. ✅ **Basic Eager Loading Present** - `screens`, `branch`, `format`, and `sound` are eager-loaded where needed
3. ✅ **Sort Column Allowlist Exists** - `sort_by` is restricted to `name`, `branch_id`, and `created_at`
4. ✅ **Status Filtering Exists** - Theater list supports active/inactive filtering
5. ✅ **Logging Exists for Write Operations** - Create/update/delete operations emit logs
6. ✅ **Typed Return Values for Main Methods** - Most public methods return `Theater`, `bool`, or `LengthAwarePaginator`

---

## Issues Found

### Issue #1: Create Path Mass Assigns Entire `$data`

**Severity:** 🟠 HIGH  
**Category:** Security - Mass Assignment / Data Integrity  
**Location:** Lines 119-123

**Evidence:**
```php
public function createTheater(array $data): Theater
{
    try {
        $theater = Theater::create($data);
```

**Problem:**
The service accepts a generic array and passes it directly into `Theater::create()`.

There is no service-level allowlist for theater fields.

**Why this matters:**
If a controller accidentally passes `$request->all()` or validation changes later, unintended fields can be persisted if the model allows them. Theater records affect public discovery, scheduling, branch assignment, and screen hierarchy.

**How to fix:**
Whitelist fields before persistence.

**Example:**
```php
use Illuminate\Support\Arr;

$payload = Arr::only($data, [
    'branch_id',
    'name',
    'address',
    'phone',
    'email',
    'description',
    'status',
]);

$theater = Theater::create($payload);
```

---

### Issue #2: Update Path Mass Assigns Entire `$data`

**Severity:** 🟠 HIGH  
**Category:** Security - Mass Assignment / Data Integrity  
**Location:** Lines 205-210

**Evidence:**
```php
public function updateTheater(int $id, array $data): Theater
{
    try {
        $theater = Theater::findOrFail($id);
        $theater->update($data);
```

**Problem:**
The update method writes the full input array directly to the model.

**Why this matters:**
Theater update endpoints are often admin-only but still require strict persistence boundaries. Unintended updates to relational keys or status fields can hide theaters, move theaters between branches, or corrupt scheduling relationships.

**How to fix:**
Use an explicit allowlist for update payloads.

**Example:**
```php
$payload = Arr::only($data, [
    'branch_id',
    'name',
    'address',
    'phone',
    'email',
    'description',
    'status',
]);

$theater->update($payload);
```

---

### Issue #3: Delete Allows Removing Theater Without Checking Screens, Showtimes, Bookings, or Orders

**Severity:** 🔴 CRITICAL  
**Category:** Business Logic / Database Correctness  
**Location:** Lines 235-242

**Evidence:**
```php
public function deleteTheater(int $id): bool
{
    try {
        $theater = Theater::findOrFail($id);
        $name = $theater->name;

        $theater->delete();
```

**Problem:**
The service deletes a theater without checking dependent records.

A theater can have screens, showtimes, seats, tickets, orders, or historical bookings through its screens.

**Why this matters:**
Deleting a theater with operational or historical data can:
- break showtime listings
- orphan screens or showtimes
- invalidate bookings/tickets
- violate foreign key constraints
- destroy audit/reporting history

For a cinema booking system, location deletion must be a controlled archive/deactivation workflow, not a blind delete.

**How to fix:**
Block deletion when dependencies exist and prefer status-based deactivation.

**Example:**
```php
if ($theater->screens()->exists()) {
    throw ValidationException::withMessages([
        'theater' => 'Cannot delete a theater with screens. Deactivate it instead.',
    ]);
}

$theater->delete();
```

For theaters with existing screens/showtimes, use:
```php
$theater->update(['status' => 0]);
```

---

### Issue #4: Delete Is Not Transactional

**Severity:** 🟠 HIGH  
**Category:** Database Correctness / Transaction Safety  
**Location:** Lines 235-248

**Evidence:**
```php
$theater = Theater::findOrFail($id);
$name = $theater->name;

$theater->delete();

Log::info('Theater deleted successfully', [
    'theater_id' => $id,
    'name' => $name
]);
```

**Problem:**
The delete operation is not wrapped in a transaction.

**Why this matters:**
If deletion behavior later includes relationship cleanup, audit records, cache invalidation, or screen updates, partial completion can occur. Deleting hierarchical cinema data should be atomic.

**How to fix:**
Use `DB::transaction()` for destructive or multi-step state changes.

---

### Issue #5: `per_page` Is Unbounded in Theater Listing

**Severity:** 🟠 HIGH  
**Category:** Performance / Abuse Control  
**Location:** Lines 26 and 68

**Evidence:**
```php
$perPage = $filters['per_page'] ?? 12;
```

```php
$theaters = $query->paginate($perPage)->withQueryString();
```

**Problem:**
The client controls page size without a maximum.

**Why this matters:**
The query eager-loads `screens` and `branch`. A large `per_page` can create large database queries, high memory usage, and oversized API responses.

**How to fix:**
Clamp pagination size.

**Example:**
```php
$perPage = min(max((int) ($filters['per_page'] ?? 12), 1), 50);
```

---

### Issue #6: `per_page` Is Also Unbounded in Theater Screens Listing

**Severity:** 🟠 HIGH  
**Category:** Performance / Abuse Control  
**Location:** Lines 171-181

**Evidence:**
```php
$perPage = $filters['per_page'] ?? 15;

$screens = $theater->screens()
    ->with(['format', 'sound'])
    ...
    ->paginate($perPage);
```

**Problem:**
`getTheaterScreens()` also allows unbounded page size.

**Why this matters:**
Screen listings eager-load relationships and can be abused in the same way as theater listing.

**How to fix:**
Clamp page size.

```php
$perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 50);
```

---

### Issue #7: Search Keyword Is Used in LIKE Without Escaping Wildcards

**Severity:** 🟡 MEDIUM  
**Category:** Performance / Query Correctness  
**Location:** Lines 31-39

**Evidence:**
```php
$keyword = $filters['q'];
$query->where(function ($sub) use ($keyword) {
    $sub->where('name', 'like', "%{$keyword}%")
        ->orWhere('address', 'like', "%{$keyword}%")
        ->orWhereHas('branch', function ($q) use ($keyword) {
            $q->where('name', 'like', "%{$keyword}%");
        });
});
```

**Problem:**
The search keyword is inserted into LIKE patterns without escaping `%`, `_`, or backslash.

**Why this matters:**
Parameter binding prevents classic SQL injection, but wildcard injection still allows clients to broaden searches unexpectedly and force expensive scans.

**How to fix:**
Escape LIKE wildcards and cap search length.

```php
$keyword = addcslashes(trim(mb_substr($filters['q'], 0, 100)), '\\%_');
```

---

### Issue #8: Search Uses Leading Wildcards Across Multiple Columns and Relationship

**Severity:** 🟡 MEDIUM  
**Category:** Performance  
**Location:** Lines 31-39

**Evidence:**
```php
$sub->where('name', 'like', "%{$keyword}%")
    ->orWhere('address', 'like', "%{$keyword}%")
    ->orWhereHas('branch', function ($q) use ($keyword) {
        $q->where('name', 'like', "%{$keyword}%");
    });
```

**Problem:**
Leading wildcard LIKE queries cannot efficiently use normal B-tree indexes and are combined with an `orWhereHas()` relationship condition.

**Why this matters:**
Theater listing can become slow as theater/location data grows. Public search endpoints should avoid unbounded table scans.

**How to fix:**
Use prefix matching where possible, full-text indexes, or a dedicated search index. At minimum, limit keyword length and fields searched.

---

### Issue #9: `getCities()` Loads All Active Theaters Into Memory

**Severity:** 🟡 MEDIUM  
**Category:** Performance  
**Location:** Lines 94-100

**Evidence:**
```php
$cities = Theater::active()
    ->whereHas('branch')
    ->with('branch:id,name')
    ->get()
    ->pluck('branch.name')
    ->unique()
    ->values();
```

**Problem:**
The method retrieves all active theaters and branches, then deduplicates branch names in PHP.

**Why this matters:**
This is inefficient and consumes unnecessary memory. The database should perform distinct selection.

**How to fix:**
Query branches directly or use a join/distinct query.

**Example:**
```php
return Branch::query()
    ->whereHas('theaters', fn ($q) => $q->active())
    ->orderBy('name')
    ->pluck('name');
```

If only the `Theater` model is available:
```php
return Theater::query()
    ->join('branches', 'branches.id', '=', 'theaters.branch_id')
    ->where('theaters.status', 1)
    ->distinct()
    ->orderBy('branches.name')
    ->pluck('branches.name');
```

---

### Issue #10: Status Filter Ignores Unsupported Values Instead of Failing Validation

**Severity:** 🟡 MEDIUM  
**Category:** API Consistency / Validation  
**Location:** Lines 51-57

**Evidence:**
```php
$status = $filters['status'] ?? 'active';
if ($status === 'active') {
    $query->active();
} elseif ($status === 'inactive') {
    $query->where('status', 0);
}
```

**Problem:**
Any unsupported status value silently results in no status filter.

**Why this matters:**
APIs should reject invalid filters consistently instead of returning unexpected broader data. This can expose inactive theaters if clients send an invalid status value.

**How to fix:**
Validate status in a FormRequest or normalize strictly.

```php
if (!in_array($status, ['active', 'inactive', 'all'], true)) {
    throw ValidationException::withMessages([
        'status' => 'Invalid theater status filter.',
    ]);
}
```

---

### Issue #11: Sort Allowlist Uses Non-Strict `in_array()`

**Severity:** 🔵 LOW  
**Category:** Code Quality / Correctness  
**Location:** Lines 60-65

**Evidence:**
```php
$allowedSorts = ['name', 'branch_id', 'created_at'];

if (in_array($sortBy, $allowedSorts)) {
    $query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc');
}
```

**Problem:**
`in_array()` is called without strict comparison.

**Why this matters:**
This is minor here because allowed values are strings, but strict comparison is standard defensive PHP practice.

**How to fix:**
```php
if (in_array($sortBy, $allowedSorts, true)) {
    ...
}
```

---

### Issue #12: If Sort Field Is Invalid, No Default Sort Is Applied

**Severity:** 🟡 MEDIUM  
**Category:** API Consistency / Pagination Correctness  
**Location:** Lines 60-66

**Evidence:**
```php
$sortBy = $filters['sort_by'] ?? 'name';
...
if (in_array($sortBy, $allowedSorts)) {
    $query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc');
}
```

**Problem:**
If `sort_by` is invalid, the method does not apply any ordering.

**Why this matters:**
Pagination without deterministic ordering can produce duplicate/missing items across pages when rows are inserted or updated.

**How to fix:**
Fallback to a default sort.

```php
if (!in_array($sortBy, $allowedSorts, true)) {
    $sortBy = 'name';
}

$query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc')
    ->orderBy('id');
```

---

### Issue #13: Listing Eager Loads Full `screens` Relationship

**Severity:** 🟡 MEDIUM  
**Category:** Performance / API Design  
**Location:** Line 28

**Evidence:**
```php
$query = Theater::query()->with(['screens', 'branch']);
```

**Problem:**
The theater list loads all screens for every theater.

**Why this matters:**
List endpoints should usually not return full child collections. This can cause heavy payloads and memory growth. If the UI only needs screen counts, `withCount('screens')` is cheaper.

**How to fix:**
Use selective eager loading or counts.

```php
$query = Theater::query()
    ->with('branch:id,name')
    ->withCount('screens');
```

---

### Issue #14: `getTheater()` Does Not Eager Load `branch`

**Severity:** 🔵 LOW  
**Category:** API Consistency / Performance  
**Location:** Lines 147-150

**Evidence:**
```php
$theater = Theater::with(['screens' => function ($q) {
    $q->active()->with(['format', 'sound']);
}])->findOrFail($id);
```

**Problem:**
Theater detail loads screens but not the theater branch.

**Why this matters:**
Other methods treat branch as a core theater relationship. If the response serializes branch later, it can cause lazy loading or inconsistent data across endpoints.

**How to fix:**
```php
Theater::with([
    'branch',
    'screens' => fn ($q) => $q->active()->with(['format', 'sound']),
])->findOrFail($id);
```

---

### Issue #15: Logging Full Filters and Full Create Payload Can Expose Data and Increase Log Volume

**Severity:** 🟡 MEDIUM  
**Category:** Security - Sensitive Data Exposure / Logging  
**Location:** Lines 70-74 and 130-134

**Evidence:**
```php
Log::info('Theaters retrieved', [
    'count' => $theaters->count(),
    'total' => $theaters->total(),
    'filters' => $filters
]);
```

```php
Log::error('Failed to create theater', [
    'data' => $data,
    'error' => $e->getMessage()
]);
```

**Problem:**
The service logs full filter arrays and full create payloads.

**Why this matters:**
Request payloads can contain untrusted or sensitive operational data. Logging full data also increases log volume and makes production logs noisy.

**How to fix:**
Log only safe metadata.

```php
Log::error('Failed to create theater', [
    'name' => $data['name'] ?? null,
    'branch_id' => $data['branch_id'] ?? null,
    'exception' => $e,
]);
```

---

### Issue #16: Read Path Logs Successful Requests at `info` Level

**Severity:** 🔵 LOW  
**Category:** Observability / Performance  
**Location:** Lines 70-74, 102, 152, 183-186

**Evidence:**
```php
Log::info('Theaters retrieved', [
    'count' => $theaters->count(),
    'total' => $theaters->total(),
    'filters' => $filters
]);
```

```php
Log::info('Cities retrieved', ['count' => $cities->count()]);
```

**Problem:**
Normal successful read operations are logged at `info` level.

**Why this matters:**
Public/catalog read paths can be high traffic. Info logs for every successful read increase cost and reduce signal-to-noise.

**How to fix:**
Use debug logs or metrics for read-path observability.

---

### Issue #17: Catch-and-Rethrow Blocks Add Noise and Risk Duplicate Logging

**Severity:** 🔵 LOW  
**Category:** Clean Code / Maintainability  
**Location:** Lines 25-83, 93-110, 121-136, 147-157, 170-195, 207-225, 237-254

**Evidence:**
```php
} catch (\Exception $e) {
    Log::error('Failed to retrieve theaters', [
        'filters' => $filters,
        'error' => $e->getMessage()
    ]);
    throw $e;
}
```

**Problem:**
Most methods catch `\Exception`, log, then rethrow the same exception.

**Why this matters:**
This creates repetitive boilerplate and can duplicate logs if Laravel's global exception handler also logs the exception. It also logs less diagnostic detail than passing the exception object.

**How to fix:**
Only catch exceptions when converting to domain exceptions or adding meaningful context. Otherwise rely on centralized exception handling.

---

### Issue #18: Exception Logs Do Not Include Exception Object/Class

**Severity:** 🔵 LOW  
**Category:** Observability / Maintainability  
**Location:** Lines 77-81, 105-108, 130-134, 220-224, 249-253

**Evidence:**
```php
Log::error('Failed to update theater', [
    'theater_id' => $id,
    'error' => $e->getMessage()
]);
```

**Problem:**
Only the exception message is logged. The exception class, code, and stack trace are not explicitly included.

**Why this matters:**
Production debugging requires distinguishing validation failures, missing records, database errors, and unexpected failures.

**How to fix:**
Pass the exception object.

```php
Log::error('Failed to update theater', [
    'theater_id' => $id,
    'exception' => $e,
]);
```

---

### Issue #19: `getCities()` Has No Declared Return Type

**Severity:** 🔵 LOW  
**Category:** Type Safety / Clean Code  
**Location:** Lines 91-104

**Evidence:**
```php
public function getCities()
{
    ...
    return $cities;
}
```

**Problem:**
The method does not declare a return type.

**Why this matters:**
Explicit return types improve static analysis and prevent accidental return contract changes.

**How to fix:**
```php
use Illuminate\Support\Collection;

public function getCities(): Collection
```

---

### Issue #20: Status Filtering in `getTheaterScreens()` Cannot Request Inactive Screens Explicitly

**Severity:** 🟡 MEDIUM  
**Category:** API Consistency / Business Logic  
**Location:** Lines 174-181

**Evidence:**
```php
->when(!empty($filters['status']), function ($q) use ($filters) {
    if ($filters['status'] === 'active') {
        $q->active();
    }
})
```

**Problem:**
Only `active` status is handled. Passing `inactive` does nothing and returns all screens.

**Why this matters:**
This is inconsistent with `getTheaters()` and can cause admin views to return misleading data.

**How to fix:**
Handle all supported values strictly.

```php
if (($filters['status'] ?? null) === 'active') {
    $query->active();
} elseif (($filters['status'] ?? null) === 'inactive') {
    $query->where('status', 0);
}
```

Invalid values should be rejected by validation.

---

## Recommendations

### IMMEDIATE

1. **Block Dangerous Theater Deletion** - Prevent deleting theaters with screens/showtimes/bookings; use deactivate/archive workflow
2. **Whitelist Create/Update Fields** - Do not mass assign raw `$data`
3. **Clamp `per_page` Values** - Protect both theater and screen list endpoints
4. **Fix Status Filter Semantics** - Reject invalid statuses and handle inactive screens correctly

### SHORT TERM

5. **Escape LIKE Wildcards** - Prevent wildcard abuse in search
6. **Avoid Loading All Screens on Theater List** - Use `withCount()` or selective columns
7. **Optimize `getCities()`** - Use database-level distinct branch selection
8. **Apply Deterministic Default Sorting** - Always sort paginated queries
9. **Improve Logging Hygiene** - Avoid logging full payloads/filters and use exception objects

### LONG TERM

10. **Introduce Theater Domain Rules** - Centralize rules for deletion, deactivation, and dependency checks
11. **Use DTOs for Theater Create/Update** - Improve service boundaries
12. **Add Tests for Delete Dependencies, Pagination Limits, Status Filters, and Search Behavior**
13. **Add Audit Logging for Theater State Changes** - Especially deactivate/delete operations

---

## Improved Version Snippet

```php
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

public function getTheaters(array $filters): LengthAwarePaginator
{
    $perPage = min(max((int) ($filters['per_page'] ?? 12), 1), 50);

    $sortBy = $filters['sort_by'] ?? 'name';
    $allowedSorts = ['name', 'branch_id', 'created_at'];

    if (!in_array($sortBy, $allowedSorts, true)) {
        $sortBy = 'name';
    }

    $sortDir = ($filters['sort_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

    return Theater::query()
        ->with('branch:id,name')
        ->withCount('screens')
        ->when(!empty($filters['q']), function ($query) use ($filters): void {
            $keyword = addcslashes(trim(mb_substr($filters['q'], 0, 100)), '\\%_');

            $query->where(function ($sub) use ($keyword): void {
                $sub->where('name', 'like', "%{$keyword}%")
                    ->orWhere('address', 'like', "%{$keyword}%")
                    ->orWhereHas('branch', fn ($q) => $q->where('name', 'like', "%{$keyword}%"));
            });
        })
        ->orderBy($sortBy, $sortDir)
        ->orderBy('id')
        ->paginate($perPage)
        ->withQueryString();
}

public function createTheater(array $data): Theater
{
    $payload = Arr::only($data, [
        'branch_id',
        'name',
        'address',
        'phone',
        'email',
        'description',
        'status',
    ]);

    return Theater::create($payload);
}

public function deleteTheater(int $id): bool
{
    return DB::transaction(function () use ($id): bool {
        $theater = Theater::query()
            ->withCount('screens')
            ->lockForUpdate()
            ->findOrFail($id);

        if ($theater->screens_count > 0) {
            throw ValidationException::withMessages([
                'theater' => 'Cannot delete a theater with screens. Deactivate it instead.',
            ]);
        }

        return (bool) $theater->delete();
    });
}
```

---

## Summary

TheaterService.php is structurally straightforward and readable, but it is not production-ready for a cinema booking system without stricter data integrity and performance safeguards.

**Strengths:**
- Clear service abstraction
- Basic eager loading
- Sort allowlist exists
- Status filter exists for theater listing
- Write operations are logged

**Main Gaps:**
1. Raw mass assignment in create/update
2. Dangerous delete with no dependency checks
3. Unbounded pagination in two methods
4. Inefficient `getCities()` implementation
5. Search wildcard abuse and expensive leading wildcard queries
6. Full screens eager-loaded in theater listing
7. Inconsistent status filter handling
8. Excessive catch/rethrow logging boilerplate

**Status:** 🚫 Request changes before production acceptance

---

*Review completed: 2026-07-14 12:22 PM*  
*File #31/137 - Phase 3: Business Logic (3/20 complete)*