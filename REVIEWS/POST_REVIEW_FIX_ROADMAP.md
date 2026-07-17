# Cinema Booking System - Post Review Fix Roadmap

**Created:** 2026-07-14  
**Source:** Findings documented under `REVIEWS/` and `REVIEWS/files/`  
**Current status:** Not production ready  
**Goal:** Convert completed review findings into an executable remediation roadmap by file group, feature area, and production risk.

---

## 1. Executive Decision

The project must not be released to production until the following areas are completed and verified:

1. Booking/payment/seat concurrency.
2. Authorization and validation hardening.
3. Admin destructive-operation safety.
4. API exception disclosure removal.
5. Database constraints for business invariants.
6. Test suite stabilization.

Some remediation has already been completed in prior phases:

- Payment/webhook idempotency baseline.
- Initial order/payment authorization policies.
- Seat locking transaction hardening.
- Order cancellation transaction hardening.
- Expired order/seat-hold cleanup hardening.
- Partial controller-thinness refactoring for booking/showtime flows.

Remaining work should continue from the current tracker state in `REVIEWS/REMEDIATION_EXECUTION_TRACKER.md`.

---

## 2. Global Fix Order

Do not fix files alphabetically. Fix by production risk.

Recommended strict order:

1. **Baseline test failures**
2. **Showtime scheduling correctness**
3. **Seat hold schema normalization**
4. **Order/payment lifecycle hardening**
5. **Authorization and FormRequest cleanup**
6. **Admin destructive-operation protection**
7. **API response and exception handling standardization**
8. **Database/model integrity**
9. **Controller/service architecture cleanup**
10. **Analytics/performance correctness**
11. **Final security and release verification**

---

## 3. Phase A - Stabilize Current Test Baseline

### Priority

Critical

### Why first

Existing failures block reliable verification. Do not continue broad fixes while the test suite has known unrelated failures that obscure regressions.

### Files / areas

- `tests/Feature/Admin/SeatLayoutTemplateAdminTest.php`
- `tests/Feature/Phase3RegressionTest.php`
- `tests/Feature/Payment/PaymentSecurityTest.php`
- routes and middleware affecting:
  - admin seat layout template routes
  - promotion validation route
  - payment security fixtures

### Tasks

- Fix admin test redirect issue by confirming whether the route requires authentication/session setup or the controller route is incorrectly protected.
- Fix promotion validation auth mismatch:
  - either route should be public and tests are correct;
  - or tests should be updated only if product/security requirement confirms auth is required.
- Fix payment security fixture that creates invalid foreign key data before business validation can run.
- Run:
  - `php artisan test --filter=SeatLayoutTemplateAdminTest`
  - `php artisan test --filter=Phase3RegressionTest`
  - `php artisan test --filter=PaymentSecurityTest`
  - `vendor\bin\phpunit --testsuite=Feature`

### Exit criteria

- Known baseline failures are either fixed or explicitly documented as accepted.
- Feature suite can be used as a regression gate.

---

## 4. Phase B - Showtime Scheduling Safety

### Priority

Critical

### Why

Showtime creation can cause overlapping schedules, duplicate screenings, inconsistent booking availability, and operational revenue loss.

### Files

- `app/Services/ShowtimeService.php`
- `app/Http/Controllers/ShowtimeController.php`
- `app/Models/Showtime.php`
- showtime migrations
- related request files to be created if missing

### Tasks

#### B1. Add database-level duplicate protection

- Add unique index for exact duplicate showtimes where valid:
  - candidate: `(screen_id, scheduled_at)`
- Before migration, add duplicate-data detection query.
- Add rollback strategy.

#### B2. Add transaction safety to bulk creation

- Wrap bulk creation in `DB::transaction()`.
- Avoid check-then-insert race as the only protection.
- Catch duplicate key violations safely.
- Return duplicate/skipped entries without exposing SQL errors.

#### B3. Add overlap validation

- Validate same-screen showtime intervals do not overlap.
- Compute end time from movie duration.
- Add cleaning/buffer time if business accepts a configurable buffer.
- Apply overlap validation to:
  - single create
  - update
  - bulk date range
  - bulk single day

#### B4. Move controller validation to FormRequest

Create:

- `StoreShowtimeRequest`
- `UpdateShowtimeRequest`
- `BulkCreateShowtimeRequest`
- `BulkSingleDayShowtimeRequest`

#### B5. Add authorization

- Create `ShowtimePolicy`.
- Require proper admin/staff permission for create/update/delete/bulk actions.
- Register policy.

### Tests

- Cannot create exact duplicate showtime.
- Cannot create overlapping showtime.
- Bulk creation skips or rejects duplicates deterministically.
- Concurrent duplicate showtime creation creates at most one row.
- Unauthorized user cannot create/update/delete showtime.

---

## 5. Phase C - Seat Hold Schema Normalization

### Priority

Blocking

### Why

Existing JSON-based seat holds are difficult to lock safely per seat and remain a structural concurrency weakness.

### Files

- `app/Models/SeatHold.php`
- `app/Services/SeatService.php`
- `app/Http/Controllers/SeatController.php`
- `app/Http/Requests/LockSeatRequest.php`
- database migrations for seat holds
- new model/migration for hold items

### Tasks

- Create `seat_hold_items` table:
  - `id`
  - `seat_hold_id`
  - `showtime_id`
  - `seat_id`
  - `status`
  - `expires_at`
  - timestamps
- Add indexes:
  - `(showtime_id, seat_id)`
  - `(expires_at)`
  - `(seat_hold_id)`
- Add uniqueness rule for active hold per showtime/seat.
- Refactor `SeatService::lock()` to write one row per held seat.
- Refactor conflict checks to query lockable rows.
- Add deterministic seat ID sorting before locking.
- Keep backward migration plan explicit:
  - if non-production, allow reset;
  - if production data exists, write data migration.

### Tests

- Two users cannot hold same seat concurrently.
- Expired hold releases correctly.
- Duplicate seat IDs in request are rejected.
- Seat from wrong screen/showtime is rejected.

---

## 6. Phase D - Order and Payment Lifecycle Completion

### Priority

Blocking

### Files

- `app/Services/OrderService.php`
- `app/Services/PaymentService.php`
- `app/Services/OrderFulfillmentService.php`
- `app/Services/OrderExpirationService.php`
- `app/Jobs/ProcessPayOSWebhook.php`
- `app/Http/Controllers/OrderController.php`
- `app/Http/Controllers/PaymentController.php`
- `app/Http/Controllers/User/PaymentController.php`
- `app/Models/Order.php`
- `app/Models/Payment.php`
- `app/Models/Ticket.php`
- `app/Models/IdempotencyKey.php`

### Tasks

- Define explicit order state machine.
- Define explicit payment state machine.
- Remove direct status mutation where possible.
- Ensure payment creation:
  - checks order ownership;
  - checks order payable state;
  - enforces idempotency before gateway call;
  - rejects changed payload for same idempotency key.
- Ensure webhook processing:
  - verifies signature before state mutation;
  - locks order/payment rows;
  - handles duplicate webhook as no-op;
  - never creates duplicate tickets.
- Ensure order creation:
  - recalculates totals server-side;
  - validates promotion inside transaction;
  - deducts stock inside transaction;
  - consumes seat holds atomically.
- Add audit/payment event logs.

### Tests

- Duplicate payment request returns same response.
- Same idempotency key with different payload is rejected.
- Duplicate webhook does not duplicate tickets/payment.
- Order expiration racing payment success does not cancel paid order.
- Client cannot control final total/status.

---

## 7. Phase E - Authorization and Validation Hardening

### Priority

Critical

### Files

- all files under `app/Http/Requests/`
- `app/Policies/`
- `app/Providers/AppServiceProvider.php`
- controllers under:
  - `app/Http/Controllers/`
  - `app/Http/Controllers/Admin/`
  - `app/Http/Controllers/User/`
  - `app/Http/Controllers/Api/V1/`
- middleware:
  - `RoleMiddleware`
  - `PermissionMiddleware`
  - `AdminMiddleware`
  - `JwtMiddleware`
  - `AuthenticateFromCookie`
  - `CookieToBearerToken`

### Tasks

- Replace all unconditional `authorize(): true` in sensitive requests.
- Add missing policies:
  - Movie
  - Showtime
  - Screen
  - Theater
  - Branch
  - Product
  - Combo
  - Promotion
  - Banner
  - Post
  - SeatLayoutTemplate
- Register all policies.
- Add explicit controller authorization for mutation endpoints.
- Harden validation:
  - max string lengths
  - enum validation
  - duplicate array ID rejection
  - max array sizes
  - date range limits
  - money precision
  - relationship validation
- Add login and password-reset throttling.
- Verify cookie/JWT security defaults.

### Tests

- Non-admin cannot access admin endpoints.
- Admin without permission is denied.
- User cannot access another user's profile/order/payment.
- Unknown privilege fields are rejected.
- Login throttling works.
- Password reset does not reveal account existence.

---

## 8. Phase F - Admin Safety, Audit Logs, Uploads

### Priority

High

### Files

- `app/Http/Controllers/Admin/BannerController.php`
- `app/Http/Controllers/Admin/BranchController.php`
- `app/Http/Controllers/Admin/ComboController.php`
- `app/Http/Controllers/Admin/PostController.php`
- `app/Http/Controllers/Admin/ProductController.php`
- `app/Http/Controllers/Admin/PromotionController.php`
- `app/Http/Controllers/Admin/ScreenController.php`
- `app/Http/Controllers/Admin/TheaterController.php`
- `app/Http/Controllers/Admin/SeatLayoutTemplateController.php`
- `app/Models/AuditLog.php`
- upload-related services/controllers

### Tasks

- Prevent destructive operations when dependent records exist:
  - screen with future showtimes
  - screen with active holds/sold tickets
  - theater/branch with active schedules
  - product/combo used in paid orders
  - promotion already used
- Replace destructive delete with deactivate/archive where appropriate.
- Add `AuditLogService`.
- Record actor, action, target, before/after, IP, user agent, request ID.
- Redact sensitive fields.
- Harden uploads:
  - MIME validation
  - extension validation
  - size limits
  - random filenames
  - storage disk abstraction
  - delete old files after DB commit
  - rollback DB if upload fails

### Tests

- Cannot regenerate seats when bookings exist.
- Cannot delete product used in paid order.
- Admin action creates audit log.
- Invalid upload is rejected.
- Failed upload does not leave broken DB row.

---

## 9. Phase G - API Consistency and Exception Handling

### Priority

High

### Files

- `app/Traits/ApiResponse.php`
- `app/Exceptions/`
- `bootstrap/app.php` or Laravel exception configuration
- all API controllers
- gateway/job classes that return/log errors

### Tasks

- Define one API response envelope.
- Define one validation error format.
- Define pagination metadata format.
- Add domain exceptions:
  - `SeatUnavailableException`
  - `PaymentAlreadyProcessedException`
  - `OrderNotPayableException`
  - `InvalidStateTransitionException`
  - `PromotionNotApplicableException`
- Centralize exception mapping.
- Remove raw `$e->getMessage()` from client responses.
- Add request/correlation ID.
- Standardize log redaction.

### Tests

- Raw exception message is not exposed.
- Validation response shape is stable.
- Domain exceptions map to correct HTTP status.
- Logs include correlation ID.
- Sensitive values are redacted.

---

## 10. Phase H - Database and Model Integrity

### Priority

High

### Files

- all model review targets under `REVIEWS/files/*_model_review.md`
- migrations for:
  - orders
  - payments
  - tickets
  - showtimes
  - seat holds
  - promotions
  - products
  - combos
  - users/roles/permissions

### Tasks

- Add unique constraints:
  - payment reference
  - order code/reference
  - ticket code/QR code
  - promotion code
  - showtime exact duplicate guard
  - active hold per showtime/seat
  - booked ticket per showtime/seat
- Add foreign keys where missing.
- Add check constraints where supported:
  - price >= 0
  - stock >= 0
  - quantity > 0
  - capacity > 0
- Harden `$fillable`:
  - remove role/security fields
  - remove lifecycle statuses
  - remove counters
  - remove financial totals where they must be service-controlled
- Add casts for:
  - booleans
  - datetimes
  - arrays
  - decimals/money
- Preserve immutable purchase snapshots:
  - product/combo name and price
  - seat label/type
  - ticket price
  - promotion details

### Tests

- DB rejects duplicate ticket for same showtime/seat.
- DB rejects duplicate payment reference.
- Model cannot mass assign restricted fields.
- Paid order snapshots do not mutate after product/promotion changes.

---

## 11. Phase I - Controller and Service Architecture Cleanup

### Priority

Medium

### Files

- all controller review files
- all service review files
- especially:
  - `ShowtimeController`
  - `ProductController`
  - `PromotionController`
  - `UserController`
  - admin CRUD controllers
  - analytics controllers
  - `ShowtimeService`
  - `ProductService`
  - `PromotionService`
  - analytics services

### Tasks

- Keep controllers limited to:
  - authorize
  - validate
  - call service/action
  - return response/resource
- Remove raw `Request` dependency from services.
- Introduce DTOs/actions where flow is complex:
  - `CreateOrderData`
  - `PaymentData`
  - `SeatLockData`
  - `ShowtimeScheduleData`
- Add API Resources:
  - User
  - Movie
  - Showtime
  - Theater
  - Screen
  - Seat
  - Order
  - Payment
  - Product
  - Combo
  - Promotion
  - Banner
  - Post
- Extract shared services:
  - file upload service
  - audit log service
  - pagination/filter helper
  - status transition service

### Tests

- Controller feature tests verify response behavior.
- Service unit tests verify business behavior.
- Resource tests verify no sensitive data leaks.

---

## 12. Phase J - Analytics, Reporting, Performance

### Priority

Medium

### Files

- `DashboardService`
- `RevenueService`
- `TicketAnalyticsService`
- `FoodAnalyticsService`
- `ComboAnalyticsService`
- `ProductService`
- reporting/admin stat controllers
- stat request files

### Tasks

- Define revenue source of truth.
- Define reporting date semantics.
- Include/exclude refunds and cancellations consistently.
- Remove overcounting caused by joins.
- Use database aggregation instead of large in-memory collections.
- Add max reporting windows.
- Add pagination/query limits.
- Whitelist sort/filter fields.
- Add indexes based on query patterns.
- Add safe caching with invalidation.

### Tests

- Analytics match seeded known dataset.
- Cancelled/refunded records are handled correctly.
- Large date ranges are rejected or bounded.
- Sorting whitelist blocks unsafe values.

---

## 13. Phase K - Final Release Gate

### Priority

Critical

### Required verification

Run:

```cmd
php artisan test
vendor\bin\phpunit --testsuite=Feature
composer audit
php artisan route:list
```

Manual/security verification:

- No raw exception disclosure.
- No IDOR on user/order/payment/profile endpoints.
- No admin destructive operation without lifecycle guard.
- No duplicate booking under concurrent traffic.
- No duplicate payment under retry/webhook replay.
- Uploads cannot store executable or unsafe content.
- Logs redact secrets.
- Production `.env.example` is safe.

### Release exit criteria

- All blocking issues fixed.
- All high issues fixed or formally accepted.
- All migrations tested on production-like data.
- Rollback plan tested.
- Payment sandbox verified.
- Webhook replay verified.
- Monitoring and alerting configured.
- Final re-review completed.

---

## 14. Immediate Next Sprint Recommendation

The next implementation sprint should focus on:

1. Fix baseline test failures.
2. Harden `ShowtimeService` bulk creation:
   - transaction
   - unique constraint
   - overlap validation
   - FormRequests
   - authorization
3. Normalize seat holds into lockable rows.
4. Add missing concurrency tests.

Do not start broad UI/admin refactoring until these are complete.
