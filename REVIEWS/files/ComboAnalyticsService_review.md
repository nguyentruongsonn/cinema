# File Review: ComboAnalyticsService.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Services/ComboAnalyticsService.php  
**Lines:** 190  
**Type:** Service Layer - Analytics / Reporting

---

## File Information

**Path:** `app/Services/ComboAnalyticsService.php`  
**Type:** Laravel Service Class  
**Lines:** 190  
**Complexity:** Medium  

**Purpose:**  
Builds dashboard analytics for combo packages and individual food/drink products:
- Summary totals
- Top combos/products
- Revenue by theater
- Per-theater breakdown
- Sales trend by date

**Business Impact:** 🟠 HIGH - This file affects management reporting, revenue dashboards, and operational decision-making. Incorrect joins or duplicated aggregation can materially misrepresent revenue and sales volume.

---

## Overall Score

**Code Quality:** 5.8/10  
**Security:** 6.4/10  
**Performance:** 4.9/10  
**Maintainability:** 5.4/10  
**Laravel Best Practice:** 5.5/10  

**Overall Score:** 5.6/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

1. ✅ **Uses Query Builder Instead of Raw Interpolated SQL** - Most query values are parameter-bound by Laravel.
2. ✅ **Separates Query Construction Helpers** - `baseComboQuery()` and `baseComboWithTheaterQuery()` reduce some duplication.
3. ✅ **Uses Constants for Polymorphic Item Types** - Avoids repeating model class strings throughout the file.
4. ✅ **Casts Numeric Output** - Revenue and counts are cast before returning API-ready arrays.
5. ✅ **Supports Both Combo and Product Analytics** - Single service covers two related reporting modes.

---

## Issues Found

### Issue #1: Mutable Service State Makes the Class Unsafe and Hard to Test

**Severity:** 🟠 HIGH  
**Category:** Architecture / Correctness / Testability  
**Location:** Lines 15 and 24

**Evidence:**
```php
private string $currentType = 'food';
```

```php
$this->currentType = $type;
```

**Problem:**
The service stores request-specific state in `$currentType`.

**Why this matters:**
Laravel services may be reused depending on container binding patterns, testing setup, queue usage, or future singleton registration. Mutable state creates hidden coupling between `getStats()` and private query builders. It also makes the query methods impossible to reason about independently.

**How to fix:**
Pass `$type` explicitly into every method that needs it.

```php
public function getStats(string $startDate, string $endDate, string $type = 'food'): array
{
    $type = $this->normalizeType($type);

    return [
        'summary' => $this->getSummary($start, $end, $type),
        ...
    ];
}
```

---

### Issue #2: `$type` Is Not Validated

**Severity:** 🟡 MEDIUM  
**Category:** Validation / Business Logic  
**Location:** Lines 22-24 and 162

**Evidence:**
```php
public function getStats(string $startDate, string $endDate, string $type = 'food'): array
```

```php
$this->currentType = $type;
```

```php
if ($this->currentType === 'combo') {
    ...
} else {
    ...
}
```

**Problem:**
Any value other than exactly `'combo'` silently falls back to food/product analytics.

**Why this matters:**
Invalid API input such as `?type=combos`, `?type=all`, or a typo silently returns the wrong dataset instead of failing validation. This causes misleading reports and makes client bugs harder to detect.

**How to fix:**
Whitelist accepted values and fail explicitly.

```php
if (!in_array($type, ['food', 'combo'], true)) {
    throw new InvalidArgumentException('Invalid analytics type.');
}
```

---

### Issue #3: Date Parsing Is Not Validated or Handled

**Severity:** 🟡 MEDIUM  
**Category:** Validation / Exception Handling / API Correctness  
**Location:** Lines 25-26

**Evidence:**
```php
$start = Carbon::parse($startDate)->startOfDay();
$end   = Carbon::parse($endDate)->endOfDay();
```

**Problem:**
Invalid date strings will throw exceptions from Carbon. The service does not validate format or convert the failure into a controlled domain/API error.

**Why this matters:**
Malformed query parameters can produce inconsistent 500 responses if callers do not catch these exceptions correctly.

**How to fix:**
Use FormRequest validation before calling the service and/or validate defensively.

```php
try {
    $start = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
    $end = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();
} catch (\Throwable $e) {
    throw new InvalidArgumentException('Invalid date range.');
}
```

---

### Issue #4: No Validation That Start Date Is Before End Date

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Validation  
**Location:** Lines 25-26

**Evidence:**
```php
$start = Carbon::parse($startDate)->startOfDay();
$end   = Carbon::parse($endDate)->endOfDay();
```

**Problem:**
The service accepts reversed date ranges.

**Why this matters:**
A reversed range returns empty analytics, which can be mistaken for no sales. Reporting code should fail fast for invalid ranges.

**How to fix:**
```php
if ($start->gt($end)) {
    throw new InvalidArgumentException('Start date must be before end date.');
}
```

---

### Issue #5: No Limit on Date Range

**Severity:** 🟠 HIGH  
**Category:** Performance / Availability  
**Location:** Lines 22-34

**Evidence:**
```php
public function getStats(string $startDate, string $endDate, string $type = 'food'): array
```

**Problem:**
There is no maximum range limit. A request can ask for years of analytics.

**Why this matters:**
This method runs five aggregation queries. On a large production database, unbounded reporting windows can create slow queries, high database CPU, memory pressure, and dashboard/API timeouts.

**How to fix:**
Restrict range size at validation layer.

```php
if ($start->diffInDays($end) > 366) {
    throw new InvalidArgumentException('Date range is too large.');
}
```

---

### Issue #6: Revenue by Theater Can Be Overcounted Because It Joins Tickets

**Severity:** 🔴 CRITICAL  
**Category:** Database Correctness / Business Logic / Reporting Accuracy  
**Location:** Lines 182-188 and 96-104

**Evidence:**
```php
return $this->baseComboQuery($start, $end)
    ->join('tickets',   'tickets.order_id',   '=', 'orders.id')
    ->join('showtimes', 'showtimes.id',        '=', 'tickets.showtime_id')
    ->join('screens',   'screens.id',          '=', 'showtimes.screen_id')
    ->join('theaters',  'theaters.id',         '=', 'screens.theater_id');
```

```php
->selectRaw('theaters.id, theaters.name, SUM(order_items.total_price) as total_revenue')
```

**Problem:**
Joining `order_items` to `tickets` by `order_id` multiplies each order item by the number of tickets in the order.

**Why this matters:**
If an order has 4 tickets and 1 combo item worth 100,000, this query counts the combo revenue 4 times. Management dashboards will report inflated food/combo revenue.

**How to fix:**
Associate order items to a theater without multiplying rows. Options:
- Store theater/showtime reference on food/combo order items.
- Aggregate order items by order first, then join to a single order showtime/theater only if the domain guarantees one theater per order.
- Use a subquery selecting distinct `order_id, theater_id` if one order cannot span multiple theaters.

Example defensive direction:
```php
$ordersByTheater = DB::table('tickets')
    ->select('tickets.order_id', 'theaters.id as theater_id')
    ->join(...)
    ->groupBy('tickets.order_id', 'theaters.id');
```

Then join to the aggregated subquery carefully.

---

### Issue #7: Per-Theater Combo Breakdown Has the Same Ticket Join Multiplication Bug

**Severity:** 🔴 CRITICAL  
**Category:** Database Correctness / Reporting Accuracy  
**Location:** Lines 111-115 and 182-188

**Evidence:**
```php
$rows = $this->baseComboWithTheaterQuery($start, $end)
    ->selectRaw("theaters.name as theater_name, {$table}.name as combo_name, SUM(order_items.total_price) as total_revenue, SUM(order_items.quantity) as total_qty")
    ->groupBy('theaters.id', 'theaters.name', "{$table}.id", "{$table}.name")
    ->orderBy('theaters.name')
    ->get();
```

**Problem:**
Because `baseComboWithTheaterQuery()` joins tickets by order, each order item can be duplicated once per ticket.

**Why this matters:**
Both revenue and quantity series in chart data can be inflated. This is a direct reporting correctness defect.

**How to fix:**
Do not join item-level rows directly to ticket-level rows unless item rows are scoped to a specific ticket/showtime. Use a distinct order-to-theater mapping or persist theater context on order items.

---

### Issue #8: Ambiguous Business Rule for Multi-Theater Orders

**Severity:** 🟠 HIGH  
**Category:** Business Logic / Data Modeling  
**Location:** Lines 182-188

**Evidence:**
```php
->join('tickets',   'tickets.order_id',   '=', 'orders.id')
->join('showtimes', 'showtimes.id',        '=', 'tickets.showtime_id')
```

**Problem:**
The query assumes order-level food/combo items can be attributed to theaters through tickets, but the code does not handle orders containing tickets for multiple showtimes or theaters.

**Why this matters:**
If an order can contain multiple showtimes/theaters, the same combo revenue may be assigned to multiple theaters. If it cannot, the constraint is not represented in this code.

**How to fix:**
Make the attribution rule explicit:
- Enforce one theater/showtime per order at the domain/database level, or
- Store theater/showtime on each food/combo line item, or
- Allocate revenue proportionally using a documented rule.

---

### Issue #9: Uses Magic Order Status Value

**Severity:** 🟡 MEDIUM  
**Category:** Maintainability / Business Logic  
**Location:** Lines 13 and 173

**Evidence:**
```php
private const ORDER_PAID    = 2;
```

```php
return $query->where('orders.status', self::ORDER_PAID)
```

**Problem:**
The paid status is represented as the magic integer `2`.

**Why this matters:**
Status integers are easy to misuse and difficult to understand. If the Order model status mapping changes, analytics silently breaks.

**How to fix:**
Use an enum or a shared domain constant from the Order model.

```php
->where('orders.status', OrderStatus::PAID)
```

---

### Issue #10: Paid Orders Are Filtered by Status and `paid_at`, But `paid_at` Nullability Is Not Explicit

**Severity:** 🟡 MEDIUM  
**Category:** Database Correctness / Business Logic  
**Location:** Lines 173-174

**Evidence:**
```php
return $query->where('orders.status', self::ORDER_PAID)
             ->whereBetween('orders.paid_at', [$start, $end]);
```

**Problem:**
The query depends on both status and paid timestamp but does not explicitly require `paid_at` to be non-null.

**Why this matters:**
`whereBetween` excludes null implicitly, but the business rule should be explicit for readability and future maintenance. It also makes indexing expectations clearer.

**How to fix:**
```php
->where('orders.status', OrderStatus::PAID)
->whereNotNull('orders.paid_at')
->whereBetween('orders.paid_at', [$start, $end])
```

---

### Issue #11: Polymorphic Type Strings Are Hardcoded

**Severity:** 🟡 MEDIUM  
**Category:** Maintainability / Laravel Best Practice  
**Location:** Lines 10-11 and 164-169

**Evidence:**
```php
private const PRODUCT_TYPE  = 'App\Models\Product';
private const COMBO_TYPE    = 'App\Models\Combo';
```

```php
->where('order_items.item_type', self::COMBO_TYPE);
```

**Problem:**
The code hardcodes FQCN morph types.

**Why this matters:**
If Laravel morph maps are introduced or class namespaces change, analytics breaks. Production Laravel apps should avoid direct dependence on raw morph class strings.

**How to fix:**
Use `Product::class`, `Combo::class`, or better, use Laravel's morph map resolver consistently.

```php
use App\Models\Product;
use App\Models\Combo;

private const PRODUCT_TYPE = Product::class;
private const COMBO_TYPE = Combo::class;
```

If morph maps are used, resolve through `Relation::getMorphedModel()` / configured aliases.

---

### Issue #12: `selectRaw()` Interpolates Table Names Derived From Mutable State

**Severity:** 🟡 MEDIUM  
**Category:** Security / Maintainability  
**Location:** Lines 63, 81, and 112

**Evidence:**
```php
$table = $this->getTableAlias();
```

```php
->selectRaw("{$table}.id, {$table}.name, SUM(order_items.quantity) as total_qty, SUM(order_items.total_price) as total_revenue")
```

**Problem:**
The raw SQL string depends on `$table`, which depends on mutable `$currentType`.

**Why this matters:**
Current values are limited by `getTableAlias()`, so this is not directly injectable as written. However, combining mutable state and raw SQL creates an unnecessary risk surface and makes future changes dangerous.

**How to fix:**
Validate type strictly, remove mutable state, and build allowed table aliases through a whitelist.

```php
$table = match ($type) {
    'combo' => 'combos',
    'food' => 'products',
};
```

---

### Issue #13: Potential Heavy Result Sets Without Top-N Limits

**Severity:** 🟠 HIGH  
**Category:** Performance / Scalability  
**Location:** Lines 77-90 and 108-153

**Evidence:**
```php
->orderByDesc('total_revenue')
->get()
```

```php
foreach ($comboNames as $combo) {
    ...
}
```

**Problem:**
Top combo/product and theater-combo breakdown queries fetch all grouped rows.

**Why this matters:**
As catalog size and theater count grow, the API can return large payloads and use significant memory while building chart series.

**How to fix:**
Add limits and pagination where appropriate.

```php
->limit(20)
->get();
```

For matrix charts, cap categories or provide explicit filters.

---

### Issue #14: Five Separate Aggregation Queries Per Request

**Severity:** 🟡 MEDIUM  
**Category:** Performance / Architecture  
**Location:** Lines 28-34

**Evidence:**
```php
return [
    'summary'           => $this->getSummary($start, $end),
    'top_combos'        => $this->getTopCombos($start, $end),
    'revenue_by_theater'=> $this->getRevenueByTheater($start, $end),
    'by_theater_combo'  => $this->getByTheaterCombo($start, $end),
    'trend'             => $this->getTrend($start, $end),
];
```

**Problem:**
Every request executes multiple heavy aggregation queries.

**Why this matters:**
Dashboard endpoints are commonly refreshed repeatedly by admins. Without caching, materialized summaries, or async analytics tables, this can load the transactional database.

**How to fix:**
Add cache with a short TTL or move analytics into reporting tables.

```php
return Cache::remember($cacheKey, now()->addMinutes(5), function () {
    return [...];
});
```

---

### Issue #15: No Index Strategy Is Enforced or Documented

**Severity:** 🟡 MEDIUM  
**Category:** Performance / Database  
**Location:** Lines 157-188

**Evidence:**
```php
DB::table('order_items')
    ->join('orders', 'orders.id', '=', 'order_items.order_id')
```

```php
->where('orders.status', self::ORDER_PAID)
->whereBetween('orders.paid_at', [$start, $end]);
```

**Problem:**
The queries rely on multiple joins and date/status filtering, but the service gives no indication that required indexes exist.

**Why this matters:**
Analytics queries need indexes such as:
- `orders(status, paid_at)`
- `order_items(order_id, item_type, item_id)`
- `tickets(order_id, showtime_id)`
- `showtimes(screen_id)`
- `screens(theater_id)`

Without these, dashboard queries will degrade as data grows.

**How to fix:**
Verify migrations add these indexes and document the query requirements.

---

### Issue #16: Money Is Returned as `float`

**Severity:** 🟡 MEDIUM  
**Category:** Financial Correctness / API Consistency  
**Location:** Lines 69, 89, 103, and 124

**Evidence:**
```php
'total_revenue'   => (float) ($row->total_revenue ?? 0),
```

```php
'total_revenue' => (float) $r->total_revenue,
```

**Problem:**
Revenue values are cast to float.

**Why this matters:**
Financial reporting should avoid floating-point representation. Even for analytics, API consumers can receive values with precision artifacts.

**How to fix:**
Return integer minor units or decimal strings.

```php
'total_revenue' => (int) $r->total_revenue
```

If DB stores decimals, return string via decimal casting.

---

### Issue #17: Daily Trend Uses SQL `DATE()` on Column

**Severity:** 🟡 MEDIUM  
**Category:** Performance / Timezone Correctness  
**Location:** Lines 39-42

**Evidence:**
```php
->selectRaw('DATE(orders.paid_at) as date, SUM(order_items.quantity) as count')
->groupBy('date')
```

**Problem:**
`DATE(orders.paid_at)` applies a function to the timestamp column.

**Why this matters:**
This can reduce index usefulness and may group by database timezone rather than application/reporting timezone.

**How to fix:**
Filter using indexed timestamp ranges and explicitly define reporting timezone. For high volume, use a generated date column or analytics table.

---

### Issue #18: Trend Omits Days With Zero Sales

**Severity:** 🔵 LOW  
**Category:** API Consistency / Reporting UX  
**Location:** Lines 37-49

**Evidence:**
```php
return $rows->map(fn($r) => [
    'date'  => $r->date,
    'count' => (int) $r->count,
])->toArray();
```

**Problem:**
Only days with rows are returned.

**Why this matters:**
Chart clients must fill missing dates themselves. This creates inconsistent frontend behavior and makes API output less predictable.

**How to fix:**
Generate the full date range and fill missing dates with zero.

---

### Issue #19: Query Builder Return Types Are Missing

**Severity:** 🔵 LOW  
**Category:** Type Safety / Maintainability  
**Location:** Lines 157 and 182

**Evidence:**
```php
private function baseComboQuery(Carbon $start, Carbon $end)
```

```php
private function baseComboWithTheaterQuery(Carbon $start, Carbon $end)
```

**Problem:**
Private query builder methods have no return type.

**Why this matters:**
Missing return types reduce static analysis value and make future refactors riskier.

**How to fix:**
```php
use Illuminate\Database\Query\Builder;

private function baseComboQuery(Carbon $start, Carbon $end): Builder
```

---

### Issue #20: No Authorization Boundary in Service Contract

**Severity:** 🟡 MEDIUM  
**Category:** Authorization / Architecture  
**Location:** Lines 22-35

**Evidence:**
```php
public function getStats(string $startDate, string $endDate, string $type = 'food'): array
```

**Problem:**
The service accepts no actor/context and performs no authorization checks.

**Why this matters:**
Analytics endpoints expose revenue and operational data. Authorization may be handled in controllers/middleware, but the service contract itself does not make that requirement visible or enforceable.

**How to fix:**
Ensure controller policies/gates protect this service. For defense-in-depth, pass an actor or dedicated query object if analytics access differs by role/theater.

---

### Issue #21: Service Name and Output Names Are Misleading for Food Mode

**Severity:** 🔵 LOW  
**Category:** Readability / API Consistency  
**Location:** Lines 8, 30, 32, 71, and 77

**Evidence:**
```php
class ComboAnalyticsService
```

```php
'top_combos'        => $this->getTopCombos($start, $end),
'by_theater_combo'  => $this->getByTheaterCombo($start, $end),
```

```php
'best_combo_name' => $bestCombo?->name ?? '—',
```

**Problem:**
When `$type` is `food`, the service still returns combo-named keys.

**Why this matters:**
API consumers must know that `top_combos` sometimes means top food/drink products. This is confusing and brittle.

**How to fix:**
Rename the service and response fields to neutral terminology, such as `item_analytics`, `top_items`, `best_item_name`, and `by_theater_item`.

---

## Recommendations

### IMMEDIATE

1. **Fix the ticket join multiplication bug** in theater-based revenue and breakdown queries.
2. **Validate `$type` strictly** and remove silent fallback behavior.
3. **Validate date format, date order, and maximum date range**.
4. **Remove mutable `$currentType` service state**.
5. **Add limits to top and matrix analytics outputs**.

### SHORT TERM

6. **Replace magic order status integer with enum/domain constant**.
7. **Return money as integer minor units or decimal strings, not float**.
8. **Add caching or reporting tables for dashboard analytics**.
9. **Verify and add required database indexes**.
10. **Make theater attribution business rules explicit**.

### LONG TERM

11. **Move heavy analytics to read models/materialized summaries**.
12. **Create dedicated DTOs for analytics requests and responses**.
13. **Add tests for multi-ticket orders to prevent revenue overcounting regressions**.
14. **Add authorization tests for analytics endpoints**.
15. **Normalize API field names away from combo-only terminology**.

---

## Summary

ComboAnalyticsService.php is functional for simple reporting, but it is not production-ready for accurate revenue analytics. The highest-risk defect is the theater analytics join path: joining `order_items` to `tickets` by `order_id` multiplies order item revenue and quantity by ticket count, causing inflated reports. This issue affects both revenue-by-theater and per-theater combo breakdown output.

**Strengths:**
- Uses Laravel query builder
- Reuses base query helpers
- Supports combo and food/drink analytics
- Casts response values
- Avoids direct user input interpolation in raw SQL

**Main Gaps:**
1. Critical revenue overcounting due to ticket joins
2. Mutable service state
3. Invalid type silently falls back to food analytics
4. Unvalidated date parsing and date ranges
5. Unbounded analytics windows and result sets
6. Multiple heavy aggregation queries per request
7. Magic paid status integer
8. Float money output
9. Missing explicit authorization boundary
10. Misleading combo-specific naming for food analytics

**Status:** 🚫 Request changes before production acceptance

---

*Review completed: 2026-07-14 01:45 PM*  
*File #38/137 - Phase 3: Business Logic (10/20 complete)*
