# File Review: ProcessPayOSWebhook.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Jobs/ProcessPayOSWebhook.php  
**Lines:** 89  
**Type:** Queue Job - Payment Webhook Processing

---

## File Information

**Path:** `app/Jobs/ProcessPayOSWebhook.php`  
**Type:** Laravel Queued Job  
**Lines:** 89  
**Complexity:** Medium  

**Purpose:**  
Processes PayOS webhook payloads asynchronously by delegating to `PaymentService::handleWebhook()` and logs processing success/failure.

**Business Impact:** 🔴 CRITICAL - Webhook processing controls payment confirmation, order fulfillment, ticket issuance, and revenue recognition.

---

## Overall Score

**Code Quality:** 6.2/10  
**Security:** 4.8/10  
**Performance:** 6.0/10  
**Maintainability:** 5.8/10  
**Laravel Best Practice:** 5.7/10  

**Overall Score:** 5.7/10

**Decision:** 🚫 **REQUEST CHANGES**

---

## Strengths

1. ✅ **Uses queued processing** - Payment webhook processing is moved off the request path.
2. ✅ **Has retry configuration** - `$tries = 3` and `$backoff = 60` are explicitly defined.
3. ✅ **Uses Dependency Injection** - `PaymentService` is injected into `handle()`.
4. ✅ **Logs processing start and success** - There is basic operational visibility.
5. ✅ **Defines `failed()` hook** - Final job failure is logged after retries are exhausted.
6. ✅ **No direct SQL or unsafe query building** - This file delegates business logic to the payment service.

---

## Issues Found

### Issue #1: Full Webhook Payload Is Logged on Final Failure

**Severity:** 🔴 CRITICAL  
**Category:** Security / Sensitive Data Exposure  
**Location:** Lines 80-84

**Evidence:**
```php
Log::critical('PayOS webhook job failed after all retries', [
    'order_code' => $this->webhookData['data']['orderCode'] ?? null,
    'error' => $exception->getMessage(),
    'webhook_data' => $this->webhookData,
]);
```

**Problem:**
The job logs the entire webhook payload.

**Why this matters:**
Payment webhook payloads can include payer details, transaction references, account identifiers, signatures, metadata, or other sensitive payment information. Logging the entire payload creates long-term sensitive data exposure through log files and centralized logging systems.

**How to fix:**
Log only whitelisted safe fields.

```php
Log::critical('PayOS webhook job failed after all retries', [
    'order_code' => data_get($this->webhookData, 'data.orderCode'),
    'payment_status' => data_get($this->webhookData, 'data.status'),
    'error_class' => $exception::class,
]);
```

---

### Issue #2: Full Stack Trace Is Logged on Every Retry Failure

**Severity:** 🟠 HIGH  
**Category:** Security / Logging  
**Location:** Lines 63-68

**Evidence:**
```php
Log::error('PayOS webhook processing failed', [
    'order_code' => $this->webhookData['data']['orderCode'] ?? null,
    'attempt' => $this->attempts(),
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
]);
```

**Problem:**
The job logs the full exception trace on each failed attempt.

**Why this matters:**
Stack traces can expose internal paths, class names, service structure, environment details, and potentially sensitive payload fragments inside exception messages.

**How to fix:**
Log trace only in local/debug environments or rely on an error monitoring system.

```php
Log::error('PayOS webhook processing failed', [
    'order_code' => data_get($this->webhookData, 'data.orderCode'),
    'attempt' => $this->attempts(),
    'error_class' => $e::class,
]);
```

---

### Issue #3: No Webhook Idempotency or Unique Job Constraint

**Severity:** 🔴 CRITICAL  
**Category:** Payment Correctness / Idempotency  
**Location:** Lines 13-57

**Evidence:**
```php
class ProcessPayOSWebhook implements ShouldQueue
```

```php
$paymentService->handleWebhook($this->webhookData);
```

**Problem:**
The job does not implement `ShouldBeUnique`, does not use `WithoutOverlapping`, and does not include a job-level idempotency key.

**Why this matters:**
Payment providers commonly deliver the same webhook multiple times. Queue retries can also execute the same payload more than once. Without idempotency, this can create duplicate payment handling, duplicate fulfillment, duplicate tickets, duplicate audit events, or incorrect order transitions.

**How to fix:**
Use the provider event/order identifier as a uniqueness/idempotency key and enforce idempotency in `PaymentService`.

```php
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ProcessPayOSWebhook implements ShouldQueue, ShouldBeUnique
{
    public function uniqueId(): string
    {
        return 'payos-webhook:' . data_get($this->webhookData, 'data.orderCode');
    }
}
```

---

### Issue #4: No Overlap Protection Per Order Code

**Severity:** 🔴 CRITICAL  
**Category:** Concurrency / Payment Race Condition  
**Location:** Lines 49-57

**Evidence:**
```php
public function handle(PaymentService $paymentService): void
{
    ...
    $paymentService->handleWebhook($this->webhookData);
```

**Problem:**
Multiple queued jobs for the same `orderCode` can execute concurrently.

**Why this matters:**
Concurrent webhook jobs can race on the same order/payment row. This is a high-risk money-flow race condition that can duplicate fulfillment or overwrite payment state.

**How to fix:**
Add `WithoutOverlapping` keyed by order code.

```php
use Illuminate\Queue\Middleware\WithoutOverlapping;

public function middleware(): array
{
    return [
        (new WithoutOverlapping('payos-webhook:' . data_get($this->webhookData, 'data.orderCode')))
            ->expireAfter(300),
    ];
}
```

---

### Issue #5: Constructor Accepts Arbitrary Payload Without Shape Validation

**Severity:** 🟠 HIGH  
**Category:** Validation / Robustness  
**Location:** Lines 41-44

**Evidence:**
```php
public function __construct(array $webhookData)
{
    $this->webhookData = $webhookData;
}
```

**Problem:**
The job accepts any array and stores it for later processing.

**Why this matters:**
If malformed data is dispatched, the job will fail asynchronously instead of being rejected at the boundary. This increases retry noise and can hide webhook integration issues.

**How to fix:**
Validate required payload shape before dispatching or in the constructor using a dedicated DTO.

```php
public function __construct(private readonly PayOSWebhookData $webhookData)
{
}
```

---

### Issue #6: Uses Loose Protected Untyped Property

**Severity:** 🔵 LOW  
**Category:** PHP 8+ / Maintainability  
**Location:** Lines 31-36

**Evidence:**
```php
/**
 * The webhook payload data.
 *
 * @var array
 */
protected $webhookData;
```

**Problem:**
The payload property is not typed.

**Why this matters:**
The project targets PHP 8+. Untyped properties reduce static analysis quality and make refactoring less safe.

**How to fix:**
```php
protected array $webhookData;
```

---

### Issue #7: Retry Policy Is Too Generic for Payment Webhooks

**Severity:** 🟡 MEDIUM  
**Category:** Queue Reliability / Payment Correctness  
**Location:** Lines 17-29

**Evidence:**
```php
public $tries = 3;
public $backoff = 60;
```

**Problem:**
The retry policy uses a fixed 60-second delay and only three attempts.

**Why this matters:**
Some failures are transient and should use exponential backoff. Other failures are permanent validation/signature problems and should not be retried. A generic retry policy can either under-retry transient DB/API failures or repeatedly retry bad payloads.

**How to fix:**
Use exception-specific retry behavior and exponential backoff.

```php
public function backoff(): array
{
    return [10, 60, 300];
}
```

---

### Issue #8: No Explicit Job Timeout

**Severity:** 🟡 MEDIUM  
**Category:** Queue Reliability / Production Readiness  
**Location:** Lines 13-29

**Evidence:**
```php
class ProcessPayOSWebhook implements ShouldQueue
{
    ...
    public $tries = 3;
    public $backoff = 60;
```

**Problem:**
The job has no explicit `$timeout`.

**Why this matters:**
Payment handling can involve database locks, external services, and fulfillment. Without a timeout, a hung job can occupy a worker and block payment processing.

**How to fix:**
```php
public int $timeout = 60;
```

---

### Issue #9: Exception Catch Uses `\Exception` Instead of `\Throwable`

**Severity:** 🟡 MEDIUM  
**Category:** Exception Handling  
**Location:** Line 62

**Evidence:**
```php
} catch (\Exception $e) {
```

**Problem:**
The catch block does not catch PHP `Error`/`Throwable` types.

**Why this matters:**
Type errors and other fatal runtime errors will skip the structured failure log in `handle()`.

**How to fix:**
```php
} catch (\Throwable $e) {
```

---

### Issue #10: Logs Exception Message Directly

**Severity:** 🟡 MEDIUM  
**Category:** Security / Logging  
**Location:** Lines 66 and 82

**Evidence:**
```php
'error' => $e->getMessage(),
```

```php
'error' => $exception->getMessage(),
```

**Problem:**
Raw exception messages are logged.

**Why this matters:**
Exception messages can include SQL fragments, provider payload values, tokens, IDs, or sensitive business details.

**How to fix:**
Log exception class and sanitized error code. Send full details to a restricted error monitoring tool when needed.

---

### Issue #11: No Dead-Letter/Persistent Failed Webhook Storage Implemented

**Severity:** 🟠 HIGH  
**Category:** Payment Operations / Recovery  
**Location:** Lines 86-87

**Evidence:**
```php
// TODO: Send alert to monitoring system (e.g., Sentry, Slack)
// TODO: Store in failed_webhooks table for manual review
```

**Problem:**
The job only has TODO comments for final failure handling.

**Why this matters:**
Payment webhook failure is not an optional enhancement. If a webhook fails permanently, the business needs a durable recovery path. Without storing failed payload metadata, orders can remain unpaid/unfulfilled while customer payment succeeded.

**How to fix:**
Persist a sanitized failed webhook record and alert operations immediately.

---

### Issue #12: No Alerting for Critical Payment Failure

**Severity:** 🟠 HIGH  
**Category:** Observability / Payment Operations  
**Location:** Lines 80-87

**Evidence:**
```php
Log::critical('PayOS webhook job failed after all retries', [
    ...
]);

// TODO: Send alert to monitoring system (e.g., Sentry, Slack)
```

**Problem:**
The job logs critical failure but does not actually notify any monitoring system.

**Why this matters:**
Logs are passive. Payment failures require active alerting because they can directly block ticket issuance after payment.

**How to fix:**
Integrate Sentry, Slack, PagerDuty, or a domain notification event for final payment webhook failures.

---

### Issue #13: No Queue Name or Priority for Payment Webhooks

**Severity:** 🟡 MEDIUM  
**Category:** Queue Architecture / Performance  
**Location:** Lines 13-44

**Evidence:**
```php
class ProcessPayOSWebhook implements ShouldQueue
```

**Problem:**
The job does not set a dedicated queue.

**Why this matters:**
Payment webhooks are high priority. They should not be delayed behind analytics, emails, cache warmers, or low-priority background work.

**How to fix:**
```php
public function __construct(array $webhookData)
{
    $this->webhookData = $webhookData;
    $this->onQueue('payments');
}
```

---

### Issue #14: No Queue Tags for Payment Monitoring

**Severity:** 🔵 LOW  
**Category:** Observability / Laravel Best Practice  
**Location:** Lines 13-89

**Evidence:**
```php
class ProcessPayOSWebhook implements ShouldQueue
```

**Problem:**
The job does not expose tags for queue monitoring.

**Why this matters:**
Horizon/queue dashboards need tags to quickly filter payment-provider jobs and specific order codes.

**How to fix:**
```php
public function tags(): array
{
    return [
        'payments',
        'payos',
        'order:' . data_get($this->webhookData, 'data.orderCode'),
    ];
}
```

---

### Issue #15: Payload Is Stored in Serialized Job Without Size Controls

**Severity:** 🟡 MEDIUM  
**Category:** Queue Performance / Reliability  
**Location:** Lines 41-44

**Evidence:**
```php
public function __construct(array $webhookData)
{
    $this->webhookData = $webhookData;
}
```

**Problem:**
The entire webhook payload is serialized into the queue job.

**Why this matters:**
Large payloads increase queue storage size and can exceed backend limits depending on the queue driver. It also increases the blast radius of sensitive data stored in queues.

**How to fix:**
Store a sanitized webhook record in a database table and queue only its ID.

```php
public function __construct(private readonly int $webhookEventId)
{
}
```

---

### Issue #16: No Signature Verification Contract Visible at Job Boundary

**Severity:** 🟡 MEDIUM  
**Category:** Security / Webhook Verification  
**Location:** Lines 41-57

**Evidence:**
```php
public function __construct(array $webhookData)
{
    $this->webhookData = $webhookData;
}

$paymentService->handleWebhook($this->webhookData);
```

**Problem:**
The job accepts payload data without any indication that it has already passed signature verification.

**Why this matters:**
Payment webhooks must be verified before processing. Even if verification happens before dispatch, the job interface does not express that only verified payloads are allowed.

**How to fix:**
Queue a `VerifiedPayOSWebhook` DTO or persisted verified webhook event ID.

---

### Issue #17: Comments Indicate Known Missing Production Work

**Severity:** 🟡 MEDIUM  
**Category:** Production Readiness / Code Smell  
**Location:** Lines 86-87

**Evidence:**
```php
// TODO: Send alert to monitoring system (e.g., Sentry, Slack)
// TODO: Store in failed_webhooks table for manual review
```

**Problem:**
Critical production behavior is left as TODO comments.

**Why this matters:**
TODOs in payment failure paths are not acceptable for production. They represent acknowledged missing operational controls.

**How to fix:**
Implement these actions before accepting the PR.

---

## Recommendations

### IMMEDIATE

1. **Remove full webhook payload logging** and log only safe whitelisted fields.
2. **Add idempotency/uniqueness by PayOS event/order identifier.**
3. **Add per-order `WithoutOverlapping` middleware.**
4. **Persist failed webhook metadata** for manual reconciliation.
5. **Add active alerting** for final payment webhook failures.
6. **Validate payload shape before dispatching the job.**

### SHORT TERM

7. **Use typed properties/DTOs** for webhook payloads.
8. **Add explicit timeout and payment queue assignment.**
9. **Use `Throwable` in catch blocks.**
10. **Replace fixed backoff with exponential or exception-aware retry policy.**
11. **Add Horizon tags** for order/payment observability.
12. **Queue only a persisted verified webhook event ID** instead of the entire payload.

### LONG TERM

13. **Create a durable webhook inbox/outbox model** with processing states.
14. **Add reconciliation tooling** for failed and duplicate payment events.
15. **Add integration tests** for duplicate webhook delivery, retry after partial failure, concurrent same-order webhooks, malformed payloads, and failed-job recovery.
16. **Define a provider-agnostic payment event contract** so gateway-specific jobs remain small and safe.

---

## Summary

`ProcessPayOSWebhook.php` has the right high-level idea—process payment webhooks asynchronously and retry failures—but it is not production-ready for a money-flow job. The biggest issues are sensitive data exposure through full payload logging, missing idempotency/unique job handling, no per-order overlap protection, and TODO-only final failure recovery. Payment provider webhooks are inherently duplicate-prone and operationally critical; this job must be deterministic, idempotent, observable, and recoverable.

**Strengths:**
- Uses queue processing
- Has basic retry settings
- Delegates to `PaymentService`
- Logs start/success/failure
- Defines a final failure hook

**Main Gaps:**
1. Full webhook payload logged on final failure
2. Full trace logged on each failed retry
3. No webhook idempotency/unique job key
4. No per-order overlap protection
5. Arbitrary unvalidated payload accepted
6. No durable failed webhook storage
7. No real alerting
8. No dedicated payment queue
9. No timeout
10. Entire payload serialized into queue storage

**Status:** 🚫 Request changes before production acceptance

---

*Review completed: 2026-07-14 02:31 PM*  
*File #45/137 - Phase 3: Business Logic (17/20 complete)*
