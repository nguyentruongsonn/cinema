# File Review: RevenueService.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Services/RevenueService.php  
**Lines:** 204  
**Type:** Service Layer - Revenue Analytics

---

## File Information

**Path:** `app/Services/RevenueService.php`  
**Type:** Laravel Service Class  
**Lines:** 204  
**Complexity:** Medium  

**Purpose:**  
Builds revenue dashboard statistics for a date range, including total revenue, top theater, top movie, payment method breakdown, revenue by theater/movie, and revenue trend.

**Business Impact:** 🔴 CRITICAL - This service reports revenue and order metrics. Incorrect calculations can mislead financial reporting, business decisions, settlement reconciliation, and performance dashboards.

---

## Overall Score

**Code Quality:** 5.4/10  
**Security:** 6.0/10  
**Performance:** 5.1/10  
**Maintainability:** 5.0/10  
**Laravel Best Practice:** 5.2/10  

**Overall Score:** 5.3/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

1. ✅ **Uses Query Builder Aggregation** - Revenue totals are computed in SQL instead of PHP loops.
2. ✅ **Uses Parameter Binding for Date Filters** - `whereBetween()` avoids direct SQL interpolation for user-provided dates.
3. ✅ **Limits Movie Breakdown** - `getRevenueByMovie()` limits results to 10.
4. ✅ **Breaks Dashboard Sections Into Private Methods** - Summary, top theater, top movie, payment methods, breakdowns, and trend are separated.
5. ✅ **Adapts Trend Granularity** - Uses day/week/month grouping based on date range length.

---

## Issues Found

### Issue #1: Date Inputs Are Parsed Without Validation or Error Handling

**Severity:** 🟡 MEDIUM  
**Category:** Validation / Exception Handling  
**Location:** Lines 15-18

**Evidence:**
```php
public function getStats(string $startDate, string $endDate): array
{
    $start = Carbon::parse($startDate)->startOfDay();
    $end   = Carbon::parse($endDate)->endOfDay();
```

**Problem:**
The service parses arbitrary date strings directly with `Carbon::parse()`.

**Why this matters:**
Invalid dates can throw runtime exceptions and return inconsistent API errors. Ambiguous date strings can also be interpreted unexpectedly.

**How to fix:**
Validate date format in a FormRequest and/or enforce strict parsing inside the service.

```php
$start = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
$end = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();
```

---

### Issue #2: Start Date Can Be After End Date

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Validation  
**Location:** Lines 17-18

**Evidence:**
```php
$start = Carbon::parse($startDate)->startOfDay();
$end   = Carbon::parse($endDate)->endOfDay();
```

**Problem:**
There is no guard that `$start <= $end`.

**Why this matters:**
A reversed range silently returns zero/empty revenue, which is misleading for financial reporting.

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
**Location:** Lines 15-28

**Evidence:**
```php
public function getStats(string $startDate, string $endDate): array
```

**Problem:**
The service allows unbounded date ranges.

**Why this matters:**
`getStats()` executes multiple aggregate queries over `orders`, `payments`, `showtimes`, `screens`, `theaters`, and `movies`. A large date range over production data can create heavy database load.

**How to fix:**
Set an explicit reporting window limit.

```php
if ($start->diffInDays($end) > 366) {
    throw new InvalidArgumentException('Revenue report range is too large.');
}
```

---

### Issue #4: Magic Order Status Integer

**Severity:** 🟡 MEDIUM  
**Category:** Maintainability / Business Logic  
**Location:** Line 10

**Evidence:**
```php
private const STATUS_CONFIRMED = 2;
```

**Problem:**
The service locally defines status `2` as confirmed/paid.

**Why this matters:**
If order lifecycle constants differ elsewhere, revenue reports become incorrect. Financial code must use a single authoritative state definition.

**How to fix:**
Use a shared enum or domain constant.

```php
->where('status', OrderStatus::Confirmed->value)
```

---

### Issue #5: Revenue Is Based on `orders.total_amount` Without Payment Reconciliation

**Severity:** 🔴 CRITICAL  
**Category:** Financial Correctness / Database Correctness  
**Location:** Lines 33-36, 51-54, 62, 87, 148, 163, and 193

**Evidence:**
```php
->sum('total_amount');
```

```php
SUM(orders.total_amount) as revenue
```

**Problem:**
Revenue is calculated from `orders.total_amount` based on order status and `paid_at`, not from successful payment records or settled amounts.

**Why this matters:**
Orders can have payment anomalies, partial refunds, failed payment records, duplicated payment records, or adjusted totals. Reporting revenue from order totals alone can overstate or understate actual collected money.

**How to fix:**
Define a canonical revenue source. For financial reporting, aggregate successful captured payments or a reconciled revenue ledger.

```php
DB::table('payments')
    ->join('orders', 'orders.id', '=', 'payments.order_id')
    ->where('payments.status', PaymentStatus::Succeeded->value)
    ->sum('payments.amount');
```

---

### Issue #6: Refunded/Cancelled Payment States Are Not Excluded

**Severity:** 🟠 HIGH  
**Category:** Financial Correctness  
**Location:** Lines 33-36 and all revenue aggregation queries

**Evidence:**
```php
->where('status', self::STATUS_CONFIRMED)
->whereBetween('paid_at', [$start, $end])
```

**Problem:**
The queries do not account for refunds, chargebacks, cancelled payments, partial refunds, or payment reversals.

**Why this matters:**
Revenue dashboards will overstate net revenue if refunded orders remain confirmed.

**How to fix:**
Join payment/refund tables or use a net revenue ledger that subtracts refunds.

---

### Issue #7: Money Values Are Cast to Float

**Severity:** 🟡 MEDIUM  
**Category:** Financial Correctness / API Consistency  
**Location:** Lines 44, 75, 98, 152, 170, and 199

**Evidence:**
```php
'total_revenue' => (float) $total,
```

```php
'revenue' => (float) $r->revenue,
```

**Problem:**
Financial amounts are returned as floats.

**Why this matters:**
Float representation can introduce precision artifacts. Money values should be integer minor units or decimal strings.

**How to fix:**
Return decimal strings or cents/minor units.

```php
'total_revenue' => (string) $total
```

---

### Issue #8: `getSummary()` Performs Two Separate Queries That Can Be Combined

**Severity:** 🔵 LOW  
**Category:** Performance  
**Location:** Lines 31-41

**Evidence:**
```php
$total = DB::table('orders')
    ...
    ->sum('total_amount');

$totalOrders = DB::table('orders')
    ...
    ->count();
```

**Problem:**
The same filter is executed twice.

**Why this matters:**
This doubles work for a common dashboard metric and can return inconsistent values under concurrent writes.

**How to fix:**
Use one aggregate query.

```php
$row = DB::table('orders')
    ->where(...)
    ->selectRaw('SUM(total_amount) as total_revenue, COUNT(*) as total_orders')
    ->first();
```

---

### Issue #9: Multiple Dashboard Queries Can Return Inconsistent Snapshots

**Severity:** 🟡 MEDIUM  
**Category:** Consistency / Reporting Correctness  
**Location:** Lines 20-28

**Evidence:**
```php
return [
    'summary'           => $this->getSummary($start, $end),
    'top_theater'       => $this->getTopTheater($start, $end),
    'top_movie'         => $this->getTopMovie($start, $end),
    'payment_methods'   => $this->getPaymentMethods($start, $end),
    'by_theater'        => $this->getRevenueByTheater($start, $end),
    'by_movie'          => $this->getRevenueByMovie($start, $end),
    'by_trend'          => $this->getRevenueTrend($start, $end),
];
```

**Problem:**
The response is composed from multiple independent queries.

**Why this matters:**
Orders/payment records inserted during report generation can make sections disagree. For example, summary revenue can differ from theater/movie breakdowns.

**How to fix:**
Use materialized analytics snapshots, a reporting table, or consistent read transaction if strict consistency is required.

---

### Issue #10: `getTopTheater()` Recomputes Total Revenue Separately

**Severity:** 🔵 LOW  
**Category:** Performance / Duplication  
**Location:** Lines 49-54

**Evidence:**
```php
$totalRevenue = DB::table('orders')
    ->where('status', self::STATUS_CONFIRMED)
    ->whereBetween('paid_at', [$start, $end])
    ->sum('total_amount');
```

**Problem:**
Total revenue is recalculated separately from `getSummary()`.

**Why this matters:**
This adds redundant database load and can produce a different denominator than the summary under concurrent writes.

**How to fix:**
Compute summary once and pass total revenue into `getTopTheater()` or use a single reporting query/model.

---

### Issue #11: Top Movie `tickets` Metric Counts Orders, Not Tickets

**Severity:** 🟠 HIGH  
**Category:** Business Logic / Reporting Correctness  
**Location:** Lines 87 and 99

**Evidence:**
```php
->selectRaw('movies.id, movies.title, SUM(orders.total_amount) as revenue, COUNT(orders.id) as tickets')
```

```php
'tickets' => (int) $top->tickets,
```

**Problem:**
The field is named `tickets`, but the query counts orders.

**Why this matters:**
A single order can contain multiple tickets. This underreports ticket volume and misleads business reporting.

**How to fix:**
Join ticket/order item records and sum actual ticket quantity.

```php
SUM(order_items.quantity) as tickets
```

Only include ticket item types.

---

### Issue #12: Revenue by Movie Also Counts Orders as Tickets

**Severity:** 🟠 HIGH  
**Category:** Business Logic / Reporting Correctness  
**Location:** Lines 163 and 171

**Evidence:**
```php
->selectRaw('movies.title, SUM(orders.total_amount) as revenue, COUNT(orders.id) as tickets')
```

```php
'tickets' => (int) $r->tickets,
```

**Problem:**
The movie breakdown repeats the same incorrect ticket calculation.

**Why this matters:**
The movie revenue chart reports a `tickets` number that is actually order count.

**How to fix:**
Use ticket records or ticket order item quantities, not order count.

---

### Issue #13: Movie/Theater Revenue Attributes Entire Order Total to Showtime

**Severity:** 🔴 CRITICAL  
**Category:** Financial Correctness / Business Logic  
**Location:** Lines 56-65, 82-90, 142-153, and 158-173

**Evidence:**
```php
SUM(orders.total_amount) as revenue
```

joined through:
```php
orders.showtime_id
```

**Problem:**
The entire order total is attributed to the theater/movie of `orders.showtime_id`.

**Why this matters:**
If `orders.total_amount` includes food, combos, promotions, fees, or other non-ticket items, theater/movie revenue is inflated. Movie revenue should usually use ticket revenue only, while concession revenue should be separate.

**How to fix:**
Aggregate revenue by item type and allocation rule. For movie/theater revenue, use ticket line items only unless explicitly reporting gross order revenue.

---

### Issue #14: Payment Method Breakdown Counts Joined Rows, Not Distinct Successful Payments

**Severity:** 🟠 HIGH  
**Category:** Financial Correctness / Payment Reporting  
**Location:** Lines 105-110

**Evidence:**
```php
DB::table('orders')
    ->join('payments', 'payments.order_id', '=', 'orders.id')
    ->where('orders.status', self::STATUS_CONFIRMED)
    ->whereBetween('orders.paid_at', [$start, $end])
    ->selectRaw('payments.method, COUNT(orders.id) as count')
```

**Problem:**
The query counts payment rows joined to orders but does not filter payment status or handle multiple payment attempts per order.

**Why this matters:**
Failed/retried/duplicate payment attempts can inflate method counts or misidentify top payment method.

**How to fix:**
Filter successful payments only and count distinct paid orders or successful captured transactions depending on the metric.

```php
->where('payments.status', PaymentStatus::Succeeded->value)
->count(DB::raw('DISTINCT orders.id'))
```

---

### Issue #15: Payment Method Breakdown Does Not Include Amount by Method

**Severity:** 🟡 MEDIUM  
**Category:** Financial Reporting / API Completeness  
**Location:** Lines 103-138

**Evidence:**
```php
->selectRaw('payments.method, COUNT(orders.id) as count')
```

**Problem:**
Payment method analytics only counts orders/payments and does not report revenue amount by method.

**Why this matters:**
For finance operations, count percentage alone is not enough. A method may have low count but high revenue.

**How to fix:**
Include amount aggregation from canonical successful payment amounts.

```php
->selectRaw('payments.method, COUNT(*) as count, SUM(payments.amount) as amount')
```

---

### Issue #16: `selectRaw("{$groupBy}...")` Uses Dynamic SQL String Construction

**Severity:** 🟡 MEDIUM  
**Category:** Security / Maintainability  
**Location:** Lines 181-193

**Evidence:**
```php
$groupBy = 'DATE_FORMAT(paid_at, \'%Y-%m\') as period';
...
->selectRaw("{$groupBy}, SUM(total_amount) as revenue, COUNT(id) as orders")
```

**Problem:**
The current `$groupBy` values are internally selected, so this is not directly exploitable in this file. However, the pattern builds SQL by string concatenation.

**Why this matters:**
This pattern becomes dangerous if future code lets user input influence grouping. It also makes static analysis and cross-database portability worse.

**How to fix:**
Keep allowed SQL expressions in a fixed map and never concatenate request values into raw SQL.

---

### Issue #17: Trend Grouping Uses Database Timezone

**Severity:** 🟡 MEDIUM  
**Category:** Timezone Correctness / Reporting Accuracy  
**Location:** Lines 181-187

**Evidence:**
```php
$groupBy = 'DATE_FORMAT(paid_at, \'%Y-%m\') as period';
...
$groupBy = 'DATE(paid_at) as period';
```

**Problem:**
Grouping uses MySQL date functions on `paid_at` without explicit timezone conversion.

**Why this matters:**
If app timezone and DB timezone differ, revenue near midnight can be grouped into the wrong day/week/month.

**How to fix:**
Define reporting timezone explicitly or store a reporting date derived from the business timezone.

---

### Issue #18: Trend Grouping Uses MySQL-Specific Functions

**Severity:** 🔵 LOW  
**Category:** Portability / Testability  
**Location:** Lines 181-187

**Evidence:**
```php
DATE_FORMAT(paid_at, '%Y-%m')
DATE_FORMAT(paid_at, '%Y-%u')
DATE(paid_at)
```

**Problem:**
The service is tightly coupled to MySQL syntax.

**Why this matters:**
This makes tests using SQLite harder and reduces database portability.

**How to fix:**
Use database-specific analytics repository abstractions or normalize reporting periods in the application/reporting table.

---

### Issue #19: Week Grouping `%Y-%u` Can Produce Ambiguous Year Boundaries

**Severity:** 🟡 MEDIUM  
**Category:** Reporting Correctness  
**Location:** Line 184

**Evidence:**
```php
$groupBy = 'DATE_FORMAT(paid_at, \'%Y-%u\') as period';
```

**Problem:**
Week-year handling can be tricky around year boundaries. `%Y-%u` may not match ISO week-year expectations.

**Why this matters:**
Revenue near New Year can be grouped under a confusing or incorrect week period for business reporting.

**How to fix:**
Define the exact week convention and test boundary dates. Use ISO week-year if required by business.

---

### Issue #20: Date Functions in Grouping Can Reduce Index Efficiency

**Severity:** 🟡 MEDIUM  
**Category:** Performance  
**Location:** Lines 181-194

**Evidence:**
```php
->selectRaw("{$groupBy}, SUM(total_amount) as revenue, COUNT(id) as orders")
->groupBy('period')
```

**Problem:**
Applying date functions to `paid_at` for grouping requires database computation over result rows.

**Why this matters:**
For large order tables, trend reports can become slow.

**How to fix:**
Use generated/indexed reporting period columns or pre-aggregated reporting tables.

---

### Issue #21: Trend Returns Only Periods With Orders

**Severity:** 🔵 LOW  
**Category:** API Consistency / Frontend Contract  
**Location:** Lines 189-202

**Evidence:**
```php
->get()
->map(fn($r) => [
    'period'  => $r->period,
    'revenue' => (float) $r->revenue,
    'orders'  => (int) $r->orders,
])
```

**Problem:**
Periods with zero revenue are omitted.

**Why this matters:**
Frontend consumers must fill missing periods themselves, causing duplicated logic and inconsistent charts.

**How to fix:**
Return a complete period series with zero values.

---

### Issue #22: No Caching for Expensive Revenue Dashboard Queries

**Severity:** 🟡 MEDIUM  
**Category:** Performance / Scalability  
**Location:** Lines 15-28

**Evidence:**
```php
public function getStats(string $startDate, string $endDate): array
```

**Problem:**
Each dashboard request recomputes all revenue aggregates.

**Why this matters:**
Revenue dashboards are frequently refreshed and can create repeated heavy database load.

**How to fix:**
Cache normalized report results for a short TTL or use materialized analytics tables.

---

### Issue #23: No Index Assumptions Documented for Core Revenue Queries

**Severity:** 🟡 MEDIUM  
**Category:** Database / Performance  
**Location:** Lines 33-193

**Evidence:**
```php
->where('status', self::STATUS_CONFIRMED)
->whereBetween('paid_at', [$start, $end])
```

**Problem:**
Most queries depend on filtering by `orders.status` and `orders.paid_at`, and joining by `showtime_id`.

**Why this matters:**
Without indexes such as `(status, paid_at)` and `(showtime_id)`, revenue reporting can scan large tables.

**How to fix:**
Ensure migrations define appropriate composite indexes for reporting queries.

```php
$table->index(['status', 'paid_at']);
$table->index(['showtime_id', 'status', 'paid_at']);
```

---

### Issue #24: Inner Joins Drop Orders With Missing Related Records

**Severity:** 🟡 MEDIUM  
**Category:** Database Correctness / Reporting Integrity  
**Location:** Lines 56-59, 82-84, 105-106, 142-145, and 158-160

**Evidence:**
```php
->join('showtimes', 'showtimes.id', '=', 'orders.showtime_id')
```

```php
->join('payments', 'payments.order_id', '=', 'orders.id')
```

**Problem:**
Inner joins exclude orders if related showtime/movie/theater/payment rows are missing.

**Why this matters:**
Revenue reports can silently lose revenue rows if historical related records are deleted or data integrity is imperfect.

**How to fix:**
Use enforced foreign keys and retention rules, or use left joins with explicit "Unknown" grouping for reporting.

---

### Issue #25: No Authorization Boundary in Service

**Severity:** 🔵 LOW  
**Category:** Authorization / Architecture  
**Location:** Lines 15-28

**Evidence:**
```php
public function getStats(string $startDate, string $endDate): array
```

**Problem:**
The service exposes financial reporting data without actor context.

**Why this matters:**
Authorization may exist at the controller level, but the service itself cannot enforce theater-level or role-level reporting restrictions.

**How to fix:**
Pass an actor/report scope into the service or ensure controllers/policies enforce access before calling it.

---

## Recommendations

### IMMEDIATE

1. **Define canonical revenue source**: successful payments or revenue ledger, not raw `orders.total_amount`.
2. **Exclude refunds, chargebacks, and failed payment attempts** from revenue calculations.
3. **Fix ticket metrics** to count actual tickets, not orders.
4. **Separate ticket/movie/theater revenue from concession/order gross revenue.**
5. **Replace magic status integer with shared enum/constant.**
6. **Return money as decimal strings or minor units, not floats.**

### SHORT TERM

7. **Validate date inputs and enforce max reporting window.**
8. **Combine duplicate summary queries and reduce repeated total revenue queries.**
9. **Add caching or materialized report tables for dashboard usage.**
10. **Handle timezone explicitly for trend grouping.**
11. **Add composite indexes for `(status, paid_at)` and reporting joins.**

### LONG TERM

12. **Introduce a reporting read model/revenue ledger.**
13. **Add reconciliation tests comparing order totals, payment totals, refunds, and dashboard output.**
14. **Return continuous period series for trends.**
15. **Define report access scope with actor/role/theater restrictions.**
16. **Document and test weekly/monthly reporting period semantics.**

---

## Summary

RevenueService.php provides useful dashboard aggregations, but it is not production-ready for financial reporting. The critical weakness is that revenue is calculated from `orders.total_amount` and order status without reconciliation against successful payments, refunds, chargebacks, or net revenue rules. Several business metrics are also incorrect: movie "tickets" counts orders, and theater/movie revenue attributes entire order totals to the showtime, which can include concessions and non-ticket items. The service also lacks date validation, max range limits, caching, timezone handling, and consistent snapshot strategy.

**Strengths:**
- Uses SQL aggregation
- Organizes dashboard sections into separate methods
- Limits movie breakdown result size
- Uses parameterized date filters
- Has adaptive trend granularity

**Main Gaps:**
1. Revenue source is not payment/revenue-ledger based
2. Refunds/chargebacks are ignored
3. Money values returned as floats
4. Ticket counts are actually order counts
5. Theater/movie revenue can include food/combo/order gross totals
6. No date validation or maximum range limit
7. Multiple queries can return inconsistent snapshots
8. Payment method counts can include retries/non-success payments
9. Timezone and week-boundary reporting ambiguity
10. No caching/materialized reporting model

**Status:** 🚫 Request changes before production acceptance

---

*Review completed: 2026-07-14 02:14 PM*  
*File #42/137 - Phase 3: Business Logic (14/20 complete)*
