# File Review: OrderExpirationService.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Services/OrderExpirationService.php  
**Lines:** 91  
**Type:** Service Layer - Order Expiration / Booking Lifecycle

---

## File Information

**Path:** `app/Services/OrderExpirationService.php`  
**Type:** Laravel Service Class  
**Lines:** 91  
**Complexity:** Low-Medium  

**Purpose:**  
Expires pending unpaid orders, checks whether an order is expired/payable, and exposes active booking statuses.

**Business Impact:** 🔴 CRITICAL - This service directly affects seat inventory release, payment eligibility, order lifecycle correctness, and duplicate booking/payment prevention.

---

## Overall Score

**Code Quality:** 5.8/10  
**Security:** 5.7/10  
**Performance:** 6.4/10  
**Maintainability:** 5.5/10  
**Laravel Best Practice:** 5.6/10  

**Overall Score:** 5.8/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

1. ✅ **Bulk Expiration Uses Single Update Query** - `expirePendingOrders()` avoids loading every expired order into memory.
2. ✅ **Filters Paid Orders Out of Expiration** - Uses `whereNull('paid_at')` before expiring pending orders.
3. ✅ **Has Dedicated Payability Check** - `isPayable()` centralizes some payment eligibility logic.
4. ✅ **Logs Expiration Events** - Both bulk and single-order expiration paths write informational logs.
5. ✅ **Supports Showtime-Scoped Cleanup** - Optional `$showtimeId` allows targeted expiration.

---

## Issues Found

### Issue #1: Order Lifecycle Statuses Are Duplicated as Magic Integers

**Severity:** 🟡 MEDIUM  
**Category:** Maintainability / Business Logic  
**Location:** Lines 10-12

**Evidence:**
```php
private const ORDER_STATUS_CANCELLED = 0;
private const ORDER_STATUS_PENDING = 1;
private const ORDER_STATUS_CONFIRMED = 2;
```

**Problem:**
The service defines its own order status constants locally.

**Why this matters:**
Order status meanings must be centralized. If another service/model/controller uses different values or names, expiration and payability rules will diverge and create production defects.

**How to fix:**
Move order statuses to a shared enum or domain constant used by every order lifecycle component.

```php
use App\Enums\OrderStatus;

->where('status', OrderStatus::Pending->value)
```

---

### Issue #2: Payment Status Uses Magic Strings

**Severity:** 🟡 MEDIUM  
**Category:** Maintainability / Business Logic  
**Location:** Lines 28, 59, and 77

**Evidence:**
```php
'payment_status' => 'expired',
```

```php
if ($order->paid_at !== null || $order->payment_status === 'paid') {
```

**Problem:**
Payment status values are raw strings embedded in the service.

**Why this matters:**
Spelling drift or inconsistent status vocabulary can allow expired orders to remain payable or paid orders to be expired incorrectly.

**How to fix:**
Use a shared payment status enum.

```php
use App\Enums\PaymentStatus;

'payment_status' => PaymentStatus::Expired->value
```

---

### Issue #3: Bulk Expiration Does Not Release Seats or Related Holds

**Severity:** 🔴 CRITICAL  
**Category:** Business Logic / Booking Correctness  
**Location:** Lines 16-31

**Evidence:**
```php
$expiredCount = $query->update([
    'status' => self::ORDER_STATUS_CANCELLED,
    'payment_status' => 'expired',
    'cancelled_at' => now(),
    'updated_at' => now(),
]);
```

**Problem:**
The method only updates the `orders` table. It does not release reserved seats, clear seat holds, update ticket records, or trigger downstream cleanup.

**Why this matters:**
In a cinema booking system, expiring an unpaid order must release inventory. Otherwise seats may remain blocked after the order is cancelled, causing revenue loss and false sell-outs.

**How to fix:**
Expiration must be part of a transactional domain workflow that cancels the order and releases all related booking resources atomically.

```php
DB::transaction(function () use ($order) {
    $order->lockForUpdate();

    // cancel order
    // release seats/holds/tickets
    // emit domain event after commit
});
```

---

### Issue #4: Bulk Update Bypasses Eloquent Events and Model-Level Side Effects

**Severity:** 🟠 HIGH  
**Category:** Architecture / Data Consistency  
**Location:** Lines 26-31

**Evidence:**
```php
$expiredCount = $query->update([
    'status' => self::ORDER_STATUS_CANCELLED,
    'payment_status' => 'expired',
    'cancelled_at' => now(),
    'updated_at' => now(),
]);
```

**Problem:**
Mass `update()` on a query builder does not fire Eloquent model events for each order.

**Why this matters:**
If seat release, audit logging, notifications, cache invalidation, or domain events are attached to model observers, they will be skipped.

**How to fix:**
Either explicitly document and implement all side effects in this service, or process locked orders in chunks through a domain cancellation method.

---

### Issue #5: Single-Order Expiration Is Not Atomic

**Severity:** 🟠 HIGH  
**Category:** Concurrency / Transactions  
**Location:** Lines 51-68

**Evidence:**
```php
if (!$this->isExpired($order)) {
    return $order;
}

$order->update([
    'status' => self::ORDER_STATUS_CANCELLED,
    'payment_status' => 'expired',
    'cancelled_at' => now(),
]);
```

**Problem:**
The expiration decision and update are not performed inside a transaction with row locking.

**Why this matters:**
A payment callback or user payment attempt can race with expiration. The order can be marked expired while payment confirmation is concurrently marking it paid, causing inconsistent money/order state.

**How to fix:**
Reload and lock the order inside a transaction before checking expiration.

```php
return DB::transaction(function () use ($order) {
    $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

    if (! $this->isExpired($lockedOrder)) {
        return $lockedOrder;
    }

    $lockedOrder->update([...]);

    return $lockedOrder->fresh();
});
```

---

### Issue #6: Bulk Expiration Can Race With Payment Confirmation

**Severity:** 🔴 CRITICAL  
**Category:** Concurrency / Payment Correctness  
**Location:** Lines 16-31

**Evidence:**
```php
->where('status', self::ORDER_STATUS_PENDING)
->whereNull('paid_at')
...
$expiredCount = $query->update([
    'status' => self::ORDER_STATUS_CANCELLED,
    'payment_status' => 'expired',
```

**Problem:**
Bulk expiration updates all matching orders without row-level locking or coordination with payment confirmation.

**Why this matters:**
A payment webhook may be processing the same order at the same time. Without a shared locking strategy, one process can mark the order cancelled/expired while another records payment. This can create paid-cancelled orders, duplicate remediation work, or lost revenue.

**How to fix:**
Payment confirmation and expiration must both acquire the same order lock and use a strict state transition machine.

---

### Issue #7: `isExpired()` Checks a Possibly Stale In-Memory Model

**Severity:** 🟡 MEDIUM  
**Category:** Correctness / Concurrency  
**Location:** Lines 43-49

**Evidence:**
```php
public function isExpired(Order $order): bool
{
    return (int) $order->status === self::ORDER_STATUS_PENDING
        && $order->expired_at !== null
        && $order->expired_at->isPast()
        && $order->paid_at === null;
}
```

**Problem:**
The method evaluates whatever state is currently loaded on the model instance.

**Why this matters:**
If another request updates payment/status after the model was loaded, this method can return the wrong result.

**How to fix:**
Use this method only for presentation/non-mutating checks, and for state changes always re-query with `lockForUpdate()`.

---

### Issue #8: `isExpired()` Does Not Check `payment_status`

**Severity:** 🟡 MEDIUM  
**Category:** Payment Correctness  
**Location:** Lines 45-48

**Evidence:**
```php
return (int) $order->status === self::ORDER_STATUS_PENDING
    && $order->expired_at !== null
    && $order->expired_at->isPast()
    && $order->paid_at === null;
```

**Problem:**
The expiration check only checks `paid_at`, not `payment_status`.

**Why this matters:**
An order can be in an intermediate payment state with `paid_at` still null. Expiring it can conflict with an in-flight payment.

**How to fix:**
Define allowed transitions explicitly. For example, only expire orders whose payment status is pending/unpaid and not processing.

---

### Issue #9: `expirePendingOrders()` Does Not Validate `$showtimeId`

**Severity:** 🟡 MEDIUM  
**Category:** Validation / API Correctness  
**Location:** Lines 14 and 22-24

**Evidence:**
```php
public function expirePendingOrders(?int $showtimeId = null): int
```

```php
if ($showtimeId !== null) {
    $query->where('showtime_id', $showtimeId);
}
```

**Problem:**
The service accepts any integer, including zero or negative values.

**Why this matters:**
Invalid IDs silently produce zero updates, hiding caller bugs. If route/request validation is missed, the service does not defend itself.

**How to fix:**
Validate domain identifiers at the boundary and enforce basic invariants in the service.

```php
if ($showtimeId !== null && $showtimeId <= 0) {
    throw new InvalidArgumentException('Invalid showtime id.');
}
```

---

### Issue #10: Multiple Calls to `now()` Can Produce Inconsistent Timestamps

**Severity:** 🔵 LOW  
**Category:** Data Consistency / Clean Code  
**Location:** Lines 20, 29-30, and 60

**Evidence:**
```php
->where('expired_at', '<=', now());
```

```php
'cancelled_at' => now(),
'updated_at' => now(),
```

```php
'cancelled_at' => now(),
```

**Problem:**
The method calls `now()` multiple times for the same operation.

**Why this matters:**
Rows selected by one timestamp may be updated with slightly different timestamps. This is minor but avoidable.

**How to fix:**
Capture the clock once.

```php
$now = now();

->where('expired_at', '<=', $now)

'cancelled_at' => $now,
'updated_at' => $now,
```

---

### Issue #11: No Audit Log for Financially Relevant State Transition

**Severity:** 🟡 MEDIUM  
**Category:** Logging / Auditability  
**Location:** Lines 33-38 and 63-66

**Evidence:**
```php
Log::info('Expired pending orders cleaned up', [
    'showtime_id' => $showtimeId,
    'expired_count' => $expiredCount,
]);
```

```php
Log::info('Pending order expired', [
    'order_id' => $order->id,
    'order_code' => $order->code,
]);
```

**Problem:**
The logs are operational logs, not durable audit records. Bulk expiration does not identify which order IDs were expired.

**Why this matters:**
Order cancellation affects payment disputes, customer support, seat inventory, and revenue reconciliation. A count-only log is insufficient for auditability.

**How to fix:**
Record auditable cancellation events per order or emit domain events with order IDs and transition metadata.

---

### Issue #12: No Idempotency or Transition Guard in `expireOrder()`

**Severity:** 🟡 MEDIUM  
**Category:** Idempotency / State Machine  
**Location:** Lines 51-68

**Evidence:**
```php
public function expireOrder(Order $order): Order
{
    if (!$this->isExpired($order)) {
        return $order;
    }

    $order->update([
```

**Problem:**
The method is idempotent only by current in-memory checks. It does not enforce state transition constraints at the database update level.

**Why this matters:**
Concurrent retries can observe stale state and perform conflicting updates.

**How to fix:**
Use conditional update with expected current state or row locking.

```php
Order::whereKey($order->id)
    ->where('status', OrderStatus::Pending)
    ->whereNull('paid_at')
    ->where('expired_at', '<=', $now)
    ->update([...]);
```

---

### Issue #13: `isPayable()` Does Not Refresh or Lock the Order

**Severity:** 🟠 HIGH  
**Category:** Concurrency / Duplicate Payment Risk  
**Location:** Lines 71-82

**Evidence:**
```php
public function isPayable(Order $order): bool
{
    if ((int) $order->status !== self::ORDER_STATUS_PENDING) {
        return false;
    }

    if ($order->paid_at !== null || $order->payment_status === 'paid') {
        return false;
    }

    return !$this->isExpired($order);
}
```

**Problem:**
Payability is determined from a possibly stale model and is not tied atomically to payment creation.

**Why this matters:**
Two requests can both see the order as payable and create duplicate payment attempts unless payment creation also enforces idempotency and locking.

**How to fix:**
Do not use this method as a payment gate unless payment creation locks the order and performs the same checks inside the transaction.

---

### Issue #14: `isPayable()` Allows Orders With Unknown Payment Statuses

**Severity:** 🟡 MEDIUM  
**Category:** Payment State Correctness  
**Location:** Lines 77-81

**Evidence:**
```php
if ($order->paid_at !== null || $order->payment_status === 'paid') {
    return false;
}

return !$this->isExpired($order);
```

**Problem:**
Any status except `'paid'` can pass this check if the order is pending and not expired.

**Why this matters:**
Statuses like `processing`, `failed`, `refunded`, `cancelled`, or `expired` may need different behavior. The current check is too permissive.

**How to fix:**
Only allow a strict allowlist.

```php
return $order->payment_status === PaymentStatus::Pending->value
    && ! $this->isExpired($order);
```

---

### Issue #15: `getActiveBookingStatuses()` Couples Seat Availability to Two Status Integers

**Severity:** 🟡 MEDIUM  
**Category:** Architecture / Business Logic  
**Location:** Lines 84-90

**Evidence:**
```php
public function getActiveBookingStatuses(): array
{
    return [
        self::ORDER_STATUS_PENDING,
        self::ORDER_STATUS_CONFIRMED,
    ];
}
```

**Problem:**
The method exposes active booking status definitions from an expiration service.

**Why this matters:**
This is not the responsibility of an expiration service. Seat availability and order lifecycle rules should come from a shared order state policy, not a cleanup service.

**How to fix:**
Move booking-active status rules into an `OrderStatusPolicy`, enum method, or dedicated domain service.

---

### Issue #16: Service Has Hidden Dependency on `expired_at` Being Cast to Carbon

**Severity:** 🔵 LOW  
**Category:** Maintainability / Runtime Safety  
**Location:** Line 47

**Evidence:**
```php
&& $order->expired_at->isPast()
```

**Problem:**
The service assumes `expired_at` is always a Carbon instance when non-null.

**Why this matters:**
If the model cast is removed or changed, this will fail at runtime.

**How to fix:**
Ensure the `Order` model casts `expired_at` consistently and add tests. For defensive code:

```php
Carbon::parse($order->expired_at)->isPast()
```

---

### Issue #17: `expireOrder()` Returns a Nullable Result as Non-Nullable Order

**Severity:** 🔵 LOW  
**Category:** Type Safety  
**Location:** Line 68

**Evidence:**
```php
return $order->fresh();
```

**Problem:**
Eloquent `fresh()` can return `null` if the model no longer exists, but the method declares `Order`.

**Why this matters:**
Static analysis and runtime expectations are inaccurate.

**How to fix:**
Refresh the model in place or handle null.

```php
$order->refresh();
return $order;
```

---

### Issue #18: No Domain Event Emitted After Expiration

**Severity:** 🟡 MEDIUM  
**Category:** Architecture / Extensibility  
**Location:** Lines 26-38 and 57-68

**Evidence:**
```php
$expiredCount = $query->update([
```

```php
$order->update([
```

**Problem:**
Order expiration is implemented as direct data mutation and logging only.

**Why this matters:**
Other parts of the system may need to react: release seats, notify users, invalidate caches, update analytics, write audit logs. Without a domain event or explicit orchestration, side effects are easily missed.

**How to fix:**
Emit an `OrderExpired` event after a successful transactional state transition.

---

### Issue #19: No Error Handling Around Expiration Updates

**Severity:** 🔵 LOW  
**Category:** Exception Handling / Observability  
**Location:** Lines 26-31 and 57-61

**Evidence:**
```php
$expiredCount = $query->update([
```

```php
$order->update([
```

**Problem:**
Database exceptions are not caught or logged with context.

**Why this matters:**
Operational failures during cleanup may be invisible except for generic application error logs.

**How to fix:**
For scheduled jobs, wrap execution at the job/command level with structured logging and retry strategy.

---

### Issue #20: Bulk Expiration Query Performance Depends on Missing Composite Indexes

**Severity:** 🟡 MEDIUM  
**Category:** Performance / Database Correctness  
**Location:** Lines 16-23

**Evidence:**
```php
->where('status', self::ORDER_STATUS_PENDING)
->whereNull('paid_at')
->whereNotNull('expired_at')
->where('expired_at', '<=', now());
```

**Problem:**
This query requires efficient filtering on `status`, `paid_at`, `expired_at`, and optionally `showtime_id`.

**Why this matters:**
Without a composite index, scheduled cleanup can scan a large orders table.

**How to fix:**
Add an index aligned to the cleanup query.

```php
$table->index(['status', 'paid_at', 'expired_at']);
$table->index(['showtime_id', 'status', 'paid_at', 'expired_at']);
```

---

## Recommendations

### IMMEDIATE

1. **Move order/payment statuses to shared enums or domain constants.**
2. **Make expiration and payment confirmation use the same row-locking/state-transition strategy.**
3. **Release seats/holds/tickets atomically when orders expire.**
4. **Do not use stale model instances for mutation decisions.**
5. **Restrict payable payment statuses to an explicit allowlist.**

### SHORT TERM

6. **Replace count-only bulk expiration with auditable per-order expiration events.**
7. **Add composite indexes for expiration cleanup queries.**
8. **Capture `now()` once per operation.**
9. **Move active booking status rules out of this service.**
10. **Use `refresh()` instead of `fresh()` or handle null.**

### LONG TERM

11. **Introduce an order state machine with allowed transitions.**
12. **Emit domain events such as `OrderExpired`.**
13. **Add tests for expiration/payment webhook races.**
14. **Add observability for expiration failures and cleanup latency.**
15. **Create reconciliation checks for expired-but-paid and paid-but-cancelled orders.**

---

## Summary

OrderExpirationService.php is a small but high-impact service. The implementation is simple, but it is not production-safe for a booking/payment domain because it mutates order states without transactions, locking, seat release, strong state transition rules, or auditable per-order records. The highest-risk defects are race conditions between expiration and payment confirmation, stale model checks, and expiration that cancels orders without releasing related inventory.

**Strengths:**
- Simple implementation
- Bulk update avoids memory-heavy loops
- Has basic pending/unpaid checks
- Logs expiration activity
- Supports showtime-scoped cleanup

**Main Gaps:**
1. Magic status integers and payment strings
2. No transaction or `lockForUpdate()`
3. Bulk expiration can race with payment webhook/confirmation
4. Expiration does not release seats or holds
5. Stale in-memory model checks
6. Payability check is too permissive
7. Bulk update bypasses model events
8. Weak audit logging
9. Active booking status rule placed in wrong service
10. Missing composite index assumptions

**Status:** 🚫 Request changes before production acceptance

---

*Review completed: 2026-07-14 02:06 PM*  
*File #41/137 - Phase 3: Business Logic (13/20 complete)*