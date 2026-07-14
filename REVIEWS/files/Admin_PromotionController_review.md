# File Review: Admin/PromotionController.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Controllers/Admin/PromotionController.php  
**Lines:** 129  
**Type:** Admin Promotion Management Controller

---

## File Summary

`Admin\PromotionController` implements admin promotion listing, category retrieval, detail retrieval, creation, update, deletion, active-status toggling, and usage-count reset. It uses Eloquent directly in the controller, inline validation, route model binding, and the shared `ApiResponse` trait.

This controller is not production-ready. It exposes high-risk revenue-affecting promotion operations without visible authorization, allows unsafe mutation of used promotions, permits arbitrary reset of usage counters, has weak discount validation that can create invalid commercial rules, uses read-modify-write toggling without locks, imports `DB` without using it, and returns raw models directly.

---

## Overall Score

**Overall Score:** 4.5/10

**Decision:** 🔴 **REQUEST CHANGES**

---

## Strengths

- Uses route model binding for `Promotion $promotion`.
- Uses shared `ApiResponse` helpers.
- Validates basic promotion fields on create/update.
- Enforces unique promotion codes on create/update.
- Normalizes promotion code to uppercase before persistence.
- Prevents deleting promotions with `usage_count > 0`.
- Uses fixed pagination size of 10 for list responses.

---

## Issues

### Issue #1

**Severity:** Critical  
**Category:** Security / Authorization  
**Location:** app/Http/Controllers/Admin/PromotionController.php:15-127

**Problem**

No method shows authentication, authorization middleware, policy, gate, or permission check.

```php
public function store(Request $request)
```

```php
public function update(Request $request, Promotion $promotion)
```

```php
public function destroy(Promotion $promotion)
```

```php
public function resetUsageCount(Promotion $promotion)
```

**Why this matters**

Promotion management directly affects revenue. Unauthorized users could create 100% discounts, reactivate expired promotions, reset usage limits, or modify active campaigns. This can lose money immediately.

**How to fix**

Add explicit middleware and policies.

```php
public function __construct()
{
    $this->middleware(['auth', 'permission:promotions.manage']);
}
```

Authorize per action:

```php
$this->authorize('update', $promotion);
$this->authorize('delete', $promotion);
```

---

### Issue #2

**Severity:** Critical  
**Category:** Business Logic / Revenue Integrity  
**Location:** app/Http/Controllers/Admin/PromotionController.php:124-127

**Problem**

The controller allows resetting `usage_count` to zero.

```php
public function resetUsageCount(Promotion $promotion)
{
    $promotion->update(['usage_count' => 0]);
    return $this->successResponse($promotion, 'Usage count reset successfully');
}
```

**Why this matters**

`usage_count` is a redemption counter. Resetting it destroys auditability and can reopen a limited promotion after customers already consumed the quota. This can cause financial loss, campaign overuse, reconciliation failures, and fraud investigation gaps.

**How to fix**

Do not reset redemption history. If a campaign needs more usage, increase `usage_limit` through an audited workflow.

```php
$promotion->update([
    'usage_limit' => $newLimit,
]);
```

Store redemptions in an immutable promotion usage table and derive counts from that table.

---

### Issue #3

**Severity:** High  
**Category:** Business Logic / Discount Validation  
**Location:** app/Http/Controllers/Admin/PromotionController.php:63-66,89-92

**Problem**

`discount_type` is validated, but `discount_value` is not constrained based on type.

```php
'discount_type' => 'required|in:percent,fixed',
'discount_value' => 'required|numeric|min:0',
'max_discount_amount' => 'nullable|numeric|min:0',
```

**Why this matters**

A percent promotion can be created with `discount_value` greater than 100. A fixed discount can exceed reasonable business bounds. A percent promotion can omit `max_discount_amount`, creating unlimited discounts on large orders.

**How to fix**

Use conditional validation.

```php
'discount_value' => [
    'required',
    'numeric',
    Rule::when($request->discount_type === 'percent', ['min:0.01', 'max:100']),
    Rule::when($request->discount_type === 'fixed', ['min:1']),
],
'max_discount_amount' => [
    Rule::requiredIf($request->discount_type === 'percent'),
    'numeric',
    'min:1',
],
```

---

### Issue #4

**Severity:** High  
**Category:** Business Logic / Money Integrity  
**Location:** app/Http/Controllers/Admin/PromotionController.php:64-66,90-92

**Problem**

Money-like fields are accepted as generic numeric values.

```php
'discount_value' => 'required|numeric|min:0',
'min_order_value' => 'nullable|numeric|min:0',
'max_discount_amount' => 'nullable|numeric|min:0',
```

**Why this matters**

Generic `numeric` input can introduce precision and rounding inconsistencies in discounts, order thresholds, and maximum discount caps. Promotion calculations must be deterministic and auditable.

**How to fix**

Store money in integer minor units or enforce decimal precision.

```php
'min_order_value' => ['nullable', 'integer', 'min:0'],
'max_discount_amount' => ['nullable', 'integer', 'min:0'],
```

Or:

```php
'min_order_value' => ['nullable', 'decimal:0,2', 'min:0'],
```

---

### Issue #5

**Severity:** High  
**Category:** Business Logic / Data Integrity  
**Location:** app/Http/Controllers/Admin/PromotionController.php:82-102

**Problem**

Used promotions can still be fully modified, including code, discount type, discount value, dates, limits, and status.

```php
public function update(Request $request, Promotion $promotion)
{
    ...
    $promotion->update($validated);
```

**Why this matters**

Changing a promotion after it has been redeemed breaks historical correctness. Orders that used the promotion may no longer match the promotion definition. This creates reconciliation, refund, customer support, and audit problems.

**How to fix**

Freeze immutable fields after first use.

```php
if ($promotion->usage_count > 0) {
    unset(
        $validated['code'],
        $validated['discount_type'],
        $validated['discount_value'],
        $validated['min_order_value'],
        $validated['max_discount_amount']
    );
}
```

Better: version promotions or store a snapshot of promotion terms on each order.

---

### Issue #6

**Severity:** High  
**Category:** Data Integrity / Uniqueness  
**Location:** app/Http/Controllers/Admin/PromotionController.php:58-77

**Problem**

The code validates uniqueness before uppercasing the promotion code.

```php
'code' => 'required|string|max:50|unique:promotions,code',
...
$validated['code'] = strtoupper($validated['code']);
```

**Why this matters**

If the database collation is case-sensitive, `sale10` can pass validation even when `SALE10` already exists, then becomes `SALE10` after validation. This can cause duplicate codes or database exceptions depending on indexes/collation. If the database has no unique index, duplicate redemptions become ambiguous.

**How to fix**

Normalize before validation or use `prepareForValidation()` in a FormRequest.

```php
protected function prepareForValidation(): void
{
    $this->merge(['code' => strtoupper((string) $this->input('code'))]);
}
```

Also enforce a database unique index on normalized `code`.

---

### Issue #7

**Severity:** Medium  
**Category:** Validation / Filtering  
**Location:** app/Http/Controllers/Admin/PromotionController.php:27-33

**Problem**

`status` and `category` filters are not validated.

```php
if ($request->filled('status') && $request->status !== 'all') {
    $query->where('status', $request->status === 'active');
}

if ($request->filled('category') && $request->category !== 'all') {
    $query->where('category', $request->category);
}
```

**Why this matters**

Invalid status values are silently treated as inactive because `$request->status === 'active'` returns false. Invalid categories can trigger unnecessary database work and inconsistent API behavior.

**How to fix**

Use FormRequest validation.

```php
'status' => ['nullable', 'in:all,active,inactive'],
'category' => ['nullable', 'string', 'max:50'],
```

---

### Issue #8

**Severity:** Medium  
**Category:** Performance / Search Abuse  
**Location:** app/Http/Controllers/Admin/PromotionController.php:19-24

**Problem**

Search input is not validated or length-limited before wildcard `LIKE` queries.

```php
$query->where(function($q) use ($request) {
    $q->where('code', 'like', '%' . $request->search . '%')
      ->orWhere('name', 'like', '%' . $request->search . '%')
      ->orWhere('description', 'like', '%' . $request->search . '%');
});
```

**Why this matters**

Leading-wildcard searches can cause table scans. Very long input can increase database CPU and response time.

**How to fix**

Validate search length.

```php
'search' => ['nullable', 'string', 'max:100'],
```

Use full-text indexes where appropriate.

---

### Issue #9

**Severity:** Medium  
**Category:** Concurrency / Lost Update  
**Location:** app/Http/Controllers/Admin/PromotionController.php:118-121

**Problem**

Status toggling uses a read-modify-write pattern without locking.

```php
$promotion->update(['status' => !$promotion->status]);
```

**Why this matters**

Concurrent toggle requests can produce an unexpected final state. Admin retry behavior or double-clicks can accidentally re-enable or disable campaigns.

**How to fix**

Prefer explicit state update endpoints.

```php
$promotion->update(['status' => $request->boolean('status')]);
```

If toggle remains, lock the row.

```php
DB::transaction(function () use ($promotion) {
    $locked = Promotion::whereKey($promotion->id)->lockForUpdate()->firstOrFail();
    $locked->update(['status' => ! $locked->status]);
});
```

---

### Issue #10

**Severity:** Medium  
**Category:** Auditability / Financial Controls  
**Location:** app/Http/Controllers/Admin/PromotionController.php:56-127

**Problem**

Promotion creation, update, deletion, status toggling, and usage reset are not audited.

```php
$promotion = Promotion::create($validated);
```

```php
$promotion->update($validated);
```

```php
$promotion->update(['usage_count' => 0]);
```

**Why this matters**

Promotion changes affect revenue. Production systems need an audit trail showing actor, changed fields, previous values, new values, timestamp, and reason.

**How to fix**

Add dedicated audit logging.

```php
Log::info('Promotion updated', [
    'actor_id' => auth()->id(),
    'promotion_id' => $promotion->id,
    'changes' => $promotion->getChanges(),
]);
```

Prefer immutable audit records in a database table.

---

### Issue #11

**Severity:** Medium  
**Category:** API Serialization / Data Exposure  
**Location:** app/Http/Controllers/Admin/PromotionController.php:37,48,53,79,104,121,127

**Problem**

Raw Eloquent models and collections are returned directly through response helpers.

```php
return $this->paginatedResponse($promotions, 'Promotions retrieved successfully');
```

```php
return $this->successResponse($promotion, 'Promotion retrieved successfully');
```

**Why this matters**

Raw model serialization exposes all visible attributes and can accidentally leak future fields. Admin frontend contracts become coupled to database schema.

**How to fix**

Use API Resources.

```php
return $this->successResponse(new PromotionResource($promotion), 'Promotion retrieved successfully');
```

---

### Issue #12

**Severity:** Medium  
**Category:** Architecture / Clean Code  
**Location:** app/Http/Controllers/Admin/PromotionController.php:15-127

**Problem**

The controller directly handles querying, filtering, validation, code normalization, business rules, persistence, deletion rules, status toggling, and usage reset.

```php
$query = Promotion::query();
```

```php
$validated['code'] = strtoupper($validated['code']);
```

```php
$promotion->update(['usage_count' => 0]);
```

**Why this matters**

This is a fat controller. Promotion rules are complex and revenue-sensitive. Keeping them in the controller makes them hard to test and easy to bypass from other call sites.

**How to fix**

Move business logic into a `PromotionService` or action classes and use FormRequests for validation.

---

### Issue #13

**Severity:** Low  
**Category:** Date Validation / Business Rules  
**Location:** app/Http/Controllers/Admin/PromotionController.php:67-68,93-94

**Problem**

`start_date` and `end_date` are nullable, and there is no rule preventing active promotions from having invalid business windows.

```php
'start_date' => 'nullable|date',
'end_date' => 'nullable|date|after_or_equal:start_date',
```

**Why this matters**

A promotion can be active with no start/end window, or with a past end date during update. That may be intentional, but the controller does not make the campaign lifecycle explicit.

**How to fix**

Define explicit business rules.

```php
'start_date' => ['required_if:status,true', 'date'],
'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
```

Or support evergreen promotions through an explicit `is_evergreen` field.

---

### Issue #14

**Severity:** Low  
**Category:** Maintainability / Dead Code  
**Location:** app/Http/Controllers/Admin/PromotionController.php:9

**Problem**

`DB` is imported but never used.

```php
use Illuminate\Support\Facades\DB;
```

**Why this matters**

Unused imports add noise and indicate absent static analysis or lint enforcement.

**How to fix**

Remove the import or use transactions where required.

```php
use Illuminate\Support\Facades\DB;
```

---

### Issue #15

**Severity:** Low  
**Category:** Type Safety  
**Location:** app/Http/Controllers/Admin/PromotionController.php:15,40,51,56,82,107,118,124

**Problem**

Controller methods do not declare return types.

```php
public function index(Request $request)
```

**Why this matters**

Explicit return types improve static analysis and refactoring safety.

**How to fix**

```php
use Illuminate\Http\JsonResponse;

public function index(Request $request): JsonResponse
```

---

### Issue #16

**Severity:** Low  
**Category:** Code Style / Readability  
**Location:** app/Http/Controllers/Admin/PromotionController.php:20

**Problem**

The closure declaration does not follow common Laravel/PHP spacing style.

```php
$query->where(function($q) use ($request) {
```

**Why this matters**

Inconsistent style makes diffs noisier and suggests formatter enforcement is missing.

**How to fix**

```php
$query->where(function ($q) use ($request) {
```

Use Laravel Pint in CI.

---

## Security Review

Security concerns:

- No visible authorization for admin promotion mutation endpoints.
- Resetting usage counters can bypass campaign limits.
- Used promotions can be modified after redemption.
- Raw model serialization couples API output to database schema.
- No audit trail exists for financial promotion changes.

No direct SQL injection is visible because Eloquent query bindings and Laravel validation rules are used.

---

## Performance Review

Performance risks:

- Wildcard search on `code`, `name`, and `description`.
- Search input is not length-limited.
- Category list uses `distinct()->pluck()` without ordering or caching.
- Missing indexes cannot be confirmed from this file, but `status`, `category`, `created_at`, and `code` are queried here.

---

## Database Review

Database/data correctness concerns:

- Promotion code uniqueness is checked before uppercase normalization.
- Usage count reset destroys redemption history.
- Used promotions can be mutated.
- Discount/money fields are too loosely validated.
- No transactions are used for sensitive state changes.
- No immutable promotion redemption ledger is enforced in this file.

---

## Concurrency Review

Concurrency risks:

- Toggle endpoint can suffer lost updates.
- Usage count reset can race with active redemptions.
- Updating promotion limits while redemptions are being processed can cause inconsistent eligibility decisions.
- Deleting based on `usage_count > 0` is a stale model check and can race with a concurrent redemption.

Use row locks and immutable redemption records for financial promotion state.

---

## Laravel Best Practice Review

Recommended improvements:

- Use FormRequest classes for index/store/update.
- Normalize `code` in `prepareForValidation()`.
- Use policies for promotion management.
- Move promotion business rules into a service/action layer.
- Use API Resources instead of raw model serialization.
- Remove unused `DB` import or use actual transactions/locks.
- Add explicit return types.
- Add audit logging for all promotion mutations.

---

## Testing Review

Recommended tests:

1. Guest cannot access promotion admin endpoints.
2. Unauthorized admin cannot create/update/delete/toggle/reset promotions.
3. Percent discount greater than 100 is rejected.
4. Percent discount without `max_discount_amount` is rejected if business rules require a cap.
5. Code uniqueness is enforced after uppercase normalization.
6. Used promotion cannot mutate immutable discount fields.
7. Used promotion usage count cannot be reset.
8. Delete races with redemption cannot delete an in-use promotion.
9. Invalid status filter is rejected instead of silently mapping to inactive.
10. Search length is validated.
11. Concurrent toggle requests do not produce unexpected final state.
12. Promotion changes create audit records.

---

## Final Decision

🔴 **REQUEST CHANGES**

`Admin\PromotionController` requires significant production hardening. The highest priority issues are missing authorization, unsafe usage-count reset, weak discount/money validation, mutation of used promotions, uniqueness-before-normalization, missing audit logs, and race-prone state changes.

---

_Review completed: 2026-07-14 04:20 PM_  
_File #69/137 - Phase 4: Controllers (21/34 complete)_
