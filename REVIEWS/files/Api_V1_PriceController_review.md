====================================================

File:
app/Http/Controllers/Api/V1/PriceController.php

Overall Score:
5.8/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Uses a consistent API response trait instead of returning raw arrays directly.
- Limits the endpoint to read-only pricing catalogue data.
- Does not use raw SQL or directly concatenate database query input.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Business Logic / Correctness

Location:
app/Http/Controllers/Api/V1/PriceController.php:28

Problem

The controller silently falls back to hard-coded pricing when a theater has no `pricing_profile`:

```php
$tp = $theater->pricing_profile ?? [
    'base_price' => 70000,
    'weekend_surcharge' => 10000,
    'holiday_surcharge' => 20000,
    'happy_day_price' => 50000,
    'student_discount' => 10000,
    'beta_ten_discount' => -10000,
];
```

Why this matters

Pricing is money-flow data. Returning fabricated default prices can directly cause incorrect customer-facing prices, incorrect expectations, and revenue loss. In production, missing pricing configuration should be treated as invalid operational data, not hidden behind defaults.

How to fix

Move pricing computation to a pricing service and require a valid pricing profile. If missing, either exclude the theater with an operational warning or return a controlled configuration error.

Example

Before

```php
$tp = $theater->pricing_profile ?? [
    'base_price' => 70000,
];
```

After

```php
if (! is_array($theater->pricing_profile) || ! isset($theater->pricing_profile['base_price'])) {
    Log::warning('Theater missing pricing profile', ['theater_id' => $theater->id]);
    continue;
}

$tp = $theater->pricing_profile;
```

----------------------------------------------------

### Issue #2

Severity:
High

Category:
API Security / Information Disclosure

Location:
app/Http/Controllers/Api/V1/PriceController.php:112

Problem

The exception handler exposes raw exception messages to API clients:

```php
return $this->errorResponse('Failed to retrieve pricing data: ' . $e->getMessage(), 500);
```

Why this matters

Raw exception messages can expose table names, column names, stack context, configuration errors, or internal implementation details. This is not production-safe.

How to fix

Log the exception server-side and return a generic client-facing error.

Example

Before

```php
return $this->errorResponse('Failed to retrieve pricing data: ' . $e->getMessage(), 500);
```

After

```php
Log::error('Failed to retrieve pricing data', [
    'exception' => $e,
]);

return $this->errorResponse('Failed to retrieve pricing data', 500);
```

----------------------------------------------------

### Issue #3

Severity:
Medium

Category:
Performance

Location:
app/Http/Controllers/Api/V1/PriceController.php:20

Problem

The endpoint loads all active theaters, all formats, and all seat types without pagination, caching, selected columns, or lifecycle filtering for formats/seat types:

```php
$theaters = Theater::active()->get();
$formats = Format::all();
$seatTypes = SeatType::all();
```

Why this matters

This endpoint builds a cross-product matrix of theaters × formats × seat types. As the catalogue grows, response size and CPU work grow quickly. Public price-list endpoints are good cache candidates.

How to fix

Use selected columns, active scopes where available, and cache the computed price list with explicit invalidation when pricing inputs change.

Example

```php
return Cache::remember('public_price_matrix:v1', now()->addMinutes(10), function () {
    $theaters = Theater::active()->select(['id', 'name', 'pricing_profile'])->get();
    $formats = Format::query()->select(['id', 'name', 'surcharge'])->get();
    $seatTypes = SeatType::query()->select(['id', 'name', 'surcharge'])->get();

    // build matrix
});
```

----------------------------------------------------

### Issue #4

Severity:
Medium

Category:
Clean Code / Architecture

Location:
app/Http/Controllers/Api/V1/PriceController.php:24

Problem

The controller contains pricing calculation business logic, including theater pricing profile parsing, format surcharge application, discount rules, seat surcharge notes, and presentation labels.

Why this matters

Controllers should orchestrate HTTP concerns, not own pricing rules. Pricing logic in controllers is hard to test, easy to duplicate, and risky to change because it affects money-flow behavior.

How to fix

Move this logic to a dedicated service such as `PriceMatrixService` or reuse the existing pricing service if applicable. Return a DTO/API Resource from the service.

Example

```php
public function index(PriceMatrixService $service)
{
    return $this->successResponse(
        $service->publicMatrix(),
        'Pricing data retrieved successfully'
    );
}
```

----------------------------------------------------

### Issue #5

Severity:
Medium

Category:
Database Correctness / Domain Modeling

Location:
app/Http/Controllers/Api/V1/PriceController.php:49

Problem

The standard seat type is detected by comparing localized display names:

```php
if (strtolower($st->name) === 'standard' || strtolower($st->name) === 'thường') {
```

Why this matters

Business logic depending on translated display names is fragile. Renaming a seat type, changing capitalization, or adding another locale will change pricing behavior. This can cause incorrect price display.

How to fix

Use a stable code/slug column for seat types, for example `code = standard`, and enforce uniqueness at the database level.

Example

```php
$standardSurcharge = optional(
    $seatTypes->firstWhere('code', 'standard')
)->surcharge ?? 0;
```

----------------------------------------------------

### Issue #6

Severity:
Medium

Category:
Money Handling

Location:
app/Http/Controllers/Api/V1/PriceController.php:46

Problem

The controller converts surcharges to floats for money arithmetic:

```php
$fSur = floatval($format->surcharge ?? 0);
```

and:

```php
$sSur = floatval($seatType->surcharge ?? 0);
```

Why this matters

Money should not be represented with floating point arithmetic. Floating point precision issues can produce incorrect totals and inconsistent API output.

How to fix

Store and calculate prices as integer minor units, e.g. VND as integer amounts, or use a Money value object.

Example

```php
$formatSurcharge = (int) $format->surcharge;
$seatSurcharge = (int) $seatType->surcharge;
```

----------------------------------------------------

### Issue #7

Severity:
Low

Category:
Clean Code / Maintainability

Location:
app/Http/Controllers/Api/V1/PriceController.php:10

Problem

`Illuminate\Http\Request` is imported but never used:

```php
use Illuminate\Http\Request;
```

Why this matters

Unused imports add noise and indicate the file has not been cleaned up. This is minor but should be fixed in production code.

How to fix

Remove the unused import.

----------------------------------------------------

### Issue #8

Severity:
Low

Category:
API Design / Localization

Location:
app/Http/Controllers/Api/V1/PriceController.php:61

Problem

The API response hard-codes Vietnamese presentation labels inside backend pricing logic:

```php
'title' => 'Thứ 2, 4, 5',
```

Why this matters

API contracts should generally return stable semantic fields and let the frontend localize labels. Hard-coded text makes localization and client consistency harder.

How to fix

Return semantic keys and optionally localized labels from a translation layer.

Example

```php
[
    'period' => 'weekday',
    'days' => [2, 4, 5],
    'adult' => $fBase,
]
```

----------------------------------------------------

### Issue #9

Severity:
Low

Category:
Readability / Type Safety

Location:
app/Http/Controllers/Api/V1/PriceController.php:16

Problem

The action has no explicit return type:

```php
public function index()
```

Why this matters

Explicit return types improve maintainability and static analysis.

How to fix

Declare the response type.

Example

```php
use Illuminate\Http\JsonResponse;

public function index(): JsonResponse
```

----------------------------------------------------

Summary

This endpoint is functionally simple but not production-ready for pricing data. The biggest problems are fabricated default prices, raw exception disclosure, float-based money arithmetic, and pricing business logic living directly in the controller. These should be fixed before relying on this API for customer-facing pricing.