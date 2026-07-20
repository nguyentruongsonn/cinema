# Cinema System Architecture

## Overview

Cinema is a Laravel modular monolith. It keeps booking and payment transactions in one consistency boundary while separating HTTP validation, authorization, serialization, business services, persistence, asynchronous work, and browser lifecycle code.

```text
Browser / Admin Turbo
        |
        v
Blade shell + REST API /api/v1
        |
        v
Middleware -> Form Requests -> Policies -> Controllers
                                      |
                                      v
                                  Services
                         /            |            \
                    Eloquent       Redis Queue    Events/Reverb
                       |               |              |
                     MySQL          Workers       Browser updates
```

## Backend Boundaries

### HTTP

- `routes/api/public.php`: public catalog, pricing, health, metrics, and API contract
- `routes/api/auth.php`: authentication and profile endpoints
- `routes/api/customer.php`: seat holds, orders, payments, tickets, and promotions
- `routes/api/admin.php`: role-protected administration
- Form Requests normalize and validate input before controllers execute.
- Policies and role middleware enforce resource and administrative authorization.
- API Resources define response boundaries and prevent accidental secret disclosure.

### Domain services

- `OrderService` coordinates order creation, reservation, cancellation, and idempotency.
- `PaymentService` owns gateway interaction and persisted payment state.
- `OrderFulfillmentService` processes verified payment exactly once.
- `OrderExpirationService` restores held inventory and promotion usage.
- `SeatService` handles locking, unlocking, expiration, and seat broadcasts.
- Analytics services define reporting date and revenue semantics.

### Concurrency and reliability

- Database transactions and row locks protect stock, promotions, loyalty points, seat holds, and check-in state.
- Idempotency keys prevent duplicate checkout side effects.
- Orders retain the exact seat hold and immutable layout snapshot used at purchase time.
- PayOS webhook work is unique, retryable, and replay-safe.
- Expiration and cleanup jobs use overlap protection.

## Data and Infrastructure

- MySQL is the source of truth for customers, catalog, bookings, payments, and tickets.
- Redis production databases are isolated by purpose:
  - DB 0: general application data
  - DB 1: cache
  - DB 2: sessions
  - DB 3: queues
- PHPUnit uses SQLite and array/sync drivers for deterministic isolated tests.
- Production workers consume `payments`, `default`, and `cleanup` queues.

Horizon is intentionally not a Windows development dependency because it requires `pcntl`. Queue contracts are Horizon-compatible on Linux; Windows uses `queue:work` and `queue:monitor-health`.

## Frontend Architecture

### Vite shell

- `resources/js/user-shell.js` bundles security utilities, API client, and authentication.
- `resources/js/admin-shell.js` bundles shared admin runtime and responsive controls.
- `resources/js/admin-navigation.js` owns Turbo Drive lifecycle and modal cleanup.
- `resources/js/admin-ticket-scanner-bootstrap.js` isolates scanner startup.
- `resources/css/user.css` and `resources/css/admin.css` produce hashed shell styles.

### Page lifecycle

Existing page scripts remain page-scoped because Turbo must initialize and clean them on every visit. Admin scripts use shared load/cleanup hooks, abort stale requests, and avoid duplicated listeners or polling. Moving these scripts into cached ES modules requires a page-by-page lifecycle conversion rather than a mechanical file move.

## API Contract

`OpenApiService` generates OpenAPI 3.1 from Laravel's registered routes. The contract includes path parameters, request bodies, authentication requirements, safe API error schemas, and every registered HTTP method.

`OpenApiContractTest` fails when a registered API URI or method is absent from the generated document.

## Observability

- `RequestIdMiddleware` validates or generates `X-Request-ID`, adds it to log context and Sentry tags, and returns it to clients.
- Sentry captures unhandled exceptions, queue jobs, SQL spans, cache/Redis work, outgoing HTTP calls, logs, and traces without default PII.
- Readiness checks validate database and cache read/write behavior.
- Protected Prometheus-format metrics expose request totals, response classes, and duration buckets.
- Slow-query logging excludes bindings to avoid leaking PII.
- Queue monitoring checks queue depth and failed-job thresholds every minute and emits critical alerts.
- Slack can be included in `LOG_STACK` as an external critical alert sink.

## Security

- HttpOnly JWT access/refresh cookies with refresh rotation
- CSRF protection for browser state changes
- Content Security Policy and hardened browser headers
- Rate limits for authentication, seats, orders, payments, tickets, and webhooks
- Signed/verified PayOS callback and webhook handling
- Policy-based IDOR protection
- Upload validation and non-public storage boundaries
- Error responses hide internal exceptions and include only a correlation ID

## Quality Strategy

- PHPUnit unit and feature tests cover validation, authorization, resources, payment replay, fulfillment, expiration, analytics, and database constraints.
- Playwright covers public smoke, booking idempotency, scanner behavior, XSS regressions, modal lifecycle, and all admin Turbo routes.
- Route reflection prevents routes from targeting missing controller methods.
- OpenAPI contract tests prevent route/documentation drift.
- ESLint, JavaScript syntax/security gates, Larastan level 5, and scoped Pint checks prevent new quality debt.
- Dependency audits and production builds run in CI.
