====================================================

File:
app/Http/Requests/StorePaymentRequest.php

Overall Score:
2.8/10

Decision:
BLOCKING

----------------------------------------------------

Strengths

- Uses a dedicated `FormRequest`.
- Requires an order reference.
- Restricts `payment_method` to a fixed list.
- Requires an amount field.

----------------------------------------------------

Issues

### Issue #1

Severity:
Critical

Category:
Authorization / IDOR

Location:
app/Http/Requests/StorePaymentRequest.php:9-12

Problem

The request authorizes every caller:

```php
public function authorize(): bool
{
    return true;
}
```

Why this matters

Payment creation is a critical money-flow operation. This request does not verify that the authenticated user owns the submitted `order_id`, is allowed to pay for it, or is authenticated at all. If route middleware is missing or misconfigured, this can become a direct IDOR allowing one user to initiate payment against another user's order.

How to fix

Require an authenticated user and verify order ownership/permission.

Example

```php
public function authorize(): bool
{
    $orderId = $this->input('order_id');

    return $this->user() !== null
        && \App\Models\Order::whereKey($orderId)
            ->where('user_id', $this->user()->id)
            ->exists();
}
```

Prefer a policy such as `$this->user()?->can('pay', $order)` when route model binding is available.

----------------------------------------------------

### Issue #2

Severity:
Critical

Category:
Payment Security / Business Logic

Location:
app/Http/Requests/StorePaymentRequest.php:19

Problem

The client is allowed to submit the payment amount:

```php
'amount' => 'required|numeric|min:0',
```

Why this matters

A payment amount must be derived server-side from the order total, not trusted from the request. This validation allows a caller to submit `0`, a smaller value than the order total, or an arbitrary amount. If downstream code uses this value, users can underpay orders or create inconsistent payment records.

How to fix

Remove `amount` from the public request contract. Compute it from the order inside the payment service.

Example

Before

```php
'amount' => 'required|numeric|min:0',
```

After

```php
'order_id' => ['required', 'integer', Rule::exists('orders', 'id')],
'payment_method' => ['required', Rule::in(['payos', 'vnpay', 'momo'])],
```

Then:

```php
$amount = $order->remaining_amount;
```

----------------------------------------------------

### Issue #3

Severity:
Critical

Category:
Idempotency / Duplicate Payment

Location:
app/Http/Requests/StorePaymentRequest.php:16-20

Problem

The request does not require an idempotency key for payment creation.

```php
return [
    'order_id' => 'required|exists:orders,id',
    'payment_method' => 'required|in:payos,credit_card,debit_card,bank_transfer,e_wallet,vnpay,momo',
    'amount' => 'required|numeric|min:0',
];
```

Why this matters

Payment creation is highly retry-prone: browsers double-submit, mobile networks retry, users refresh checkout pages, and gateways may timeout. Without a required idempotency key, duplicate requests can create duplicate payment attempts, duplicate gateway links, duplicate payment rows, or conflicting payment states.

How to fix

Require an idempotency key and enforce it atomically in the payment service/database.

Example

```php
'idempotency_key' => ['required', 'string', 'max:100'],
```

Back it with a unique constraint scoped to the user/order/payment action.

----------------------------------------------------

### Issue #4

Severity:
High

Category:
Validation / Order State Correctness

Location:
app/Http/Requests/StorePaymentRequest.php:17

Problem

The request only checks that the order exists:

```php
'order_id' => 'required|exists:orders,id',
```

Why this matters

An order row can exist while being expired, cancelled, already paid, partially refunded, owned by another user, or otherwise not payable. Accepting any existing order can create duplicate payment attempts, payment for invalid orders, or state-machine corruption.

How to fix

Validate payable order state using a scoped rule or custom validation rule.

Example

```php
'order_id' => [
    'required',
    'integer',
    Rule::exists('orders', 'id')->where(fn ($query) =>
        $query->where('user_id', $this->user()->id)
              ->where('status', 'pending')
    ),
],
```

Final status checks must still happen inside a transaction with row locking.

----------------------------------------------------

### Issue #5

Severity:
High

Category:
Money Correctness

Location:
app/Http/Requests/StorePaymentRequest.php:19

Problem

The amount rule permits zero and arbitrary decimal precision:

```php
'amount' => 'required|numeric|min:0',
```

Why this matters

Even if the amount were only informational, allowing zero and arbitrary decimals is unsafe for money. It can create invalid gateway requests, rounding mismatches, failed reconciliation, or database precision errors.

How to fix

Do not accept amount from the client. If an amount field is retained for a specific gateway use case, it must be greater than zero, precision-bound, and compared against the server-calculated order amount.

Example

```php
'amount' => ['required', 'decimal:0,2', 'min:1'],
```

Server-side comparison remains mandatory.

----------------------------------------------------

### Issue #6

Severity:
High

Category:
Payment Method / Architecture

Location:
app/Http/Requests/StorePaymentRequest.php:18

Problem

The request lists many payment methods:

```php
'payment_method' => 'required|in:payos,credit_card,debit_card,bank_transfer,e_wallet,vnpay,momo',
```

Why this matters

The request contract accepts methods that may not be implemented, configured, enabled, or available for the user's region/order. This creates broken checkout paths and can persist unsupported payment methods into payment records.

How to fix

Validate against enabled payment methods from configuration or a payment provider registry, not a hard-coded list in the request.

Example

```php
'payment_method' => ['required', Rule::in(config('payments.enabled_methods'))],
```

Also validate gateway availability for the current environment and order currency.

----------------------------------------------------

### Issue #7

Severity:
Medium

Category:
Validation / API Correctness

Location:
app/Http/Requests/StorePaymentRequest.php:17

Problem

`order_id` does not explicitly require an integer:

```php
'order_id' => 'required|exists:orders,id',
```

Why this matters

IDs should have explicit type validation for a stable API contract. This request is inconsistent with other request classes that validate IDs as integers.

How to fix

Add `integer`.

Example

```php
'order_id' => ['required', 'integer', Rule::exists('orders', 'id')],
```

----------------------------------------------------

### Issue #8

Severity:
Medium

Category:
Clean Code / API Consistency

Location:
app/Http/Requests/StorePaymentRequest.php:23-26

Problem

The request defines an empty messages method:

```php
public function messages(): array
{
    return [];
}
```

Why this matters

This is dead code and adds noise. For a checkout/payment endpoint, validation errors should be clear and consistent because they directly affect payment conversion and supportability.

How to fix

Remove the method or provide meaningful payment-specific validation messages.

Example

```php
public function messages(): array
{
    return [
        'order_id.required' => 'An order is required to create a payment.',
        'payment_method.in' => 'The selected payment method is not supported.',
    ];
}
```

----------------------------------------------------

Final Assessment

`StorePaymentRequest` is blocking for production. It authorizes all callers, trusts a client-supplied payment amount, lacks idempotency, validates only order existence rather than ownership/payable state, and accepts hard-coded payment methods that may not match configured gateways. A payment request contract must be strict, server-derived, ownership-aware, and idempotent before it can be safely used in a cinema booking checkout flow.
