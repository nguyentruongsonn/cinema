# File Review: Admin/ComboController.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Controllers/Admin/ComboController.php  
**Lines:** 202  
**Type:** Admin Combo Management Controller

---

## File Summary

`Admin\ComboController` manages combo listing, detail retrieval, creation, update, deletion, status toggling, available product lookup, image upload, combo item replacement, and original-price calculation.

The controller is not production-ready. It mixes API orchestration, validation, file upload, database transactions, pricing calculation, and domain mutations in one class. The largest risks are missing visible authorization, raw exception disclosure, unsafe public file upload consistency, stale/incorrect product price calculations, unsafe deletion/update of combos that may already be used in orders, and weak validation of money and item semantics.

---

## Overall Score

**Overall Score:** 4.9/10

**Decision:** 🔴 **REQUEST CHANGES**

---

## Strengths

- Uses eager loading for `comboItems.product` in list/show paths.
- Wraps create/update database mutations in transactions.
- Validates combo item existence and quantity minimums.
- Restricts image MIME types beyond only `image`.
- Uses shared `ApiResponse` trait for response structure.
- Recalculates `original_price` from product prices instead of trusting client input.

---

## Issues

### Issue #1

**Severity:** Critical  
**Category:** Security / Authorization  
**Location:** app/Http/Controllers/Admin/ComboController.php:17-186

**Problem**

The controller has no visible authentication, authorization middleware, policy checks, gate checks, or permission checks for any admin combo operation.

```php
public function store(Request $request)
```

```php
public function update(Request $request, Combo $combo)
```

```php
public function destroy(Combo $combo)
```

```php
public function toggleActive(Combo $combo)
```

**Why this matters**

Combos affect sellable products, pricing, availability, and revenue. If route middleware is missing or misconfigured, unauthorized users could create discounted combos, modify prices, delete combos, or deactivate food sales.

Admin controllers should enforce permissions defensively, especially for revenue-impacting mutations.

**How to fix**

Add explicit middleware and/or policy checks.

```php
public function __construct()
{
    $this->middleware(['auth', 'permission:combos.manage']);
}
```

And use policies:

```php
$this->authorize('update', $combo);
```

---

### Issue #2

**Severity:** High  
**Category:** Security / Information Disclosure  
**Location:** app/Http/Controllers/Admin/ComboController.php:99-102, 157-160

**Problem**

Exception messages are returned directly to API clients.

```php
return $this->errorResponse('Failed to create combo: ' . $e->getMessage(), 500);
```

```php
return $this->errorResponse('Failed to update combo: ' . $e->getMessage(), 500);
```

**Why this matters**

Database errors, filesystem paths, SQL fragments, constraint names, and stack-related details can leak through exception messages. Production APIs must not expose internal failure details.

**How to fix**

Log the exception server-side and return a generic message.

```php
Log::error('Failed to create combo', [
    'exception' => $e,
    'actor_id' => auth()->id(),
]);

return $this->errorResponse('Failed to create combo.', 500);
```

---

### Issue #3

**Severity:** High  
**Category:** Database / Filesystem Consistency  
**Location:** app/Http/Controllers/Admin/ComboController.php:63-101, 118-159

**Problem**

The controller performs file uploads inside database transaction blocks using `move()` to public path, but does not clean up uploaded files if the database transaction fails.

```php
$file->move(public_path('images/products'), $filename);
$validated['image_url'] = '/images/products/' . $filename;
```

```php
DB::rollBack();
return $this->errorResponse('Failed to create combo: ' . $e->getMessage(), 500);
```

**Why this matters**

Database rollbacks do not roll back filesystem writes. A failed combo creation/update can leave orphan public files. During update, the new image can be uploaded even if item creation or original price update fails.

**How to fix**

Track uploaded path and delete it on failure. Prefer `Storage` over `move()`.

```php
$uploadedPath = null;

try {
    DB::transaction(function () use (&$uploadedPath, $request, $validated) {
        if ($request->hasFile('image_file')) {
            $uploadedPath = $request->file('image_file')->store('combos', 'public');
        }

        ...
    });
} catch (Throwable $e) {
    if ($uploadedPath) {
        Storage::disk('public')->delete($uploadedPath);
    }

    throw $e;
}
```

---

### Issue #4

**Severity:** High  
**Category:** Business Logic / Revenue Correctness  
**Location:** app/Http/Controllers/Admin/ComboController.php:53-60, 74-80, 107-115, 129-135

**Problem**

The controller allows any non-negative combo price, including `0`, and does not verify that combo price is sensible relative to product totals.

```php
'price' => 'required|numeric|min:0',
```

```php
'price' => $validated['price'],
```

**Why this matters**

A mistakenly submitted `0` or severely underpriced combo can directly lose money. The controller calculates `original_price`, but does not use it to enforce pricing rules, maximum discount, or approval requirements.

**How to fix**

Use integer minor units for money and enforce pricing policy.

```php
'price' => ['required', 'integer', 'min:1'],
```

After calculating original price:

```php
if ($comboPrice < $originalPrice * 0.5) {
    throw ValidationException::withMessages([
        'price' => 'Combo discount exceeds allowed threshold.',
    ]);
}
```

---

### Issue #5

**Severity:** High  
**Category:** Business Logic / Inventory Correctness  
**Location:** app/Http/Controllers/Admin/ComboController.php:59-60, 83-88, 113-115, 141-146

**Problem**

The controller validates `items.*.product_id` only exists, but does not require products to be active, food/drink type, in stock, or unique within the combo.

```php
'items.*.product_id' => 'required|exists:products,id',
```

**Why this matters**

An admin request can create combos using inactive products, non-food products, out-of-stock products, or duplicate product rows. Duplicate rows can produce confusing pricing and inventory calculations.

This also conflicts with `getAvailableProducts()`, which only exposes active food/drink products with stock > 0.

**How to fix**

Use constrained existence validation and distinct product IDs.

```php
'items.*.product_id' => [
    'required',
    'distinct',
    Rule::exists('products', 'id')->where(fn ($q) => $q
        ->where('status', 1)
        ->whereIn('type', ['food', 'drink'])
    ),
],
```

---

### Issue #6

**Severity:** High  
**Category:** Database Correctness / Historical Orders  
**Location:** app/Http/Controllers/Admin/ComboController.php:137-146, 163-166

**Problem**

`update()` deletes all combo items and recreates them, while `destroy()` deletes the combo without checking whether it is referenced by existing orders.

```php
$combo->comboItems()->delete();
```

```php
$combo->delete(); // Cascade xóa combo_items
```

**Why this matters**

If orders reference combos or combo items, changing or deleting combos can corrupt historical order interpretation, reporting, refunds, fulfillment, and customer receipts.

For commerce systems, sold item definitions should usually be immutable snapshots or versioned records.

**How to fix**

- Block deletion when referenced by orders.
- Version combo definitions instead of rewriting items used historically.
- Store purchased combo name/price/items snapshot on order line items.

```php
if ($combo->orderItems()->exists()) {
    return $this->errorResponse('Cannot delete combo used by orders.', 409);
}
```

---

### Issue #7

**Severity:** Medium  
**Category:** Security / File Upload Hardening  
**Location:** app/Http/Controllers/Admin/ComboController.php:56, 66-70, 111, 121-125

**Problem**

Uploaded images are moved directly into `public/images/products` using a predictable timestamp-based filename.

```php
$filename = time() . '_' . Str::slug($validated['name']) . '.' . $file->getClientOriginalExtension();
$file->move(public_path('images/products'), $filename);
```

**Why this matters**

Predictable filenames can collide under concurrent uploads within the same second for the same slug. Direct public writes bypass Laravel disk abstraction and make storage changes harder. The code also trusts the client extension after MIME validation.

**How to fix**

Use `Storage` with generated names.

```php
$path = $request->file('image_file')->storePublicly('combos', 'public');
$validated['image_url'] = Storage::url($path);
```

Add dimensions and re-encoding if production hardening is required.

---

### Issue #8

**Severity:** Medium  
**Category:** Validation / API Correctness  
**Location:** app/Http/Controllers/Admin/ComboController.php:21-32

**Problem**

List filters are not validated.

```php
if ($request->filled('search')) {
```

```php
if ($request->filled('status') && $request->status !== 'all') {
    $query->where('status', $request->status === 'active');
}
```

**Why this matters**

Invalid status values are interpreted as inactive because `$request->status === 'active'` is false. For example, `status=abc` returns inactive combos instead of a validation error. Search length is unbounded.

**How to fix**

Validate filters.

```php
$request->validate([
    'search' => ['nullable', 'string', 'max:255'],
    'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
]);
```

---

### Issue #9

**Severity:** Medium  
**Category:** API Consistency / Data Exposure  
**Location:** app/Http/Controllers/Admin/ComboController.php:40, 47, 98, 156, 173, 186

**Problem**

The controller returns raw Eloquent models and collections.

```php
return $this->successResponse($combo, 'Combo retrieved successfully');
```

```php
return $this->successResponse($products, 'Available products retrieved successfully');
```

**Why this matters**

Raw model serialization exposes API output to database/model changes and may leak internal fields later. It also creates unstable frontend contracts.

**How to fix**

Use API resources.

```php
return $this->successResponse(new ComboResource($combo), 'Combo retrieved successfully');
```

```php
return $this->successResponse(ProductOptionResource::collection($products), 'Available products retrieved successfully');
```

---

### Issue #10

**Severity:** Medium  
**Category:** Performance / Maintainability  
**Location:** app/Http/Controllers/Admin/ComboController.php:34-38, 43-47, 95-97, 153-154

**Problem**

The controller manually assigns `available_stock` by reading the same accessor.

```php
$combo->available_stock = $combo->available_stock;
```

**Why this matters**

This is a code smell and may trigger additional database work depending on how the accessor is implemented. It also mutates model instances only to force serialization.

**How to fix**

Expose computed fields through an API Resource.

```php
'available_stock' => $this->available_stock,
```

If the accessor performs queries, optimize it with eager-loaded aggregates.

---

### Issue #11

**Severity:** Medium  
**Category:** Transactions / Concurrency  
**Location:** app/Http/Controllers/Admin/ComboController.php:83-91, 141-149, 192-200

**Problem**

`updateOriginalPrice()` computes product prices after combo item writes but does not lock product rows or protect against concurrent product price changes.

```php
$originalPrice = DB::table('combo_items')
    ->join('products', 'combo_items.product_id', '=', 'products.id')
    ->where('combo_items.combo_id', $combo->id)
    ->selectRaw('SUM(products.price * combo_items.quantity) as total')
    ->value('total');
```

**Why this matters**

If product prices are edited concurrently while combo creation/update is running, `original_price` can be calculated from inconsistent product data. This is especially risky because `original_price` is used for business display/discount perception.

**How to fix**

Lock relevant products during combo creation/update or calculate from a fetched stable product set.

```php
$products = Product::whereIn('id', $productIds)->lockForUpdate()->get();
```

---

### Issue #12

**Severity:** Medium  
**Category:** Architecture / SOLID  
**Location:** app/Http/Controllers/Admin/ComboController.php:50-200

**Problem**

The controller owns validation, upload handling, transaction control, combo creation, combo item replacement, price calculation, and product lookup.

**Why this matters**

This violates single responsibility and makes the logic hard to test. Combo pricing and inventory rules should be centralized and reused by checkout/order logic.

**How to fix**

Move business logic into services:

- `ComboService::create()`
- `ComboService::update()`
- `ComboService::delete()`
- `ComboPricingService::calculateOriginalPrice()`
- `ComboImageService` or reusable upload service

---

### Issue #13

**Severity:** Medium  
**Category:** Observability / Audit Logging  
**Location:** app/Http/Controllers/Admin/ComboController.php:50-173

**Problem**

Combo create, update, delete, and status toggle operations are not audit logged.

**Why this matters**

Combos affect prices and revenue. Production systems need audit history for price/content changes, including actor ID, old values, new values, and timestamp.

**How to fix**

Log after successful commit.

```php
AuditLog::record('combo.updated', [
    'actor_id' => auth()->id(),
    'combo_id' => $combo->id,
    'changes' => $combo->getChanges(),
]);
```

---

### Issue #14

**Severity:** Low  
**Category:** Type Safety / Readability  
**Location:** app/Http/Controllers/Admin/ComboController.php:17, 43, 50, 105, 163, 169, 177, 192

**Problem**

Methods do not declare return types.

```php
public function index(Request $request)
```

```php
private function updateOriginalPrice(Combo $combo)
```

**Why this matters**

Explicit return types improve static analysis and refactoring safety.

**How to fix**

```php
use Illuminate\Http\JsonResponse;

public function index(Request $request): JsonResponse
private function updateOriginalPrice(Combo $combo): void
```

---

### Issue #15

**Severity:** Low  
**Category:** Validation / Data Quality  
**Location:** app/Http/Controllers/Admin/ComboController.php:55, 110

**Problem**

`description` has no maximum length.

```php
'description' => 'nullable|string',
```

**Why this matters**

Large descriptions can bloat responses and database rows.

**How to fix**

```php
'description' => ['nullable', 'string', 'max:1000'],
```

---

## Security Review

Security concerns:

- No visible authorization for admin combo operations.
- Raw exception details are returned to clients.
- Uploaded files are written directly to public path.
- Filename generation is predictable and collision-prone.
- Raw models/collections are returned.
- No audit trail for price-impacting changes.

No raw user SQL is visible. Query builder usage uses bindings, so direct SQL injection is not present in reviewed code.

---

## Performance Review

Performance concerns:

- `available_stock` accessor is forced on every listed combo and may perform repeated calculations.
- Search uses leading wildcard `%term%`.
- Pagination size is fixed at 10 and not client-configurable, which is safe but inflexible.
- `getAvailableProducts()` returns all active in-stock food/drink products without pagination.
- Rebuilding all combo items on every update is inefficient for large combos.

Recommended improvements:

- Use resources with optimized computed fields.
- Add pagination to available products if product catalog can grow.
- Validate search and consider full-text indexing.
- Use differential item updates or versioned combo definitions.

---

## Database Review

Database correctness concerns:

- Filesystem writes are not rolled back with DB transactions.
- Product row constraints are insufficient.
- Product price calculation can be stale under concurrent price updates.
- Combo deletion/update does not guard historical orders.
- Money uses `numeric`, which can lead to decimal precision issues depending on storage/casting.

Recommended improvements:

- Store money in integer minor units or strict decimal casts.
- Lock products during combo calculation.
- Prevent deleting/modifying combos referenced by orders.
- Use snapshots/versioning for sold combos.

---

## Concurrency Review

Concurrency risks:

- Timestamp filename collisions on simultaneous uploads.
- Concurrent combo updates can overwrite each other.
- Concurrent product price changes can produce inconsistent `original_price`.
- Deleting combo can race with checkout/order creation.
- Blind `toggleActive()` can lose updates when two admins toggle concurrently.

Recommended improvements:

- Use random filenames.
- Use explicit activate/deactivate endpoints instead of toggle.
- Use `lockForUpdate()` around combo/product rows during updates.
- Block mutations when checkout/order references exist.

---

## Laravel Best Practice Review

Recommended improvements:

- Use FormRequest classes for store/update/list.
- Use policies/gates.
- Use `Storage` instead of direct `move(public_path(...))`.
- Use API Resources.
- Move domain logic into services.
- Use `DB::transaction()` closure instead of manual begin/commit/rollback.
- Use `Throwable` instead of `Exception` if catching is necessary.
- Log exceptions instead of returning internal messages.

---

## Testing Review

Recommended tests:

1. Guest cannot access combo admin endpoints.
2. Non-admin cannot create/update/delete/toggle combos.
3. Admin without combo permission is rejected.
4. Invalid list status is rejected instead of treated as inactive.
5. Duplicate product IDs in combo items are rejected.
6. Inactive products cannot be added to combos.
7. Non-food/drink products cannot be added to combos.
8. Failed DB create removes uploaded image.
9. Failed update removes newly uploaded image and preserves existing combo image.
10. Combo used in an order cannot be deleted.
11. Combo used in an order cannot have historical item definitions corrupted.
12. Raw exception messages are not returned.
13. Concurrent uploads do not create filename collisions.
14. Concurrent product price updates do not produce inconsistent `original_price`.
15. `available_stock` does not trigger N+1 queries.

---

## Final Decision

🔴 **REQUEST CHANGES**

`Admin\ComboController` must be changed before production approval. The blocking concerns are missing visible authorization, raw exception disclosure, unsafe filesystem/database consistency, weak item/product validation, unsafe historical mutation/deletion of commerce records, and controller-heavy architecture.

---

_Review completed: 2026-07-14 03:52 PM_  
_File #63/137 - Phase 4: Controllers (15/34 complete)_