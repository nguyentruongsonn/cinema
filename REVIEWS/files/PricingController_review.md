# File Review: PricingController.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Controllers/PricingController.php  
**Lines:** 143  
**Type:** Ticket Pricing API Controller

---

## File Summary

`PricingController` exposes pricing calculation endpoints for single-ticket pricing, all customer-type pricing, showtime-derived pricing, and a weekly pricing table. It delegates core calculation to `TicketPricingService`, but still performs request validation, date parsing, showtime lookup, format resolution, response shaping, and weekly table generation inside the controller.

---

## Overall Score

**Overall Score:** 6.2/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

- Uses constructor dependency injection for `TicketPricingService`.
- Uses `ApiResponse` for consistent success responses.
- Basic validation exists for format, customer type, boolean flags, and non-negative surcharge.
- `fromShowtime()` eager-loads related `movie`, `format`, and `screen.theater` records instead of triggering obvious N+1 queries.
- Controller does not perform database writes, so there is no direct transaction or rollback risk in this file.

---

## Issues

### Issue #1

**Severity:** High  
**Category:** Business Logic / Price Correctness  
**Location:** app/Http/Controllers/PricingController.php:88-104

**Problem**

`fromShowtime()` calculates ticket price from showtime-related fields but ignores the showtime's own base price during the calculation.

```php
$result = $this->pricingService->calculate(
    format:          $formatKey,
    scheduledAt:     $showtime->scheduled_at,
    customerType:    $validated['customer_type']  ?? 'adult',
    isDoubleSeat:    (bool) ($validated['is_double_seat'] ?? false),
    movieSurcharge:  (int) ($showtime->movie?->surcharge ?? 0),
    extraHolidays:   [],
    formatSurcharge: (int) ($showtime->format?->surcharge ?? 0),
    seatSurcharge:   0, // Note: API doesn't know seat type here without seat_id
    theaterPricing:  $showtime->screen?->theater?->pricing_profile
);
```

The showtime price is only added afterward as comparison metadata.

```php
$result['showtime_base_price'] = (int) $showtime->price;
```

**Why this matters**

For a cinema booking system, pricing must be financially correct. Returning a calculated price that does not include the actual showtime base price can cause displayed prices to differ from checkout/order prices. This can lead to customer disputes, undercharging, overcharging, or abandoned checkout.

**How to fix**

Make the pricing service accept and use the showtime base price explicitly, or make this endpoint clearly return comparison-only data and not final payable price.

```php
$result = $this->pricingService->calculateForShowtime(
    showtime: $showtime,
    customerType: $validated['customer_type'] ?? 'adult',
    isDoubleSeat: (bool) ($validated['is_double_seat'] ?? false),
);
```

---

### Issue #2

**Severity:** High  
**Category:** Business Logic / Seat Pricing Correctness  
**Location:** app/Http/Controllers/PricingController.php:81-104

**Problem**

`fromShowtime()` accepts only `customer_type` and `is_double_seat`, but does not accept or resolve `seat_id` / seat type. It hardcodes seat surcharge to zero.

```php
'customer_type'  => 'sometimes|in:adult,student,child,senior',
'is_double_seat' => 'sometimes|boolean',
```

```php
seatSurcharge:   0, // Note: API doesn't know seat type here without seat_id
```

**Why this matters**

Seat type is a core dimension of cinema ticket pricing. Hardcoding seat surcharge to zero makes the endpoint unsafe for any final-price or checkout-preview use case. If the frontend uses this endpoint for booking, premium/VIP/double-seat pricing can be wrong.

**How to fix**

Require a seat identifier when calculating a showtime-specific price, validate that the seat belongs to the showtime's screen, and pass the resolved seat surcharge to the pricing service.

```php
$validated = $request->validate([
    'customer_type' => ['sometimes', 'in:adult,student,child,senior'],
    'seat_id' => ['required', 'integer', 'exists:seats,id'],
]);

$seat = Seat::query()
    ->where('screen_id', $showtime->screen_id)
    ->findOrFail($validated['seat_id']);
```

---

### Issue #3

**Severity:** High  
**Category:** Validation / Business Logic  
**Location:** app/Http/Controllers/PricingController.php:31-37 and 58-63

**Problem**

`calculate()` and `calculateAll()` allow arbitrary future/past dates accepted by Laravel's loose `date` rule.

```php
'scheduled_at'    => 'required|date',
```

**Why this matters**

Pricing rules are time-sensitive. Accepting arbitrary historical dates, extremely far future dates, or ambiguous date strings can return nonsensical prices and can be abused to probe pricing behavior. It also creates inconsistent behavior depending on PHP date parsing.

**How to fix**

Use stricter date format and business bounds.

```php
'scheduled_at' => [
    'required',
    'date_format:Y-m-d H:i:s',
    'after_or_equal:now',
    'before_or_equal:' . now()->addMonths(6)->format('Y-m-d H:i:s'),
],
```

If multiple formats are intentionally supported, normalize them in a FormRequest with explicit rules/tests.

---

### Issue #4

**Severity:** Medium  
**Category:** Architecture / Fat Controller  
**Location:** app/Http/Controllers/PricingController.php:81-110 and 123-139

**Problem**

The controller contains business orchestration and response-shaping logic, including showtime lookup, format parsing, fallback defaults, manual surcharge mapping, weekly calendar construction, and derived `day_type`.

```php
$formatLabel = $showtime->format?->name ?? '2D';
$formatKey   = str_contains(strtoupper($formatLabel), '3D') ? '3D' : '2D';
```

```php
for ($i = 0; $i < 7; $i++) {
    $day  = Carbon::today()->addDays($i);
    $rows = [];

    foreach (['10:30', '15:00', '20:00', '23:00'] as $time) {
        $dt = Carbon::parse($day->toDateString() . ' ' . $time);
        $rows[$time] = $this->pricingService->calculateAll($format, $dt);
    }
```

**Why this matters**

Pricing is business-critical and should be centralized. Keeping orchestration in the controller makes pricing behavior harder to test, reuse, audit, and keep consistent with checkout/order creation.

**How to fix**

Move showtime-derived pricing and weekly table generation into a dedicated service method.

```php
$result = $this->pricingService->calculateForShowtime($showtime, $validated);
```

```php
$table = $this->pricingService->weeklyTable($validated['format']);
```

---

### Issue #5

**Severity:** Medium  
**Category:** Laravel Best Practice / FormRequest  
**Location:** app/Http/Controllers/PricingController.php:29-37, 56-63, 81-86, 118-121

**Problem**

All endpoints use inline validation instead of dedicated FormRequest classes.

```php
$validated = $request->validate([
    'format'          => 'required|in:2D,3D',
    'scheduled_at'    => 'required|date',
    'customer_type'   => 'sometimes|in:adult,student,child,senior',
    'is_double_seat'  => 'sometimes|boolean',
    'movie_surcharge' => 'sometimes|integer|min:0',
]);
```

**Why this matters**

Inline validation duplicates rules, makes authorization unavailable at the request layer, and makes pricing input behavior harder to test independently.

**How to fix**

Create dedicated requests such as `CalculateTicketPriceRequest`, `CalculateAllTicketPricesRequest`, `ShowtimePricingRequest`, and `WeeklyPricingTableRequest`.

---

### Issue #6

**Severity:** Medium  
**Category:** Security / Information Exposure  
**Location:** app/Http/Controllers/PricingController.php:106-108

**Problem**

`fromShowtime()` exposes internal pricing comparison fields directly.

```php
$result['showtime_base_price'] = (int) $showtime->price;
$result['showtime_id']         = $showtime->id;
```

**Why this matters**

Exposing internal base price alongside calculated pricing can leak implementation details of the pricing model. If promotional, surcharge, or theater pricing rules are intended to be opaque, this gives clients more information than necessary.

**How to fix**

Return an explicit public DTO/resource containing only fields required by the frontend.

```php
return $this->successResponse(
    new ShowtimePricingResource($result),
    'Showtime ticket price calculated'
);
```

---

### Issue #7

**Severity:** Medium  
**Category:** API Correctness / Exception Handling  
**Location:** app/Http/Controllers/PricingController.php:39-47, 65-72, 88-110, 123-141

**Problem**

The controller does not handle service/domain exceptions explicitly and relies on framework defaults for all failures.

```php
$result = $this->pricingService->calculate(...);
```

```php
$showtime = \App\Models\Showtime::with(['movie', 'format', 'screen.theater'])->findOrFail($id);
```

**Why this matters**

Framework validation and model-not-found handling may be acceptable if globally standardized, but service-level pricing errors can produce inconsistent API responses if they are not mapped to a domain-safe error shape. Pricing endpoints should return predictable validation/domain errors, especially if consumed by checkout flows.

**How to fix**

Define domain exceptions in the pricing service and map them through centralized exception handling or explicit controller handling.

```php
throw new InvalidPricingConfigurationException('Theater pricing profile is invalid.');
```

Then map to a consistent JSON response in `Handler` / Laravel exception rendering.

---

### Issue #8

**Severity:** Medium  
**Category:** Maintainability / Magic Values  
**Location:** app/Http/Controllers/PricingController.php:32-36, 59-62, 84-85, 128

**Problem**

The controller hardcodes pricing domain values and weekly table showtimes.

```php
'format'          => 'required|in:2D,3D',
'customer_type'   => 'sometimes|in:adult,student,child,senior',
```

```php
foreach (['10:30', '15:00', '20:00', '23:00'] as $time) {
```

**Why this matters**

Pricing dimensions and display times are domain rules. Duplicating them as magic strings makes future changes error-prone and can cause divergence between frontend, API, checkout, and reporting.

**How to fix**

Use enums/config/constants.

```php
Rule::in(CustomerType::values())
```

```php
foreach (config('cinema.pricing.weekly_table_times') as $time) {
```

---

### Issue #9

**Severity:** Medium  
**Category:** API Semantics / Unsafe GET Side Inputs  
**Location:** app/Http/Controllers/PricingController.php:18-28 and 50-55

**Problem**

The comments document `GET/POST` usage for calculation endpoints that accept request body/query pricing parameters.

```php
GET/POST /api/v1/pricing/calculate
```

```php
GET/POST /api/v1/pricing/calculate-all
```

**Why this matters**

Supporting both GET and POST for calculation can create caching ambiguity and inconsistent client behavior. GET requests with pricing parameters may be cached by browsers/proxies, logged in URLs, and treated as shareable despite being context-specific.

**How to fix**

Use one clear API convention:

- `POST /api/v1/pricing/calculate` for calculation previews with structured body.
- `GET` only for cacheable reference tables with stable query parameters.

---

### Issue #10

**Severity:** Low  
**Category:** Readability / Localization  
**Location:** app/Http/Controllers/PricingController.php:18-28, 50-55, 75-80, 90, 106, 113-117

**Problem**

Comments are written in Vietnamese in an otherwise English PHP/Laravel codebase.

```php
/**
 * Tính giá 1 vé.
 */
```

```php
// Resolve format label từ format relation hoặc fallback '2D'
```

**Why this matters**

Mixed-language comments reduce maintainability for international teams. Several comments also restate what the code does instead of explaining non-obvious business decisions.

**How to fix**

Use English consistently or remove redundant comments.

---

### Issue #11

**Severity:** Low  
**Category:** Type Safety / Laravel Best Practice  
**Location:** app/Http/Controllers/PricingController.php:29, 56, 81, 118

**Problem**

Controller actions do not declare return types.

```php
public function calculate(Request $request)
```

```php
public function weeklyTable(Request $request)
```

**Why this matters**

Explicit return types improve static analysis and reduce ambiguity about API controller contracts.

**How to fix**

Use `JsonResponse` return types.

```php
use Illuminate\Http\JsonResponse;

public function calculate(CalculateTicketPriceRequest $request): JsonResponse
```

---

### Issue #12

**Severity:** Low  
**Category:** Testability / Static Model Access  
**Location:** app/Http/Controllers/PricingController.php:88

**Problem**

The controller performs static model access directly.

```php
$showtime = \App\Models\Showtime::with(['movie', 'format', 'screen.theater'])->findOrFail($id);
```

**Why this matters**

Static model access in controllers makes the method harder to unit test and couples controller behavior directly to Eloquent. It also bypasses any showtime service/repository method that might enforce visibility, activity, or availability rules.

**How to fix**

Use route model binding or a service method.

```php
public function fromShowtime(ShowtimePricingRequest $request, Showtime $showtime): JsonResponse
```

or:

```php
$showtime = $this->pricingService->resolveShowtimeForPricing($id);
```

---

## Security Review

No direct SQL injection, XSS, file upload, or password handling issue was found in this file.

Security concerns are mostly indirect:

- Pricing internals are exposed through `showtime_base_price`.
- Loose date parsing accepts ambiguous input.
- Static showtime lookup does not visibly enforce showtime visibility/availability authorization rules.
- GET pricing endpoints may expose context-specific pricing parameters in URLs/logs if routes actually allow GET.

---

## Performance Review

Performance risks:

- `weeklyTable()` performs 28 pricing service calculations per request without caching.
- Weekly table times are hardcoded and recalculated on every request.
- No caching is used for stable pricing reference tables.
- `fromShowtime()` eager loads relationships appropriately for one showtime, so no N+1 issue is present there.

Recommended improvement:

```php
Cache::remember("pricing:weekly:{$format}:" . now()->toDateString(), 3600, fn () => ...);
```

Only cache if pricing rules do not change frequently or include proper invalidation.

---

## Database Review

Database access exists only in `fromShowtime()`.

Concerns:

- No route model binding.
- No explicit filtering for active/public showtimes.
- No seat-to-showtime/screen validation because seat is not accepted.
- No writes exist, so no transaction is required in this controller.

---

## Concurrency Review

No write concurrency issue exists in this controller because it performs calculations and reads only.

However, price correctness under concurrency is still important at checkout: this endpoint must not be treated as authoritative for final charged price unless order creation recalculates price server-side using current locked booking/order context.

---

## Laravel Best Practice Review

Recommended improvements:

- Use FormRequest classes.
- Use route model binding for showtime endpoints.
- Use `JsonResponse` return types.
- Move showtime pricing orchestration and weekly table generation into the service layer.
- Replace magic values with enums/config.
- Use API Resources/DTOs for public pricing responses.

---

## Testing Review

Recommended tests:

1. `calculate()` rejects unsupported formats and customer types.
2. `calculate()` rejects ambiguous/invalid/past/far-future `scheduled_at` values after stricter validation is added.
3. `fromShowtime()` includes the correct base showtime pricing in the returned final price.
4. `fromShowtime()` validates seat belongs to the showtime screen when seat-specific pricing is introduced.
5. `weeklyTable()` returns exactly seven days and expected configured time slots.
6. Pricing response shape is consistent across all endpoints.
7. Inactive/private showtimes cannot be priced if they should not be publicly visible.

---

## Final Decision

🚫 **REQUEST CHANGES**

The controller is reasonably small and delegates core calculations, but it is not production-ready for a booking/payment system. The biggest risks are price correctness in `fromShowtime()`, missing seat-type pricing, loose date validation, magic domain values, and business orchestration living in the controller.

---

_Review completed: 2026-07-14 03:02 PM_  
_File #53/137 - Phase 4: Controllers (5/34 complete)_
