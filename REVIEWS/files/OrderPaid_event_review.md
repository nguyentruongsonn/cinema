# File Review: OrderPaid.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Events/OrderPaid.php  
**Lines:** 45  
**Type:** Broadcast Event - Order Payment Notification

---

## File Information

**Path:** `app/Events/OrderPaid.php`  
**Type:** Laravel Event / Broadcast Event  
**Lines:** 45  
**Complexity:** Low  

**Purpose:**  
Broadcasts an `order.paid` event on a private order channel with payment status information.

**Business Impact:** 🟠 HIGH - Payment completion notifications affect customer UX and may trigger frontend order/ticket display flows.

---

## Overall Score

**Code Quality:** 7.0/10  
**Security:** 5.8/10  
**Performance:** 6.8/10  
**Maintainability:** 6.8/10  
**Laravel Best Practice:** 6.4/10  

**Overall Score:** 6.6/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

1. ✅ **Small focused event** - The event has a single clear responsibility.
2. ✅ **Uses constructor property promotion** - Concise PHP 8+ style.
3. ✅ **Uses readonly properties** - Prevents mutation after construction.
4. ✅ **Uses `PrivateChannel`** - Better than public broadcasting for order data.
5. ✅ **Broadcast payload is limited** - Does not expose payment amount, gateway payload, or full order object.
6. ✅ **Explicit broadcast name** - `broadcastAs()` provides a stable frontend event name.

---

## Issues Found

### Issue #1: Private Channel Authorization Is Not Enforced in This File

**Severity:** 🟠 HIGH  
**Category:** Authorization / IDOR  
**Location:** Lines 21-29

**Evidence:**
```php
/**
 * Phát sóng trên private channel theo orderCode.
 * Chỉ user sở hữu đơn hàng mới nhận được.
 */
public function broadcastOn(): array
{
    return [
        new PrivateChannel("order.{$this->orderCode}"),
    ];
}
```

**Problem:**
The comment claims only the order owner can receive the event, but the file does not enforce that. `PrivateChannel` only requires that a corresponding channel authorization callback exists and correctly checks ownership.

**Why this matters:**
If channel authorization is missing or incorrectly implemented elsewhere, any authenticated user may subscribe to another user's order channel by guessing the `orderCode`. This is an IDOR risk and can expose order payment status and order number.

**How to fix:**
Ensure channel authorization validates both the authenticated user and order ownership. Do not rely on comments as security.

```php
Broadcast::channel('order.{orderCode}', function ($user, int $orderCode) {
    return Order::where('order_code', $orderCode)
        ->where('user_id', $user->id)
        ->exists();
});
```

---

### Issue #2: Channel Uses Order Code Instead of User-Scoped Channel

**Severity:** 🟡 MEDIUM  
**Category:** Security / Information Exposure  
**Location:** Line 28

**Evidence:**
```php
new PrivateChannel("order.{$this->orderCode}"),
```

**Problem:**
The channel name is based only on `orderCode`.

**Why this matters:**
Order codes are often externally visible in payment flows, callbacks, QR payments, emails, or URLs. A channel keyed only by order code makes authorization mistakes more dangerous because the subscription target can be guessed or obtained.

**How to fix:**
Use a user-scoped channel or include user scoping in the channel structure.

```php
new PrivateChannel("users.{$this->userId}.orders.{$this->orderCode}");
```

Authorization must still be enforced.

---

### Issue #3: `userId` Is Captured But Not Used

**Severity:** 🟡 MEDIUM  
**Category:** Clean Code / Security Design  
**Location:** Lines 15-19 and 25-29

**Evidence:**
```php
public function __construct(
    public readonly int    $orderCode,
    public readonly string $orderNumber,
    public readonly int    $userId,
) {}
```

```php
new PrivateChannel("order.{$this->orderCode}"),
```

**Problem:**
The event receives `userId`, but does not use it in the broadcast channel or payload.

**Why this matters:**
This is a code smell and indicates an incomplete authorization design. If `userId` is important for routing, it should be used. If it is not needed, it should be removed to avoid misleading maintainers.

**How to fix:**
Either use `userId` in the channel name or remove it from the constructor.

```php
new PrivateChannel("users.{$this->userId}.orders");
```

---

### Issue #4: `ShouldBroadcastNow` Can Add Latency to Payment Processing

**Severity:** 🟡 MEDIUM  
**Category:** Performance / Reliability  
**Location:** Line 11

**Evidence:**
```php
class OrderPaid implements ShouldBroadcastNow
```

**Problem:**
The event broadcasts synchronously immediately instead of being queued.

**Why this matters:**
Order payment completion is a money-flow path. Broadcasting synchronously can slow down the payment confirmation transaction/request and can fail if the broadcaster is unavailable. Payment persistence and fulfillment should not be coupled to websocket delivery.

**How to fix:**
Use `ShouldBroadcast` unless there is a strict requirement for synchronous broadcast.

```php
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class OrderPaid implements ShouldBroadcast
```

Also configure a dedicated queue for broadcast events if needed.

---

### Issue #5: No Broadcast Queue/Connection Isolation

**Severity:** 🔵 LOW  
**Category:** Queue Architecture / Maintainability  
**Location:** Line 11

**Evidence:**
```php
class OrderPaid implements ShouldBroadcastNow
```

**Problem:**
The event does not specify a queue, connection, or broadcast isolation strategy.

**Why this matters:**
Payment notifications should not be delayed behind unrelated work, but should also not block critical payment processing. Dedicated broadcast queue isolation improves reliability and observability.

**How to fix:**
Use queued broadcasting and assign a queue.

```php
public string $connection = 'redis';
public string $queue = 'broadcasts';
```

---

### Issue #6: Hard-Coded Status String

**Severity:** 🔵 LOW  
**Category:** Maintainability / Domain Consistency  
**Location:** Line 42

**Evidence:**
```php
'status'       => 'paid',
```

**Problem:**
The paid status is hard-coded as a magic string.

**Why this matters:**
If order/payment status naming changes, this event can drift from domain constants or enums and produce inconsistent API/frontend state.

**How to fix:**
Use a domain enum or model constant.

```php
'status' => Order::STATUS_PAID,
```

---

### Issue #7: No Event Versioning

**Severity:** 🔵 LOW  
**Category:** API Consistency / Frontend Compatibility  
**Location:** Lines 37-44

**Evidence:**
```php
public function broadcastWith(): array
{
    return [
        'order_code'   => $this->orderCode,
        'order_number' => $this->orderNumber,
        'status'       => 'paid',
    ];
}
```

**Problem:**
The broadcast payload has no schema version.

**Why this matters:**
Realtime events are API contracts. Without event versioning, frontend consumers can break silently when payload fields are renamed or changed.

**How to fix:**
Add a version field.

```php
'event_version' => 1,
```

---

### Issue #8: No Timestamp in Broadcast Payload

**Severity:** 🔵 LOW  
**Category:** API Consistency / Observability  
**Location:** Lines 37-44

**Evidence:**
```php
return [
    'order_code'   => $this->orderCode,
    'order_number' => $this->orderNumber,
    'status'       => 'paid',
];
```

**Problem:**
The payload does not include the time the event was produced.

**Why this matters:**
Clients and logs cannot distinguish stale events from current events, especially under reconnect/retry behavior.

**How to fix:**
Include a server-side timestamp.

```php
'paid_at' => now()->toISOString(),
```

---

### Issue #9: Comment Is Not in Project-Wide Language/Style

**Severity:** 🔵 LOW  
**Category:** Readability / Maintainability  
**Location:** Lines 21-24

**Evidence:**
```php
/**
 * Phát sóng trên private channel theo orderCode.
 * Chỉ user sở hữu đơn hàng mới nhận được.
 */
```

**Problem:**
The comment is written in Vietnamese while most code/review conventions appear English.

**Why this matters:**
Mixed-language comments reduce maintainability for distributed teams and future reviewers.

**How to fix:**
Use one project-wide language for code comments.

```php
/**
 * Broadcasts on a private order channel.
 * Only the owner of the order should be authorized to subscribe.
 */
```

---

### Issue #10: No Tests Implied for Broadcast Authorization Contract

**Severity:** 🟡 MEDIUM  
**Category:** Testing / Authorization  
**Location:** Lines 21-29

**Evidence:**
```php
new PrivateChannel("order.{$this->orderCode}"),
```

**Problem:**
This file relies on external channel authorization but does not make the ownership requirement enforceable by construction.

**Why this matters:**
Broadcast authorization bugs are easy to miss and can expose private order information. This event should have tests verifying that a user cannot subscribe to another user's order channel.

**How to fix:**
Add feature tests for channel authorization:
- owner can subscribe to the order channel
- non-owner cannot subscribe
- unauthenticated user cannot subscribe

---

## Recommendations

### IMMEDIATE

1. **Verify and test private channel authorization** for `order.{orderCode}`.
2. **Use a user-scoped channel name** or remove unused `userId`.
3. **Switch from `ShouldBroadcastNow` to queued `ShouldBroadcast`** unless synchronous broadcast is explicitly required.
4. **Replace hard-coded `paid` with a domain constant/enum.**

### SHORT TERM

5. **Add event schema version and timestamp** to the broadcast payload.
6. **Add broadcast authorization tests** for owner/non-owner/guest subscription cases.
7. **Standardize comment language**.
8. **Define broadcast queue/connection** for operational isolation.

### LONG TERM

9. **Document realtime event contracts** for frontend consumers.
10. **Adopt a consistent event payload envelope** for all broadcast events.
11. **Centralize order channel naming** to avoid drift between event, routes/channels, and frontend subscriptions.

---

## Summary

`OrderPaid.php` is compact and avoids exposing full order/payment objects, but it is not yet strong enough for production payment notifications. The biggest risk is authorization: the file claims only the order owner receives the event, but the implementation depends entirely on external channel authorization. The use of `orderCode` as the sole channel identifier increases the impact of any channel authorization mistake. Synchronous broadcasting also risks coupling websocket delivery to payment processing.

**Strengths:**
- Small focused event
- Uses private channel
- Minimal payload
- Readonly constructor properties
- Explicit broadcast alias

**Main Gaps:**
1. Ownership authorization not enforced in this file
2. Channel name is not user-scoped
3. `userId` is unused
4. Synchronous broadcasting can delay payment path
5. Hard-coded status string
6. No event version/timestamp
7. Broadcast authorization tests are required

**Status:** 🚫 Request changes before production acceptance

---

*Review completed: 2026-07-14 02:36 PM*  
*File #46/137 - Phase 3: Business Logic (18/20 complete)*
