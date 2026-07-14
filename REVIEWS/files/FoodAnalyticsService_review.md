# File Review: FoodAnalyticsService.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Services/FoodAnalyticsService.php  
**Lines:** 181  
**Type:** Service Layer - Food/Product Analytics

---

## File Information

**Path:** `app/Services/FoodAnalyticsService.php`  
**Type:** Laravel Service Class  
**Lines:** 181  
**Complexity:** Medium  

**Purpose:**  
Builds food/product analytics for paid orders, including summary cards, top food products, and daily quantity trend.

**Business Impact:** 🟠 HIGH - This service affects operational and revenue reporting for food sales. Incorrect aggregation can mislead inventory, merchandising, and revenue decisions.

---

## Overall Score

**Code Quality:** 5.7/10  
**Security:** 6.2/10  
**Performance:** 5.5/10  
**Maintainability:** 5.2/10  
**Laravel Best Practice:** 5.4/10  

**Overall Score:** 5.6/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

1. ✅ **Uses SQL Aggregation** - Totals and rankings are calculated by the database rather than by PHP loops.
2. ✅ **Uses Parameterized Query Builder** - `whereIn`, `whereBetween`, and joins avoid raw user SQL injection in current code.
3. ✅ **Limits Top Product Result Size** - `getTopProducts()` has a default limit of 10.
4. ✅ **Separates Analytics Blocks Into Private Methods** - Summary, trends, top products, and base query are separated.
5. ✅ **Type Filter Whitelist Exists** - Product type filter is restricted to known food types.

---

## Issues Found

### Issue #1: Date Inputs Are Parsed Without Validation or Error Handling

**Severity:** 🟡 MEDIUM  
**Category:** Validation / Exception Handling  
**Location:** Lines 27-30

**Evidence:**
```php
public function getStats(string $startDate, string $endDate, ?string $type = null): array
{
    $start = Carbon::parse($startDate)->startOfDay();
    $end   = Carbon::parse($endDate)->endOfDay();
```

**Problem:**
Arbitrary strings are parsed directly with `Carbon::parse()`.

**Why this matters:**
Invalid date input can throw an exception and produce inconsistent API errors if callers do not catch it. This service should not depend on undocumented upstream validation.

**How to fix:**
Validate with FormRequest and normalize before calling the service, or enforce strict parsing inside the service.

```php
$start = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
$end = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();
```

---

### Issue #2: Start Date Can Be After End Date

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Validation  
**Location:** Lines 29-30

**Evidence:**
```php
$start = Carbon::parse($startDate)->startOfDay();
$end   = Carbon::parse($endDate)->endOfDay();
```

**Problem:**
There is no check that `$start <= $end`.

**Why this matters:**
Reversed ranges silently return empty analytics, which is misleading for admin users and difficult to debug.

**How to fix:**
```php
if ($start->gt($end)) {
    throw new InvalidArgumentException('Start date must be before or equal to end date.');
}
```

---

### Issue #3: No Maximum Date Range Limit

**Severity:** 🟠 HIGH  
**Category:** Performance / Availability  
**Location:** Lines 27-43

**Evidence:**
```php
public function getStats(string $startDate, string $endDate, ?string $type = null): array
```

**Problem:**
The service allows unbounded analytics ranges.

**Why this matters:**
Large ranges can trigger multiple aggregation queries over `order_items`, `orders`, and `products`. On production data this can become expensive and degrade admin/API performance.

**How to fix:**
Enforce a maximum range.

```php
if ($start->diffInDays($end) > 366) {
    throw new InvalidArgumentException('Analytics range is too large.');
}
```

---

### Issue #4: Invalid Type Filter Is Silently Ignored

**Severity:** 🟡 MEDIUM  
**Category:** Validation / API Correctness  
**Location:** Lines 32-35

**Evidence:**
```php
$types = ($type && in_array($type, self::FOOD_TYPES, true))
    ? [$type]
    : self::FOOD_TYPES;
```

**Problem:**
If an invalid type is supplied, the service silently returns all food types.

**Why this matters:**
This creates misleading API behavior. A typo or unsupported type produces broader data than requested instead of a validation error.

**How to fix:**
Reject invalid type values.

```php
if ($type !== null && ! in_array($type, self::FOOD_TYPES, true)) {
    throw new InvalidArgumentException('Invalid food type.');
}
```

---

### Issue #5: Magic Model Class String

**Severity:** 🔵 LOW  
**Category:** Maintainability / Laravel Best Practice  
**Location:** Line 10

**Evidence:**
```php
private const PRODUCT_MODEL = 'App\Models\Product';
```

**Problem:**
The model class is hard-coded as a string.

**Why this matters:**
This is fragile during refactors and bypasses PHP static analysis.

**How to fix:**
Import the model and use class constant.

```php
use App\Models\Product;

private const PRODUCT_MODEL = Product::class;
```

---

### Issue #6: Magic Paid Status Integer

**Severity:** 🟡 MEDIUM  
**Category:** Maintainability / Business Logic  
**Location:** Lines 11 and 178

**Evidence:**
```php
private const ORDER_PAID    = 2;
```

```php
->where('orders.status', self::ORDER_PAID)
```

**Problem:**
The service defines its own integer meaning for paid orders.

**Why this matters:**
If order statuses change, analytics silently become incorrect. Status values should be centralized in an enum or domain constant.

**How to fix:**
Use a shared status enum/value object.

```php
->where('orders.status', OrderStatus::PAID)
```

---

### Issue #7: Money Values Are Returned as Float

**Severity:** 🟡 MEDIUM  
**Category:** Financial Correctness / API Consistency  
**Location:** Lines 63, 110, 114, 130, 149, and 165

**Evidence:**
```php
'total_revenue'   => (float) ($totals->total_revenue ?? 0),
```

```php
'total_revenue' => (float) $r->total_revenue,
```

**Problem:**
Financial values are cast to float.

**Why this matters:**
Binary floating-point values are not appropriate for money because they can introduce precision artifacts in API responses and downstream calculations.

**How to fix:**
Return decimal strings or integer minor units.

```php
'total_revenue' => (string) ($totals->total_revenue ?? '0.00')
```

---

### Issue #8: Returned Keys Use Combo Terminology for Food Analytics

**Severity:** 🟡 MEDIUM  
**Category:** API Design / Maintainability  
**Location:** Lines 39-41 and 65-66

**Evidence:**
```php
'top_combos'        => $this->getTopProducts($start, $end, $types), // Renamed for compatibility
'revenue_by_theater'=> [], // Food stats don't have theater breakdown
'by_theater_combo'  => ['theater_names' => [], 'combo_names' => [], 'revenue_series' => [], 'qty_series' => []],
```

```php
'best_combo_name' => $best?->name ?? '—',
'best_combo_qty'  => (int) ($best?->qty ?? 0),
```

**Problem:**
Food analytics returns combo-named keys for frontend compatibility.

**Why this matters:**
This creates a misleading API contract and hidden coupling to frontend implementation details. Future maintainers will confuse combo analytics with food analytics.

**How to fix:**
Introduce a versioned API contract or adapter layer. Use accurate domain names in the service:

```php
'top_products' => ...
'best_product_name' => ...
```

Map legacy keys at the controller/resource boundary if necessary.

---

### Issue #9: Empty Placeholder Analytics Sections Are Hardcoded

**Severity:** 🔵 LOW  
**Category:** API Design / Code Smell  
**Location:** Lines 40-41

**Evidence:**
```php
'revenue_by_theater'=> [], // Food stats don't have theater breakdown
'by_theater_combo'  => ['theater_names' => [], 'combo_names' => [], 'revenue_series' => [], 'qty_series' => []],
```

**Problem:**
The service returns empty structures for analytics it does not support.

**Why this matters:**
Consumers may interpret empty arrays as real zero-data analytics rather than unsupported metrics. This also pollutes the service with frontend compatibility concerns.

**How to fix:**
Remove unsupported fields from the food analytics contract or include explicit metadata such as `supported: false`.

---

### Issue #10: Dead Private Methods Are Kept in Production Service

**Severity:** 🟡 MEDIUM  
**Category:** Clean Code / Maintainability  
**Location:** Lines 85-168

**Evidence:**
```php
private function getSummary(Carbon $start, Carbon $end, array $types): array
```

```php
private function getTypeRatio(Carbon $start, Carbon $end): array
```

```php
private function getRevenueTrend(Carbon $start, Carbon $end, array $types, int $limit = 8): array
```

**Problem:**
`getSummary()`, `getTypeRatio()`, and `getRevenueTrend()` are not called by `getStats()` or by any code in this file.

**Why this matters:**
Dead code increases maintenance cost, test burden, and the chance that stale business logic remains in the codebase.

**How to fix:**
Remove unused methods or expose them intentionally through the service contract with tests.

---

### Issue #11: Base Query Has No Return Type

**Severity:** 🔵 LOW  
**Category:** Type Safety / Maintainability  
**Location:** Line 171

**Evidence:**
```php
private function baseQuery(Carbon $start, Carbon $end, array $types)
```

**Problem:**
The return type is omitted.

**Why this matters:**
Static analysis and IDE support are weaker. Refactoring query code becomes riskier.

**How to fix:**
Declare an explicit type.

```php
use Illuminate\Database\Query\Builder;

private function baseQuery(Carbon $start, Carbon $end, array $types): Builder
```

---

### Issue #12: Type Array Is Trusted Internally Without Revalidation

**Severity:** 🔵 LOW  
**Category:** Defensive Coding / Maintainability  
**Location:** Lines 171-179

**Evidence:**
```php
private function baseQuery(Carbon $start, Carbon $end, array $types)
{
    return DB::table('order_items')
        ...
        ->whereIn('products.type', $types)
```

**Problem:**
`baseQuery()` accepts arbitrary arrays and applies them directly to `whereIn()`.

**Why this matters:**
The method is currently private, but future changes may pass unchecked arrays and accidentally broaden or break analytics.

**How to fix:**
Keep validation centralized and consider a value object or enum for product types.

---

### Issue #13: Trend Uses `DATE(orders.paid_at)` Without Explicit Timezone Handling

**Severity:** 🟡 MEDIUM  
**Category:** Timezone Correctness / Reporting Accuracy  
**Location:** Lines 73-76

**Evidence:**
```php
->selectRaw('DATE(orders.paid_at) as date, SUM(order_items.quantity) as count')
->groupBy('date')
->orderBy('date')
```

**Problem:**
Daily grouping relies on the database timezone.

**Why this matters:**
If the application timezone and database timezone differ, sales can be assigned to the wrong day in reports.

**How to fix:**
Define reporting timezone explicitly, use a generated reporting date, or normalize `paid_at` storage and reporting rules.

---

### Issue #14: `DATE(orders.paid_at)` Grouping Can Reduce Index Efficiency

**Severity:** 🟡 MEDIUM  
**Category:** Performance  
**Location:** Lines 73-76

**Evidence:**
```php
->selectRaw('DATE(orders.paid_at) as date, SUM(order_items.quantity) as count')
->groupBy('date')
```

**Problem:**
Applying a function to a column in grouping can reduce index usefulness for reporting workloads.

**Why this matters:**
On large `orders` tables, daily trend queries may become slow.

**How to fix:**
Use a generated/indexed reporting date column or a materialized analytics table.

---

### Issue #15: Trend Returns Only Dates With Data

**Severity:** 🔵 LOW  
**Category:** API Consistency / Frontend Contract  
**Location:** Lines 73-82

**Evidence:**
```php
return $rows->map(fn($r) => [
    'date'  => $r->date,
    'count' => (int) $r->count,
])->toArray();
```

**Problem:**
Missing dates are omitted instead of returned with zero counts.

**Why this matters:**
The frontend must infer missing days, increasing duplicated logic and inconsistent chart rendering.

**How to fix:**
Return a continuous date range with zeros for missing dates.

---

### Issue #16: Analytics Query May Overcount If `order_items.item_type` Format Is Inconsistent

**Severity:** 🟡 MEDIUM  
**Category:** Database Correctness / Polymorphic Data  
**Location:** Lines 173-177

**Evidence:**
```php
->join('products', 'products.id', '=', 'order_items.item_id')
->where('order_items.item_type', self::PRODUCT_MODEL)
```

**Problem:**
The query assumes polymorphic `item_type` stores the exact string `App\Models\Product`.

**Why this matters:**
If Laravel morph maps are used or class names change, food analytics returns no rows or incorrect rows.

**How to fix:**
Use Laravel morph map constants consistently or avoid polymorphic string coupling in reporting queries.

---

### Issue #17: No Explicit Exclusion of Refunded/Cancelled Payment States Beyond Order Status

**Severity:** 🟡 MEDIUM  
**Category:** Financial Correctness / Business Logic  
**Location:** Lines 178-179

**Evidence:**
```php
->where('orders.status', self::ORDER_PAID)
->whereBetween('orders.paid_at', [$start, $end]);
```

**Problem:**
The query only checks order status and paid timestamp. It does not verify payment/refund state.

**Why this matters:**
If an order remains paid after partial/full refund or has payment anomalies, analytics can overstate revenue.

**How to fix:**
Join payments or use canonical net revenue/tendered payment state depending on the domain model.

---

### Issue #18: Revenue Uses Stored `order_items.total_price` Without Reconciliation

**Severity:** 🟡 MEDIUM  
**Category:** Financial Correctness / Reporting  
**Location:** Lines 51-52, 90-91, 122, 138, and 157

**Evidence:**
```php
SUM(order_items.total_price) as total_revenue
```

**Problem:**
The service assumes `order_items.total_price` is authoritative and already reflects discounts, refunds, taxes, and adjustments.

**Why this matters:**
If `total_price` is denormalized and not reconciled, reports can diverge from payment/order totals.

**How to fix:**
Define canonical revenue source and include reconciliation tests. If `order_items.total_price` is pre-discount, use allocated net amounts instead.

---

### Issue #19: Multiple Separate Queries Can Return Inconsistent Snapshots

**Severity:** 🔵 LOW  
**Category:** Consistency / Transactions  
**Location:** Lines 37-43 and called methods

**Evidence:**
```php
return [
    'summary'           => $this->getSummaryCompatible($start, $end, $types),
    'top_combos'        => $this->getTopProducts($start, $end, $types),
    'revenue_by_theater'=> [],
    'by_theater_combo'  => ['theater_names' => [], 'combo_names' => [], 'revenue_series' => [], 'qty_series' => []],
    'trend'             => $this->getTrend($start, $end, $types),
];
```

**Problem:**
The response is composed from multiple queries without a consistent read strategy.

**Why this matters:**
Orders paid while the dashboard is being generated can make summary, top products, and trend disagree.

**How to fix:**
For strict reporting consistency, use analytics snapshots/materialized tables or a repeatable-read transaction for report generation.

---

### Issue #20: No Caching for Analytics Results

**Severity:** 🔵 LOW  
**Category:** Performance / Scalability  
**Location:** Lines 27-43

**Evidence:**
```php
public function getStats(string $startDate, string $endDate, ?string $type = null): array
```

**Problem:**
Every request recomputes the same aggregation queries.

**Why this matters:**
Admin dashboards are often refreshed repeatedly. Without short caching, repeated identical queries can add avoidable database load.

**How to fix:**
Add short TTL caching with normalized keys and correct invalidation strategy.

---

## Recommendations

### IMMEDIATE

1. **Validate and normalize date inputs** before calling analytics queries.
2. **Reject invalid type filters** instead of silently returning all types.
3. **Add maximum date range limits** to protect production database performance.
4. **Replace magic status integer with shared order status enum/constant**.
5. **Return monetary values as decimal strings or integer minor units**.

### SHORT TERM

6. **Rename combo-compatible API fields** or isolate compatibility mapping outside this service.
7. **Remove unused private methods** or wire them into a documented API contract.
8. **Add explicit return type for `baseQuery()`**.
9. **Handle timezone explicitly for daily trend grouping**.
10. **Return continuous date series for trend data**.

### LONG TERM

11. **Create analytics read models/materialized reports** for scalable dashboard reporting.
12. **Define canonical revenue source for order items vs payments vs refunds**.
13. **Add tests for invalid filters, reversed date ranges, refund handling, and timezone boundaries**.
14. **Add short TTL caching with normalized keys and reliable invalidation**.

---

## Summary

FoodAnalyticsService.php is functional for basic food product reporting, but it is not production-ready as-is. The main concerns are weak validation, unbounded analytics queries, inaccurate/fragile API semantics caused by combo compatibility fields, magic status/model constants, float money values, and dead code. The service also needs stronger reporting correctness around refunds, timezone grouping, and canonical revenue definitions.

**Strengths:**
- Uses query builder aggregations
- Whitelists product types
- Limits top product results
- Separates query concerns into helper methods
- Avoids raw user SQL concatenation

**Main Gaps:**
1. Unvalidated date inputs and no reversed range guard
2. No maximum reporting window
3. Invalid type silently expands to all food data
4. Magic paid status and model string
5. Float money values
6. Combo terminology leaked into food analytics API
7. Dead private methods
8. Timezone ambiguity in daily trend
9. No refund/payment-state reconciliation
10. No caching or consistent snapshot strategy

**Status:** 🚫 Request changes before production acceptance

---

*Review completed: 2026-07-14 01:59 PM*  
*File #40/137 - Phase 3: Business Logic (12/20 complete)*