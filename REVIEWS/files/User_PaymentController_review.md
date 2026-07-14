# File Review: User/PaymentController.php

**Review Date:** 2026-07-14  
**Reviewer:** Senior Software Engineer + Security Reviewer  
**File:** app/Http/Controllers/User/PaymentController.php  
**Lines:** 121  
**Type:** User Payment Controller

---

## File Summary

`User\PaymentController` handles user-facing payment initiation, PayOS webhook processing, and order summary polling. It delegates core payment/order work to `PaymentService` and `OrderService`, uses `CreatePaymentRequest`, and returns responses through the shared `ApiResponse` trait.

This controller is better structured than several other controllers because it uses FormRequest validation, resources for order summary, dependency injection, and reports unexpected exceptions. However, it still has production-impacting issues around webhook placement/verification visibility, sensitive gateway exception disclosure, payment idempotency/retry safety, hard-coded base URL handling, controller-side gateway synchronization during polling, and insufficient visible rate limiting/audit logging.

---

## Overall Score

**Overall Score:** 6.6/10

**Decision:** 🟠 **REQUEST CHANGES**

---

## Strengths

- Uses `CreatePaymentRequest` for payment creation validation.
- Uses dependency injection for `PaymentService` and `OrderService`.
- Uses explicit `JsonResponse` return types.
- Checks authenticated user type before payment creation and order summary access.
- Uses `OrderSummaryResource` for order summary serialization.
- Reports unexpected exceptions in `createPayment()` and `handleWebhook()`.
- Delegates most business logic to services instead of implementing payment state transitions directly in the controller.

---

## Issues

### Issue #1

**Severity:** Critical  
**Category:** Security / Webhook Verification  
**Location:** app/Http/Controllers/User/PaymentController.php:73-77

**Problem**

The webhook endpoint accepts raw request payload and passes it directly to the payment service. This file shows no signature verification, replay protection, source validation, or middleware guarantee.

```php
public function handleWebhook(Request $request): JsonResponse
{
    try {
        $result = $this->paymentService->handleWebhook($request->all());
```

**Why this matters**

Payment webhooks are money-flow endpoints. If route middleware is missing or misconfigured, an attacker can forge payment notifications. The controller itself provides no visible enforcement, and the review must treat this file as standalone source evidence.

**How to fix**

Require explicit webhook verification middleware or inject a verified webhook request type.

```php
public function __construct(...)
{
    $this->middleware('payos.signature')->only('handleWebhook');
}
```

Prefer a dedicated request object that exposes verified payload only:

```php
public function handleWebhook(VerifiedPayOSWebhookRequest $request): JsonResponse
{
    $result = $this->paymentService->handleWebhook($request->verifiedPayload());
}
```

---

### Issue #2

**Severity:** High  
**Category:** Architecture / Security Boundary  
**Location:** app/Http/Controllers/User/PaymentController.php:23,73

**Problem**

A PayOS webhook handler is placed inside a `User` namespace controller.

```php
namespace App\Http\Controllers\User;
```

```php
public function handleWebhook(Request $request): JsonResponse
```

**Why this matters**

Webhook endpoints are system-to-system callbacks, not user actions. Placing the webhook inside a user controller blurs authentication expectations and increases the risk that user-facing middleware, CSRF/auth assumptions, or route grouping are applied incorrectly.

**How to fix**

Move webhook handling to a dedicated integration controller.

```php
namespace App\Http\Controllers\Webhooks;

final class PayOSWebhookController extends Controller
{
    public function __invoke(VerifiedPayOSWebhookRequest $request): JsonResponse
    {
        ...
    }
}
```

---

### Issue #3

**Severity:** High  
**Category:** Security / Information Disclosure  
**Location:** app/Http/Controllers/User/PaymentController.php:61-62

**Problem**

The controller returns the raw payment gateway exception message to the client.

```php
} catch (PaymentGatewayException $e) {
    return $this->error('Lỗi cổng thanh toán: ' . $e->getMessage(), 502);
}
```

**Why this matters**

Gateway exception messages can include provider error details, request fields, order codes, signature/debug data, endpoint URLs, or internal failure reasons. Payment errors should be sanitized for clients and logged server-side.

**How to fix**

Report/log the detailed exception and return a generic message.

```php
} catch (PaymentGatewayException $e) {
    report($e);

    return $this->error('Không thể tạo thanh toán vào lúc này.', 502);
}
```

---

### Issue #4

**Severity:** High  
**Category:** Payment Idempotency / Duplicate Orders  
**Location:** app/Http/Controllers/User/PaymentController.php:47-53

**Problem**

Payment creation initiates payment directly with no visible idempotency key, duplicate-click protection, or client retry handling in the controller contract.

```php
$result = $this->paymentService->initiate(
    $user,
    $showtime,
    $request->validated(),
    url(''),
);
```

**Why this matters**

Payment initiation is vulnerable to duplicate submissions from double-clicks, browser retries, mobile network retries, and client timeout retries. Without idempotency at the API boundary, duplicate orders and duplicate gateway payment links can be created.

**How to fix**

Require an idempotency key for payment creation and pass it to the service.

```php
$idempotencyKey = $request->header('Idempotency-Key');

$result = $this->paymentService->initiate(
    $user,
    $showtime,
    $request->validated(),
    config('app.frontend_url'),
    $idempotencyKey,
);
```

---

### Issue #5

**Severity:** High  
**Category:** Authorization / IDOR Defense  
**Location:** app/Http/Controllers/User/PaymentController.php:102-106

**Problem**

Order summary lookup searches by gateway code first and then checks ownership in application code.

```php
$order = $this->orderService->findByGatewayCode($orderCode);

if (!$order || $order->user_id !== $user->id) {
    return $this->notFound('Không tìm thấy đơn hàng yêu cầu.');
}
```

**Why this matters**

Although the ownership check prevents direct data return, fetching by externally supplied gateway code first is weaker than scoping the query by the authenticated user. It can still create timing differences and unnecessary access to another user's order object in memory.

**How to fix**

Scope the lookup by user at the query/service layer.

```php
$order = $this->orderService->findUserOrderByGatewayCode($user->id, $orderCode);

if (! $order) {
    return $this->notFound('Không tìm thấy đơn hàng yêu cầu.');
}
```

---

### Issue #6

**Severity:** Medium  
**Category:** Side Effects / API Semantics  
**Location:** app/Http/Controllers/User/PaymentController.php:108-111

**Problem**

A read endpoint performs gateway synchronization as a side effect.

```php
if ($order->status !== Order::STATUS_PAID) {
    $this->paymentService->syncFromGateway($order);
}
```

**Why this matters**

Polling an order summary endpoint can trigger repeated external gateway calls, causing slow responses, provider rate limiting, race conditions with webhook processing, and unpredictable read behavior. GET-style summary endpoints should not perform heavy write/sync side effects.

**How to fix**

Move synchronization to queued jobs/webhook processing or provide a dedicated POST action for explicit sync.

```php
// Prefer: return current state only.
return $this->ok(new OrderSummaryResource($order));
```

If sync is required, throttle and lock it in the service.

---

### Issue #7

**Severity:** Medium  
**Category:** Concurrency / Payment State Race  
**Location:** app/Http/Controllers/User/PaymentController.php:108-111

**Problem**

The summary polling sync can run concurrently with webhook processing.

```php
$this->paymentService->syncFromGateway($order);
```

**Why this matters**

If webhook processing and user polling update the same order/payment concurrently, the system can produce lost updates, duplicate fulfillment, duplicate events, or inconsistent order/payment status unless the service has strict locking and idempotency.

**How to fix**

Ensure all payment status transitions are performed through one idempotent, locked service path. Prefer queued gateway sync with per-order lock.

```php
PaymentSyncJob::dispatch($order->id)->onQueue('payments');
```

---

### Issue #8

**Severity:** Medium  
**Category:** Configuration / Environment Correctness  
**Location:** app/Http/Controllers/User/PaymentController.php:52

**Problem**

The base URL is passed as `url('')`.

```php
url(''),
```

**Why this matters**

Payment gateway return/cancel/webhook URLs should not depend on the current request host. Behind proxies, load balancers, local tunnels, or malicious Host headers, generated URLs can be wrong or unsafe if trusted proxy/host configuration is imperfect.

**How to fix**

Use explicit configuration.

```php
config('app.frontend_url')
```

Or dedicated payment URL config:

```php
config('services.payos.return_base_url')
```

---

### Issue #9

**Severity:** Medium  
**Category:** Validation / Request Boundary  
**Location:** app/Http/Controllers/User/PaymentController.php:76

**Problem**

The webhook passes the entire request payload with `$request->all()`.

```php
$result = $this->paymentService->handleWebhook($request->all());
```

**Why this matters**

`all()` includes every submitted field and couples the service to unbounded, unvalidated input. Webhook payloads should be normalized and validated before entering payment state-transition logic.

**How to fix**

Use a dedicated webhook FormRequest.

```php
$result = $this->paymentService->handleWebhook($request->validated());
```

---

### Issue #10

**Severity:** Medium  
**Category:** Observability / Payment Audit Logging  
**Location:** app/Http/Controllers/User/PaymentController.php:36-67,73-88,95-120

**Problem**

There is no explicit payment audit logging in this controller for creation, webhook receipt, webhook skip, duplicate processing, or user polling sync.

```php
return $this->ok([
    'checkout_url'       => $result['checkout_url'],
    'gateway_order_code' => $result['gateway_order_code'],
    'order_number'       => $result['order_number'],
], 'Tạo đơn hàng thành công.');
```

**Why this matters**

Payment systems require traceability. In production, support and incident response need to know who initiated a payment, what gateway code was created, when callbacks arrived, and whether duplicate/skipped webhooks happened.

**How to fix**

Add structured logs or domain events with safe fields.

```php
logger()->info('Payment initiated', [
    'user_id' => $user->id,
    'order_number' => $result['order_number'] ?? null,
    'gateway_order_code' => $result['gateway_order_code'] ?? null,
]);
```

Do not log secrets or full webhook payloads.

---

### Issue #11

**Severity:** Medium  
**Category:** Rate Limiting / Abuse Protection  
**Location:** app/Http/Controllers/User/PaymentController.php:36-67,95-120

**Problem**

No visible rate limiting is applied to payment creation or order polling.

```php
public function createPayment(CreatePaymentRequest $request): JsonResponse
```

```php
public function showOrderSummary(Request $request, int $orderCode): JsonResponse
```

**Why this matters**

Payment creation can create expensive gateway calls and database writes. Order polling can trigger gateway sync calls. Without rate limiting, authenticated users can abuse these endpoints intentionally or accidentally.

**How to fix**

Apply dedicated throttles.

```php
$this->middleware('throttle:payment-create')->only('createPayment');
$this->middleware('throttle:order-polling')->only('showOrderSummary');
```

---

### Issue #12

**Severity:** Medium  
**Category:** Error Handling / Webhook Retry Semantics  
**Location:** app/Http/Controllers/User/PaymentController.php:83-88

**Problem**

Webhook processing returns `400` for `InvalidArgumentException` and `500` for unexpected failures, but the controller does not clearly distinguish permanent invalid webhooks from temporarily failed processing.

```php
} catch (\InvalidArgumentException $e) {
    return $this->error($e->getMessage(), 400);
} catch (Throwable $e) {
    report($e);
    return $this->error('Lỗi xử lý webhook.', 500);
}
```

**Why this matters**

Payment gateways retry based on status codes. Returning the wrong class of error can either suppress needed retries or cause repeated retries for permanent invalid data. The raw invalid argument message can also expose validation internals.

**How to fix**

Map webhook failures intentionally.

```php
catch (InvalidWebhookSignatureException $e) {
    report($e);
    return $this->error('Invalid webhook signature.', 401);
}

catch (WebhookTemporarilyUnavailableException $e) {
    report($e);
    return $this->error('Webhook processing temporarily unavailable.', 503);
}
```

---

### Issue #13

**Severity:** Medium  
**Category:** API Contract / Response Safety  
**Location:** app/Http/Controllers/User/PaymentController.php:55-59

**Problem**

The method assumes the service always returns specific array keys.

```php
'checkout_url'       => $result['checkout_url'],
'gateway_order_code' => $result['gateway_order_code'],
'order_number'       => $result['order_number'],
```

**Why this matters**

If the service response shape changes or a gateway partial failure occurs, this will throw an undefined index error and return a generic 500 after the order may already have been created.

**How to fix**

Return a typed DTO from `PaymentService`.

```php
return $this->ok([
    'checkout_url' => $result->checkoutUrl,
    'gateway_order_code' => $result->gatewayOrderCode,
    'order_number' => $result->orderNumber,
], 'Tạo đơn hàng thành công.');
```

---

### Issue #14

**Severity:** Low  
**Category:** Clean Code / Unused Import  
**Location:** app/Http/Controllers/User/PaymentController.php:8

**Problem**

`OrderSummaryResource` is used, but imports should be periodically verified. In this file, the imports are otherwise mostly used. No unused model import issue is visible for `User`, `Order`, or `Showtime`.

```php
use App\Http\Resources\OrderSummaryResource;
```

**Why this matters**

Unused imports are minor, but import hygiene improves readability and prevents stale dependencies.

**How to fix**

Run Laravel Pint/PHP-CS-Fixer and static analysis to enforce import cleanup. No direct removal is required for this line because it is used at line 119.

---

### Issue #15

**Severity:** Low  
**Category:** Clean Code / Formatting Consistency  
**Location:** app/Http/Controllers/User/PaymentController.php:55-59,78-81,104

**Problem**

The file uses manually aligned array arrows and inconsistent negation spacing.

```php
'checkout_url'       => $result['checkout_url'],
'gateway_order_code' => $result['gateway_order_code'],
```

```php
if (!$order || $order->user_id !== $user->id) {
```

**Why this matters**

Manual alignment and inconsistent style create noisy diffs and reduce maintainability. Laravel Pint should enforce a consistent code style.

**How to fix**

Use standard formatting.

```php
if (! $order || $order->user_id !== $user->id) {
```

---

## Security Review

Security concerns:

- Webhook verification is not visible in this controller.
- Webhook handler is placed in a user namespace, weakening architectural boundaries.
- Payment gateway exception details are returned to clients.
- Order summary lookup should be scoped by authenticated user at query level.
- Payment creation and polling rate limits are not visible.
- Webhook uses unvalidated `$request->all()`.

No direct SQL injection is visible in this controller.

---

## Performance Review

Performance concerns:

- Order summary polling can trigger external gateway synchronization.
- Missing visible rate limiting can amplify external API calls.
- Showtime is loaded with `screen`, but payment initiation may require additional relations; service should define required loading consistently.
- Repeated `refresh()->load()` is acceptable for one order but should not trigger unnecessary large relationship graphs.

---

## Database Review

Database/data correctness concerns:

- Payment initiation lacks visible idempotency at the request boundary.
- Polling sync may race with webhook processing.
- Gateway-code lookup should be scoped by user.
- Controller assumes service result keys exist after payment initiation.

---

## Concurrency Review

Concurrency concerns:

- Duplicate payment creation is possible without visible idempotency.
- Webhook and polling sync can update the same order concurrently.
- Repeated user polling may create multiple gateway sync attempts.
- Correctness depends heavily on `PaymentService` locking and idempotency.

---

## Laravel Best Practice Review

Recommended improvements:

- Move webhook logic to a dedicated webhook controller.
- Use a verified webhook FormRequest.
- Add explicit middleware for webhook signature verification and throttling.
- Use config values instead of `url('')` for gateway URLs.
- Scope order lookup by authenticated user in the service/query.
- Avoid write/sync side effects in read endpoints.
- Return a DTO from `PaymentService` instead of a raw array.
- Use generic client error messages and log detailed exceptions.

---

## Testing Review

Recommended tests:

1. Payment creation requires authentication.
2. Payment creation requires idempotency and returns the same result for retry.
3. Payment creation does not leak gateway exception details.
4. Invalid/missing webhook signature is rejected.
5. Valid PayOS webhook is processed once.
6. Duplicate webhook returns idempotent success.
7. Invalid webhook payload does not expose internal exception messages.
8. Order summary cannot access another user's order.
9. Order summary lookup is scoped by user.
10. Polling does not create duplicate payment synchronization side effects.
11. Webhook processing and polling sync cannot double-fulfill an order.
12. Payment creation and polling endpoints are rate limited.
13. Gateway URLs use configured base URL, not request host.

---

## Final Decision

🟠 **REQUEST CHANGES**

`User\PaymentController` is partially well-structured, but money-flow endpoints require stricter production guarantees. Webhook verification must be explicit, payment creation needs idempotency at the API boundary, raw gateway error disclosure must be removed, polling should not perform uncontrolled gateway sync side effects, and order lookup should be scoped by user before retrieval.

---

_Review completed: 2026-07-14 04:50 PM_  
_File #75/137 - Phase 4: Controllers (27/34 complete)_
