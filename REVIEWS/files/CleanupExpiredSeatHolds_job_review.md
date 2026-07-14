# File Review: CleanupExpiredSeatHolds.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Jobs/CleanupExpiredSeatHolds.php  
**Lines:** 17  
**Type:** Queue Job - Seat Hold Cleanup

---

## File Information

**Path:** `app/Jobs/CleanupExpiredSeatHolds.php`  
**Type:** Laravel Queued Job  
**Lines:** 17  
**Complexity:** Low  

**Purpose:**  
Queued job that delegates expired seat hold cleanup to `SeatService::cleanupExpiredSeatHolds()`.

**Business Impact:** 🔴 HIGH - Expired seat-hold cleanup directly affects seat availability, duplicate booking prevention, booking conversion, and customer experience.

---

## Overall Score

**Code Quality:** 6.8/10  
**Security:** 6.5/10  
**Performance:** 6.0/10  
**Maintainability:** 6.4/10  
**Laravel Best Practice:** 5.8/10  

**Overall Score:** 6.3/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

1. ✅ **Small and focused job** - The job delegates to a service instead of embedding cleanup logic.
2. ✅ **Uses Dependency Injection** - `SeatService` is injected into `handle()`.
3. ✅ **Implements `ShouldQueue`** - Cleanup can run asynchronously and not block API requests.
4. ✅ **No sensitive data serialized** - The job has no constructor payload or user data.
5. ✅ **Simple control flow** - The file is easy to read.

---

## Issues Found

### Issue #1: No Queue Retry/Timeout Configuration for Critical Cleanup Job

**Severity:** 🟡 MEDIUM  
**Category:** Queue Reliability / Production Readiness  
**Location:** Lines 9-16

**Evidence:**
```php
class CleanupExpiredSeatHolds implements ShouldQueue
{
    use Queueable;

    public function handle(SeatService $seatService): void
    {
        $seatService->cleanupExpiredSeatHolds();
    }
}
```

**Problem:**
The job does not define `$tries`, `$timeout`, `backoff()`, or `retryUntil()`.

**Why this matters:**
Seat hold cleanup is business-critical. If the job hangs or repeatedly fails, expired seats may remain unavailable. If it retries too aggressively, it may overload the database or conflict with active booking operations.

**How to fix:**
Define explicit queue runtime behavior.

```php
public int $tries = 3;
public int $timeout = 60;

public function backoff(): array
{
    return [10, 30, 60];
}
```

---

### Issue #2: No Failure Handling or Operational Alerting

**Severity:** 🟡 MEDIUM  
**Category:** Logging / Observability  
**Location:** Lines 13-16

**Evidence:**
```php
public function handle(SeatService $seatService): void
{
    $seatService->cleanupExpiredSeatHolds();
}
```

**Problem:**
There is no `failed()` method to log cleanup failures or alert operators.

**Why this matters:**
If cleanup silently fails in production, seats can remain locked indefinitely, causing lost sales and customer complaints.

**How to fix:**
Add a `failed()` hook with structured logging.

```php
public function failed(Throwable $exception): void
{
    Log::error('Expired seat hold cleanup job failed', [
        'exception' => $exception->getMessage(),
    ]);
}
```

---

### Issue #3: No Overlap Protection

**Severity:** 🟠 HIGH  
**Category:** Concurrency / Queue Safety  
**Location:** Lines 9-16

**Evidence:**
```php
class CleanupExpiredSeatHolds implements ShouldQueue
```

**Problem:**
The job does not prevent multiple cleanup jobs from running at the same time.

**Why this matters:**
Concurrent cleanup jobs may process the same expired holds simultaneously. Depending on the service implementation, this can cause duplicate updates, lock contention, deadlocks, misleading logs, or race conditions with active booking/payment flows.

**How to fix:**
Use Laravel queue middleware such as `WithoutOverlapping`.

```php
use Illuminate\Queue\Middleware\WithoutOverlapping;

public function middleware(): array
{
    return [
        (new WithoutOverlapping('cleanup-expired-seat-holds'))->expireAfter(300),
    ];
}
```

---

### Issue #4: No Unique Job Constraint

**Severity:** 🟡 MEDIUM  
**Category:** Queue Reliability / Performance  
**Location:** Lines 9-16

**Evidence:**
```php
class CleanupExpiredSeatHolds implements ShouldQueue
```

**Problem:**
The job does not implement uniqueness, so repeated scheduler dispatches can enqueue multiple identical cleanup jobs.

**Why this matters:**
If the scheduler is delayed or queue workers are down, many cleanup jobs can accumulate and then execute together when workers recover.

**How to fix:**
Use `ShouldBeUnique` or queue middleware depending on dispatch behavior.

```php
use Illuminate\Contracts\Queue\ShouldBeUnique;

class CleanupExpiredSeatHolds implements ShouldQueue, ShouldBeUnique
{
    public int $uniqueFor = 300;
}
```

---

### Issue #5: Queue Name Is Not Explicit

**Severity:** 🔵 LOW  
**Category:** Operations / Maintainability  
**Location:** Lines 9-16

**Evidence:**
```php
class CleanupExpiredSeatHolds implements ShouldQueue
{
    use Queueable;
```

**Problem:**
The job does not define which queue it should run on.

**Why this matters:**
Seat cleanup is time-sensitive. If it shares a default queue with slow email, analytics, or webhook jobs, expired holds may remain unavailable longer than intended.

**How to fix:**
Assign the job to a dedicated queue at construction or dispatch time.

```php
public function __construct()
{
    $this->onQueue('seat-maintenance');
}
```

---

### Issue #6: No Idempotency Contract at Job Level

**Severity:** 🟡 MEDIUM  
**Category:** Idempotency / Correctness  
**Location:** Lines 13-16

**Evidence:**
```php
$seatService->cleanupExpiredSeatHolds();
```

**Problem:**
The job does not document or enforce that cleanup is idempotent.

**Why this matters:**
Queued jobs can run more than once due to retries, worker crashes, visibility timeout issues, or manual re-dispatch. Cleanup code must be safe to repeat.

**How to fix:**
Document the idempotency requirement and ensure the service performs conditional updates based on current state.

---

### Issue #7: No Metrics Emitted for Cleanup Result

**Severity:** 🔵 LOW  
**Category:** Observability / Business Monitoring  
**Location:** Lines 13-16

**Evidence:**
```php
public function handle(SeatService $seatService): void
{
    $seatService->cleanupExpiredSeatHolds();
}
```

**Problem:**
The job ignores the cleanup result and emits no metrics.

**Why this matters:**
Operations teams need to know how many holds expired, how long cleanup took, and whether cleanup is falling behind.

**How to fix:**
Have `cleanupExpiredSeatHolds()` return a result object or count, then log/emit metrics.

```php
$count = $seatService->cleanupExpiredSeatHolds();

Log::info('Expired seat holds cleaned up', [
    'count' => $count,
]);
```

---

### Issue #8: Business-Critical Job Has No Scheduler/Dispatch Context in File

**Severity:** 🔵 LOW  
**Category:** Maintainability / Architecture  
**Location:** Lines 9-16

**Evidence:**
```php
class CleanupExpiredSeatHolds implements ShouldQueue
```

**Problem:**
The file contains no indication of expected cadence, queue, or operational contract.

**Why this matters:**
Future maintainers cannot determine whether this job is expected to run every minute, every five minutes, or only manually. For seat holds, timing is business-critical.

**How to fix:**
Document expected cadence in a class-level docblock and ensure scheduler configuration is reviewed separately.

```php
/**
 * Cleans expired seat holds. Expected to run every minute.
 */
```

---

### Issue #9: No Explicit Transaction/Locking Boundary at Job Level

**Severity:** 🟡 MEDIUM  
**Category:** Concurrency / Database Correctness  
**Location:** Lines 13-16

**Evidence:**
```php
$seatService->cleanupExpiredSeatHolds();
```

**Problem:**
The job delegates to the service without establishing a transaction or lock boundary.

**Why this matters:**
For seat holds, cleanup can race with booking confirmation, payment callback, or seat re-hold operations. While transaction logic may belong in the service, the job should make its concurrency contract explicit through middleware and service requirements.

**How to fix:**
Keep database transactions in the service, but add queue-level overlap protection and document that service cleanup must use atomic conditional updates.

---

### Issue #10: No Tags for Queue Monitoring

**Severity:** 🔵 LOW  
**Category:** Observability / Laravel Best Practice  
**Location:** Lines 9-16

**Evidence:**
```php
class CleanupExpiredSeatHolds implements ShouldQueue
```

**Problem:**
The job does not define queue tags.

**Why this matters:**
If Laravel Horizon or queue monitoring is used, tags make it easier to track seat-related maintenance jobs.

**How to fix:**
```php
public function tags(): array
{
    return ['seat-holds', 'cleanup', 'booking'];
}
```

---

## Recommendations

### IMMEDIATE

1. **Add `WithoutOverlapping` middleware** to prevent concurrent cleanup execution.
2. **Define retry, timeout, and backoff policy** for predictable queue behavior.
3. **Add a `failed()` method** with structured error logging.
4. **Ensure `SeatService::cleanupExpiredSeatHolds()` is idempotent and atomic.**

### SHORT TERM

5. **Use a dedicated queue** for seat-maintenance jobs.
6. **Emit cleanup metrics/logs** such as number of holds released and execution duration.
7. **Add queue tags** for observability.
8. **Document expected scheduler cadence** for this critical cleanup job.

### LONG TERM

9. **Consider event/audit logging** for expired seat hold releases.
10. **Add integration tests** for repeated job execution, concurrent jobs, and failure retries.
11. **Use database-level constraints/conditional updates** to make cleanup safe under duplicate execution.

---

## Summary

`CleanupExpiredSeatHolds.php` is intentionally small and delegates correctly to `SeatService`, but it is not production-ready as a critical queue job. Seat hold cleanup directly affects availability and revenue, so queue behavior must be explicit. The job currently lacks retry policy, timeout, failure logging, overlap protection, uniqueness, queue assignment, metrics, and monitoring tags.

**Strengths:**
- Focused job
- Dependency injection used
- No sensitive payload serialized
- Asynchronous execution supported

**Main Gaps:**
1. No overlap protection
2. No retry/backoff/timeout policy
3. No failure logging
4. No unique job protection
5. No dedicated queue
6. No metrics or audit trail
7. No documented cadence
8. No explicit idempotency contract

**Status:** 🚫 Request changes before production acceptance

---

*Review completed: 2026-07-14 02:25 PM*  
*File #44/137 - Phase 3: Business Logic (16/20 complete)*
