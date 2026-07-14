# File Review: ShowtimeService.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Services/ShowtimeService.php  
**Lines:** 295  
**Type:** Service Layer - Showtime Management

---

## File Information

**Path:** `app/Services/ShowtimeService.php`  
**Type:** Laravel Service Class  
**Lines:** 295  
**Complexity:** Medium  

**Purpose:**  
Handles showtime-related business logic:
- Lists showtimes with filters, sorting, pagination, and eager-loaded relationships
- Retrieves showtime details
- Creates, updates, and deletes showtimes
- Retrieves public movie showtimes grouped by theater and format
- Adds computed start/end time fields

**Business Impact:** 🔴 CRITICAL - Directly affects scheduling, seat availability, booking correctness, and customer-facing showtime data

---

## Overall Score

**Code Quality:** 6.0/10  
**Security:** 5.8/10  
**Performance:** 6.1/10  
**Maintainability:** 5.9/10  
**Laravel Best Practice:** 5.6/10  

**Overall Score:** 5.9/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

1. ✅ **Eager Loading Used Extensively** - Main listing and detail endpoints avoid obvious N+1 relationship loading
2. ✅ **Sort Field Allowlist Exists** - `applySorting()` restricts sort columns to `scheduled_at` and `created_at`
3. ✅ **Public Showtime Query Excludes Inactive/Past Showtimes** - `getFilteredShowtimes()` filters `status = 1` and future dates
4. ✅ **Typed Method Signatures Used in Several Places** - `string|int`, `LengthAwarePaginator`, `Collection`, `Showtime`, and `Builder` are used
5. ✅ **Grouping Logic Is Isolated** - Theater/format grouping is separated into a private method
6. ✅ **Computed End Time Provided** - Adds useful client-facing showtime timing metadata

---

## Issues Found

### Issue #1: Create Path Has No Transaction, Conflict Detection, or Schedule Overlap Validation

**Severity:** 🔴 CRITICAL  
**Category:** Business Logic / Database Correctness / Concurrency  
**Location:** Lines 61-66

**Evidence:**
```php
public function create(array $data): Showtime
{
    $showtime = Showtime::create($data);
    $showtime->load(['movie', 'screen', 'screen.theater', 'format']);
    return $showtime;
}
```

**Problem:**
The service creates showtimes directly without checking whether the selected screen already has another showtime overlapping the new showtime's scheduled time and movie duration.

There is no transaction, no row lock, no overlap query, and no business rule enforcement.

**Why this matters:**
A cinema screen cannot run two movies at the same time. This can cause:
- overlapping showtimes in the same screen
- invalid seat maps for simultaneous sessions
- customers booking seats for impossible schedules
- operational and financial issues

This is production-blocking for a cinema booking system.

**How to fix:**
Validate schedule conflicts before creating. Use a transaction and lock relevant screen/showtime rows.

**Example:**
```php
return DB::transaction(function () use ($data): Showtime {
    $movie = Movie::query()->findOrFail($data['movie_id']);
    $start = Carbon::parse($data['scheduled_at']);
    $end = $start->copy()->addMinutes($movie->duration);

    $conflict = Showtime::query()
        ->where('screen_id', $data['screen_id'])
        ->where('status', 1)
        ->where(function ($query) use ($start, $end) {
            $query->whereBetween('scheduled_at', [$start, $end])
                ->orWhereRaw('DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > ?', [$start]);
        })
        ->lockForUpdate()
        ->exists();

    if ($conflict) {
        throw ValidationException::withMessages([
            'scheduled_at' => 'Showtime overlaps with another showtime in this screen.',
        ]);
    }

    return Showtime::create($data);
});
```

The exact overlap logic should use persisted showtime duration or join movie duration reliably.

---

### Issue #2: Update Path Can Create Overlapping Showtimes

**Severity:** 🔴 CRITICAL  
**Category:** Business Logic / Database Correctness / Concurrency  
**Location:** Lines 71-76

**Evidence:**
```php
public function update(int $id, array $data): Showtime
{
    $showtime = Showtime::findOrFail($id);
    $showtime->update($data);
    $showtime->load(['movie', 'screen', 'screen.theater', 'format']);
    return $showtime;
}
```

**Problem:**
Updating a showtime can change `screen_id`, `movie_id`, `scheduled_at`, `status`, or format/version fields without validating conflicts.

**Why this matters:**
Even if create validation existed elsewhere, update can bypass scheduling constraints and create invalid operational schedules.

**How to fix:**
Apply the same overlap validation on update, excluding the current showtime ID.

**Example:**
```php
$conflict = Showtime::query()
    ->whereKeyNot($showtime->id)
    ->where('screen_id', $screenId)
    ->where('status', 1)
    ->where(/* overlap condition */)
    ->lockForUpdate()
    ->exists();
```

---

### Issue #3: Create and Update Mass Assign Raw `$data`

**Severity:** 🟠 HIGH  
**Category:** Security - Mass Assignment / Data Integrity  
**Location:** Lines 61-64 and 71-74

**Evidence:**
```php
$showtime = Showtime::create($data);
```

```php
$showtime->update($data);
```

**Problem:**
The service accepts generic arrays and passes them directly to Eloquent mass assignment. There is no service-level allowlist.

**Why this matters:**
If a controller passes `$request->all()` or validation changes later, unintended columns can be written if the model permits them. Showtime records are business-critical because they drive booking, pricing, and seat availability.

**How to fix:**
Whitelist fields at the service boundary.

**Example:**
```php
$payload = Arr::only($data, [
    'movie_id',
    'screen_id',
    'format_id',
    'version_type_id',
    'scheduled_at',
    'price',
    'status',
    'seat_layout_snapshot_id',
]);

$showtime = Showtime::create($payload);
```

---

### Issue #4: Delete Allows Removing Showtimes Without Checking Existing Bookings, Orders, Seats, or Tickets

**Severity:** 🔴 CRITICAL  
**Category:** Business Logic / Data Integrity  
**Location:** Lines 82-85

**Evidence:**
```php
public function delete(int $id): bool
{
    $showtime = Showtime::findOrFail($id);
    return (bool) $showtime->delete();
}
```

**Problem:**
The service deletes a showtime without checking whether it already has bookings, seat holds, tickets, paid orders, or historical transactions.

**Why this matters:**
Deleting a showtime with bookings can:
- orphan booking/order/ticket records
- break customer order history
- invalidate paid tickets
- cause refund/accounting discrepancies
- violate foreign key constraints

**How to fix:**
Do not hard-delete showtimes once bookings or tickets exist. Use cancellation/status workflow.

**Example:**
```php
if ($showtime->orders()->exists() || $showtime->tickets()->exists()) {
    throw ValidationException::withMessages([
        'showtime' => 'Cannot delete a showtime with existing bookings or tickets.',
    ]);
}

$showtime->update(['status' => ShowtimeStatus::CANCELLED]);
```

---

### Issue #5: Delete Is Not Transactional and Has No Audit Logging

**Severity:** 🟠 HIGH  
**Category:** Database Correctness / Auditability  
**Location:** Lines 82-85

**Evidence:**
```php
$showtime = Showtime::findOrFail($id);
return (bool) $showtime->delete();
```

**Problem:**
Deletion is not wrapped in a transaction and does not create any audit log.

**Why this matters:**
Showtime deletion/cancellation is operationally sensitive. Production systems need traceability for who changed/cancelled a showtime, when, and why.

**How to fix:**
Wrap state changes in a transaction and record audit data.

---

### Issue #6: `per_page` Is Unbounded

**Severity:** 🟠 HIGH  
**Category:** Performance / Abuse Control  
**Location:** Lines 33-34

**Evidence:**
```php
$perPage = (int) $request->query('per_page', 15);
$showtimes = $query->paginate($perPage);
```

**Problem:**
The client controls page size without a maximum. Large `per_page` values can cause heavy queries and large responses.

**Why this matters:**
Showtime listings include many eager-loaded relations. Unbounded pagination can become a denial-of-service vector.

**How to fix:**
Clamp page size.

```php
$perPage = min(max((int) $request->query('per_page', 15), 1), 50);
```

---

### Issue #7: Service Layer Depends Directly on `Illuminate\Http\Request`

**Severity:** 🟡 MEDIUM  
**Category:** Architecture / Testability / Clean Code  
**Location:** Lines 11, 18, 213, 265

**Evidence:**
```php
use Illuminate\Http\Request;
```

```php
public function getAll(Request $request): LengthAwarePaginator
```

```php
private function applyFilters(Builder $query, Request $request): Builder
```

**Problem:**
The service is tightly coupled to the HTTP layer. Services should receive validated data or DTOs, not framework request objects.

**Why this matters:**
This makes the service harder to test, reuse from jobs/commands, and reason about. It also blurs controller/service responsibilities.

**How to fix:**
Accept an array or DTO.

```php
public function getAll(array $filters): LengthAwarePaginator
```

Controllers/FormRequests should extract validated query parameters before calling the service.

---

### Issue #8: Filtering Uses Raw Request Values Without Type Normalization

**Severity:** 🟡 MEDIUM  
**Category:** Validation / Data Correctness  
**Location:** Lines 215-240

**Evidence:**
```php
$query->where('movie_id', $request->movie_id);
$query->where('screen_id', $request->screen_id);
$query->whereHas('screen', fn ($q) => $q->where('theater_id', $request->theater_id));
$query->whereDate('scheduled_at', $request->date);
$query->whereDate('scheduled_at', '>=', $request->date_from);
$query->whereDate('scheduled_at', '<=', $request->date_to);
```

**Problem:**
The service assumes request values are valid integers/dates. There is no normalization at this layer.

**Why this matters:**
Invalid IDs/dates can produce unexpected query behavior or database errors if controller validation is missed or reused incorrectly.

**How to fix:**
Use FormRequest validation and pass validated data to the service, or normalize defensively inside the service.

---

### Issue #9: Search Keyword Is Used in LIKE Without Escaping Wildcards

**Severity:** 🟡 MEDIUM  
**Category:** Performance / Query Correctness  
**Location:** Lines 254-257

**Evidence:**
```php
if ($request->filled('q')) {
    $search = $request->q;
    $query->whereHas('movie', fn ($q) => $q->where('title', 'like', "%{$search}%"));
}
```

**Problem:**
The keyword is used directly in a LIKE pattern without escaping `%`, `_`, or backslash.

**Why this matters:**
This allows wildcard abuse and broad scans. Parameter binding protects from classic SQL injection, but not wildcard injection.

**How to fix:**
Escape LIKE wildcards and cap search length.

```php
$search = addcslashes(trim(mb_substr($request->query('q'), 0, 100)), '\\%_');
$query->whereHas('movie', fn ($q) => $q->where('title', 'like', "%{$search}%"));
```

---

### Issue #10: `whereDate()` Filters Can Prevent Index-Efficient Range Queries

**Severity:** 🟡 MEDIUM  
**Category:** Performance / Database Correctness  
**Location:** Lines 231-240

**Evidence:**
```php
$query->whereDate('scheduled_at', $request->date);
$query->whereDate('scheduled_at', '>=', $request->date_from);
$query->whereDate('scheduled_at', '<=', $request->date_to);
```

**Problem:**
`whereDate()` applies a date extraction function to the column in SQL, which can reduce index usage on `scheduled_at`.

**Why this matters:**
Showtime queries are likely frequent and time-range based. Poor date filtering can become slow as showtimes grow.

**How to fix:**
Use datetime ranges.

```php
$query->whereBetween('scheduled_at', [
    Carbon::parse($date)->startOfDay(),
    Carbon::parse($date)->endOfDay(),
]);
```

For `date_from`/`date_to`, use start/end of day boundaries.

---

### Issue #11: Default `upcoming=true` Silently Hides Past Showtimes in Admin-Like Listing

**Severity:** 🟡 MEDIUM  
**Category:** API Consistency / Business Logic  
**Location:** Lines 250-252

**Evidence:**
```php
if ($request->boolean('upcoming', true)) {
    $query->where('scheduled_at', '>=', now());
}
```

**Problem:**
The default behavior hides past showtimes unless the client explicitly passes `upcoming=false`.

**Why this matters:**
The docblock says this is for "admin/catalog". Admin listing often needs historical showtimes for investigation, reporting, refunds, and support. A hidden default filter can confuse consumers.

**How to fix:**
Separate public catalog and admin listing methods or make the default explicit per endpoint.

---

### Issue #12: Public Showtime Filtering Does Not Match Stated 20-Minute Grace Period

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Requirement Mismatch  
**Location:** Lines 89-91 and 118-131

**Evidence:**
```php
/**
 * Get showtimes for a movie by slug or ID, grouped by theater then format.
 * Only shows next 5 days, excludes showtimes that started more than 20min ago.
 */
```

```php
$now = Carbon::now();
...
->where('scheduled_at', '>', $now)
```

**Problem:**
The comment says showtimes that started more than 20 minutes ago should be excluded, which implies showtimes started within the last 20 minutes may still be shown. The code excludes all showtimes before now.

**Why this matters:**
The implementation does not match the documented business rule. This can create inconsistent customer experience and confusion around late ticket purchase rules.

**How to fix:**
Either update the comment or implement the rule.

```php
$cutoff = Carbon::now()->subMinutes(20);
$query->where('scheduled_at', '>', $cutoff);
```

Only do this if late booking is actually allowed.

---

### Issue #13: Public Query Uses Magic Number `5` for Display Window

**Severity:** 🔵 LOW  
**Category:** Maintainability / Configuration  
**Location:** Lines 118-120

**Evidence:**
```php
$endDate = $now->copy()->addDays(5)->endOfDay();
```

**Problem:**
The public showtime display window is hard-coded to 5 days.

**Why this matters:**
Business rules like schedule visibility windows usually change. Hard-coding makes configuration and testing harder.

**How to fix:**
Move to config.

```php
$days = (int) config('cinema.showtime_public_days', 5);
$endDate = now()->addDays($days)->endOfDay();
```

---

### Issue #14: Grouping Assumes Required Relationships Always Exist

**Severity:** 🟡 MEDIUM  
**Category:** Correctness / Null Safety  
**Location:** Lines 143-154 and 172-179

**Evidence:**
```php
$theaterId = $showtime->screen->theater_id;
```

```php
'id' => $showtime->screen->theater->id,
'name' => $showtime->screen->theater->name,
```

```php
'id' => $showtime->screen->id,
'name' => $showtime->screen->name,
```

**Problem:**
The code dereferences `screen` and `screen->theater` without null checks.

**Why this matters:**
If data integrity is imperfect, a deleted/missing screen/theater can cause a fatal error in the public showtime API.

**How to fix:**
Enforce foreign keys and required relationships at the database level. Also use defensive filtering or fail explicitly.

```php
if (!$showtime->screen || !$showtime->screen->theater) {
    continue; // or throw domain exception
}
```

---

### Issue #15: Inline Fully Qualified `Str::slug()` Indicates Missing Import and Inconsistent Style

**Severity:** 🔵 LOW  
**Category:** Clean Code / Readability  
**Location:** Line 164

**Evidence:**
```php
'slug' => $showtime->format?->name ? \Illuminate\Support\Str::slug($showtime->format->name) : 'standard',
```

**Problem:**
The class uses a fully-qualified class name inline instead of importing `Illuminate\Support\Str`.

**Why this matters:**
This reduces readability and is inconsistent with normal Laravel style.

**How to fix:**
```php
use Illuminate\Support\Str;
```

Then:

```php
'slug' => $showtime->format?->name ? Str::slug($showtime->format->name) : 'standard',
```

---

### Issue #16: `enrichShowtime()` Mutates Eloquent Models With Dynamic Properties

**Severity:** 🟡 MEDIUM  
**Category:** API Design / Maintainability  
**Location:** Lines 283-293

**Evidence:**
```php
$showtime->start_time = $showtime->scheduled_at
    ? $showtime->scheduled_at->format('Y-m-d H:i:s')
    : null;

$showtime->end_time_estimated = $showtime->scheduled_at && $showtime->movie
    ? $showtime->scheduled_at->copy()->addMinutes($showtime->movie->duration)->format('Y-m-d H:i:s')
    : null;

return $showtime;
```

**Problem:**
The service mutates Eloquent model instances by attaching dynamic response-only properties.

**Why this matters:**
This couples presentation concerns into the service/model layer and can produce inconsistent serialization. Laravel API Resources or model accessors are cleaner and more predictable.

**How to fix:**
Use API Resources for response shaping or append accessors intentionally.

---

### Issue #17: No Cache Invalidation for Movie Detail Cache Despite Showtime Changes

**Severity:** 🟡 MEDIUM  
**Category:** Cache Correctness / Architecture  
**Location:** Lines 61-85

**Evidence:**
```php
public function create(array $data): Showtime
{
    $showtime = Showtime::create($data);
    ...
}
```

```php
public function update(int $id, array $data): Showtime
{
    ...
    $showtime->update($data);
    ...
}
```

```php
public function delete(int $id): bool
{
    $showtime = Showtime::findOrFail($id);
    return (bool) $showtime->delete();
}
```

**Problem:**
This service changes showtimes, but there is no cache invalidation. `MovieService::getMovie()` caches movies with `showtimes.screen.theater`. Showtime changes can leave cached movie detail responses stale.

**Why this matters:**
Customers may see stale schedules after showtime creation/update/deletion.

**How to fix:**
Centralize cache invalidation and clear affected movie keys after committed showtime changes.

---

## Recommendations

### IMMEDIATE

1. **Add Screen Schedule Conflict Validation** - Prevent overlapping showtimes on create/update
2. **Use Transactions and Row Locks for Schedule Changes** - Avoid concurrent conflicting showtime creation
3. **Block Deletion of Booked/Paid Showtimes** - Use cancel/archive workflow instead
4. **Whitelist Create/Update Payload Fields** - Avoid raw mass assignment
5. **Clamp `per_page`** - Prevent oversized eager-loaded responses

### SHORT TERM

6. **Remove HTTP Request Dependency from Service** - Accept validated arrays/DTOs
7. **Escape LIKE Search Wildcards** - Prevent wildcard abuse
8. **Replace `whereDate()` With Range Queries** - Preserve index efficiency
9. **Fix 20-Minute Grace Period Mismatch** - Align code and documented business rule
10. **Invalidate Movie/Showtime Caches After Schedule Changes**

### LONG TERM

11. **Use API Resources for Response Shaping** - Stop mutating Eloquent models dynamically
12. **Introduce Showtime Domain Rules/Policy Layer** - Centralize scheduling, cancellation, and booking constraints
13. **Add Tests for Overlap, Concurrent Create, Delete With Bookings, Pagination Limits**
14. **Move Business Constants to Config** - Public visibility window, late-booking grace period, max page size

---

## Improved Version Snippet

```php
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

public function create(array $data): Showtime
{
    return DB::transaction(function () use ($data): Showtime {
        $payload = Arr::only($data, [
            'movie_id',
            'screen_id',
            'format_id',
            'version_type_id',
            'scheduled_at',
            'price',
            'status',
            'seat_layout_snapshot_id',
        ]);

        $this->assertNoScheduleConflict($payload);

        $showtime = Showtime::create($payload);

        DB::afterCommit(function () use ($showtime): void {
            // Forget movie/showtime cache keys here.
        });

        return $showtime->load(['movie', 'screen', 'screen.theater', 'format']);
    });
}

public function delete(int $id): bool
{
    return DB::transaction(function () use ($id): bool {
        $showtime = Showtime::query()
            ->lockForUpdate()
            ->findOrFail($id);

        if ($showtime->orders()->exists() || $showtime->tickets()->exists()) {
            throw ValidationException::withMessages([
                'showtime' => 'Cannot delete a showtime with existing bookings or tickets.',
            ]);
        }

        return (bool) $showtime->delete();
    });
}

public function getAll(array $filters): LengthAwarePaginator
{
    $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 50);

    return Showtime::query()
        ->with([...])
        ->paginate($perPage);
}
```

---

## Summary

ShowtimeService.php has a useful structure and decent eager loading, but it is not production-ready for a cinema booking domain. Showtime creation, update, and deletion are business-critical operations and currently lack the core safeguards needed to prevent invalid schedules and destructive changes to booked sessions.

**Strengths:**
- Eager loading is used on listing/detail paths
- Sorting allowlist exists
- Public movie showtimes are grouped clearly
- Public query excludes inactive and future-out-of-window showtimes
- Several method signatures are typed

**Main Gaps:**
1. No overlap/conflict validation when creating or updating showtimes
2. No transaction/locking around schedule mutations
3. Raw mass assignment on create/update
4. Showtime deletion ignores existing bookings/tickets/orders
5. Unbounded pagination
6. Service is coupled to HTTP Request objects
7. Public documented 20-minute rule does not match implementation
8. Showtime changes do not invalidate cached movie details

**Status:** 🚫 Request changes before production acceptance

---

*Review completed: 2026-07-14 12:15 PM*  
*File #30/137 - Phase 3: Business Logic (2/20 complete)*
