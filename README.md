# Cinema Booking Platform

Cinema is a Laravel 12 booking platform with a customer storefront, Turbo-powered administration, PayOS payment processing, real-time seat updates, and production-oriented security and reliability controls.

## Technology

- PHP 8.2+ and Laravel 12
- MySQL 8 for transactional data
- Redis for production cache, sessions, queues, and distributed locks
- Vite 7, Bootstrap 5, Tailwind CSS 4, and Hotwire Turbo 8
- Laravel Reverb for optional real-time seat and order events
- Sentry for errors, tracing, logs, and performance telemetry
- PHPUnit, Playwright, Larastan, ESLint, and Laravel Pint

## Main Capabilities

- Movie, theater, screen, showtime, seat-layout, product, combo, promotion, order, payment, ticket, post, and banner management
- Seat holding and checkout serialization with expiration cleanup
- Idempotent order/payment operations and replay-safe PayOS webhooks
- Ticket QR verification with atomic check-in
- Role and policy-based administration
- Account-scoped admin caching, stale request cancellation, and paginated listings
- Runtime-generated OpenAPI 3.1 contract
- Liveness, readiness, protected metrics, request correlation, slow-query logging, and queue monitoring

## Local Setup

```powershell
composer install
npm ci
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Local development may keep `CACHE_STORE`, `SESSION_DRIVER`, and `QUEUE_CONNECTION` on database/array drivers. Production should use the Redis defaults documented in `.env.example`.

## Development

```powershell
composer dev
```

This starts the Laravel server, queue listener, application logs, and Vite development server.

## Quality Gates

```powershell
composer test:structure
composer analyse
composer test:modern-format
npm run lint
npm run test:frontend:syntax
npm run test:frontend:security
npm run build
php artisan test --compact
npm run test:browser:smoke
```

GitHub Actions runs static analysis, formatting, frontend checks, the production build, PHP tests, browser smoke, and dependency audits.

## Operations

- Liveness: `GET /api/v1/health/live`
- Readiness: `GET /api/v1/health/ready`
- OpenAPI: `GET /api/v1/docs/openapi.json`
- Metrics: `GET /api/v1/internal/metrics` with `Authorization: Bearer <METRICS_TOKEN>`
- Queue monitor: `php artisan queue:monitor-health --json` (depth, oldest pending age, failed jobs)
- Operations monitor: `php artisan operations:monitor-health --json` (queue, overdue payments, unsent invoice emails)
- Scheduler: `php artisan schedule:work`
- Business queue worker: `php artisan queue:work --queue=emails,payments,default,cleanup --sleep=1 --tries=3 --timeout=120`
- Realtime queue worker: `php artisan queue:work --queue=broadcasts --sleep=1 --tries=3 --timeout=30`

Readiness fails when a monitored queue exceeds `QUEUE_MONITOR_MAX_DEPTH` or its oldest ready job exceeds `QUEUE_MONITOR_MAX_AGE_SECONDS`. Keep the `broadcasts` worker separate so realtime traffic cannot be starved by business jobs.

Production Supervisor, deployment verification, staging browser checks, booking concurrency probe, and backup/restore procedures are documented in `deployment/OPERATIONS_RUNBOOK.md`.

Laravel Horizon requires the Unix-only `pcntl` PHP extension and therefore is not installed in the Windows/XAMPP development environment. The application uses Redis queues and a cross-platform queue monitor locally; Horizon can be added on a Linux runtime without changing queue contracts.

## Documentation

- Architecture: `ARCHITECTURE.md`
- API contract: `docs/api/README.md`
- Codebase review: `PROJECT_CODEBASE_REVIEW_REPORT.md`
- Remediation plan: `PROJECT_CODEBASE_REVIEW_PLAN.md`
