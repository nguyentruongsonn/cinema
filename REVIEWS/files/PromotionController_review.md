# File Review: PromotionController.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Controllers/PromotionController.php  
**Lines:** 124  
**Type:** Promotion / Voucher API Controller

---

## File Summary

`PromotionController` exposes endpoints for listing registered promotions, registering a voucher code for the authenticated user, and validating a voucher against an order total. It delegates core promotion logic to `PromotionService` and formats promotion data manually before returning API responses through the shared `ApiResponse` trait.

This controller handles money-affecting discount validation and user-specific voucher registration, so correctness and security standards must be high. The implementation has several production concerns: raw exception disclosure, nullable authenticated user handling, weak validation, incorrect HTTP status mapping, manual serialization, and discount validation based on client-supplied order totals.

---

## Overall Score

**Overall Score:** 5.9/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

- Uses constructor dependency injection for `PromotionService`.
- Uses `JsonResponse` return types.
- Uses the shared `ApiResponse` trait for response formatting.
- Performs basic validation for voucher code and order total.
- Keeps most business logic out of the controller by delegating to `PromotionService`.
- Formats output explicitly rather than returning the full promotion model directly.
- Does not perform direct SQL queries or direct database writes in the controller.

---

## Issues

### Issue #1

**Severity:** High  
**Category:** Security / Information Disclosure  
**Location:** app/Http/Controllers/PromotionController.php:28-30, 53-55, 101-103

**Problem**

The controller returns raw exception messages to API clients in all three actions.

```php
} catch (\Exception $e) {
    return $this->errorResponse('Failed to load registered promotions: ' . $e->getMessage(), 500);
}
```

```php
} catch (\Exception $e) {
    return $this->errorResponse('Failed to register promotion: ' . $e->getMessage(), 500);
}
```

```php
} catch (\Exception $e) {
    return $this->errorResponse('Failed to validate promotion: ' . $e->getMessage(), 500);
}
```

**Why this matters**

Promotion validation and registration are public/user-facing endpoints. Raw exception messages can expose SQL errors, table/column names, promotion business rules, stack internals, filesystem paths, or service implementation details. This is not production-safe.

**How to fix**

Log the exception server-side and return a generic message.

```php
use Illuminate\Support\Facades\Log;

catch (\Throwable $e) {
    Log::error('Failed to validate promotion', [
        'exception' => $e,
        'code' => $code,
        'user_id' => $request->user()?->id,
    ]);

    return $this->errorResponse('Failed to validate promotion', 500);
}
```

Prefer centralized exception handling instead of broad local catches.

---

### Issue #2

**Severity:** High  
**Category:** Security / Authentication / Correctness  
**Location:** app/Http/Controllers/PromotionController.php:23, 41, 72

**Problem**

The controller passes `$request->user()` into service methods without verifying that a user is authenticated.

```php
->getUserRegisteredPromotions($request->user())
```

```php
$request->user(),
```

```php
$result = $this->promotionService->validatePromotion($code, $orderTotal, $request->user());
```

**Why this matters**

Registered promotions and voucher registration are user-specific operations. If the route is not protected by authentication middleware, `$request->user()` may be `null`. Depending on `PromotionService`, this can cause a 500 error, incorrect validation behavior, or accidental public access to user-specific promotion flows.

**How to fix**

Protect the routes with authentication middleware and/or explicitly reject unauthenticated requests.

```php
$user = $request->user();

if (!$user) {
    return $this->errorResponse('Unauthenticated.', 401);
}

$promotions = $this->promotionService->getUserRegisteredPromotions($user);
```

A better approach is to enforce authentication at the route/controller middleware level and type assumptions accordingly.

---

### Issue #3

**Severity:** High  
**Category:** Business Logic / Money Correctness  
**Location:** app/Http/Controllers/PromotionController.php:63-72

**Problem**

The promotion validation endpoint trusts `order_total` supplied by the client.

```php
$request->validate([
    'order_total' => ['required', 'numeric', 'min:0'],
]);

$orderTotal = (float) $request->input('order_total');

$result = $this->promotionService->validatePromotion($code, $orderTotal, $request->user());
```

**Why this matters**

Voucher validity and discount amount are money-affecting. A client can submit an arbitrary `order_total` that does not match the actual cart/order total. Even if the final order creation recalculates totals later, returning a discount based on client-supplied totals can mislead the frontend and produce inconsistent checkout behavior. If any downstream flow trusts this validation result, this can lose money.

**How to fix**

Validate promotions against a server-calculated cart/order/booking total, not a raw client-provided total.

```php
$request->validate([
    'order_id' => ['required', 'integer', 'exists:orders,id'],
]);

$order = $this->orderService->findForUser($request->integer('order_id'), $request->user());
$orderTotal = $this->pricingService->calculatePayableTotal($order);

$result = $this->promotionService->validatePromotion($code, $orderTotal, $request->user());
```

If this endpoint is only an estimate endpoint, name and document it as such, and never let the final checkout use this result without recalculation.

---

### Issue #4

**Severity:** High  
**Category:** Concurrency / Business Logic  
**Location:** app/Http/Controllers/PromotionController.php:33-55

**Problem**

Voucher registration has no visible idempotency or conflict-safe response handling at the controller boundary.

```php
$result = $this->promotionService->registerPromotionForUser(
    $request->user(),
    (string) $request->input('code')
);
```

**Why this matters**

Registering a promotion is a state-changing user action. Double-clicks, retries, mobile network retries, or concurrent requests can attempt duplicate registration. This must be safe at the service/database level, but the controller also does not expose an idempotency contract or distinguish already-registered from not-found/error states.

**How to fix**

Ensure `PromotionService` performs registration inside a transaction using a database unique constraint on `(user_id, promotion_id)` and returns deterministic status codes. Consider idempotent behavior for repeated registration attempts.

```php
if ($result['already_registered']) {
    return $this->successResponse(
        $this->formatPromotion($result['promotion']),
        'Voucher already registered.'
    );
}
```

---

### Issue #5

**Severity:** Medium  
**Category:** API / HTTP Status Correctness  
**Location:** app/Http/Controllers/PromotionController.php:45-47

**Problem**

All failed registration results are returned as `404`.

```php
if (!$result['success']) {
    return $this->errorResponse($result['message'], 404);
}
```

**Why this matters**

Not every registration failure is "not found". Examples include expired voucher, inactive voucher, usage limit exceeded, already registered, unauthenticated user, not eligible, or validation failure. Returning `404` for all failures makes clients handle errors incorrectly and hides important business states.

**How to fix**

Have the service return a machine-readable error code or exception type, then map it to the correct HTTP status.

```php
$status = match ($result['reason'] ?? null) {
    'not_found' => 404,
    'expired', 'inactive', 'not_eligible', 'usage_limit_reached' => 422,
    'already_registered' => 409,
    default => 400,
};

return $this->errorResponse($result['message'], $status);
```

---

### Issue #6

**Severity:** Medium  
**Category:** Validation / Business Logic  
**Location:** app/Http/Controllers/PromotionController.php:35-37 and 63-67

**Problem**

Validation is minimal and does not normalize or constrain promotion codes beyond string length. `order_total` also allows zero.

```php
'code' => ['required', 'string', 'max:50'],
```

```php
'order_total' => ['required', 'numeric', 'min:0'],
```

**Why this matters**

Promotion codes are domain identifiers. Leading/trailing spaces, inconsistent case, invalid characters, or zero-value totals can create inconsistent matching, cache misses, unnecessary database lookups, and confusing user behavior.

**How to fix**

Normalize and validate the code format. Reject impossible order totals for voucher application if the business requires a positive amount.

```php
'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/'],
'order_total' => ['required', 'numeric', 'gt:0'],
```

Normalize before passing to the service:

```php
$code = strtoupper(trim((string) $request->input('code')));
```

---

### Issue #7

**Severity:** Medium  
**Category:** Laravel Best Practice / FormRequest  
**Location:** app/Http/Controllers/PromotionController.php:19-103

**Problem**

The controller uses inline validation for multiple actions.

```php
$request->validate([
    'code' => ['required', 'string', 'max:50'],
]);
```

```php
$request->validate([
    'order_total' => ['required', 'numeric', 'min:0'],
]);
```

**Why this matters**

Promotion registration and validation are business-sensitive flows. FormRequests would centralize validation, authorization, normalization, and test coverage. Inline validation makes the controller responsible for request semantics and makes future changes harder to maintain.

**How to fix**

Create dedicated request classes.

```php
public function register(RegisterPromotionRequest $request): JsonResponse
{
    $result = $this->promotionService->registerPromotionForUser(
        $request->user(),
        $request->validated('code')
    );
}
```

```php
public function validate(ValidatePromotionRequest $request, string $code): JsonResponse
{
    $orderTotal = $request->validated('order_total');
}
```

Use `prepareForValidation()` in the FormRequest to trim/uppercase voucher codes.

---

### Issue #8

**Severity:** Medium  
**Category:** API Contract / Response Serialization  
**Location:** app/Http/Controllers/PromotionController.php:106-123

**Problem**

The controller manually serializes promotion data instead of using an API Resource.

```php
private function formatPromotion($promotion): array
{
    return [
        'id' => $promotion->id,
        'code' => $promotion->code,
        'name' => $promotion->name ?? null,
        ...
    ];
}
```

**Why this matters**

Manual serialization in controllers is duplicated-prone and hard to keep consistent across endpoints. If admin/user promotion responses diverge, this approach can cause inconsistent fields, accidental exposure, or missing formatting rules. It also mixes presentation logic into the controller.

**How to fix**

Move serialization into a `PromotionResource`.

```php
return $this->successResponse(
    PromotionResource::collection($promotions),
    'Danh sách voucher đã đăng ký.'
);
```

If the response needs registration-specific pivot fields, include them conditionally in the resource.

---

### Issue #9

**Severity:** Medium  
**Category:** Money / Type Correctness  
**Location:** app/Http/Controllers/PromotionController.php:69, 114, 116, 117

**Problem**

The controller converts monetary values to floats.

```php
$orderTotal = (float) $request->input('order_total');
```

```php
'discount_value' => (float) $promotion->discount_value,
'max_discount_amount' => (float) ($promotion->max_discount_amount ?? 0),
'min_order_value' => (float) ($promotion->min_order_value ?? 0),
```

**Why this matters**

Money should not be represented with binary floating-point values in backend business logic or API contracts if precision matters. Float conversion can introduce rounding inconsistencies, especially when discounts are calculated, compared to minimum order values, or displayed across clients.

**How to fix**

Use integer minor units or decimal strings consistently.

```php
'discount_value' => (string) $promotion->discount_value,
'max_discount_amount' => (string) ($promotion->max_discount_amount ?? '0.00'),
'min_order_value' => (string) ($promotion->min_order_value ?? '0.00'),
```

For calculations, use decimal-safe logic or integer cents/VND minor units depending on project currency rules.

---

### Issue #10

**Severity:** Medium  
**Category:** API Consistency / Localization  
**Location:** app/Http/Controllers/PromotionController.php:27, 100 and 29, 54, 102

**Problem**

Success messages are Vietnamese while error messages are English.

```php
return $this->successResponse($promotions, 'Danh sách voucher đã đăng ký.');
```

```php
return $this->successResponse(..., 'Mã khuyến mãi hợp lệ!');
```

```php
return $this->errorResponse('Failed to validate promotion: ' . $e->getMessage(), 500);
```

**Why this matters**

Mixed response languages create inconsistent API behavior and poor client UX. API messages should be localized consistently or clients should rely on stable error codes and translate on the frontend.

**How to fix**

Use translation keys or standardize API messages.

```php
return $this->successResponse($promotions, __('promotions.registered_list'));
```

Prefer machine-readable error codes in API responses.

---

### Issue #11

**Severity:** Low  
**Category:** Exception Handling / Correctness  
**Location:** app/Http/Controllers/PromotionController.php:28, 53, 101

**Problem**

The controller catches `\Exception`, not `\Throwable`.

```php
} catch (\Exception $e) {
```

**Why this matters**

PHP `TypeError`, `Error`, and other `Throwable` failures bypass these catches, while regular exceptions are converted into custom API responses. This creates inconsistent error behavior. Broad local catches are also usually inferior to centralized exception handling.

**How to fix**

Prefer centralized exception handling. If local handling is required, catch `\Throwable`, log it, and return safe generic responses.

```php
} catch (\Throwable $e) {
    Log::error('Promotion registration failed', ['exception' => $e]);

    return $this->errorResponse('Failed to register promotion', 500);
}
```

---

### Issue #12

**Severity:** Low  
**Category:** Type Safety / Maintainability  
**Location:** app/Http/Controllers/PromotionController.php:106

**Problem**

`formatPromotion()` accepts an untyped `$promotion`.

```php
private function formatPromotion($promotion): array
```

**Why this matters**

The method assumes Eloquent-like properties and a possible pivot relation. Without a type hint, incorrect inputs fail at runtime and static analysis cannot help.

**How to fix**

Use an explicit model type.

```php
use App\Models\Promotion;

private function formatPromotion(Promotion $promotion): array
{
    ...
}
```

If different DTOs/models can be passed, create a dedicated resource/DTO instead.

---

### Issue #13

**Severity:** Low  
**Category:** Data Serialization / Date Formatting  
**Location:** app/Http/Controllers/PromotionController.php:118-120

**Problem**

Date fields are returned directly without explicit formatting.

```php
'start_date' => $promotion->start_date ?? null,
'end_date' => $promotion->end_date ?? null,
'registered_at' => $promotion->pivot->created_at ?? null,
```

**Why this matters**

Returning raw Carbon/model date objects relies on Laravel serialization behavior and model casts. API clients should receive stable, documented date formats.

**How to fix**

Format dates explicitly in a resource.

```php
'start_date' => $this->start_date?->toISOString(),
'end_date' => $this->end_date?->toISOString(),
'registered_at' => $this->whenPivotLoaded('promotion_user', fn () => $this->pivot->created_at?->toISOString()),
```

---

## Security Review

No direct SQL injection, XSS, file upload, password handling, JWT handling, or webhook verification logic exists in this file.

Security concerns:

- Raw exception messages are returned to clients.
- User-specific operations rely on `$request->user()` without visible authentication enforcement.
- Promotion validation trusts client-provided `order_total`.
- Voucher registration is state-changing and lacks visible idempotency or conflict-safe API behavior.
- Manual serialization may expose promotion fields if expanded carelessly.

---

## Performance Review

Potential performance concerns:

- `registered()` maps every returned promotion in memory with no visible pagination or hard limit.
- If `getUserRegisteredPromotions()` does not limit results or eager-load pivot data, response size and query count can grow.
- Invalid/un-normalized voucher codes can cause unnecessary service/database work.

Recommended improvements:

- Enforce service-level limits or pagination for registered promotions if users can accumulate many vouchers.
- Normalize code before lookup.
- Use API Resources for consistent serialization and conditional pivot handling.

---

## Database Review

This controller performs no direct database operations.

Database correctness depends on `PromotionService`, but the controller exposes flows that require:

- Unique constraint on user-promotion registration.
- Transactional registration.
- Atomic usage limit checks.
- Server-calculated order totals for final discount application.
- Correct decimal/money handling.

---

## Concurrency Review

Concurrency-sensitive areas:

- `register()` can receive duplicate concurrent registration attempts.
- Promotion validation based on availability/usage limits can become stale before checkout.
- Final promotion redemption must be atomic and must not rely on this validation endpoint as proof of eligibility.

Required service/database protections:

- Transactional registration.
- Unique `(user_id, promotion_id)` constraint.
- Atomic promotion usage increment during final redemption.
- Idempotent retry handling for registration and checkout.

---

## Laravel Best Practice Review

Recommended improvements:

- Use FormRequests for registration and validation requests.
- Enforce authentication via middleware or explicit guards.
- Use API Resources instead of controller-level manual formatting.
- Avoid raw exception messages and prefer centralized exception handling.
- Return accurate HTTP statuses for different business failures.
- Avoid floats for money.
- Normalize voucher codes in request preparation.

---

## Testing Review

Recommended tests:

1. Unauthenticated users cannot list registered promotions.
2. Unauthenticated users cannot register a promotion.
3. Unauthenticated users cannot validate user-specific promotion eligibility if authentication is required.
4. Raw exception messages are not returned to clients.
5. Duplicate concurrent promotion registration is idempotent or returns a deterministic conflict response.
6. Already-registered, expired, inactive, usage-limit, and not-found voucher cases return correct HTTP statuses.
7. Voucher code input is trimmed/normalized and invalid code formats are rejected.
8. Promotion validation does not trust arbitrary client totals for final checkout discount calculation.
9. Money values are serialized consistently without float precision errors.
10. Date fields are returned in a stable documented format.

---

## Final Decision

🚫 **REQUEST CHANGES**

This controller touches discount and voucher flows, so production standards must be stricter. It currently exposes raw exception details, trusts client-supplied order totals for promotion validation, does not visibly enforce authentication for user-specific operations, maps business failures to weak HTTP statuses, and manually serializes money/date values in the controller.

---

_Review completed: 2026-07-14 03:10 PM_  
_File #56/137 - Phase 4: Controllers (8/34 complete)_
