# File Review: Admin/ScreenController.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Controllers/Admin/ScreenController.php  
**Lines:** 233  
**Type:** Admin Screen / Seat Layout / Format Management Controller

---

## File Summary

`Admin\ScreenController` manages screen listing, screen creation/update/deletion, screen activation toggling, seat display/update, format CRUD, and internal seat generation from seat layout templates. It uses a mix of FormRequests and raw `Request`, direct Eloquent queries, manual transactions, bulk seat insertion, and raw JSON responses.

This controller is high-risk for a cinema booking system because screen/seat layout changes directly affect booking correctness. The implementation can delete seats for active or historically booked showtimes, regenerate seat IDs, destroy referential integrity, expose raw exception messages, update seat statuses without validation or transactions, and allows read-modify-write toggling without locks. It also has no visible authorization for admin-only operations.

---

## Overall Score

**Overall Score:** 4.2/10

**Decision:** 🔴 **BLOCKING**

---

## Strengths

- Uses `StoreScreenRequest`, `UpdateScreenRequest`, `StoreFormatRequest`, and `UpdateFormatRequest` for some write operations.
- Uses eager loading in `index()` for `theater`, `format`, and `seatLayoutTemplate`.
- Wraps screen creation/update/deletion in database transactions.
- Logs some create/update/delete exceptions.
- Bulk inserts generated seats instead of creating each seat individually.
- Scopes seat status updates by `screen_id` when updating by seat ID.

---

## Issues

### Issue #1

**Severity:** Critical  
**Category:** Security / Authorization  
**Location:** app/Http/Controllers/Admin/ScreenController.php:23-232

**Problem**

No method shows authentication, authorization middleware, policy, gate, or permission check.

```php
public function index(Request $request)
```

```php
public function store(StoreScreenRequest $request)
```

```php
public function destroy(Screen $screen)
```

```php
public function updateSeats(Request $request, Screen $screen)
```

**Why this matters**

Screen, seat, and format management are administrative operations. Unauthorized access can modify seat availability, delete screens, delete formats, or regenerate seats, directly corrupting bookings and revenue.

**How to fix**

Add explicit authorization.

```php
public function __construct()
{
    $this->middleware(['auth', 'permission:screens.manage']);
}
```

Use policies for sensitive operations.

```php
$this->authorize('update', $screen);
$this->authorize('delete', $screen);
```

---

### Issue #2

**Severity:** Critical  
**Category:** Database Correctness / Booking Integrity  
**Location:** app/Http/Controllers/Admin/ScreenController.php:179-180

**Problem**

Seat regeneration deletes all existing seats for a screen.

```php
// Delete existing seats
Seat::where('screen_id', $screen->id)->delete();
```

**Why this matters**

Seats are core booking entities. Deleting seats can orphan tickets, seat holds, bookings, historical order items, or showtime-seat state if any other table references seat IDs. Even if foreign keys block deletion, this flow fails at runtime. If cascading deletes exist, it can destroy booking history.

**How to fix**

Do not delete production seats once a screen has showtimes/bookings. Version layouts or create new screens/layout versions.

```php
if ($screen->showtimes()->exists()) {
    throw ValidationException::withMessages([
        'seat_layout_template_id' => 'Cannot regenerate seats for a screen with showtimes.',
    ]);
}
```

For future schedules, use seat layout versioning instead of destructive regeneration.

---

### Issue #3

**Severity:** Critical  
**Category:** Business Logic / Data Loss  
**Location:** app/Http/Controllers/Admin/ScreenController.php:104-110

**Problem**

Deleting a screen manually deletes all associated seats and then deletes the screen.

```php
Seat::where('screen_id', $screen->id)->delete();
$screen->delete();
```

**Why this matters**

A screen with showtimes, tickets, seat holds, or orders must not be deletable. This can destroy seat references and break historical booking records.

**How to fix**

Block deletion when dependent business records exist.

```php
if ($screen->showtimes()->exists()) {
    return response()->json([
        'success' => false,
        'message' => 'Cannot delete screen with showtimes.'
    ], 422);
}
```

Prefer soft-delete/deactivation for operational entities.

---

### Issue #4

**Severity:** Critical  
**Category:** Business Logic / Booking Integrity  
**Location:** app/Http/Controllers/Admin/ScreenController.php:73-87

**Problem**

Updating a screen can regenerate seats when the template changes.

```php
$oldTemplateId = $screen->seat_layout_template_id;
$screen->update($validated);

if ($oldTemplateId != $screen->seat_layout_template_id) {
    $this->generateSeatsForScreen($screen);
}
```

**Why this matters**

Changing a layout after showtimes or bookings exist can invalidate seat assignments. Customers may have booked seats that no longer exist or map to different physical seats.

**How to fix**

Prevent layout changes after the screen is used.

```php
if (
    $request->input('seat_layout_template_id') !== $screen->seat_layout_template_id &&
    $screen->showtimes()->exists()
) {
    return response()->json([
        'success' => false,
        'message' => 'Cannot change layout for a screen with scheduled showtimes.'
    ], 422);
}
```

---

### Issue #5

**Severity:** High  
**Category:** Security / Information Disclosure  
**Location:** app/Http/Controllers/Admin/ScreenController.php:66-70,91-95,113-117

**Problem**

Raw exception messages are returned to the client.

```php
return response()->json(['success' => false, 'message' => 'Có lỗi xảy ra: ' . $e->getMessage()], 500);
```

```php
return response()->json(['success' => false, 'message' => 'Không thể xóa phòng chiếu: ' . $e->getMessage()], 500);
```

**Why this matters**

Exception messages can reveal table names, SQL errors, paths, class names, constraint names, and implementation details.

**How to fix**

Log internally and return generic messages.

```php
Log::error('Error creating screen', ['exception' => $e]);

return response()->json([
    'success' => false,
    'message' => 'Unable to create screen.'
], 500);
```

---

### Issue #6

**Severity:** High  
**Category:** Validation / Data Integrity  
**Location:** app/Http/Controllers/Admin/ScreenController.php:136-145

**Problem**

`updateSeats()` uses raw `Request` and does not validate the shape, IDs, or boolean values of `seats`.

```php
$seats = $request->input('seats', []);

foreach ($seats as $id => $status) {
    Seat::where('id', $id)->where('screen_id', $screen->id)->update(['status' => (bool)$status]);
}
```

**Why this matters**

Malformed payloads can be silently accepted. PHP boolean casting is dangerous: strings like `"false"` cast to `true`. Invalid IDs are silently ignored. This can make seats available/unavailable incorrectly.

**How to fix**

Use a FormRequest.

```php
'seats' => ['required', 'array'],
'seats.*.id' => ['required', 'integer', Rule::exists('seats', 'id')->where('screen_id', $screen->id)],
'seats.*.status' => ['required', 'boolean'],
```

Use `$request->boolean()` semantics or strict validation before update.

---

### Issue #7

**Severity:** High  
**Category:** Concurrency / Atomicity  
**Location:** app/Http/Controllers/Admin/ScreenController.php:136-145

**Problem**

Seat status updates are performed in a loop without a transaction or lock.

```php
foreach ($seats as $id => $status) {
    Seat::where('id', $id)->where('screen_id', $screen->id)->update(['status' => (bool)$status]);
}
```

**Why this matters**

Partial updates can occur if an exception happens midway. Concurrent booking/seat-hold operations can read inconsistent availability while admin updates are in progress.

**How to fix**

Wrap updates in a transaction and coordinate with booking constraints.

```php
DB::transaction(function () use ($screen, $validatedSeats) {
    foreach ($validatedSeats as $seat) {
        Seat::where('id', $seat['id'])
            ->where('screen_id', $screen->id)
            ->lockForUpdate()
            ->update(['status' => $seat['status']]);
    }
});
```

Also block updates for seats attached to active showtimes/bookings.

---

### Issue #8

**Severity:** High  
**Category:** Runtime Bug / Null Dereference  
**Location:** app/Http/Controllers/Admin/ScreenController.php:186-214

**Problem**

Seat generation assumes at least one `SeatType` exists. If the table is empty, `$type` becomes null and `$type->id` crashes.

```php
$seatTypes = SeatType::all()->keyBy('name');
$standardType = $seatTypes->get('Standard') ?? $seatTypes->first();
...
'seat_type_id' => $type->id,
```

**Why this matters**

A missing seed or data corruption causes screen creation/update to fail at runtime.

**How to fix**

Validate required seat types before generation.

```php
if (! $standardType) {
    throw new RuntimeException('No seat types configured.');
}
```

Prefer configuration/seeding guarantees plus database constraints.

---

### Issue #9

**Severity:** High  
**Category:** Data Correctness / Layout Validation  
**Location:** app/Http/Controllers/Admin/ScreenController.php:191-193

**Problem**

`seat_matrix` is parsed with weak fallback defaults.

```php
$parts = explode('x', $template->seat_matrix);
$rows = (int) ($parts[0] ?? 12);
$cols = (int) ($parts[1] ?? 12);
```

**Why this matters**

Invalid template data silently becomes `0`, `12`, or another unintended layout. This can create zero seats, massive layouts, or incorrect capacity.

**How to fix**

Validate layout templates before use.

```php
if (! preg_match('/^[1-9][0-9]?x[1-9][0-9]?$/', $template->seat_matrix)) {
    throw new RuntimeException('Invalid seat matrix.');
}
```

Also cap rows/columns to safe operational limits.

---

### Issue #10

**Severity:** High  
**Category:** Data Correctness / Seat Labels  
**Location:** app/Http/Controllers/Admin/ScreenController.php:198-199

**Problem**

Row labels are generated with ASCII `chr(65 + $r)`.

```php
$rowLabel = chr(65 + $r); // A, B, C, ...
```

**Why this matters**

For more than 26 rows, labels become `[`, `\`, `]`, etc. That corrupts seat labels and can expose invalid seat names to customers.

**How to fix**

Use Excel-style row labels and validate row count.

```php
private function rowLabel(int $index): string
{
    // A-Z, AA, AB...
}
```

---

### Issue #11

**Severity:** Medium  
**Category:** API Consistency  
**Location:** app/Http/Controllers/Admin/ScreenController.php:44-50,65,90,101,112,130-133,145,152,158,165,167

**Problem**

The controller returns manually shaped raw JSON responses instead of the shared API response convention.

```php
return response()->json([
    'screens' => $screens,
    'formats' => $formats,
    'version_types' => $versionTypes,
    'theaters' => $theaters,
    'templates' => $templates
]);
```

**Why this matters**

API clients receive inconsistent response envelopes across endpoints. Error handling, pagination handling, and frontend integration become harder.

**How to fix**

Use the project response trait/resources consistently.

```php
return $this->successResponse([
    'screens' => ScreenResource::collection($screens),
    ...
]);
```

---

### Issue #12

**Severity:** Medium  
**Category:** API Serialization / Data Exposure  
**Location:** app/Http/Controllers/Admin/ScreenController.php:44-50,130-133,152,158

**Problem**

Raw Eloquent models and paginators are returned directly.

```php
'screens' => $screens,
'formats' => $formats,
'version_types' => $versionTypes,
'theaters' => $theaters,
'templates' => $templates
```

```php
'screen' => $screen,
'seats' => $seats
```

**Why this matters**

Raw model serialization exposes all visible attributes and tightly couples the API contract to database schema.

**How to fix**

Use API Resources for `Screen`, `Seat`, `Format`, `Theater`, and templates.

---

### Issue #13

**Severity:** Medium  
**Category:** Performance / Search Abuse  
**Location:** app/Http/Controllers/Admin/ScreenController.php:25-35

**Problem**

Search input is not validated or length-limited before wildcard `LIKE` queries.

```php
$search = $request->input('search');

$query->where('name', 'like', "%{$search}%")
      ->orWhere('code', 'like', "%{$search}%")
```

**Why this matters**

Leading-wildcard searches can cause table scans. Very long search strings can increase database CPU.

**How to fix**

Validate search input.

```php
'search' => ['nullable', 'string', 'max:100'],
```

Use indexes/full-text search where appropriate.

---

### Issue #14

**Severity:** Medium  
**Category:** Query Logic / Maintainability  
**Location:** app/Http/Controllers/Admin/ScreenController.php:29-34

**Problem**

The `orWhere` clauses are not grouped inside a nested `where`.

```php
->when($search, function ($query) use ($search) {
    $query->where('name', 'like', "%{$search}%")
          ->orWhere('code', 'like', "%{$search}%")
          ->orWhereHas('theater', function ($q) use ($search) {
```

**Why this matters**

If additional filters are added before or after this block, ungrouped `OR` conditions can break query semantics and leak results outside intended filters.

**How to fix**

Group search conditions.

```php
->when($search, function ($query) use ($search) {
    $query->where(function ($query) use ($search) {
        $query->where('name', 'like', "%{$search}%")
            ->orWhere('code', 'like', "%{$search}%")
            ->orWhereHas('theater', fn ($q) => $q->where('name', 'like', "%{$search}%"));
    });
})
```

---

### Issue #15

**Severity:** Medium  
**Category:** Performance / Unbounded Selector Data  
**Location:** app/Http/Controllers/Admin/ScreenController.php:39-42

**Problem**

The index endpoint loads all formats, version types, active theaters, and active templates.

```php
$formats = Format::latest()->get();
$versionTypes = VersionType::latest()->get();
$theaters = Theater::active()->get();
$templates = SeatLayoutTemplate::active()->get();
```

**Why this matters**

List endpoints become heavier as reference data grows. The screen index mixes grid data with form bootstrap data, increasing latency and payload size.

**How to fix**

Split reference-data endpoints or cache small lookup data.

```php
Cache::remember('admin.screen.lookups', 3600, fn () => [
    'formats' => FormatResource::collection(Format::latest()->get()),
    ...
]);
```

---

### Issue #16

**Severity:** Medium  
**Category:** Concurrency / Lost Update  
**Location:** app/Http/Controllers/Admin/ScreenController.php:98-101

**Problem**

Status toggling uses read-modify-write without locking.

```php
$screen->update(['status' => !$screen->status]);
```

**Why this matters**

Concurrent toggle requests can produce unexpected final state. Deactivating a screen can also conflict with active showtimes.

**How to fix**

Use explicit desired status and validate operational constraints.

```php
$validated = $request->validate(['status' => ['required', 'boolean']]);
$screen->update(['status' => $validated['status']]);
```

---

### Issue #17

**Severity:** Medium  
**Category:** Business Logic / Operational Integrity  
**Location:** app/Http/Controllers/Admin/ScreenController.php:98-101

**Problem**

A screen can be toggled inactive without checking active/future showtimes.

```php
$screen->update(['status' => !$screen->status]);
```

**Why this matters**

Disabling a screen with active scheduled showtimes can leave sellable sessions in an inconsistent state or hide screens needed for operations.

**How to fix**

Block deactivation when active/future showtimes exist or define cascading rules explicitly.

---

### Issue #18

**Severity:** Medium  
**Category:** Database Correctness / Transactions  
**Location:** app/Http/Controllers/Admin/ScreenController.php:231

**Problem**

`generateSeatsForScreen()` updates the screen inside the helper after inserting seats.

```php
$screen->update(['capacity' => $totalCapacity]);
```

**Why this matters**

The helper assumes the caller wraps it in a transaction. If reused outside a transaction later, seat generation can partially persist. It also causes a nested model update while the caller has just updated the screen.

**How to fix**

Make transaction boundaries explicit in a service/action and do not hide writes inside helper methods.

```php
DB::transaction(fn () => $this->screenLayoutService->regenerateSeats($screen));
```

---

### Issue #19

**Severity:** Medium  
**Category:** Architecture / Single Responsibility  
**Location:** app/Http/Controllers/Admin/ScreenController.php:21-233

**Problem**

One controller handles screens, seats, formats, lookup data, and seat-generation algorithms.

```php
class ScreenController extends Controller
```

**Why this matters**

This violates Single Responsibility. Seat generation and format CRUD are separate domains. The controller is hard to test and maintain.

**How to fix**

Split responsibilities:

- `Admin\ScreenController`
- `Admin\ScreenSeatController`
- `Admin\FormatController`
- `ScreenSeatGenerationService`

---

### Issue #20

**Severity:** Medium  
**Category:** Auditability  
**Location:** app/Http/Controllers/Admin/ScreenController.php:53-167

**Problem**

Admin changes to screens, seats, and formats are not audited.

```php
$screen = Screen::create($validated);
```

```php
$screen->update($validated);
```

```php
Seat::where('id', $id)->where('screen_id', $screen->id)->update(['status' => (bool)$status]);
```

**Why this matters**

Seat/screen changes affect ticket sales and customer reservations. Production systems need actor, old values, new values, timestamp, and reason.

**How to fix**

Create structured audit records for every admin mutation.

---

### Issue #21

**Severity:** Medium  
**Category:** Data Integrity / Format Delete  
**Location:** app/Http/Controllers/Admin/ScreenController.php:161-168

**Problem**

Format deletion relies on catching a generic exception.

```php
try {
    $format->delete();
    return response()->json(['success' => true, 'message' => 'Xóa định dạng chiếu thành công.']);
} catch (\Exception $e) {
    return response()->json(['success' => false, 'message' => 'Không thể xóa định dạng vì đang có phòng chiếu sử dụng.'], 400);
}
```

**Why this matters**

Any exception is treated as "format is in use", hiding real production issues. It also does not check relationships explicitly.

**How to fix**

Check dependencies before delete.

```php
if ($format->screens()->exists()) {
    return response()->json([
        'success' => false,
        'message' => 'Cannot delete format in use.'
    ], 422);
}
```

Log unexpected exceptions.

---

### Issue #22

**Severity:** Medium  
**Category:** Mass Assignment / Input Boundary  
**Location:** app/Http/Controllers/Admin/ScreenController.php:61,82,151,157

**Problem**

Validated arrays are passed directly to `create()` and `update()`.

```php
$screen = Screen::create($validated);
```

```php
$format->update($request->validated());
```

**Why this matters**

This is safe only if FormRequests and model `$fillable` are perfectly restricted. For admin endpoints, mass assignment should still be explicit for sensitive fields such as `capacity`, `status`, and foreign keys.

**How to fix**

Map allowed fields explicitly in a service/action.

```php
$screen->fill([
    'name' => $validated['name'],
    'code' => $validated['code'],
    ...
])->save();
```

---

### Issue #23

**Severity:** Low  
**Category:** Type Safety  
**Location:** app/Http/Controllers/Admin/ScreenController.php:23,53,73,98,104,120,136,149,155,161,172

**Problem**

No method declares return types.

```php
public function index(Request $request)
```

```php
private function generateSeatsForScreen(Screen $screen)
```

**Why this matters**

Return types improve static analysis and refactoring safety.

**How to fix**

```php
use Illuminate\Http\JsonResponse;

public function index(Request $request): JsonResponse
private function generateSeatsForScreen(Screen $screen): void
```

---

### Issue #24

**Severity:** Low  
**Category:** Code Style  
**Location:** app/Http/Controllers/Admin/ScreenController.php:142

**Problem**

Missing spacing around cast.

```php
'status' => (bool)$status
```

**Why this matters**

Inconsistent style suggests formatter enforcement is missing.

**How to fix**

```php
'status' => (bool) $status
```

Run Laravel Pint in CI.

---

## Security Review

Security concerns:

- No visible authorization across admin endpoints.
- Raw exception messages are returned to clients.
- Raw Eloquent models are returned directly.
- Seat status can be updated with unvalidated raw request input.
- No audit logging for revenue-affecting screen/seat changes.

No direct SQL injection is visible because Eloquent query bindings are used.

---

## Performance Review

Performance concerns:

- Wildcard search without length limits.
- Lookup data is loaded on every index request.
- Seat generation builds the full insert array in memory.
- `updateSeats()` performs one query per seat.
- Raw model serialization may return unnecessary fields.

---

## Database Review

Database/data correctness concerns:

- Seat deletion/regeneration can destroy booking references.
- Screen deletion can destroy historical seat data.
- No explicit dependency checks for showtimes/bookings before destructive operations.
- `seat_matrix` parsing is weak and can generate invalid seat layouts.
- No visible uniqueness guarantees for generated seat labels.
- Format deletion relies on generic exception handling.

---

## Concurrency Review

Concurrency risks:

- Toggle endpoint is race-prone.
- Seat updates are not transactional.
- Seat regeneration can race with booking/seat-hold operations.
- Screen deletion can race with showtime creation or booking activity.
- No `lockForUpdate()` is used for destructive or availability-affecting operations.

---

## Laravel Best Practice Review

Recommended improvements:

- Add middleware/policies.
- Use FormRequests for `index()` and `updateSeats()`.
- Use API Resources and a shared response trait.
- Move seat generation to a dedicated service/action.
- Split format CRUD into a separate controller.
- Avoid raw exception disclosure.
- Use explicit relationship checks before delete/regeneration.
- Use soft deletes/deactivation for operational entities.
- Add typed responses.
- Add Laravel Pint and static analysis enforcement.

---

## Testing Review

Recommended tests:

1. Guest cannot access screen admin endpoints.
2. User without screen permissions cannot mutate screens/seats/formats.
3. Screen with showtimes cannot be deleted.
4. Screen with showtimes cannot change seat layout template.
5. Seat regeneration does not delete historical booked seats.
6. Invalid `seat_matrix` is rejected.
7. Missing `SeatType` records fail safely with controlled error.
8. `updateSeats()` rejects malformed payloads.
9. `"false"` string does not incorrectly activate seats.
10. Seat updates are atomic.
11. Concurrent status toggles produce deterministic results.
12. Format in use cannot be deleted and unexpected exceptions are logged.
13. Raw exception text is not returned in API responses.
14. Screen/seat/format mutations create audit records.

---

## Final Decision

🔴 **BLOCKING**

`Admin\ScreenController` must be redesigned before production use. The destructive seat regeneration/deletion behavior can corrupt bookings and historical records. The controller also lacks visible authorization, exposes raw exception messages, accepts unvalidated seat updates, and combines too many responsibilities in one class.

---

_Review completed: 2026-07-14 04:30 PM_  
_File #71/137 - Phase 4: Controllers (23/34 complete)_
