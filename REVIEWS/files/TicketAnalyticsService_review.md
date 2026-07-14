# File Review: TicketAnalyticsService.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Services/TicketAnalyticsService.php  
**Lines:** 163  
**Type:** Service Layer - Ticket Analytics

---

## File Information

**Path:** `app/Services/TicketAnalyticsService.php`  
**Type:** Laravel Service Class  
**Lines:** 163  
**Complexity:** Medium  

**Purpose:**  
Builds ticket analytics for a date range, including total sold tickets, average tickets per day, peak purchase hour, occupancy rate, ticket sales trend, top movies, and theater occupancy.

**Business Impact:** 🔴 HIGH - This service drives ticket sales analytics and occupancy reporting. Incorrect data can mislead operations, staffing, showtime planning, movie ranking, and executive reporting.

---

## Overall Score

**Code Quality:** 5.5/10  
**Security:** 6.2/10  
**Performance:** 5.0/10  
**Maintainability:** 5.1/10  
**Laravel Best Practice:** 5.0/10  

**Overall Score:** 5.3/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

1. ✅ **Uses SQL Aggregation** - Ticket totals, peak hour, trend, and top movies are calculated in SQL.
2. ✅ **Separates Occupancy Calculation** - `calculateOccupancyRate()` is separated from `getStats()`.
3. ✅ **Avoids Direct User SQL Interpolation for Dates** - Date filters use `whereBetween()` with query bindings.
4. ✅ **Limits Top Movies Result** - Top movie query limits to 5 records.
5. ✅ **Handles Empty Showtime Set** - Occupancy method returns zero values when no showtimes are found.

---

## Issues Found

### Issue #1: Date Inputs Are Parsed Without Validation or Error Handling

**Severity:** 🟡 MEDIUM  
**Category:** Validation / Exception Handling  
**Location:** Lines 13-16

**Evidence:**
```php
public function getStats(string $startDate, string $endDate): array
{
    $start = Carbon::parse($startDate)->startOfDay();
    $end   = Carbon::parse($endDate)->endOfDay();
```

**Problem:**
The service accepts date strings and parses them directly with `Carbon::parse()`.

**Why this matters:**
Invalid or ambiguous input can throw unhandled exceptions or produce unexpected date ranges. Analytics APIs should fail predictably with validation errors.

**How to fix:**
Validate dates in a FormRequest and use strict date parsing.

```php
$start = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
$end = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();
```

---

### Issue #2: Start Date Can Be After End Date

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Validation  
**Location:** Lines 15-18

**Evidence:**
```php
$start = Carbon::parse($startDate)->startOfDay();
$end   = Carbon::parse($endDate)->endOfDay();

$totalDays = $start->diffInDays($end) + 1;
```

**Problem:**
There is no explicit validation that start date is before or equal to end date.

**Why this matters:**
Depending on Carbon behavior, reversed ranges can produce misleading totals, average-per-day values, and empty datasets instead of a clear validation error.

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
**Location:** Lines 13-79

**Evidence:**
```php
public function getStats(string $startDate, string $endDate): array
```

**Problem:**
The service allows unbounded analytics ranges.

**Why this matters:**
The method executes multiple aggregate queries and loads all active showtimes for the range into memory. A very large range can create heavy database load and memory pressure.

**How to fix:**
Enforce a maximum report range.

```php
if ($start->diffInDays($end) > 366) {
    throw new InvalidArgumentException('Ticket analytics range is too large.');
}
```

---

### Issue #4: Magic Order Status Integer

**Severity:** 🟡 MEDIUM  
**Category:** Maintainability / Business Logic  
**Location:** Lines 23, 33, 48, 60, and 116

**Evidence:**
```php
->where('orders.status', 2)
```

**Problem:**
The service hard-codes `2` as the paid/confirmed order status in multiple locations.

**Why this matters:**
If order status definitions change or differ elsewhere, ticket analytics silently becomes incorrect.

**How to fix:**
Use a shared enum or domain constant.

```php
->where('orders.status', OrderStatus::Confirmed->value)
```

---

### Issue #5: Analytics Are Based on Order Status Only, Not Payment Success

**Severity:** 🟠 HIGH  
**Category:** Financial / Business Correctness  
**Location:** Lines 21-25, 31-38, 46-53, 56-66, and 113-119

**Evidence:**
```php
->join('orders', 'orders.id', '=', 'tickets.order_id')
->where('orders.status', 2)
->whereBetween('orders.paid_at', [$start, $end])
```

**Problem:**
Sold tickets are counted based on order status and `paid_at`, without checking successful payment records.

**Why this matters:**
If an order status is incorrectly set, payment was refunded, chargeback occurred, or payment reconciliation fails, analytics will count tickets that should not be counted as sold.

**How to fix:**
Use the canonical paid-ticket definition. Join successful payments or a ticket/order fulfillment ledger.

```php
->join('payments', 'payments.order_id', '=', 'orders.id')
->where('payments.status', PaymentStatus::Succeeded->value)
```

---

### Issue #6: Refunds/Cancellations Are Not Excluded

**Severity:** 🟠 HIGH  
**Category:** Business Logic / Reporting Correctness  
**Location:** Lines 21-25, 31-38, 46-53, 56-66, and 113-119

**Evidence:**
```php
->where('orders.status', 2)
```

**Problem:**
The queries do not account for refunded tickets, cancelled orders, reversed payments, or voided tickets.

**Why this matters:**
Ticket sales and occupancy will be overstated if refunded or cancelled tickets remain associated with confirmed orders or historical ticket rows.

**How to fix:**
Filter by ticket status and successful non-refunded payment state.

```php
->where('tickets.status', TicketStatus::Issued->value)
->whereNull('tickets.refunded_at')
```

---

### Issue #7: Multiple Queries Can Return Inconsistent Analytics Snapshot

**Severity:** 🟡 MEDIUM  
**Category:** Consistency / Reporting Correctness  
**Location:** Lines 21-77

**Evidence:**
```php
$totalTickets = DB::table('tickets')...
$peakHourRow = DB::table('tickets')...
$occupancyData = $this->calculateOccupancyRate($start, $end);
$trend = DB::table('tickets')...
$topMovies = DB::table('tickets')...
```

**Problem:**
The response is built from several independent database queries.

**Why this matters:**
Orders/tickets created during execution can make summary, trend, top movies, and occupancy disagree.

**How to fix:**
For strict reporting consistency, use a reporting read model/materialized table or run under a consistent read transaction.

---

### Issue #8: Trend Returns Query Builder Collection Directly Instead of Normalized Arrays

**Severity:** 🔵 LOW  
**Category:** API Consistency / Maintainability  
**Location:** Lines 46-53 and 75

**Evidence:**
```php
$trend = DB::table('tickets')
    ...
    ->get();

return [
    ...
    'trend' => $trend,
```

**Problem:**
The method returns Laravel collection objects containing stdClass rows for `trend`.

**Why this matters:**
Services should return predictable arrays/DTOs, not database row objects. This leaks persistence details into API consumers.

**How to fix:**
```php
'trend' => $trend->map(fn ($row) => [
    'date' => $row->date,
    'ticket_count' => (int) $row->ticket_count,
])->toArray(),
```

---

### Issue #9: Top Movies Also Returns Query Builder Collection Directly

**Severity:** 🔵 LOW  
**Category:** API Consistency / Maintainability  
**Location:** Lines 56-66 and 76

**Evidence:**
```php
$topMovies = DB::table('tickets')
    ...
    ->get();

return [
    ...
    'top_movies' => $topMovies,
```

**Problem:**
The method returns raw database result collection for `top_movies`.

**Why this matters:**
This produces inconsistent API shapes compared with `theater_occupancy`, which is a PHP array. It also leaves numeric casting to serialization behavior.

**How to fix:**
Map rows into arrays and cast values.

---

### Issue #10: Peak Hour Formatting Can Produce Invalid `24:00` End Time

**Severity:** 🔵 LOW  
**Category:** Business Logic / API Correctness  
**Location:** Line 40

**Evidence:**
```php
$peakHour = $peakHourRow ? str_pad($peakHourRow->hour, 2, '0', STR_PAD_LEFT) . ':00 - ' . str_pad($peakHourRow->hour + 1, 2, '0', STR_PAD_LEFT) . ':00' : 'N/A';
```

**Problem:**
If peak hour is 23, the output becomes `23:00 - 24:00`.

**Why this matters:**
`24:00` may be acceptable in some reporting contexts but is often inconsistent with normal API time formatting. It can break consumers expecting `HH:MM` from `00:00` to `23:59`.

**How to fix:**
```php
$endHour = ((int) $peakHourRow->hour + 1) % 24;
```

---

### Issue #11: Peak Hour Uses Database Timezone

**Severity:** 🟡 MEDIUM  
**Category:** Timezone Correctness  
**Location:** Lines 35-36

**Evidence:**
```php
->selectRaw('HOUR(orders.paid_at) as hour, COUNT(tickets.id) as ticket_count')
->groupBy(DB::raw('HOUR(orders.paid_at)'))
```

**Problem:**
The hour is extracted by MySQL from `orders.paid_at` without explicit timezone conversion.

**Why this matters:**
If the database timezone differs from the business/reporting timezone, peak-hour analytics will be wrong.

**How to fix:**
Store a business-timezone reporting timestamp or use explicit timezone conversion in the reporting query.

---

### Issue #12: Trend Grouping Uses Database Timezone

**Severity:** 🟡 MEDIUM  
**Category:** Timezone Correctness  
**Location:** Lines 50-51

**Evidence:**
```php
->selectRaw('DATE_FORMAT(orders.paid_at, "%Y-%m-%d") as date, COUNT(tickets.id) as ticket_count')
->groupBy(DB::raw('DATE_FORMAT(orders.paid_at, "%Y-%m-%d")'))
```

**Problem:**
Daily grouping uses database date conversion without explicit timezone.

**Why this matters:**
Sales near midnight can be reported on the wrong day.

**How to fix:**
Define reporting timezone and use a normalized reporting date column or explicit conversion.

---

### Issue #13: MySQL-Specific SQL Reduces Portability and Testability

**Severity:** 🔵 LOW  
**Category:** Laravel Best Practice / Testability  
**Location:** Lines 35-36 and 50-51

**Evidence:**
```php
HOUR(orders.paid_at)
DATE_FORMAT(orders.paid_at, "%Y-%m-%d")
```

**Problem:**
The service is coupled to MySQL-specific functions.

**Why this matters:**
Feature tests using SQLite can fail or require special handling. The analytics code becomes harder to test portably.

**How to fix:**
Move reporting SQL to a database-specific analytics repository or use persisted reporting dimensions.

---

### Issue #14: Date Functions Reduce Index Efficiency

**Severity:** 🟡 MEDIUM  
**Category:** Performance  
**Location:** Lines 35-36 and 50-51

**Evidence:**
```php
HOUR(orders.paid_at)
DATE_FORMAT(orders.paid_at, "%Y-%m-%d")
```

**Problem:**
The database must compute functions over result rows for grouping.

**Why this matters:**
Large ticket/order tables can make analytics queries slow.

**How to fix:**
Use generated/indexed reporting columns such as `paid_date` and `paid_hour`, or pre-aggregated analytics tables.

---

### Issue #15: Trend Omits Dates With Zero Tickets

**Severity:** 🔵 LOW  
**Category:** API Consistency / Frontend Contract  
**Location:** Lines 46-53

**Evidence:**
```php
->groupBy(DB::raw('DATE_FORMAT(orders.paid_at, "%Y-%m-%d")'))
->orderBy('date')
->get();
```

**Problem:**
Only dates with ticket sales are returned.

**Why this matters:**
Frontend consumers must fill missing dates themselves, causing duplicated and inconsistent chart logic.

**How to fix:**
Generate a full date series from `$start` to `$end` and fill missing days with zero.

---

### Issue #16: Occupancy Uses Showtime Date, While Ticket Sales Use Paid Date

**Severity:** 🟠 HIGH  
**Category:** Business Logic / Reporting Correctness  
**Location:** Lines 20-24 and 87-91

**Evidence:**
```php
->whereBetween('orders.paid_at', [$start, $end])
```

```php
->whereBetween('showtimes.scheduled_at', [$start, $end])
```

**Problem:**
Ticket sales metrics are based on payment date, while occupancy is based on showtime date.

**Why this matters:**
A report for one date range mixes two different time semantics. Tickets sold today for a show next week affect sales totals but not occupancy, while a show today with tickets paid yesterday affects occupancy but not sales.

**How to fix:**
Split API fields clearly into `sales_by_paid_date` and `occupancy_by_showtime_date`, or accept separate date filters.

---

### Issue #17: Occupancy Sold Tickets Are Not Filtered by Payment Date or Ticket Status

**Severity:** 🟠 HIGH  
**Category:** Business Logic / Reporting Correctness  
**Location:** Lines 113-119

**Evidence:**
```php
$ticketsByShowtime = DB::table('tickets')
    ->join('orders', 'orders.id', '=', 'tickets.order_id')
    ->whereIn('tickets.showtime_id', $showtimeIds)
    ->where('orders.status', 2)
    ->selectRaw('tickets.showtime_id, count(tickets.id) as sold_seats')
```

**Problem:**
Occupancy counts all tickets for selected showtimes where the order status is 2, regardless of ticket status, refund state, or cancellation.

**Why this matters:**
Occupancy can be overstated if tickets were refunded/voided/cancelled.

**How to fix:**
Filter only issued/active tickets and successful non-refunded payments.

---

### Issue #18: Occupancy Counts Seats From `seats` Without Excluding Unavailable/Disabled Seats

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / Database Correctness  
**Location:** Lines 105-109

**Evidence:**
```php
$seatsByScreen = DB::table('seats')
    ->whereIn('screen_id', $screenIds)
    ->selectRaw('screen_id, count(id) as total_seats')
    ->groupBy('screen_id')
```

**Problem:**
All seats for a screen are counted as capacity.

**Why this matters:**
Maintenance seats, disabled seats, blocked seats, or inactive seats should not necessarily count as sellable capacity. Occupancy can be understated.

**How to fix:**
Filter sellable/active seats based on the actual schema.

```php
->where('status', SeatStatus::Active->value)
```

---

### Issue #19: Occupancy Assumes Current Seat Layout Applies to Historical Showtimes

**Severity:** 🟡 MEDIUM  
**Category:** Historical Reporting Correctness  
**Location:** Lines 102-109 and 125-130

**Evidence:**
```php
$screenIds = $showtimes->pluck('screen_id')->unique();

$seatsByScreen = DB::table('seats')
    ->whereIn('screen_id', $screenIds)
```

**Problem:**
The service uses the current seats table to calculate capacity for historical showtimes.

**Why this matters:**
If a screen layout changed after the showtime, historical occupancy becomes incorrect.

**How to fix:**
Store showtime capacity at showtime creation or use immutable seat layout snapshots.

---

### Issue #20: Loading All Showtimes Into Memory Can Be Expensive

**Severity:** 🟡 MEDIUM  
**Category:** Performance / Scalability  
**Location:** Lines 87-93 and 125-141

**Evidence:**
```php
$showtimes = DB::table('showtimes')
    ...
    ->get();

foreach ($showtimes as $st) {
```

**Problem:**
All matching showtimes are loaded into PHP memory and iterated manually.

**Why this matters:**
Large date ranges or many theaters/screens can create high memory usage and slower responses.

**How to fix:**
Push occupancy aggregation into SQL or process in chunks.

---

### Issue #21: `whereIn()` With Large Showtime List Can Become Slow

**Severity:** 🟡 MEDIUM  
**Category:** Performance  
**Location:** Lines 112-116

**Evidence:**
```php
$showtimeIds = $showtimes->pluck('id');
$ticketsByShowtime = DB::table('tickets')
    ->join('orders', 'orders.id', '=', 'tickets.order_id')
    ->whereIn('tickets.showtime_id', $showtimeIds)
```

**Problem:**
The service builds a large `WHERE IN (...)` list from all showtime IDs.

**Why this matters:**
Large `IN` lists can produce slow queries and oversized SQL payloads.

**How to fix:**
Use a join/subquery against the filtered showtimes instead of passing all IDs into `whereIn()`.

---

### Issue #22: No Caching for Expensive Analytics

**Severity:** 🟡 MEDIUM  
**Category:** Performance / Scalability  
**Location:** Lines 13-79

**Evidence:**
```php
public function getStats(string $startDate, string $endDate): array
```

**Problem:**
Every request recomputes all analytics from transactional tables.

**Why this matters:**
Dashboard endpoints are often refreshed frequently. Repeated aggregate queries against `tickets`, `orders`, `showtimes`, and `seats` can degrade production database performance.

**How to fix:**
Cache normalized results or use materialized analytics tables.

---

### Issue #23: No Index Requirements Documented for Analytics Queries

**Severity:** 🟡 MEDIUM  
**Category:** Database / Performance  
**Location:** Lines 21-119

**Evidence:**
```php
->join('orders', 'orders.id', '=', 'tickets.order_id')
->where('orders.status', 2)
->whereBetween('orders.paid_at', [$start, $end])
```

```php
->whereBetween('showtimes.scheduled_at', [$start, $end])
->where('showtimes.status', 1)
```

**Problem:**
The service depends heavily on filters and joins that require indexes.

**Why this matters:**
Without proper indexes, these reports can scan large transactional tables.

**How to fix:**
Ensure migrations include indexes such as:
```php
$table->index(['status', 'paid_at']);
$table->index(['order_id', 'showtime_id']);
$table->index(['showtime_id']);
$table->index(['scheduled_at', 'status']);
$table->index(['screen_id']);
```

---

### Issue #24: Long `getStats()` Method Mixes Multiple Responsibilities

**Severity:** 🔵 LOW  
**Category:** Clean Code / SRP  
**Location:** Lines 13-79

**Evidence:**
```php
public function getStats(string $startDate, string $endDate): array
```

**Problem:**
`getStats()` parses input, computes summary, peak hour, occupancy, trend, and top movies.

**Why this matters:**
The method is harder to test and maintain. Changes to one metric risk affecting unrelated metrics.

**How to fix:**
Extract private methods:
- `getTotalTickets()`
- `getPeakHour()`
- `getTicketTrend()`
- `getTopMovies()`
- `normalizeDateRange()`

---

### Issue #25: No Authorization Scope in Analytics Service

**Severity:** 🔵 LOW  
**Category:** Authorization / Architecture  
**Location:** Lines 13-79

**Evidence:**
```php
public function getStats(string $startDate, string $endDate): array
```

**Problem:**
The service accepts only dates and has no actor or theater scope.

**Why this matters:**
If non-global admins use this service, it cannot restrict analytics to permitted theaters or regions.

**How to fix:**
Pass an authenticated actor/report scope and apply it consistently to all queries.

```php
public function getStats(User $actor, DateRange $range): array
```

---

## Recommendations

### IMMEDIATE

1. **Validate date inputs strictly** and reject reversed ranges.
2. **Replace magic statuses** with shared enums/constants.
3. **Define canonical "sold ticket" rules** including successful payment, ticket issued status, and refund/cancellation handling.
4. **Separate sales-by-paid-date from occupancy-by-showtime-date** or clearly expose them as different report dimensions.
5. **Normalize returned data** into arrays/DTOs instead of returning raw database collections.
6. **Fix peak hour timezone and midnight handling.**

### SHORT TERM

7. **Add max reporting range limits** to protect the database.
8. **Add caching/materialized analytics** for dashboard use.
9. **Move repeated ticket/order filters into reusable query methods.**
10. **Use SQL aggregation/subqueries for occupancy instead of loading all showtimes.**
11. **Fill missing trend dates with zero values.**

### LONG TERM

12. **Create analytics read models** for ticket sales and occupancy.
13. **Store immutable showtime capacity snapshots** to support historical occupancy correctness.
14. **Add indexes for order paid date/status, ticket showtime/order, and showtime scheduled/status.**
15. **Add reporting timezone policy and tests for midnight/year-boundary behavior.**
16. **Add actor/theater scoped analytics authorization.**

---

## Summary

TicketAnalyticsService.php provides useful analytics output, but it is not production-ready for reliable reporting. The biggest correctness issue is inconsistent date semantics: ticket sales are filtered by order payment date, while occupancy is calculated by showtime date. Sold tickets are counted using only order status and do not account for payment success, refunds, cancellations, or ticket status. The service also hard-codes status integers, returns raw database collections, uses MySQL-specific date functions, lacks timezone handling, and can become expensive for large date ranges.

**Strengths:**
- SQL-based aggregation
- Clear high-level analytics fields
- Occupancy logic separated into its own method
- Top movies result is limited
- Empty showtime case is handled

**Main Gaps:**
1. Date input validation missing
2. Magic order/showtime status integers
3. Sold-ticket definition is incomplete
4. Refunds/cancellations ignored
5. Mixed paid-date and showtime-date semantics
6. Raw database collections returned
7. MySQL-specific/timezone-sensitive grouping
8. Occupancy uses current seat layout for historical showtimes
9. Large date ranges can overload database and PHP memory
10. No cache/materialized analytics model

**Status:** 🚫 Request changes before production acceptance

---

*Review completed: 2026-07-14 02:21 PM*  
*File #43/137 - Phase 3: Business Logic (15/20 complete)*
