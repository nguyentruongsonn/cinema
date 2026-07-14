# File Review: Admin/TicketStatController.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Controllers/Admin/TicketStatController.php  
**Lines:** 33  
**Type:** Admin Ticket Analytics Controller

---

## File Summary

`Admin\TicketStatController` exposes a single `stats()` endpoint for admin ticket analytics. It accepts a `StatFilterRequest`, derives a date range, calls `TicketAnalyticsService::getStats()`, and returns the result through the shared `ApiResponse` trait.

The controller is small and uses dependency injection and a FormRequest, but it is still not production-ready. It has no visible authorization, leaks raw exception messages to clients, does not log analytics failures, relies on implicit date defaults in the controller, and returns the raw service contract directly.

---

## Overall Score

**Overall Score:** 6.1/10

**Decision:** 🟠 **REQUEST CHANGES**

---

## Strengths

- Uses constructor dependency injection for `TicketAnalyticsService`.
- Uses `readonly` promoted property, reducing accidental reassignment.
- Uses `StatFilterRequest` instead of raw `Request`.
- Uses a shared `ApiResponse` trait for response consistency.
- Keeps controller logic minimal.

---

## Issues

### Issue #1

**Severity:** Critical  
**Category:** Security / Authorization  
**Location:** app/Http/Controllers/Admin/TicketStatController.php:20-31

**Problem**

The controller has no visible authentication, authorization middleware, policy, gate, or permission check.

```php
public function stats(StatFilterRequest $request)
```

**Why this matters**

Ticket analytics expose sensitive business data: sales volume, ticket counts, period performance, and operational metrics. This endpoint must be restricted to authorized admin/reporting roles.

**How to fix**

Add explicit middleware or policy checks.

```php
public function __construct(
    private readonly TicketAnalyticsService $ticketAnalyticsService
) {
    $this->middleware(['auth', 'permission:reports.ticket.view']);
}
```

Or authorize inside the method:

```php
$this->authorize('viewTicketStats');
```

---

### Issue #2

**Severity:** High  
**Category:** Security / Information Disclosure  
**Location:** app/Http/Controllers/Admin/TicketStatController.php:29-30

**Problem**

The catch block returns the raw exception message to API clients.

```php
return $this->errorResponse('Failed to retrieve ticket stats: ' . $e->getMessage(), 500);
```

**Why this matters**

Exception messages can expose table names, SQL fragments, database structure, service internals, filesystem paths, or sensitive implementation details. Admin endpoints still must not leak internals.

**How to fix**

Return a generic client message and log the detailed exception server-side.

```php
report($e);

return $this->errorResponse('Failed to retrieve ticket stats.', 500);
```

---

### Issue #3

**Severity:** Medium  
**Category:** Observability / Logging  
**Location:** app/Http/Controllers/Admin/TicketStatController.php:29-30

**Problem**

The exception handler does not log or report failures.

```php
} catch (\Throwable $e) {
    return $this->errorResponse('Failed to retrieve ticket stats: ' . $e->getMessage(), 500);
}
```

**Why this matters**

Analytics failures indicate possible broken reporting, incorrect revenue/ticket dashboards, or database query problems. Without logs, production troubleshooting and incident response become difficult.

**How to fix**

Log with context.

```php
logger()->error('Failed to retrieve ticket stats', [
    'start_date' => $startDate ?? null,
    'end_date' => $endDate ?? null,
    'exception' => $e,
]);
```

---

### Issue #4

**Severity:** Medium  
**Category:** Exception Handling / Architecture  
**Location:** app/Http/Controllers/Admin/TicketStatController.php:22-31

**Problem**

The controller catches all `\Throwable`.

```php
try {
    ...
} catch (\Throwable $e) {
    ...
}
```

**Why this matters**

Catching all throwables at the controller level can hide programming errors and bypass centralized exception handling, reporting, and standardized error formatting. It also makes it harder to distinguish expected domain errors from infrastructure failures.

**How to fix**

Let the global exception handler handle unexpected exceptions, or catch only expected domain exceptions.

```php
$stats = $this->ticketAnalyticsService->getStats($startDate, $endDate);

return $this->successResponse($stats, 'Ticket stats retrieved successfully');
```

If catching is required:

```php
catch (AnalyticsUnavailableException $e) {
    report($e);
    return $this->errorResponse('Ticket analytics are temporarily unavailable.', 503);
}
```

---

### Issue #5

**Severity:** Medium  
**Category:** Business Logic / Date Semantics  
**Location:** app/Http/Controllers/Admin/TicketStatController.php:23-24

**Problem**

The controller applies default date-range behavior directly.

```php
$startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
$endDate   = $request->input('end_date', now()->toDateString());
```

**Why this matters**

Date defaults are reporting business rules. Keeping them in the controller duplicates likely behavior across analytics endpoints and risks inconsistent date semantics between dashboard, revenue, food, combo, and ticket stats.

**How to fix**

Move date-range normalization to the FormRequest or a shared analytics date-range value object.

```php
$dateRange = $request->dateRange();

$stats = $this->ticketAnalyticsService->getStats(
    $dateRange->startDate(),
    $dateRange->endDate()
);
```

---

### Issue #6

**Severity:** Medium  
**Category:** Timezone / Reporting Correctness  
**Location:** app/Http/Controllers/Admin/TicketStatController.php:23-24

**Problem**

The endpoint uses `now()` directly to derive date boundaries.

```php
now()->startOfMonth()->toDateString()
now()->toDateString()
```

**Why this matters**

Analytics date boundaries must be explicit and consistent. If app timezone, database timezone, and business timezone differ, reports can include/exclude incorrect tickets around midnight.

**How to fix**

Use a centralized business timezone configuration and normalized date range object.

```php
$timezone = config('cinema.reporting_timezone', config('app.timezone'));
$today = now($timezone)->toDateString();
```

---

### Issue #7

**Severity:** Medium  
**Category:** API Contract / Serialization  
**Location:** app/Http/Controllers/Admin/TicketStatController.php:26-28

**Problem**

The controller returns the raw service result directly.

```php
$stats = $this->ticketAnalyticsService->getStats($startDate, $endDate);

return $this->successResponse($stats, 'Ticket stats retrieved successfully');
```

**Why this matters**

Returning raw arrays/collections from services couples the API response to the internal service implementation. Changes inside `TicketAnalyticsService` can become breaking API changes.

**How to fix**

Introduce a dedicated resource/DTO for ticket stats.

```php
return $this->successResponse(
    TicketStatsResource::make($stats),
    'Ticket stats retrieved successfully'
);
```

---

### Issue #8

**Severity:** Medium  
**Category:** Validation / Defensive Limits  
**Location:** app/Http/Controllers/Admin/TicketStatController.php:20-26

**Problem**

The controller relies on `StatFilterRequest`, but this file does not show any enforcement of maximum report range before calling analytics.

```php
public function stats(StatFilterRequest $request)
{
    ...
    $stats = $this->ticketAnalyticsService->getStats($startDate, $endDate);
}
```

**Why this matters**

Unbounded analytics date ranges can create expensive aggregate queries over large order/ticket tables. This can degrade admin dashboards and database performance.

**How to fix**

Ensure `StatFilterRequest` enforces a maximum range, or enforce it through a shared date-range object before service execution.

```php
if ($dateRange->days() > 366) {
    return $this->errorResponse('Date range is too large.', 422);
}
```

---

### Issue #9

**Severity:** Low  
**Category:** Type Safety  
**Location:** app/Http/Controllers/Admin/TicketStatController.php:20

**Problem**

The `stats()` method does not declare a return type.

```php
public function stats(StatFilterRequest $request)
```

**Why this matters**

Return types improve static analysis and make API-controller contracts clearer.

**How to fix**

```php
use Illuminate\Http\JsonResponse;

public function stats(StatFilterRequest $request): JsonResponse
```

---

### Issue #10

**Severity:** Low  
**Category:** Code Style / Readability  
**Location:** app/Http/Controllers/Admin/TicketStatController.php:24

**Problem**

Manual alignment introduces extra spacing.

```php
$endDate   = $request->input('end_date', now()->toDateString());
```

**Why this matters**

Manual alignment often causes noisy diffs and inconsistent style. Automated formatting should enforce a single style.

**How to fix**

Use normal spacing and Laravel Pint.

```php
$endDate = $request->input('end_date', now()->toDateString());
```

---

### Issue #11

**Severity:** Low  
**Category:** Documentation Drift  
**Location:** app/Http/Controllers/Admin/TicketStatController.php:16-19

**Problem**

The route path is embedded in a method comment.

```php
/**
 * Return ticket statistics for the given date range.
 * GET /api/v1/admin/tickets/stats?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
 */
```

**Why this matters**

Inline route comments easily become stale when routes change. Route definitions and OpenAPI documentation should be the source of truth.

**How to fix**

Move API documentation to OpenAPI/Scribe annotations or centralized route docs.

---

## Security Review

Security concerns:

- No visible authorization for sensitive admin reporting data.
- Raw exception messages are returned to clients.
- No audit/access logging for analytics access.

No SQL injection is visible in this controller because it delegates to `TicketAnalyticsService`; service-level query safety must be reviewed separately.

---

## Performance Review

Performance concerns:

- Controller does not visibly enforce maximum date range.
- Raw analytics service response may include heavy structures.
- Default month-to-date query is reasonable, but date-range normalization should be centralized and bounded.

---

## Database Review

This controller does not directly access the database. Database correctness depends on `TicketAnalyticsService::getStats()`, which was reviewed separately. The controller still needs to enforce validated and bounded input before delegating to analytics queries.

---

## Concurrency Review

No write-side concurrency issue is present because the controller is read-only. However, analytics reads must define consistency expectations: whether stats are realtime, eventually consistent, cached, or based on settled orders only.

---

## Laravel Best Practice Review

Recommended improvements:

- Add explicit admin/report authorization.
- Avoid returning raw exception messages.
- Use global exception handling for unexpected failures.
- Add return type.
- Move date-range defaulting/normalization into `StatFilterRequest` or a value object.
- Use a stats DTO/resource to stabilize the API contract.
- Add structured logging for failures.

---

## Testing Review

Recommended tests:

1. Guest cannot access ticket stats.
2. Non-admin/non-reporting user cannot access ticket stats.
3. Authorized reporting user can retrieve stats.
4. Missing dates default to the correct business reporting range.
5. Date range above maximum limit is rejected.
6. Invalid date order is rejected by `StatFilterRequest`.
7. Service exception returns generic error without leaking exception message.
8. Service exception is logged/reported.
9. Response shape remains stable through a DTO/resource.
10. Timezone boundary behavior is tested around month start/end.

---

## Final Decision

🟠 **REQUEST CHANGES**

`Admin\TicketStatController` is structurally cleaner than many admin controllers because it uses dependency injection, a FormRequest, and shared responses. It still must not be approved until authorization is explicit, raw exception disclosure is removed, failures are logged, and reporting date/API contracts are centralized and bounded.

---

_Review completed: 2026-07-14 04:45 PM_  
_File #74/137 - Phase 4: Controllers (26/34 complete)_
