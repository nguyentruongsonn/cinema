# File Review: Admin/BranchController.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Controllers/Admin/BranchController.php  
**Lines:** 67  
**Type:** Admin Branch Management Controller

---

## File Summary

`Admin\BranchController` provides branch listing, creation, update, activation toggling, and deletion. It uses FormRequest classes for create/update validation, but the controller still directly performs Eloquent queries and mutations, returns raw models/paginators, and does not show any explicit authorization or safe deletion checks.

Branch data is operationally important because branches are usually related to theaters, screens, showtimes, seats, orders, and reporting. Deleting or deactivating a branch without business constraints can break revenue reporting, future showtimes, and public discovery.

---

## Overall Score

**Overall Score:** 5.4/10

**Decision:** 🔴 **REQUEST CHANGES**

---

## Strengths

- Uses dedicated `StoreBranchRequest` and `UpdateBranchRequest` for create/update validation.
- Uses route model binding for `Branch $branch`.
- Keeps controller short and readable.
- Uses pagination for branch listing.
- Uses Eloquent query builder with parameter binding for search conditions.

---

## Issues

### Issue #1

**Severity:** Critical  
**Category:** Security / Authorization  
**Location:** app/Http/Controllers/Admin/BranchController.php:13-65

**Problem**

The controller contains no visible authentication, authorization middleware, permission checks, policy checks, or gate checks.

```php
public function store(StoreBranchRequest $request)
```

```php
public function update(UpdateBranchRequest $request, Branch $branch)
```

```php
public function toggleActive(Branch $branch)
```

```php
public function destroy(Branch $branch)
```

**Why this matters**

Branch management is an admin-only operation. If route-level middleware is missing or misconfigured, unauthorized users can create branches, update operational locations, disable branches, or delete branch records.

This is a high-impact authorization risk because branches can affect theater availability, showtime visibility, reporting, and customer-facing booking flows.

**How to fix**

Enforce authorization in the controller through middleware and/or policies.

```php
public function __construct()
{
    $this->middleware(['auth', 'permission:branches.manage']);
}
```

And for model-specific operations:

```php
public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
{
    $this->authorize('update', $branch);

    ...
}
```

---

### Issue #2

**Severity:** High  
**Category:** Database Correctness / Business Logic  
**Location:** app/Http/Controllers/Admin/BranchController.php:62-65

**Problem**

`destroy()` deletes a branch without checking whether it has dependent theaters, screens, showtimes, orders, tickets, or reports.

```php
public function destroy(Branch $branch)
{
    $branch->delete();
    return response()->json(['success' => true, 'message' => 'Xóa chi nhánh thành công.']);
}
```

**Why this matters**

Deleting a branch that is referenced by operational or historical records can break foreign key constraints, orphan related data if database constraints are weak, or corrupt business/reporting history.

For a cinema booking system, historical revenue and orders should remain tied to the correct branch. Hard deletion is unsafe unless all dependency rules are explicitly enforced.

**How to fix**

Use soft deletes where appropriate and block deletion when dependent records exist.

```php
if ($branch->theaters()->exists()) {
    return response()->json([
        'success' => false,
        'message' => 'Cannot delete branch with existing theaters.',
    ], 409);
}

$branch->delete();
```

Prefer domain-level deletion rules in a `BranchService`.

---

### Issue #3

**Severity:** High  
**Category:** Business Logic / Availability Correctness  
**Location:** app/Http/Controllers/Admin/BranchController.php:56-59

**Problem**

`toggleActive()` can deactivate a branch without checking active theaters, screens, future showtimes, active seat holds, or pending bookings.

```php
$branch->update(['is_active' => !$branch->is_active]);
```

**Why this matters**

A branch with future showtimes or active bookings should not be silently deactivated without cascading business actions. Customers may already have valid tickets, and public pages may become inconsistent.

This can cause lost revenue, customer support incidents, and inconsistent availability across APIs.

**How to fix**

Enforce transition rules before deactivation.

```php
if ($branch->showtimes()->where('start_time', '>', now())->exists()) {
    return response()->json([
        'success' => false,
        'message' => 'Cannot deactivate branch with future showtimes.',
    ], 409);
}
```

Use a service method such as `BranchService::deactivate()` to centralize the state transition.

---

### Issue #4

**Severity:** High  
**Category:** Validation / Data Integrity  
**Location:** app/Http/Controllers/Admin/BranchController.php:31, 45

**Problem**

The controller overrides `is_active` based on request presence instead of relying on validated boolean data.

```php
$validated['is_active'] = $request->has('is_active') ? 1 : 0;
```

**Why this matters**

For JSON APIs, `has('is_active')` returns true even when the client sends `false` or `0`. This means a request intending to set `is_active` to false can incorrectly store true.

Example payload:

```json
{
  "name": "Branch A",
  "is_active": false
}
```

The field is present, so the controller sets it to `1`.

**How to fix**

Use validated boolean data.

```php
$validated['is_active'] = $request->boolean('is_active');
```

Or preserve default behavior explicitly:

```php
$validated['is_active'] = $validated['is_active'] ?? false;
```

---

### Issue #5

**Severity:** Medium  
**Category:** API Consistency / Data Exposure  
**Location:** app/Http/Controllers/Admin/BranchController.php:25, 35-39, 49-53, 59, 65

**Problem**

The controller returns mixed response shapes and raw Eloquent models/paginators.

```php
return response()->json($branches);
```

```php
return response()->json([
    'success' => true,
    'message' => 'Tạo chi nhánh thành công.',
    'data' => $branch
], 201);
```

```php
return response()->json(['success' => true, 'is_active' => $branch->is_active]);
```

**Why this matters**

Inconsistent API envelopes make client code brittle and complicate frontend error handling. Returning raw Eloquent models couples API output to database schema and may expose future internal columns.

**How to fix**

Use API Resources and a consistent response trait.

```php
return BranchResource::collection($branches);
```

```php
return $this->successResponse(new BranchResource($branch), 'Branch created successfully', 201);
```

---

### Issue #6

**Severity:** Medium  
**Category:** Validation / Performance  
**Location:** app/Http/Controllers/Admin/BranchController.php:15-23

**Problem**

The list endpoint accepts unvalidated `search` input.

```php
$search = $request->input('search');
```

```php
$query->where('name', 'like', "%{$search}%")
      ->orWhere('address', 'like', "%{$search}%");
```

**Why this matters**

Although Eloquent parameter binding prevents direct SQL injection here, unbounded search strings can degrade query performance and create oversized database queries.

**How to fix**

Validate query filters.

```php
$validated = $request->validate([
    'search' => ['nullable', 'string', 'max:255'],
]);
```

---

### Issue #7

**Severity:** Medium  
**Category:** Query Correctness / Maintainability  
**Location:** app/Http/Controllers/Admin/BranchController.php:17-21

**Problem**

The search condition uses `orWhere()` directly inside the `when()` closure without grouping future filters.

```php
$query->where('name', 'like', "%{$search}%")
      ->orWhere('address', 'like', "%{$search}%");
```

**Why this matters**

The current query only has search, so it works today. But once additional filters are added, the ungrouped `orWhere()` can change query semantics and return rows that bypass other constraints.

**How to fix**

Group OR conditions.

```php
$query->where(function ($q) use ($search) {
    $q->where('name', 'like', "%{$search}%")
      ->orWhere('address', 'like', "%{$search}%");
});
```

---

### Issue #8

**Severity:** Medium  
**Category:** Observability / Audit Logging  
**Location:** app/Http/Controllers/Admin/BranchController.php:28-65

**Problem**

Create, update, activation toggle, and delete operations are not audit logged.

**Why this matters**

Branch operations affect customer-facing availability and business reporting. Production admin systems need an audit trail for who changed what and when.

**How to fix**

Record audit logs after successful mutations.

```php
AuditLog::record('branch.updated', [
    'actor_id' => auth()->id(),
    'branch_id' => $branch->id,
    'changes' => $branch->getChanges(),
]);
```

---

### Issue #9

**Severity:** Medium  
**Category:** Architecture / Clean Code  
**Location:** app/Http/Controllers/Admin/BranchController.php:17-23, 31-33, 45-47, 58, 64

**Problem**

The controller directly owns query construction and domain mutations.

```php
$branches = Branch::query()
```

```php
$branch = Branch::create($validated);
```

```php
$branch->update(['is_active' => !$branch->is_active]);
```

**Why this matters**

Business rules around deletion, deactivation, branch uniqueness, and operational dependencies should live in a service/domain layer, not be spread across controller actions.

**How to fix**

Move branch business operations to a `BranchService`.

```php
$branch = $this->branchService->create($request->validated(), $request->user());
```

---

### Issue #10

**Severity:** Low  
**Category:** Type Safety / Readability  
**Location:** app/Http/Controllers/Admin/BranchController.php:13, 28, 42, 56, 62

**Problem**

Controller methods do not declare return types.

```php
public function index(Request $request)
```

**Why this matters**

Explicit return types improve readability, static analysis, and refactoring safety.

**How to fix**

```php
use Illuminate\Http\JsonResponse;

public function index(Request $request): JsonResponse
```

---

## Security Review

Security concerns:

- No visible authorization for admin branch operations.
- Raw Eloquent serialization may expose internal fields.
- Deletion and activation changes lack audit trail.
- Search input is not validated or length-limited.

No raw SQL is present. The `LIKE` query is constructed through Eloquent, so SQL injection is not directly visible in this file.

---

## Performance Review

Performance concerns:

- Search uses leading wildcard `%term%`, which cannot use normal indexes efficiently.
- Search input length is not capped.
- No `per_page` parameter is accepted, which avoids abuse but also hardcodes pagination behavior.
- Returning raw paginator may include more metadata than the API contract requires.

Recommended improvements:

- Validate and cap `search`.
- Add full-text search if branch data grows.
- Use API resources for controlled payloads.

---

## Database Review

Database correctness concerns:

- Hard deletion is allowed without dependency checks.
- Activation toggling ignores future operational records.
- `is_active` boolean handling is incorrect for JSON false values.
- No transaction boundaries around admin mutations if future side effects are added.

Recommended improvements:

- Use soft deletes or guarded delete rules.
- Enforce branch dependency constraints.
- Fix boolean parsing.
- Add database constraints for critical relationships.

---

## Concurrency Review

Concurrency risks:

- Two admins toggling the same branch concurrently can cause lost updates.
- Branch deletion can race with creation of related theaters/showtimes.
- Activation/deactivation does not lock related operational records.

Recommended improvements:

- Replace blind toggle with explicit `activate` / `deactivate` commands.
- Use transactions and `lockForUpdate()` around state transitions.
- Enforce database foreign keys and conflict responses.

---

## Laravel Best Practice Review

Recommended improvements:

- Add policies/gates for branch management.
- Use API Resources.
- Use a service layer for branch lifecycle rules.
- Use grouped search query conditions.
- Use explicit return types.
- Use validated boolean fields instead of `$request->has()`.
- Add audit logging.

---

## Testing Review

Recommended tests:

1. Guest cannot access branch admin endpoints.
2. Non-admin cannot create/update/delete/toggle branches.
3. Admin without branch permission is rejected.
4. JSON `is_active: false` stores false, not true.
5. Invalid search values are rejected.
6. Deleting a branch with theaters/showtimes is rejected.
7. Deactivating a branch with future showtimes is rejected.
8. Response envelopes are consistent.
9. Raw internal branch fields are not exposed.
10. Concurrent toggles do not produce lost updates.

---

## Final Decision

🔴 **REQUEST CHANGES**

`Admin\BranchController` requires changes before production approval. The most important issues are missing visible authorization, incorrect boolean handling for JSON requests, unsafe hard deletion, unsafe deactivation of operational branches, inconsistent raw API responses, and missing audit logging.

---

_Review completed: 2026-07-14 03:45 PM_  
_File #62/137 - Phase 4: Controllers (14/34 complete)_
