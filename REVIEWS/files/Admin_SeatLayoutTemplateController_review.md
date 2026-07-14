# File Review: Admin/SeatLayoutTemplateController.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Controllers/Admin/SeatLayoutTemplateController.php  
**Lines:** 71  
**Type:** Admin Seat Layout Template Management Controller

---

## File Summary

`Admin\SeatLayoutTemplateController` manages seat layout templates through list, create, update, status toggle, and delete endpoints. It uses FormRequest classes for create/update but uses raw request input for listing and performs direct Eloquent operations in the controller.

This file is not production-ready. Seat layout templates are high-impact configuration because they can define physical seat maps used to generate screens/seats. The controller lacks visible authorization, has unsafe boolean handling for JSON APIs, allows race-prone status toggling, deletes templates without dependency checks, searches JSON/text layout data with expensive wildcard queries, and returns raw Eloquent models/paginators without resources.

---

## Overall Score

**Overall Score:** 4.9/10

**Decision:** 🟠 **REQUEST CHANGES**

---

## Strengths

- Uses `StoreSeatLayoutTemplateRequest` and `UpdateSeatLayoutTemplateRequest` for create/update.
- Uses route model binding for update, toggle, and delete.
- Groups search `OR` conditions inside a nested `where`, avoiding a common query-scope bug.
- Uses pagination for the index endpoint.
- Controller is small and readable.

---

## Issues

### Issue #1

**Severity:** Critical  
**Category:** Security / Authorization  
**Location:** app/Http/Controllers/Admin/SeatLayoutTemplateController.php:13-69

**Problem**

There is no visible authentication, authorization middleware, policy, gate, or permission check for any admin seat layout template operation.

```php
public function index(Request $request)
```

```php
public function store(StoreSeatLayoutTemplateRequest $request)
```

```php
public function destroy(SeatLayoutTemplate $seatLayoutTemplate)
```

**Why this matters**

Seat layout templates can directly affect screen layout generation and downstream booking correctness. Unauthorized users must not be able to create, update, publish, unpublish, or delete templates.

**How to fix**

Add explicit middleware and/or policy checks.

```php
public function __construct()
{
    $this->middleware(['auth', 'permission:seat-layout-templates.manage']);
}
```

For object-specific actions:

```php
$this->authorize('update', $seatLayoutTemplate);
```

---

### Issue #2

**Severity:** High  
**Category:** Business Logic / Data Integrity  
**Location:** app/Http/Controllers/Admin/SeatLayoutTemplateController.php:65-69

**Problem**

The controller deletes a template without checking whether it is referenced by screens, generated seats, audit history, or other operational configuration.

```php
public function destroy(SeatLayoutTemplate $seatLayoutTemplate)
{
    $seatLayoutTemplate->delete();

    return response()->json(['success' => true, 'message' => 'Xóa mẫu sơ đồ ghế thành công.']);
}
```

**Why this matters**

Deleting layout templates can break historical traceability and may corrupt admin workflows if existing screens depend on the template. If foreign keys exist, this can fail at runtime. If cascade rules exist, it may delete related configuration unexpectedly.

**How to fix**

Block deletion when the template is in use and prefer soft-delete/archive behavior.

```php
if ($seatLayoutTemplate->screens()->exists()) {
    return response()->json([
        'success' => false,
        'message' => 'Cannot delete a layout template currently used by screens.'
    ], 422);
}

$seatLayoutTemplate->delete();
```

---

### Issue #3

**Severity:** High  
**Category:** Business Logic / Operational Safety  
**Location:** app/Http/Controllers/Admin/SeatLayoutTemplateController.php:48-53

**Problem**

The controller updates an existing layout template directly without checking whether it is already used by screens or generated seats.

```php
$validated = $request->validated();
$validated['status'] = $request->has('status') ? 1 : 0;

$seatLayoutTemplate->update($validated);
```

**Why this matters**

If templates are reused as source definitions for screens, mutating a template after use can create mismatch between template definition, generated seats, and future screens. Layout changes need versioning, cloning, or explicit immutability once used.

**How to fix**

Enforce immutability/versioning for used templates.

```php
if ($seatLayoutTemplate->screens()->exists()) {
    return response()->json([
        'success' => false,
        'message' => 'Used layout templates cannot be modified. Create a new version instead.'
    ], 422);
}
```

---

### Issue #4

**Severity:** High  
**Category:** Concurrency / Lost Update  
**Location:** app/Http/Controllers/Admin/SeatLayoutTemplateController.php:58-62

**Problem**

The status toggle uses a read-modify-write operation without locking.

```php
$seatLayoutTemplate->update(['status' => !$seatLayoutTemplate->status]);
```

**Why this matters**

Concurrent toggle requests can produce an unexpected final state. Toggle endpoints are inherently unsafe because the requested final state depends on stale model state.

**How to fix**

Replace toggle with explicit state-setting.

```php
$validated = $request->validate([
    'status' => ['required', 'boolean'],
]);

$seatLayoutTemplate->update(['status' => $validated['status']]);
```

---

### Issue #5

**Severity:** High  
**Category:** Validation / Boolean Handling  
**Location:** app/Http/Controllers/Admin/SeatLayoutTemplateController.php:41,51

**Problem**

Status is derived from field presence instead of validated boolean value.

```php
$validated['status'] = $request->has('status') ? 1 : 0;
```

**Why this matters**

For JSON APIs, a payload containing `"status": false` still makes `has('status')` return true, so the status is incorrectly saved as `1`. This is a correctness bug and can publish templates that the client intended to save as drafts.

**How to fix**

Use validated boolean semantics.

```php
$validated['status'] = $request->boolean('status');
```

Even better, normalize this inside the FormRequest.

---

### Issue #6

**Severity:** Medium  
**Category:** Validation / Query Abuse  
**Location:** app/Http/Controllers/Admin/SeatLayoutTemplateController.php:13-16

**Problem**

The index endpoint reads `search` and `status` from raw request input without validation.

```php
$search = $request->input('search');
$status = $request->input('status'); // 'all', 'published', 'draft'
```

**Why this matters**

Unvalidated search allows very long wildcard searches. Invalid status values are silently treated as "all", which can hide client bugs and produce overly broad results.

**How to fix**

Use a dedicated index FormRequest.

```php
'search' => ['nullable', 'string', 'max:100'],
'status' => ['nullable', Rule::in(['all', 'published', 'draft'])],
'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
```

---

### Issue #7

**Severity:** Medium  
**Category:** Performance  
**Location:** app/Http/Controllers/Admin/SeatLayoutTemplateController.php:19-24

**Problem**

The search performs leading-wildcard `LIKE` queries across `template_name`, `description`, and `seat_matrix`.

```php
$sq->where('template_name', 'like', "%{$search}%")
   ->orWhere('description', 'like', "%{$search}%")
   ->orWhere('seat_matrix', 'like', "%{$search}%");
```

**Why this matters**

Searching `seat_matrix` with `%term%` is likely expensive, especially if `seat_matrix` is JSON or large text. This can cause table scans and slow admin pages as template volume grows.

**How to fix**

Do not search the full matrix by default. Search indexed metadata fields only, or add a proper generated/search column.

```php
$sq->where('template_name', 'like', "{$search}%")
   ->orWhere('description', 'like', "%{$search}%");
```

---

### Issue #8

**Severity:** Medium  
**Category:** API Consistency  
**Location:** app/Http/Controllers/Admin/SeatLayoutTemplateController.php:35,45,55,62,69

**Problem**

The controller returns inconsistent response shapes.

```php
return response()->json($templates);
```

```php
return response()->json(['success' => true, 'message' => 'Tạo mẫu sơ đồ ghế thành công.', 'data' => $template]);
```

```php
return response()->json(['success' => true, 'message' => 'Cập nhật mẫu sơ đồ ghế thành công.']);
```

**Why this matters**

API clients need stable response contracts. This endpoint returns a raw paginator for index, model data for create, no data for update, and only status for toggle.

**How to fix**

Use a consistent response envelope and resources.

```php
return $this->successResponse(
    new SeatLayoutTemplateResource($template),
    'Seat layout template created successfully',
    201
);
```

---

### Issue #9

**Severity:** Medium  
**Category:** API Serialization / Data Exposure  
**Location:** app/Http/Controllers/Admin/SeatLayoutTemplateController.php:35,45

**Problem**

Raw Eloquent paginator/model data is returned directly.

```php
return response()->json($templates);
```

```php
'data' => $template
```

**Why this matters**

Raw serialization exposes the API to schema changes and may leak internal columns or large layout payloads unnecessarily.

**How to fix**

Use API Resources.

```php
return SeatLayoutTemplateResource::collection($templates);
```

---

### Issue #10

**Severity:** Medium  
**Category:** Mass Assignment / Input Boundary  
**Location:** app/Http/Controllers/Admin/SeatLayoutTemplateController.php:43,53

**Problem**

The controller passes the entire validated array directly into Eloquent mass assignment.

```php
$template = SeatLayoutTemplate::create($validated);
```

```php
$seatLayoutTemplate->update($validated);
```

**Why this matters**

This is only safe if both the FormRequests and model `$fillable` are perfectly restrictive. Layout templates are operational configuration, so explicit field mapping is safer.

**How to fix**

Map only allowed fields.

```php
$seatLayoutTemplate->fill([
    'template_name' => $validated['template_name'],
    'description' => $validated['description'] ?? null,
    'seat_matrix' => $validated['seat_matrix'],
    'status' => $validated['status'],
])->save();
```

---

### Issue #11

**Severity:** Medium  
**Category:** Domain Validation / Layout Correctness  
**Location:** app/Http/Controllers/Admin/SeatLayoutTemplateController.php:38-43,48-53

**Problem**

The controller blindly persists validated data and does not show any domain-level validation for `seat_matrix` consistency before create/update.

```php
$validated = $request->validated();
$template = SeatLayoutTemplate::create($validated);
```

**Why this matters**

FormRequest validation can validate shape, but layout templates usually require domain checks: valid rows/columns, valid seat labels, no duplicate seats, aisles/walkways rules, supported seat types, capacity consistency, and max capacity limits. Bad templates can propagate into screen generation and booking failures.

**How to fix**

Move layout validation to a domain service/action.

```php
$this->seatLayoutTemplateService->validateMatrix($validated['seat_matrix']);
$template = $this->seatLayoutTemplateService->create($validated);
```

---

### Issue #12

**Severity:** Medium  
**Category:** Observability / Audit Logging  
**Location:** app/Http/Controllers/Admin/SeatLayoutTemplateController.php:38-69

**Problem**

No mutation action is logged.

```php
$template = SeatLayoutTemplate::create($validated);
```

```php
$seatLayoutTemplate->update($validated);
```

```php
$seatLayoutTemplate->delete();
```

**Why this matters**

Seat layout templates affect seat inventory. Production systems need audit records for who changed a template, what changed, and when.

**How to fix**

Emit audit logs or domain events.

```php
AuditLog::record('seat_layout_template.updated', [
    'actor_id' => auth()->id(),
    'template_id' => $seatLayoutTemplate->id,
    'changes' => $seatLayoutTemplate->getChanges(),
]);
```

---

### Issue #13

**Severity:** Medium  
**Category:** Exception Handling  
**Location:** app/Http/Controllers/Admin/SeatLayoutTemplateController.php:38-69

**Problem**

Expected domain/database failure cases are not handled explicitly.

```php
$seatLayoutTemplate->delete();
```

**Why this matters**

If a delete/update violates a constraint, clients receive whatever the global exception handler returns. Admin APIs should provide clear domain errors for expected cases such as "template in use".

**How to fix**

Check expected constraints before mutation and return domain-specific status codes.

```php
return response()->json([
    'success' => false,
    'message' => 'Template is currently in use.'
], 422);
```

---

### Issue #14

**Severity:** Low  
**Category:** HTTP Semantics  
**Location:** app/Http/Controllers/Admin/SeatLayoutTemplateController.php:45

**Problem**

Create returns no explicit `201 Created` status.

```php
return response()->json(['success' => true, 'message' => 'Tạo mẫu sơ đồ ghế thành công.', 'data' => $template]);
```

**Why this matters**

REST API clients rely on status codes for workflow and error handling.

**How to fix**

```php
return response()->json([
    'success' => true,
    'message' => 'Tạo mẫu sơ đồ ghế thành công.',
    'data' => new SeatLayoutTemplateResource($template),
], 201);
```

---

### Issue #15

**Severity:** Low  
**Category:** Type Safety  
**Location:** app/Http/Controllers/Admin/SeatLayoutTemplateController.php:13,38,48,58,65

**Problem**

Controller methods do not declare return types.

```php
public function index(Request $request)
```

**Why this matters**

Return types improve static analysis, documentation, and refactoring safety.

**How to fix**

```php
use Illuminate\Http\JsonResponse;

public function index(Request $request): JsonResponse
```

---

### Issue #16

**Severity:** Low  
**Category:** Clean Code / Magic Strings  
**Location:** app/Http/Controllers/Admin/SeatLayoutTemplateController.php:16,27-30

**Problem**

Status filter values are hard-coded strings in the controller.

```php
$status = $request->input('status'); // 'all', 'published', 'draft'

if ($status === 'published') {
    $query->where('status', true);
} elseif ($status === 'draft') {
    $query->where('status', false);
}
```

**Why this matters**

Magic strings are easy to mistype and hard to reuse consistently across API docs, frontend code, and tests.

**How to fix**

Use constants or an enum-like value object.

```php
final class SeatLayoutTemplateStatusFilter
{
    public const ALL = 'all';
    public const PUBLISHED = 'published';
    public const DRAFT = 'draft';
}
```

---

## Security Review

Security concerns:

- No visible authorization for admin template management.
- Raw model serialization exposes schema and potentially large/internal layout fields.
- Unauthorized template changes can affect seat inventory and booking correctness.
- No audit logging for sensitive admin configuration mutations.

No direct SQL injection is visible because Eloquent query binding is used.

---

## Performance Review

Performance concerns:

- Leading-wildcard search can cause full table scans.
- Searching `seat_matrix` is likely expensive and should be avoided or indexed differently.
- Fixed pagination size is acceptable but not configurable through validated input.
- Raw serialization can return unnecessarily large matrix payloads.

---

## Database Review

Database/data correctness concerns:

- Delete does not check dependencies.
- Update does not enforce immutability/versioning for templates in use.
- Status changes are not transactional and do not check operational dependencies.
- Direct mass assignment relies on external model/FormRequest correctness.

---

## Concurrency Review

Concurrency concerns:

- `toggleActive()` is race-prone.
- Concurrent update/delete against templates in use can create inconsistent operational configuration.
- No optimistic lock/versioning is used for template edits.

---

## Laravel Best Practice Review

Recommended improvements:

- Add policies or permission middleware.
- Use a dedicated index FormRequest.
- Use API Resources.
- Use shared API response helpers.
- Replace `toggleActive()` with explicit status update.
- Move layout matrix validation and mutation rules to a service/action.
- Add audit logging for create/update/publish/delete.
- Add return types.

---

## Testing Review

Recommended tests:

1. Guest cannot access seat layout template admin endpoints.
2. Non-admin/non-permissioned user cannot create/update/delete templates.
3. JSON payload `"status": false` stores draft status correctly.
4. Invalid status filter is rejected.
5. Overlong search input is rejected.
6. Used templates cannot be deleted.
7. Used templates cannot be modified without versioning.
8. Duplicate/invalid seat labels in `seat_matrix` are rejected.
9. Toggle endpoint is replaced or concurrent status updates produce deterministic result.
10. Create returns `201 Created`.
11. API resource does not expose unintended fields.
12. Admin mutations create audit records.

---

## Final Decision

🟠 **REQUEST CHANGES**

`Admin\SeatLayoutTemplateController` handles high-impact cinema seating configuration but lacks production-grade authorization, dependency protection, deterministic state changes, audit logging, and robust layout-domain validation. It should not be approved until these risks are addressed.

---

_Review completed: 2026-07-14 04:40 PM_  
_File #73/137 - Phase 4: Controllers (25/34 complete)_
