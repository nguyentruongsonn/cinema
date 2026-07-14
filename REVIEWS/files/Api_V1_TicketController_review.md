====================================================

File:
app/Http/Controllers/Api/V1/TicketController.php

Overall Score:
4.1/10

Decision:
BLOCKING

----------------------------------------------------

Strengths

- User ticket listing and detail lookup are scoped by `user_id`, reducing IDOR risk for those two customer-facing actions.
- Uses eager loading for related order, showtime, movie, screen, theater, branch, seat, and seat type data.
- Paginates the ticket list and caps `per_page` to 50.

----------------------------------------------------

Issues

### Issue #1

Severity:
Critical

Category:
Authorization / Security

Location:
app/Http/Controllers/Api/V1/TicketController.php:116

Problem

The scanner verification endpoint does not authenticate or authorize an admin/scanner user before validating and consuming a ticket:

```php
public function verify(Request $request): JsonResponse
{
    try {
        $request->validate([
            'ticket_code' => 'required|string'
        ]);
```

There is no `$request->user()` check, no role/permission check, no policy call, and no visible guard requirement in this method.

Why this matters

Anyone who can reach this endpoint can submit a ticket code and mark a ticket as used. This is a direct revenue and customer-support risk. A malicious user could invalidate another customer's ticket before arrival.

How to fix

Require authentication and explicit authorization for ticket scanning. Enforce it with middleware and/or a policy/gate inside the method.

Example

```php
public function verify(VerifyTicketRequest $request): JsonResponse
{
    $this->authorize('verify', Ticket::class);

    // scanner-only verification logic
}
```

----------------------------------------------------

### Issue #2

Severity:
Critical

Category:
Concurrency / Database Correctness

Location:
app/Http/Controllers/Api/V1/TicketController.php:142

Problem

Ticket consumption is not atomic. The code checks status and later updates the ticket without a transaction or row lock:

```php
if ($ticket->status === 'used') {
    return $this->error('Vé đã được sử dụng trước đó vào lúc ' . $ticket->used_at->format('d/m/Y H:i'), 400);
}
```

and:

```php
$ticket->status = 'used';
$ticket->used_at = now();
$ticket->save();
```

Why this matters

Two scanners or repeated requests can read the same valid ticket simultaneously and both mark it as used. This creates an audit gap and can allow duplicate entry because both requests may return success.

How to fix

Wrap verification in a database transaction and lock the ticket row with `lockForUpdate()` before checking and updating state.

Example

```php
return DB::transaction(function () use ($ticketCode) {
    $ticket = Ticket::query()
        ->where('ticket_code', $ticketCode)
        ->lockForUpdate()
        ->firstOrFail();

    if ($ticket->status !== Ticket::STATUS_VALID) {
        return $this->error('Ticket is not valid.', 409);
    }

    $ticket->forceFill([
        'status' => Ticket::STATUS_USED,
        'used_at' => now(),
    ])->save();

    return $this->ok(/* response */);
});
```

----------------------------------------------------

### Issue #3

Severity:
High

Category:
API Security / Information Disclosure

Location:
app/Http/Controllers/Api/V1/TicketController.php:71

Problem

The list endpoint exposes raw exception details, file paths, and line numbers to clients:

```php
return $this->error('Đã xảy ra lỗi khi tải danh sách vé. Chi tiết: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine(), 500);
```

Why this matters

This leaks internal file paths, line numbers, and exception messages. In production this can expose implementation details useful for attackers.

How to fix

Log the exception server-side and return a generic message.

Example

```php
report($e);

return $this->error('Đã xảy ra lỗi khi tải danh sách vé.', 500);
```

----------------------------------------------------

### Issue #4

Severity:
High

Category:
Validation / Security

Location:
app/Http/Controllers/Api/V1/TicketController.php:119

Problem

Ticket verification accepts an unbounded raw string:

```php
'ticket_code' => 'required|string'
```

Why this matters

Ticket codes are security-sensitive bearer identifiers. The request should enforce max length, expected format, normalization, and rate limiting. Without bounds, the endpoint is easier to abuse for brute-force attempts and unnecessarily accepts malformed input.

How to fix

Use a FormRequest with strict rules and rate limiting.

Example

```php
'ticket_code' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9\\-]+$/']
```

----------------------------------------------------

### Issue #5

Severity:
High

Category:
Security / Brute Force Protection

Location:
app/Http/Controllers/Api/V1/TicketController.php:125

Problem

The scanner endpoint directly looks up tickets by `ticket_code` and returns whether the ticket exists:

```php
$ticket = Ticket::query()
    ->where('ticket_code', $ticketCode)
```

and:

```php
return $this->error('Vé không tồn tại.', 404);
```

There is no visible throttling, scanner identity, audit logging, or failed-attempt tracking.

Why this matters

Ticket codes function like bearer tokens. An unauthenticated or weakly protected verification endpoint can be brute-forced or used to enumerate valid tickets.

How to fix

Require scanner authentication, apply route-level throttling, log every verification attempt, and use non-enumerating responses where appropriate.

Example

```php
RateLimiter::attempt(
    'ticket-scan:' . $request->user()->id,
    30,
    fn () => $this->verifyTicket($request)
);
```

----------------------------------------------------

### Issue #6

Severity:
High

Category:
Audit Logging

Location:
app/Http/Controllers/Api/V1/TicketController.php:160

Problem

Ticket consumption is a business-critical action but no audit log is written:

```php
$ticket->status = 'used';
$ticket->used_at = now();
$ticket->save();
```

Why this matters

Production ticket scanning must be auditable. The system should record who scanned the ticket, when, from which device/location/IP, and what prior state was changed. Without this, fraud investigations and customer disputes are difficult.

How to fix

Persist a ticket verification audit record inside the same transaction as the status update.

Example

```php
TicketScanLog::create([
    'ticket_id' => $ticket->id,
    'scanner_user_id' => $request->user()->id,
    'scanned_at' => now(),
    'ip_address' => $request->ip(),
]);
```

----------------------------------------------------

### Issue #7

Severity:
Medium

Category:
Correctness / Null Safety

Location:
app/Http/Controllers/Api/V1/TicketController.php:143

Problem

The code assumes `used_at` is non-null when status is `used`:

```php
return $this->error('Vé đã được sử dụng trước đó vào lúc ' . $ticket->used_at->format('d/m/Y H:i'), 400);
```

Why this matters

If data is inconsistent, this will throw an exception while handling an already-used ticket. A defensive production implementation should not assume lifecycle fields are always populated unless enforced by database constraints.

How to fix

Handle null safely and enforce status/timestamp invariants at the database/model level.

Example

```php
$usedAt = $ticket->used_at?->format('d/m/Y H:i') ?? 'không rõ thời gian';
```

----------------------------------------------------

### Issue #8

Severity:
Medium

Category:
Business Logic

Location:
app/Http/Controllers/Api/V1/TicketController.php:156

Problem

The ticket is rejected as expired when the showtime scheduled time is earlier than `now()`:

```php
if ($ticket->showtime && $ticket->showtime->scheduled_at < now()) {
    return $this->error('Suất chiếu đã qua. Vé không còn hiệu lực.', 400);
}
```

Why this matters

Cinema ticket scanning usually remains valid for a configurable admission window after the scheduled start time. This implementation may reject late arrivals immediately after the show starts. Conversely, it does not check an earliest valid scan time.

How to fix

Use explicit business rules such as `scan_opens_at` and `scan_closes_at`, or compute a configured grace window.

Example

```php
$scanClosesAt = $ticket->showtime->scheduled_at->copy()->addMinutes(config('cinema.scan_grace_minutes', 30));

if (now()->greaterThan($scanClosesAt)) {
    return $this->error('Ticket scan window has closed.', 400);
}
```

----------------------------------------------------

### Issue #9

Severity:
Medium

Category:
API Design / Serialization

Location:
app/Http/Controllers/Api/V1/TicketController.php:59

Problem

The endpoint returns raw Eloquent models from paginator items:

```php
'data' => $tickets->items(),
```

and the detail endpoint returns the raw ticket model:

```php
return $this->ok($ticket, 'Tải thông tin vé thành công.');
```

Why this matters

Raw model serialization creates unstable API contracts and can expose fields unintentionally when model attributes or relationships change. Ticket data is sensitive and should be explicitly shaped.

How to fix

Use API Resources for ticket list/detail responses.

Example

```php
return TicketResource::collection($tickets);
```

----------------------------------------------------

### Issue #10

Severity:
Medium

Category:
Maintainability / Laravel Best Practices

Location:
app/Http/Controllers/Api/V1/TicketController.php:42

Problem

Status validation is implemented manually in the controller:

```php
$validStatuses = ['valid', 'used', 'cancelled', 'refunded'];

if (!in_array($status, $validStatuses)) {
```

Why this matters

This duplicates domain constants and makes status changes error-prone. It also uses loose `in_array` comparison.

How to fix

Use a FormRequest and centralize statuses in an enum or model constants. Use strict validation.

Example

```php
Rule::in(TicketStatus::values())
```

----------------------------------------------------

### Issue #11

Severity:
Medium

Category:
Architecture / SRP

Location:
app/Http/Controllers/Api/V1/TicketController.php:116

Problem

The controller owns ticket verification business logic: validation, lookup, status checks, date validity, mutation, and response construction.

Why this matters

Ticket scanning is a critical domain workflow. Keeping it in the controller makes it harder to test, audit, lock transactionally, and reuse from other scanner clients.

How to fix

Move verification to a dedicated service such as `TicketVerificationService` with transactional semantics and a typed result object.

Example

```php
$result = $ticketVerificationService->verify(
    ticketCode: $request->validated('ticket_code'),
    scanner: $request->user(),
    ipAddress: $request->ip()
);
```

----------------------------------------------------

### Issue #12

Severity:
Low

Category:
API Consistency / Localization

Location:
app/Http/Controllers/Api/V1/TicketController.php:23

Problem

The controller hard-codes Vietnamese API messages throughout the backend:

```php
return $this->unauthorized('Người dùng không được xác thực.');
```

Why this matters

Hard-coded localized strings make API consistency, translation, and client behavior harder to manage. API clients usually benefit from stable error codes plus localized frontend messages.

How to fix

Return stable error codes and put translations in language files or the frontend.

Example

```php
return $this->unauthorized(__('errors.unauthenticated'));
```

----------------------------------------------------

Summary

This file contains a blocking production issue: the scanner verification endpoint can mark tickets as used without visible authentication/authorization and without transactional row locking. That is a direct fraud, revenue, and customer-experience risk. The endpoint must be redesigned around authenticated scanner authorization, strict validation, transaction + `lockForUpdate()`, audit logging, and API Resources before production use.