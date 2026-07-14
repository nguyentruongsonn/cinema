# File Review: DashboardService.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Services/DashboardService.php  
**Lines:** 208  
**Type:** Service Layer - Admin Dashboard / Analytics

---

## File Information

**Path:** `app/Services/DashboardService.php`  
**Type:** Laravel Service Class  
**Lines:** 208  
**Complexity:** Medium  

**Purpose:**  
Builds admin dashboard statistics:
- Summary cards
- Revenue by day
- Top movies
- Traffic heatmap
- Recent orders

**Business Impact:** 🟠 HIGH - This service powers administrative revenue and operational reporting. Incorrect aggregation can mislead business decisions and hide revenue, booking, or traffic problems.

---

## Overall Score

**Code Quality:** 5.6/10  
**Security:** 5.8/10  
**Performance:** 4.8/10  
**Maintainability:** 5.2/10  
**Laravel Best Practice:** 5.3/10  

**Overall Score:** 5.3/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

1. ✅ **Uses Short Cache TTL** - `Cache::remember()` reduces repeated dashboard query load for identical ranges.
2. ✅ **Eager Loads Recent Order Relationships** - `getRecentOrders()` avoids N+1 queries for user/showtime/payment data.
3. ✅ **Uses Query Builder for Aggregations** - Most dashboard metrics are expressed as database-level aggregations.
4. ✅ **Limits Top Movies and Recent Orders** - Prevents unbounded response size for two dashboard sections.
5. ✅ **Separates Dashboard Sections Into Private Methods** - Improves readability compared with one large method.

---

## Issues Found

### Issue #1: Cache Key Uses Raw Date Input Without Normalization

**Severity:** 🟡 MEDIUM  
**Category:** Performance / Cache Correctness  
**Location:** Line 21

**Evidence:**
```php
$cacheKey = "admin:dashboard:stats:{$start}:{$end}";
```

**Problem:**
The cache key uses raw input strings. Equivalent dates can produce different cache keys, e.g. `2026-07-14`, `2026-07-14 00:00:00`, and other parseable variants.

**Why this matters:**
This reduces cache hit rate and allows unnecessary cache key proliferation. If dates are user-controlled, many unique cache keys can be generated.

**How to fix:**
Normalize dates before building the cache key.

```php
$startDate = Carbon::createFromFormat('Y-m-d', $start)->toDateString();
$endDate = Carbon::createFromFormat('Y-m-d', $end)->toDateString();

$cacheKey = "admin:dashboard:stats:{$startDate}:{$endDate}";
```

---

### Issue #2: `clearStatsCache()` Does Not Clear Actual Dynamic Cache Keys

**Severity:** 🟠 HIGH  
**Category:** Cache Correctness / Maintainability  
**Location:** Lines 21 and 32-39

**Evidence:**
```php
$cacheKey = "admin:dashboard:stats:{$start}:{$end}";
```

```php
Cache::forget('admin:dashboard:stats:week');
Cache::forget('admin:dashboard:stats:month');
Cache::forget('admin:dashboard:stats:year');
// Legacy key cleanup
Cache::forget('admin:dashboard:stats');
```

**Problem:**
The service writes cache entries using `admin:dashboard:stats:{start}:{end}`, but `clearStatsCache()` forgets unrelated static keys.

**Why this matters:**
Dashboard cache invalidation is effectively broken for current keys. Admins may see stale revenue/order data after payments, cancellations, or order updates.

**How to fix:**
Use cache tags if supported by the configured cache driver.

```php
Cache::tags(['admin_dashboard'])->remember($cacheKey, now()->addSeconds(30), ...);
Cache::tags(['admin_dashboard'])->flush();
```

If tags are unavailable, use deterministic known ranges or versioned cache keys.

---

### Issue #3: Date Inputs Are Not Validated or Handled

**Severity:** 🟡 MEDIUM  
**Category:** Validation / Exception Handling  
**Location:** Lines 19, 47-48, 138-139, and 176-177

**Evidence:**
```php
public function getStats(string $start, string $end): array
```

```php
$currentStart = Carbon::parse($start)->startOfDay();
$currentEnd = Carbon::parse($end)->endOfDay();
```

**Problem:**
The service accepts arbitrary strings and parses them repeatedly without validation or controlled exception handling.

**Why this matters:**
Malformed input can throw Carbon exceptions and return inconsistent API errors if the controller does not handle them. Repeated parsing also creates duplicated validation logic.

**How to fix:**
Validate dates in a FormRequest and normalize once in `getStats()` before passing Carbon objects to private methods.

---

### Issue #4: No Validation That Start Date Is Before End Date

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Validation  
**Location:** Lines 47-53, 138-145, and 176-184

**Evidence:**
```php
$currentStart = Carbon::parse($start)->startOfDay();
$currentEnd = Carbon::parse($end)->endOfDay();
```

**Problem:**
Reversed date ranges are accepted.

**Why this matters:**
A reversed dashboard range returns empty or misleading metrics instead of a clear validation error.

**How to fix:**
```php
if ($currentStart->gt($currentEnd)) {
    throw new InvalidArgumentException('Start date must be before end date.');
}
```

---

### Issue #5: No Maximum Date Range

**Severity:** 🟠 HIGH  
**Category:** Performance / Availability  
**Location:** Lines 19-29

**Evidence:**
```php
public function getStats(string $start, string $end): array
```

**Problem:**
There is no upper bound on requested dashboard date ranges.

**Why this matters:**
A request covering years can execute multiple heavy aggregation queries against transactional tables. This risks slow queries, DB CPU spikes, and admin API timeouts.

**How to fix:**
Enforce a maximum reporting range in request validation or service-level guard.

```php
if ($startDate->diffInDays($endDate) > 366) {
    throw new InvalidArgumentException('Dashboard range is too large.');
}
```

---

### Issue #6: Magic Order Status Integers

**Severity:** 🟡 MEDIUM  
**Category:** Maintainability / Business Logic  
**Location:** Lines 12-14 and repeated query filters

**Evidence:**
```php
private const STATUS_CONFIRMED = 2;
private const STATUS_PENDING = 1;
private const STATUS_CANCELLED = 0;
```

**Problem:**
Order statuses are represented as integer constants inside this service. Two constants are unused.

**Why this matters:**
If status semantics change in the Order model, dashboard metrics silently become wrong. Unused constants also create confusion.

**How to fix:**
Use a shared enum/domain constant.

```php
->where('status', OrderStatus::PAID)
```

Remove unused constants.

---

### Issue #7: Revenue Uses `orders.total_amount`, Which May Include Non-Ticket Items and Discounts

**Severity:** 🟠 HIGH  
**Category:** Business Logic / Reporting Correctness  
**Location:** Lines 55-64 and 179-188

**Evidence:**
```php
->sum('total_amount');
```

```php
SUM(orders.total_amount) as revenue
```

**Problem:**
Dashboard revenue and movie revenue are based on `orders.total_amount`.

**Why this matters:**
If orders include tickets, combos, products, promotions, fees, or discounts, movie-level revenue becomes incorrectly attributed to movies. Top movies by revenue may include food/combo revenue, not just ticket revenue.

**How to fix:**
Use line-item/ticket revenue for movie metrics, and define separate metrics for gross order revenue, ticket revenue, food revenue, discount, and net revenue.

---

### Issue #8: Ticket Count Is Actually Order Count

**Severity:** 🟠 HIGH  
**Category:** Business Logic / Reporting Correctness  
**Location:** Lines 68-79

**Evidence:**
```php
// 2. Tickets (Orders count for simplicity as 'tickets sold')
$currentTickets = DB::table('orders')
    ->where('status', self::STATUS_CONFIRMED)
    ->whereBetween('paid_at', [$currentStart, $currentEnd])
    ->count();
```

**Problem:**
The dashboard labels orders as tickets sold.

**Why this matters:**
One order can contain multiple tickets. This materially undercounts tickets sold and makes occupancy/conversion metrics incorrect.

**How to fix:**
Count tickets from the `tickets` table for paid orders.

```php
DB::table('tickets')
    ->join('orders', 'orders.id', '=', 'tickets.order_id')
    ->where('orders.status', OrderStatus::PAID)
    ->whereBetween('orders.paid_at', [$start, $end])
    ->count('tickets.id');
```

---

### Issue #9: Retention Rate Ignores Selected Date Range

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / API Consistency  
**Location:** Lines 92-102

**Evidence:**
```php
$userOrderCounts = DB::table('orders')
    ->select('user_id', DB::raw('count(*) as order_count'))
    ->where('status', self::STATUS_CONFIRMED)
    ->whereNotNull('user_id')
    ->groupBy('user_id')
    ->get();
```

**Problem:**
Retention is calculated across all historical confirmed orders, while other cards use the selected date range.

**Why this matters:**
The dashboard mixes time-scoped metrics with lifetime metrics in the same card set. Users will interpret retention as belonging to the selected range.

**How to fix:**
Either:
- Scope retention to the selected range and compare to the previous range, or
- Rename it explicitly as lifetime retention and separate it from range-based cards.

---

### Issue #10: Retention Query Loads All Users Into PHP Memory

**Severity:** 🟠 HIGH  
**Category:** Performance / Scalability  
**Location:** Lines 92-102

**Evidence:**
```php
->groupBy('user_id')
->get();

$totalBuyingUsers = $userOrderCounts->count();
$returningUsers = $userOrderCounts->where('order_count', '>=', 2)->count();
```

**Problem:**
The query loads every buying user's order count into application memory.

**Why this matters:**
On production datasets, this can consume large memory and slow dashboard responses. The database should calculate the aggregate.

**How to fix:**
Use subqueries and aggregate in SQL.

```php
$base = DB::table('orders')
    ->select('user_id', DB::raw('COUNT(*) as order_count'))
    ->where('status', OrderStatus::PAID)
    ->whereNotNull('user_id')
    ->groupBy('user_id');

$totalBuyingUsers = DB::query()->fromSub($base, 'u')->count();
$returningUsers = DB::query()->fromSub($base, 'u')->where('order_count', '>=', 2)->count();
```

---

### Issue #11: `getRevenueByDay()` Returns Missing Dates

**Severity:** 🔵 LOW  
**Category:** API Consistency / Frontend Contract  
**Location:** Lines 136-148

**Evidence:**
```php
->groupBy(DB::raw('DATE(paid_at)'))
->orderBy('date')
->get();
```

**Problem:**
Only dates that have revenue rows are returned.

**Why this matters:**
The frontend must fill missing days. This creates duplicated presentation logic and inconsistent chart behavior.

**How to fix:**
Return a continuous date series with zero revenue for missing days.

---

### Issue #12: `DATE(paid_at)` Can Hurt Index Usage and Has Timezone Ambiguity

**Severity:** 🟡 MEDIUM  
**Category:** Performance / Timezone Correctness  
**Location:** Lines 142 and 146

**Evidence:**
```php
->selectRaw('DATE(paid_at) as date, SUM(total_amount) as revenue')
->groupBy(DB::raw('DATE(paid_at)'))
```

**Problem:**
The query applies `DATE()` to the column and groups by database timezone.

**Why this matters:**
This can reduce index efficiency and produce date buckets that do not match the application/admin timezone.

**How to fix:**
Use a reporting date column, generated column, materialized analytics table, or explicitly convert timezone in SQL with a documented standard.

---

### Issue #13: Traffic Heatmap Does Not Use Selected Date Range

**Severity:** 🟠 HIGH  
**Category:** API Consistency / Business Logic  
**Location:** Lines 27 and 155-168

**Evidence:**
```php
'traffic_heatmap' => $this->getTrafficHeatmap(),
```

```php
private function getTrafficHeatmap()
```

**Problem:**
The traffic heatmap ignores the requested `$start` and `$end` date range.

**Why this matters:**
The dashboard presents multiple metrics as if they correspond to the same selected period, but the heatmap is lifetime/all-time. This is misleading.

**How to fix:**
Pass the normalized date range to `getTrafficHeatmap()` and filter `showtimes.scheduled_at` or `orders.paid_at`.

---

### Issue #14: Traffic Heatmap Counts Orders, Not Customers or Tickets

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Reporting Correctness  
**Location:** Lines 160-166

**Evidence:**
```php
COUNT(orders.id) as customer_count
```

**Problem:**
The field is named `customer_count`, but the query counts orders.

**Why this matters:**
An order can represent multiple customers/tickets. This undercounts physical attendance and mislabels the metric.

**How to fix:**
Count tickets if heatmap means attendance, or rename the field to `order_count`.

---

### Issue #15: Traffic Heatmap Relies on `orders.showtime_id`

**Severity:** 🟠 HIGH  
**Category:** Database Correctness / Architecture  
**Location:** Line 161

**Evidence:**
```php
->join('showtimes', 'orders.showtime_id', '=', 'showtimes.id')
```

**Problem:**
The service assumes `orders` has a direct `showtime_id`.

**Why this matters:**
Elsewhere in the project, order-to-showtime association may be through tickets. If an order can contain multiple tickets/showtimes, this query is structurally incorrect and can omit or misattribute traffic.

**How to fix:**
Use the authoritative relationship for attendance, usually `tickets -> showtimes`.

```php
DB::table('tickets')
    ->join('orders', 'orders.id', '=', 'tickets.order_id')
    ->join('showtimes', 'showtimes.id', '=', 'tickets.showtime_id')
```

---

### Issue #16: Top Movies Count Orders as Tickets Sold

**Severity:** 🟠 HIGH  
**Category:** Business Logic / Reporting Correctness  
**Location:** Lines 179-188

**Evidence:**
```php
COUNT(orders.id) as tickets_sold
```

**Problem:**
The query labels order count as `tickets_sold`.

**Why this matters:**
If one order contains more than one ticket, top movie ticket counts are underreported. This affects ranking, sales reporting, and operational analysis.

**How to fix:**
Join and count `tickets.id`, or sum ticket quantities if tickets are represented differently.

---

### Issue #17: Top Movies Revenue Can Be Duplicated or Misattributed Depending on Order Model

**Severity:** 🟠 HIGH  
**Category:** Database Correctness / Business Logic  
**Location:** Lines 179-185

**Evidence:**
```php
->selectRaw('movies.id, movies.title, movies.poster_url, COUNT(orders.id) as tickets_sold, SUM(orders.total_amount) as revenue')
->join('showtimes', 'showtimes.id', '=', 'orders.showtime_id')
->join('movies', 'movies.id', '=', 'showtimes.movie_id')
```

**Problem:**
Movie revenue is attributed via `orders.showtime_id` and `orders.total_amount`.

**Why this matters:**
If an order contains multiple tickets, product items, combos, or discounts, `orders.total_amount` is not reliable movie revenue. If orders can span multiple showtimes, this query is wrong.

**How to fix:**
Use tickets/order items as the source of truth and separate ticket revenue from non-ticket revenue.

---

### Issue #18: Recent Orders Are Not Filtered by Status or Date Range

**Severity:** 🟡 MEDIUM  
**Category:** API Consistency / Business Logic  
**Location:** Lines 28 and 191-197

**Evidence:**
```php
'recent_orders' => $this->getRecentOrders(),
```

```php
Order::query()
    ->with(['user', 'showtime.movie', 'showtime.screen.theater', 'payment'])
    ->latest()
    ->limit(5)
    ->get()
```

**Problem:**
Recent orders are global and include any status.

**Why this matters:**
Dashboard sections otherwise appear tied to the selected date range and paid/confirmed status. Recent orders can include pending/cancelled records and unrelated time periods.

**How to fix:**
Make this intentional in the API contract or filter by date/status.

---

### Issue #19: Recent Orders May Expose Excess User and Payment Data Through Eager Loading

**Severity:** 🟡 MEDIUM  
**Category:** Security / Sensitive Data Exposure  
**Location:** Lines 193-206

**Evidence:**
```php
->with(['user', 'showtime.movie', 'showtime.screen.theater', 'payment'])
```

**Problem:**
The service eager loads full user and payment models, then maps limited fields.

**Why this matters:**
Although the returned array is limited, full sensitive models are loaded into memory and can be accidentally exposed if the mapping changes. Payment data should be treated as sensitive.

**How to fix:**
Select only required columns and avoid loading payment unless its fields are used.

```php
->with([
    'user:id,name,full_name',
    'showtime:id,movie_id,screen_id',
    'showtime.movie:id,title',
])
```

---

### Issue #20: Money Is Returned as Float

**Severity:** 🟡 MEDIUM  
**Category:** Financial Correctness / API Consistency  
**Location:** Lines 106 and 203

**Evidence:**
```php
'value' => (float)$currentRevenue,
```

```php
'total_amount' => (float) $order->total_amount,
```

**Problem:**
Revenue and order totals are cast to float.

**Why this matters:**
Financial values should not be represented with binary floating point in API contracts because precision artifacts can occur.

**How to fix:**
Return integer minor units or decimal strings.

---

### Issue #21: Dashboard Authorization Is Not Visible in the Service Contract

**Severity:** 🟡 MEDIUM  
**Category:** Authorization / Architecture  
**Location:** Lines 19-30

**Evidence:**
```php
public function getStats(string $start, string $end): array
```

**Problem:**
The service exposes admin revenue and order metrics without requiring an actor, scope, theater context, or authorization decision.

**Why this matters:**
Authorization may exist in controllers/middleware, but this service contract does not make access requirements explicit. Dashboard data is sensitive and should be protected consistently.

**How to fix:**
Enforce authorization in the controller through policies/gates and consider passing an authorized actor/context for theater-scoped dashboards.

---

### Issue #22: Missing Return Types on Private Methods

**Severity:** 🔵 LOW  
**Category:** Type Safety / Maintainability  
**Location:** Lines 136, 155, 174, and 191

**Evidence:**
```php
private function getRevenueByDay(string $start, string $end)
```

```php
private function getTrafficHeatmap()
```

**Problem:**
Several methods omit return types.

**Why this matters:**
Missing return types reduce static analysis quality and make refactoring riskier.

**How to fix:**
Declare return types such as `Collection` or `array`.

---

### Issue #23: Inconsistent Response Types

**Severity:** 🟡 MEDIUM  
**Category:** API Consistency / Maintainability  
**Location:** Lines 23-29, 136-148, 155-168, 174-188, and 191-206

**Evidence:**
```php
'cards' => $this->getCardStats($start, $end),
'revenue_by_day' => $this->getRevenueByDay($start, $end),
'top_movies' => $this->getTopMovies($start, $end),
'traffic_heatmap' => $this->getTrafficHeatmap(),
'recent_orders' => $this->getRecentOrders(),
```

**Problem:**
Some sections return arrays, while others return Laravel Collections of `stdClass` or mapped Collections.

**Why this matters:**
Inconsistent response shapes complicate API serialization and testing. API services should return predictable DTOs/arrays/resources.

**How to fix:**
Convert all dashboard sections to arrays with explicit field casting.

---

### Issue #24: Duplicate Date Parsing Across Methods

**Severity:** 🔵 LOW  
**Category:** Clean Code / Maintainability  
**Location:** Lines 47-48, 138-139, and 176-177

**Evidence:**
```php
Carbon::parse($start)->startOfDay();
Carbon::parse($end)->endOfDay();
```

**Problem:**
The same parsing logic appears in multiple methods.

**Why this matters:**
Validation and normalization rules can drift over time.

**How to fix:**
Parse and validate once in `getStats()` and pass `Carbon` instances to private methods.

---

## Recommendations

### IMMEDIATE

1. **Fix dashboard cache invalidation** so dynamic keys can actually be cleared.
2. **Validate and normalize date inputs once** before building cache keys or queries.
3. **Add maximum date range limits** to prevent expensive dashboard scans.
4. **Replace order-count-as-ticket-count logic** with real ticket counting.
5. **Fix top movie revenue attribution** to avoid using full order total as movie revenue.
6. **Make heatmap respect the selected date range**.

### SHORT TERM

7. **Move status constants to shared enum/domain constants**.
8. **Calculate retention in SQL instead of loading all users into memory**.
9. **Return consistent arrays/DTOs instead of mixed collections and arrays**.
10. **Return money as integer minor units or decimal strings**.
11. **Select only required columns for recent order relationships**.

### LONG TERM

12. **Create reporting/read models for dashboard metrics**.
13. **Add dashboard analytics tests covering multi-ticket orders**.
14. **Add authorization tests for admin-only dashboard access**.
15. **Define clear metric semantics: gross revenue, ticket revenue, product revenue, net revenue, attendance, orders**.

---

## Summary

DashboardService.php provides useful admin dashboard metrics, but several metrics are not production-safe. The most important correctness issues are counting orders as tickets, attributing full order totals to movies, ignoring the selected date range for heatmap/recent orders, and using cache invalidation keys that do not match the actual cache keys. These issues can mislead administrators and produce materially incorrect business reports.

**Strengths:**
- Uses short-lived caching
- Uses eager loading for recent orders
- Splits metrics into private methods
- Limits top movies and recent orders
- Uses aggregate queries for several metrics

**Main Gaps:**
1. Broken cache invalidation for dynamic range keys
2. No date validation, order validation, or range limit
3. Counts orders as tickets sold
4. Uses full order totals for movie revenue
5. Retention ignores selected date range and loads all users into memory
6. Heatmap ignores selected date range
7. Heatmap and top movies rely on `orders.showtime_id`
8. Mixed API response types
9. Float money values
10. Admin authorization not explicit in service contract

**Status:** 🚫 Request changes before production acceptance

---

*Review completed: 2026-07-14 01:52 PM*  
*File #39/137 - Phase 3: Business Logic (11/20 complete)*