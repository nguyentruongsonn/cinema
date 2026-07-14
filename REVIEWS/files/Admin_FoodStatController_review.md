# File Review: Admin/FoodStatController.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Controllers/Admin/FoodStatController.php  
**Lines:** 35  
**Type:** Admin Food Statistics Controller

---

## File Summary

`Admin\FoodStatController` exposes a food analytics endpoint. It accepts a validated `StatFilterRequest`, reads `start_date`, `end_date`, and optional `type`, delegates calculation to `FoodAnalyticsService`, and returns results through `ApiResponse`.

The controller is concise and uses dependency injection, but it is not production-ready. It lacks visible authorization, returns raw exception messages to clients, does not log failures, includes an unused import, has no explicit return type, and exposes raw service output as the API contract.

---

## Overall Score

**Overall Score:** 6.1/10

**Decision:** 🔴 **REQUEST CHANGES**

---

## Strengths

- Uses constructor dependency injection for `FoodAnalyticsService`.
- Uses `readonly` promoted property.
- Uses `StatFilterRequest` instead of base request validation in the controller.
- Delegates analytics logic to a service.
- Uses shared `ApiResponse` helpers.

---

## Issues

### Issue #1

**Severity:** Critical  
**Category:** Security / Authorization  
**Location:** app/Http/Controllers/Admin/FoodStatController.php:21-33

**Problem**

The admin food statistics endpoint has no visible authentication, authorization middleware, policy, gate, or permission check.

```php
public function stats(StatFilterRequest $request)
```

**Why this matters**

Food analytics can expose business-sensitive sales performance, product demand, revenue trends, inventory signals, and operational data. If route-level middleware is missing or misconfigured, unauthorized users can access confidential business intelligence.

**How to fix**

Add explicit middleware and/or permission checks.

```php
public function __construct(
    private readonly FoodAnalyticsService $foodAnalyticsService
) {
    $this->middleware(['auth', 'permission:analytics.view']);
}
```

Or authorize in the action:

```php
$this->authorize('viewFoodAnalytics');
```

---

### Issue #2

**Severity:** High  
**Category:** Security / Information Disclosure  
**Location:** app/Http/Controllers/Admin/FoodStatController.php:31-33

**Problem**

The controller returns raw exception messages to API clients.

```php
return $this->errorResponse('Failed to retrieve food stats: ' . $e->getMessage(), 500);
```

**Why this matters**

Exception messages can expose SQL errors, schema details, service internals, cache keys, filesystem paths, or infrastructure details. Production APIs must not disclose internal exception messages.

**How to fix**

Log the exception server-side and return a generic error.

```php
Log::error('Failed to retrieve food stats', [
    'exception' => $e,
    'actor_id' => auth()->id(),
    'start_date' => $startDate ?? null,
    'end_date' => $endDate ?? null,
    'type' => $type ?? null,
]);

return $this->errorResponse('Failed to retrieve food stats.', 500);
```

---

### Issue #3

**Severity:** Medium  
**Category:** Observability / Exception Handling  
**Location:** app/Http/Controllers/Admin/FoodStatController.php:23-33

**Problem**

The catch block suppresses exceptions without logging.

```php
} catch (\Throwable $e) {
    return $this->errorResponse('Failed to retrieve food stats: ' . $e->getMessage(), 500);
}
```

**Why this matters**

Analytics failures need production visibility. Without structured logs, operators cannot diagnose failed reports, invalid filters, service failures, or database performance issues.

**How to fix**

Add structured logging with request context.

```php
Log::error('Food stats request failed', [
    'actor_id' => auth()->id(),
    'start_date' => $startDate ?? null,
    'end_date' => $endDate ?? null,
    'type' => $type ?? null,
    'exception' => $e,
]);
```

---

### Issue #4

**Severity:** Medium  
**Category:** Validation / Business Logic  
**Location:** app/Http/Controllers/Admin/FoodStatController.php:24-28

**Problem**

The controller reads `type` from the request and passes it to the service, but this file does not show any explicit type validation at the controller boundary.

```php
$type      = $request->input('type');  // optional

$stats = $this->foodAnalyticsService->getStats($startDate, $endDate, $type);
```

**Why this matters**

The PHPDoc documents `type=popcorn|drink|snack`, but without visible validation in this file, invalid values can reach the service. This is especially risky because `FoodAnalyticsService` was separately reviewed with concerns around invalid type handling expanding or changing analytics scope.

**How to fix**

Ensure `StatFilterRequest` validates `type` for this endpoint or use a dedicated request class.

```php
'type' => ['nullable', Rule::in(['popcorn', 'drink', 'snack'])],
```

A dedicated `FoodStatFilterRequest` would be clearer than overloading a generic stats request.

---

### Issue #5

**Severity:** Medium  
**Category:** API Consistency / Data Contract  
**Location:** app/Http/Controllers/Admin/FoodStatController.php:28-30

**Problem**

The controller returns raw service output directly.

```php
$stats = $this->foodAnalyticsService->getStats($startDate, $endDate, $type);

return $this->successResponse($stats, 'Food stats retrieved successfully');
```

**Why this matters**

The API response shape is coupled to `FoodAnalyticsService::getStats()`. Any internal service change can become a breaking API change for clients. Analytics responses should be explicitly serialized.

**How to fix**

Use a resource or DTO.

```php
return $this->successResponse(
    new FoodStatsResource($stats),
    'Food stats retrieved successfully'
);
```

---

### Issue #6

**Severity:** Low  
**Category:** Clean Code / Unused Import  
**Location:** app/Http/Controllers/Admin/FoodStatController.php:9

**Problem**

`Illuminate\Http\Request` is imported but never used.

```php
use Illuminate\Http\Request;
```

**Why this matters**

Unused imports add noise and reduce code cleanliness. They also indicate insufficient static analysis or linting enforcement.

**How to fix**

Remove the unused import.

```php
use Illuminate\Http\Request;
```

Delete the line entirely.

---

### Issue #7

**Severity:** Low  
**Category:** Type Safety / Readability  
**Location:** app/Http/Controllers/Admin/FoodStatController.php:21

**Problem**

The action does not declare a return type.

```php
public function stats(StatFilterRequest $request)
```

**Why this matters**

Explicit return types improve static analysis, IDE support, and refactoring safety.

**How to fix**

```php
use Illuminate\Http\JsonResponse;

public function stats(StatFilterRequest $request): JsonResponse
```

---

### Issue #8

**Severity:** Low  
**Category:** Readability / Style  
**Location:** app/Http/Controllers/Admin/FoodStatController.php:24-26

**Problem**

The controller uses column-aligned assignments and an inline comment.

```php
$startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
$endDate   = $request->input('end_date', now()->toDateString());
$type      = $request->input('type');  // optional
```

**Why this matters**

Column alignment creates noisy diffs when variable names change, and the `// optional` comment repeats what nullable request validation should express.

**How to fix**

```php
$startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
$endDate = $request->input('end_date', now()->toDateString());
$type = $request->input('type');
```

---

## Security Review

Security concerns:

- No visible authorization for admin food analytics.
- Raw exception messages are returned to clients.
- Optional `type` filter validation is not visible in this controller.
- No audit or access logging for sensitive analytics access.

No direct SQL injection is visible in this controller because query work is delegated to `FoodAnalyticsService`.

---

## Performance Review

The controller performs no heavy loops or direct database queries. Performance risk comes from delegating potentially expensive analytics requests without visible range enforcement or caching at this boundary.

Recommended improvements:

- Ensure `StatFilterRequest` enforces maximum date range.
- Add caching in service layer for expensive repeated reports.
- Log slow analytics requests with filters.

---

## Database Review

No direct database writes or transactions exist in this controller. Database correctness depends on `FoodAnalyticsService`.

This controller should not pass unvalidated filter values into analytics queries.

---

## Concurrency Review

No direct concurrency-sensitive writes are visible. This is a read-only endpoint. Service-level consistency matters because analytics may read orders/payments/products while they are being updated.

---

## Laravel Best Practice Review

Recommended improvements:

- Add explicit authorization.
- Use a dedicated request if food-specific filters differ from generic stat filters.
- Remove unused imports.
- Add explicit return type.
- Log exceptions and return generic errors.
- Use API resources/DTOs for stable response shape.

---

## Testing Review

Recommended tests:

1. Guest cannot access food stats.
2. Non-admin cannot access food stats.
3. Admin without analytics permission is rejected.
4. Valid date range returns expected response envelope.
5. Missing dates default correctly.
6. Invalid `type` is rejected.
7. Invalid date ranges are rejected by request validation.
8. Service exceptions are logged and return generic 500 responses.
9. Response schema remains stable regardless of service internals.
10. Unused imports/static analysis rules are enforced in CI.

---

## Final Decision

🔴 **REQUEST CHANGES**

`Admin\FoodStatController` needs production hardening before approval. The main required changes are explicit authorization, safe exception handling with logging, validated food-specific filters, a stable response contract, and cleanup of unused imports/type declarations.

---

_Review completed: 2026-07-14 04:04 PM_  
_File #66/137 - Phase 4: Controllers (18/34 complete)_
