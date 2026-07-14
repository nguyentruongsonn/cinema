# File Review: SeatStatusUpdated.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Events/SeatStatusUpdated.php  
**Lines:** 50  
**Type:** Broadcast Event - Seat Availability Notification

---

## File Information

**Path:** `app/Events/SeatStatusUpdated.php`  
**Type:** Laravel Event / Broadcast Event  
**Lines:** 50  
**Complexity:** Low

**Purpose:**  
Broadcasts realtime seat status changes for a showtime so clients can update seat availability.

**Business Impact:** 🔴 CRITICAL - Seat status broadcasting directly affects booking UX, seat lock visibility, perceived availability, and duplicate-booking prevention signals.

---

## Overall Score

**Code Quality:** 6.8/10  
**Security:** 4.9/10  
**Performance:** 6.2/10  
**Maintainability:** 6.1/10  
**Laravel Best Practice:** 5.8/10

**Overall Score:** 5.9/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

1. ✅ **Small focused event** - The event has a single clear responsibility.
2. ✅ **Uses constructor property promotion** - Concise PHP 8+ implementation.
3. ✅ **Uses readonly properties** - Prevents accidental mutation after dispatch.
4. ✅ **Broadcast payload is small** - Only sends showtime, seat, status, and user id.
5. ✅ **Explicit broadcast name** - `seat.status.updated` is stable for frontend consumers.
6. ✅ **Public channel intent is documented** - The file clearly states unauthenticated users can receive updates.

---

## Issues Found

### Issue #1: Public Broadcast Exposes User ID

**Severity:** 🔴 CRITICAL  
**Category:** Security / Privacy / Sensitive Data Exposure  
**Location:** Lines 41-48

**Evidence:**

```php
public function broadcastWith(): array
{
    return [
        'showtime_id' => $this->showtimeId,
        'seat_id'     => $this->seatId,
        'status'      => $this->status,
        'user_id'     => $this->userId,
    ];
}
```

**Problem:**
The event broadcasts `user_id` on a public channel.

**Why this matters:**
The comment explicitly states the channel is public and available to unauthenticated users. Broadcasting user IDs exposes customer activity and allows observers to correlate seat selections with specific users. This is a privacy leak and creates unnecessary customer tracking risk.

**How to fix:**
Remove `user_id` from the public payload. If the frontend needs to identify the current user's own lock, send a non-sensitive per-session/client token visible only to that client or use a private user-scoped channel for user-specific data.

```php
public function broadcastWith(): array
{
    return [
        'showtime_id' => $this->showtimeId,
        'seat_id' => $this->seatId,
        'status' => $this->status,
    ];
}
```

---

### Issue #2: Public Showtime Channel Enables Competitor/Scraper Monitoring

**Severity:** 🟠 HIGH  
**Category:** Security / Business Data Exposure  
**Location:** Lines 22-30

**Evidence:**

```php
/**
 * Phát sóng trên public channel của suất chiếu.
 * Dùng public channel để tất cả người dùng (kể cả chưa đăng nhập) đều nhận được.
 */
public function broadcastOn(): array
{
    return [
        new Channel("showtime.{$this->showtimeId}"),
    ];
}
```

**Problem:**
All seat activity for a showtime is broadcast on a public channel.

**Why this matters:**
Seat locking and availability changes reveal demand patterns, purchase intent, occupancy velocity, and showtime popularity in real time. Competitors or scrapers can monitor public channels without authentication and collect business-sensitive analytics.

**How to fix:**
Require authentication for realtime seat updates or expose only coarse availability data publicly. Prefer a private/presence channel with authorization tied to the showtime booking page/session.

```php
use Illuminate\Broadcasting\PrivateChannel;

new PrivateChannel("showtime.{$this->showtimeId}.seats");
```

---

### Issue #3: `ShouldBroadcastNow` Couples Broadcasting to Booking Flow

**Severity:** 🟠 HIGH  
**Category:** Performance / Reliability / Booking Correctness  
**Location:** Line 11

**Evidence:**

```php
class SeatStatusUpdated implements ShouldBroadcastNow
```

**Problem:**
The event broadcasts synchronously.

**Why this matters:**
Seat locking and release flows are latency-sensitive. Synchronous broadcasting can slow the booking request and can fail due to broadcaster/network problems. A websocket outage must not cause booking or seat lock operations to fail or become slower.

**How to fix:**
Use queued broadcasting and isolate it on a high-priority realtime queue.

```php
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class SeatStatusUpdated implements ShouldBroadcast
{
    public string $queue = 'broadcasts';
}
```

---

### Issue #4: Status Is Unvalidated Free-Form String

**Severity:** 🟠 HIGH  
**Category:** Validation / Business Logic Correctness  
**Location:** Lines 15-20 and 43-48

**Evidence:**

```php
public function __construct(
    public readonly int    $showtimeId,
    public readonly int    $seatId,
    public readonly string $status,  // 'locked' | 'available'
    public readonly ?int   $userId = null,
) {}
```

```php
'status'      => $this->status,
```

**Problem:**
`status` is only documented by an inline comment. The constructor accepts any string.

**Why this matters:**
A typo or invalid state can be broadcast to all clients and cause inconsistent frontend behavior. Seat state is a critical booking domain concept and should not depend on comments for correctness.

**How to fix:**
Use a backed enum or validate the allowed statuses at construction.

```php
enum SeatBroadcastStatus: string
{
    case Locked = 'locked';
    case Available = 'available';
}
```

---

### Issue #5: Event Does Not Include Seat Hold Expiration Time

**Severity:** 🟡 MEDIUM  
**Category:** Business Logic / API Consistency  
**Location:** Lines 41-48

**Evidence:**

```php
return [
    'showtime_id' => $this->showtimeId,
    'seat_id'     => $this->seatId,
    'status'      => $this->status,
    'user_id'     => $this->userId,
];
```

**Problem:**
When a seat is locked, the event does not include when the lock expires.

**Why this matters:**
Clients cannot display accurate countdowns or recover from missed release events. This can produce stale UI where seats appear locked longer than they actually are.

**How to fix:**
Include a non-sensitive expiration timestamp for lock events.

```php
'locked_until' => $this->lockedUntil?->toISOString(),
```

---

### Issue #6: No Event Versioning

**Severity:** 🔵 LOW  
**Category:** API Consistency / Frontend Compatibility  
**Location:** Lines 41-49

**Evidence:**

```php
public function broadcastWith(): array
{
    return [
        'showtime_id' => $this->showtimeId,
        'seat_id'     => $this->seatId,
        'status'      => $this->status,
        'user_id'     => $this->userId,
    ];
}
```

**Problem:**
The event payload has no schema version.

**Why this matters:**
Realtime event payloads are API contracts. Without versioning, frontend clients can break silently when fields are removed, renamed, or changed.

**How to fix:**
Add an event version field.

```php
'event_version' => 1,
```

---

### Issue #7: No Server Timestamp in Broadcast Payload

**Severity:** 🔵 LOW  
**Category:** API Consistency / Observability  
**Location:** Lines 41-49

**Evidence:**

```php
return [
    'showtime_id' => $this->showtimeId,
    'seat_id'     => $this->seatId,
    'status'      => $this->status,
    'user_id'     => $this->userId,
];
```

**Problem:**
The event does not include a server timestamp.

**Why this matters:**
Clients cannot determine whether an event is stale after reconnects, websocket delays, or out-of-order delivery.

**How to fix:**
Include an emitted timestamp.

```php
'emitted_at' => now()->toISOString(),
```

---

### Issue #8: No Sequence Number or Revision for Out-of-Order Events

**Severity:** 🟡 MEDIUM  
**Category:** Concurrency / Realtime Correctness  
**Location:** Lines 41-49

**Evidence:**

```php
'status'      => $this->status,
```

**Problem:**
The event has no monotonic version, lock revision, or update timestamp tied to the seat state.

**Why this matters:**
Realtime events can arrive out of order. A client may receive `available` and then an older `locked` event, causing incorrect UI state. For seat booking, stale realtime state can mislead users and increase failed booking attempts.

**How to fix:**
Include a seat state version or authoritative `updated_at` timestamp from the persisted seat/hold state.

```php
'state_version' => $this->stateVersion,
```

---

### Issue #9: Comment Is Not in Project-Wide Language/Style

**Severity:** 🔵 LOW  
**Category:** Readability / Maintainability  
**Location:** Lines 22-25 and 33-35

**Evidence:**

```php
/**
 * Phát sóng trên public channel của suất chiếu.
 * Dùng public channel để tất cả người dùng (kể cả chưa đăng nhập) đều nhận được.
 */
```

```php
/**
 * Tên event mà JS sẽ lắng nghe.
 */
```

**Problem:**
Comments are written in Vietnamese while the codebase/review conventions primarily use English.

**Why this matters:**
Mixed-language comments reduce maintainability for distributed engineering teams.

**How to fix:**
Use one project-wide language for code comments.

```php
/**
 * Broadcasts seat status updates for a showtime.
 */
```

---

### Issue #10: Inline Comment Documents Business Rule Instead of Enforcing It

**Severity:** 🟡 MEDIUM  
**Category:** Clean Code / Domain Correctness  
**Location:** Line 18

**Evidence:**

```php
public readonly string $status,  // 'locked' | 'available'
```

**Problem:**
Allowed seat status values are encoded in an inline comment rather than code.

**Why this matters:**
Comments do not enforce runtime or static correctness. Future callers can pass invalid statuses and still compile.

**How to fix:**
Replace the primitive string with a domain enum or constants.

```php
public function __construct(
    public readonly int $showtimeId,
    public readonly int $seatId,
    public readonly SeatStatus $status,
) {}
```

---

### Issue #11: No Channel Authorization Tests Are Implied for Public Exposure

**Severity:** 🟡 MEDIUM  
**Category:** Testing / Security  
**Location:** Lines 22-30

**Evidence:**

```php
new Channel("showtime.{$this->showtimeId}"),
```

**Problem:**
The event intentionally uses a public channel, but there is no enforceable guardrail in this file preventing sensitive fields from being added to the payload later.

**Why this matters:**
Public realtime channels are easy to misuse. A future change can add customer, order, or payment metadata and expose it to unauthenticated users.

**How to fix:**
Add tests asserting the public payload contains only explicitly approved fields and never includes user/order/payment identifiers.

---

### Issue #12: Primitive ID Payload Makes Event Easy to Misuse

**Severity:** 🔵 LOW  
**Category:** Maintainability / Type Safety  
**Location:** Lines 15-20

**Evidence:**

```php
public function __construct(
    public readonly int    $showtimeId,
    public readonly int    $seatId,
    public readonly string $status,
    public readonly ?int   $userId = null,
) {}
```

**Problem:**
The event accepts unrelated primitive IDs without verifying that the seat belongs to the showtime.

**Why this matters:**
If callers pass mismatched `showtimeId` and `seatId`, clients receive invalid seat updates for the wrong showtime. The event does not make invalid combinations impossible.

**How to fix:**
Construct the event from a validated domain object or DTO that represents a real seat state transition.

```php
public function __construct(public readonly SeatStatusChangedData $data)
{
}
```

---

## Recommendations

### IMMEDIATE

1. **Remove `user_id` from the public broadcast payload.**
2. **Reconsider public channel exposure** for realtime seat activity.
3. **Switch from `ShouldBroadcastNow` to queued `ShouldBroadcast`** to avoid coupling websocket delivery to booking flow.
4. **Replace free-form `status` string with an enum or constants.**
5. **Add tests that guarantee no sensitive fields are broadcast publicly.**

### SHORT TERM

6. **Add lock expiration timestamp** for locked seat events.
7. **Add event version and server timestamp** to the payload.
8. **Add state revision/update timestamp** to protect clients from out-of-order events.
9. **Standardize comment language.**
10. **Use a DTO/domain event object** instead of loose primitive IDs.

### LONG TERM

11. **Define a realtime event contract** for all booking-related broadcasts.
12. **Centralize seat status transition broadcasting** so only committed state changes emit events.
13. **Add integration tests for websocket event ordering, stale lock releases, and concurrent seat lock updates.**

---

## Summary

`SeatStatusUpdated.php` is concise, but it is not production-ready for a critical booking workflow. The most serious issue is broadcasting `user_id` on a public channel available to unauthenticated users. The second major concern is synchronous broadcasting through `ShouldBroadcastNow`, which couples websocket delivery to seat locking/release paths. The event also relies on string comments for status correctness and lacks metadata needed for reliable realtime clients.

**Strengths:**

- Small and focused
- Uses readonly constructor properties
- Simple payload
- Explicit event name
- Clear public-channel intent

**Main Gaps:**

1. Public channel exposes `user_id`
2. Public channel leaks realtime demand/seat activity
3. Synchronous broadcasting can slow booking flow
4. Free-form unvalidated status string
5. No lock expiration timestamp
6. No event version/timestamp
7. No sequence/version for out-of-order events
8. No guardrail tests for public payload safety

**Status:** 🚫 Request changes before production acceptance

---

_Review completed: 2026-07-14 02:42 PM_  
_File #47/137 - Phase 3: Business Logic (19/20 complete)_
