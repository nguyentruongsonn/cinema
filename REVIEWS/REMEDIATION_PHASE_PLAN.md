# Cinema Booking System - Remediation Phase Plan

**Created:** 2026-07-14  
**Based on:** `REVIEWS/FINAL_COMPREHENSIVE_CODE_REVIEW_REPORT.md`, `REVIEWS/REVIEW_PROGRESS.md`, and individual review files under `REVIEWS/files/`  
**Current Production Readiness:** 🔴 BLOCKING - NOT PRODUCTION READY  
**Target:** Stabilize payment, booking, authorization, validation, auditability, and maintainability before production release.

---

## 1. Executive Summary

The review identified production-blocking risks across payment, booking, seat locking, admin operations, authorization, validation, and data integrity.

This plan divides remediation into controlled phases so the team can fix the most dangerous issues first, reduce regression risk, and validate each phase before moving to the next.

### Main Principles

1. **Fix money and booking correctness first.**
2. **Do not refactor broadly before stabilizing concurrency and data integrity.**
3. **Every critical fix must include tests.**
4. **No production release before Phase 1 and Phase 2 are fully completed.**
5. **Each phase must end with verification, regression tests, and review.**

---

## 2. Phase Overview

| Phase | Name | Priority | Estimated Duration | Production Gate |
| ----- | ---- | -------- | ------------------ | --------------- |
| Phase 0 | Preparation & Baseline | Critical | 1-2 days | Required before coding |
| Phase 1 | Payment, Booking, Seat-Hold Correctness | Blocking | 1-2 weeks | Required before production |
| Phase 2 | Authorization, Authentication, Validation | Critical | 1-2 weeks | Required before production |
| Phase 3 | Admin Safety, Audit Logs, File Uploads | High | 1-2 weeks | Required before admin launch |
| Phase 4 | API Consistency, Exception Handling, Logging | High | 1 week | Required before public API release |
| Phase 5 | Data Model Integrity & Database Constraints | High | 1-2 weeks | Required before high traffic |
| Phase 6 | Service/Controller Refactor & Architecture Cleanup | Medium | 2-4 weeks | Required for maintainability |
| Phase 7 | Performance, Analytics Accuracy, Caching | Medium | 1-2 weeks | Required before scale |
| Phase 8 | Test Hardening, Security Verification, Release Readiness | Critical | 1-2 weeks | Final release gate |

---

# Phase 0 - Preparation & Baseline

## Goal

Create a safe remediation baseline before changing production-critical logic.

## Scope

- Freeze current behavior with regression tests where possible.
- Create issue tracker tickets from review findings.
- Define release branches and rollback strategy.
- Confirm database state and migration strategy.
- Confirm payment gateway sandbox and webhook replay tooling.

## Tasks

### 0.1 Create Remediation Branches

- [ ] Create main remediation branch: `fix/production-readiness`
- [ ] Create sub-branches per phase:
  - [ ] `fix/payment-booking-concurrency`
  - [ ] `fix/auth-authorization-validation`
  - [ ] `fix/admin-safety-audit`
  - [ ] `fix/api-exceptions-logging`
  - [ ] `fix/db-integrity`
  - [ ] `refactor/controllers-services`
  - [ ] `perf/analytics-caching`
  - [ ] `test/release-hardening`

### 0.2 Convert Findings into Tickets

- [ ] Create tickets for each blocking finding.
- [ ] Create tickets for each high-severity finding.
- [ ] Link each ticket to the relevant review file.
- [ ] Add acceptance criteria and test requirements.

### 0.3 Establish Baseline Tests

- [ ] Run existing PHPUnit test suite.
- [ ] Document current failing tests.
- [ ] Add smoke tests for:
  - [ ] user login
  - [ ] movie list
  - [ ] showtime list
  - [ ] seat lock
  - [ ] order creation
  - [ ] payment creation
  - [ ] webhook handling
  - [ ] admin CRUD basics

### 0.4 Define Release Safety Rules

- [ ] No direct push to main branch.
- [ ] Every fix requires PR review.
- [ ] Every blocking/high fix requires automated test coverage.
- [ ] Database migrations must include rollback strategy.
- [ ] Payment/booking changes require concurrency tests.

## Exit Criteria

- [ ] All remediation tickets created.
- [ ] Baseline test result documented.
- [ ] Branching and rollback strategy agreed.
- [ ] Team understands production blockers.

---

# Phase 1 - Payment, Booking, Seat-Hold Correctness

## Priority

🔴 Blocking

## Goal

Eliminate duplicate payments, duplicate bookings, race conditions, and non-atomic booking/payment workflows.

## Primary Review References

- `REVIEWS/PaymentService_Review.md`
- `REVIEWS/PaymentController_Review.md`
- `REVIEWS/BookingController_Review.md`
- `REVIEWS/OrderController_Review.md`
- `REVIEWS/OrderService_Review.md`
- `REVIEWS/SeatService_Review.md`
- `REVIEWS/OrderFulfillmentService_Review.md`
- `REVIEWS/files/SeatHold_model_review.md`
- `REVIEWS/files/IdempotencyKey_model_review.md`
- `REVIEWS/files/CreatePaymentRequest_review.md`
- `REVIEWS/files/StorePaymentRequest_review.md`
- `REVIEWS/files/StoreOrderRequest_review.md`
- `REVIEWS/files/LockSeatRequest_review.md`
- `REVIEWS/files/ProcessPayOSWebhook_job_review.md`

## Problems to Fix

1. Payment idempotency is incomplete.
2. Seat holds store seat IDs as JSON, preventing safe per-seat locking.
3. Booking flow is vulnerable to race conditions.
4. Payment/order state transitions are not consistently atomic.
5. Webhook processing lacks strong idempotency.
6. Requests allow weak/client-controlled payment/order data.
7. Expired order/seat cleanup can race with payment completion.

## Tasks

### 1.1 Redesign Seat Holds

- [ ] Replace JSON `seat_ids` with normalized lockable rows.
- [ ] Create table such as `seat_hold_items`:
  - `id`
  - `seat_hold_id`
  - `showtime_id`
  - `seat_id`
  - `status`
  - `expires_at`
  - timestamps
- [ ] Add unique constraint for active seat hold per showtime/seat.
- [ ] Add indexes:
  - `(showtime_id, seat_id)`
  - `(expires_at)`
  - `(seat_hold_id)`
- [ ] Migrate existing data safely or document non-production reset strategy.
- [ ] Update `SeatHold` relationships.
- [ ] Update seat lock/release services to use row-level locks.

### 1.2 Add Atomic Seat Locking

- [ ] Wrap seat lock operation in `DB::transaction`.
- [ ] Lock relevant showtime and seat rows with `lockForUpdate`.
- [ ] Validate seat belongs to the requested showtime/screen.
- [ ] Reject duplicate seat IDs in request.
- [ ] Reject expired or already-held seats.
- [ ] Ensure lock expiration is calculated server-side.
- [ ] Add deterministic lock ordering to reduce deadlock risk.

### 1.3 Add Payment Idempotency

- [ ] Define idempotency key contract.
- [ ] Require idempotency key for payment creation.
- [ ] Add database unique constraint:
  - `idempotency_keys.key`
  - or `(user_id, key, operation)`
- [ ] Persist request hash and response snapshot.
- [ ] Return the original response on safe retry.
- [ ] Reject same key with different payload.
- [ ] Ensure idempotency is enforced before gateway call.
- [ ] Ensure gateway reference/order code is unique.

### 1.4 Fix Order Creation Atomicity

- [ ] Wrap order creation, order items, ticket reservation, seat hold consumption, and stock deduction in one transaction.
- [ ] Lock order-relevant rows.
- [ ] Recalculate price server-side.
- [ ] Reject client-controlled totals.
- [ ] Validate promotion eligibility inside the transaction.
- [ ] Ensure order status transitions are explicit and validated.

### 1.5 Fix Payment State Machine

- [ ] Define allowed payment statuses.
- [ ] Define allowed order statuses.
- [ ] Implement explicit transition methods:
  - `markPending`
  - `markPaid`
  - `markFailed`
  - `markCancelled`
  - `markExpired`
  - `refund`
- [ ] Prevent direct status mass assignment.
- [ ] Ensure duplicate webhook/payment callbacks are no-op if already processed.
- [ ] Add payment event/audit records.

### 1.6 Fix Webhook Processing

- [ ] Verify webhook signature before dispatching job.
- [ ] Store webhook event ID/hash.
- [ ] Add unique constraint to prevent duplicate webhook processing.
- [ ] Add per-order locking during webhook processing.
- [ ] Redact sensitive payload before logging.
- [ ] Add retry/backoff/failure handling.
- [ ] Add dead-letter/manual recovery process.

### 1.7 Fix Expiration Race Conditions

- [ ] Lock order before expiring.
- [ ] Check payment status inside transaction.
- [ ] Do not expire an order if payment is in processing or paid.
- [ ] Release seat holds atomically.
- [ ] Make cleanup job idempotent.
- [ ] Add overlap protection for cleanup job.

## Required Tests

- [ ] Two concurrent users cannot hold the same seat.
- [ ] Two concurrent orders cannot book the same seat.
- [ ] Repeated payment request with same idempotency key returns same result.
- [ ] Same idempotency key with different payload is rejected.
- [ ] Duplicate webhook does not duplicate payment/order fulfillment.
- [ ] Payment success racing with order expiration does not lose paid order.
- [ ] Expired holds are released safely.
- [ ] Deadlock retry behavior is tested.

## Exit Criteria

- [ ] No duplicate payment path remains.
- [ ] No duplicate seat booking path remains.
- [ ] All money/booking operations are transactional.
- [ ] All critical concurrency tests pass.
- [ ] Review confirms no direct status mass assignment remains in payment/order lifecycle.

---

# Phase 2 - Authorization, Authentication, Validation

## Priority

🔴 Critical

## Goal

Prevent unauthorized access, privilege escalation, IDOR, weak authentication, and invalid input reaching business logic.

## Primary Review References

- `REVIEWS/files/AuthController_review.md`
- `REVIEWS/files/AuthService_review.md`
- `REVIEWS/files/JwtMiddleware_review.md`
- `REVIEWS/files/AuthenticateFromCookie_review.md`
- `REVIEWS/files/CookieToBearerToken_review.md`
- `REVIEWS/files/RoleMiddleware_review.md`
- `REVIEWS/files/PermissionMiddleware_review.md`
- `REVIEWS/files/AdminMiddleware_review.md`
- `REVIEWS/files/UserController_review.md`
- `REVIEWS/files/UserService_review.md`
- all request review files under `REVIEWS/files/*Request*_review.md`

## Problems to Fix

1. Many `FormRequest::authorize()` methods allow everything.
2. Controllers lack visible authorization checks.
3. Admin operations rely too heavily on route/middleware assumptions.
4. User update paths allow sensitive field mutation.
5. Password, reset, and login flows need stronger controls.
6. Validation is inconsistent across create/update/payment/order APIs.

## Tasks

### 2.1 Implement Policies and Gates

- [ ] Create policies for:
  - [ ] User
  - [ ] Movie
  - [ ] Showtime
  - [ ] Screen
  - [ ] Theater
  - [ ] Branch
  - [ ] Product
  - [ ] Combo
  - [ ] Promotion
  - [ ] Banner
  - [ ] Post
  - [ ] Order
  - [ ] Payment
  - [ ] SeatLayoutTemplate
- [ ] Register policies.
- [ ] Replace controller assumptions with `$this->authorize(...)`.
- [ ] Add ownership checks for user resources.
- [ ] Add permission checks for admin resources.

### 2.2 Fix FormRequest Authorization

- [ ] Update all `authorize()` methods.
- [ ] Ensure user can only update own profile unless admin.
- [ ] Ensure order/payment requests verify ownership.
- [ ] Ensure admin requests require proper permissions.
- [ ] Ensure stats requests require reporting permission.

### 2.3 Harden Request Validation

- [ ] Reject unknown or unsafe fields.
- [ ] Add max lengths to all string fields.
- [ ] Add strict enums for status/type fields.
- [ ] Normalize email, phone, slug, and codes.
- [ ] Validate date ranges.
- [ ] Validate money as integer minor units or decimal with strict precision.
- [ ] Reject duplicate IDs in arrays.
- [ ] Limit array sizes.
- [ ] Validate relationships:
  - seat belongs to screen/showtime
  - product exists and is purchasable
  - promotion is active and eligible
  - order belongs to current user
  - payment belongs to current order/user

### 2.4 Harden Authentication

- [ ] Add login rate limiting.
- [ ] Add forgot/reset password throttling.
- [ ] Use strong password rules consistently.
- [ ] Prevent password reuse where required.
- [ ] Normalize login credentials.
- [ ] Avoid user enumeration.
- [ ] Review JWT expiration/refresh behavior.
- [ ] Ensure refresh token rotation/replay detection.
- [ ] Ensure cookie `httpOnly`, `secure`, and `sameSite` settings are correct.

### 2.5 Remove Sensitive Mass Assignment

- [ ] Audit `$fillable` in all security-sensitive models.
- [ ] Move status/role/security field changes into explicit service methods.
- [ ] Prevent users from assigning:
  - role
  - status
  - loyalty points
  - email verification state
  - payment status
  - order status
  - audit metadata
  - counters
  - financial totals

## Required Tests

- [ ] Non-admin cannot access admin endpoints.
- [ ] Admin without permission cannot perform restricted actions.
- [ ] User cannot access another user's order/payment/profile.
- [ ] User cannot mass-assign role/status/loyalty fields.
- [ ] Invalid order/payment payloads are rejected.
- [ ] Duplicate array IDs are rejected.
- [ ] Login throttling works.
- [ ] Password reset does not reveal account existence.

## Exit Criteria

- [ ] All sensitive endpoints have explicit authorization.
- [ ] All FormRequests have meaningful authorization.
- [ ] Mass assignment privilege escalation paths are closed.
- [ ] Authentication cookies/tokens follow secure defaults.
- [ ] Authorization and validation test suite passes.

---

# Phase 3 - Admin Safety, Audit Logs, File Uploads

## Priority

🟠 High

## Goal

Prevent admin actions from corrupting production data, ensure admin actions are auditable, and harden file upload handling.

## Primary Review References

- `REVIEWS/files/Admin_ScreenController_review.md`
- `REVIEWS/files/Admin_PromotionController_review.md`
- `REVIEWS/files/Admin_ProductController_review.md`
- `REVIEWS/files/Admin_ComboController_review.md`
- `REVIEWS/files/Admin_PostController_review.md`
- `REVIEWS/files/Admin_BannerController_review.md`
- `REVIEWS/files/Admin_TheaterController_review.md`
- `REVIEWS/files/Admin_SeatLayoutTemplateController_review.md`
- `REVIEWS/files/AuditLog_model_review.md`

## Problems to Fix

1. Admin screen/seat regeneration can corrupt bookings.
2. Admin mutation actions lack strong lifecycle checks.
3. File uploads are not consistently hardened.
4. Admin actions lack audit logs.
5. Promotion usage/counters can be reset or changed unsafely.

## Tasks

### 3.1 Block Destructive Admin Operations

- [ ] Prevent deleting/updating screens with future showtimes.
- [ ] Prevent regenerating seats for screens with sold tickets or active holds.
- [ ] Prevent deleting theaters/branches with active schedules.
- [ ] Prevent deleting products/combos used in paid orders.
- [ ] Prevent modifying promotion rules after usage unless versioned.
- [ ] Use soft-delete or inactive status where appropriate.

### 3.2 Add Safe Lifecycle Workflows

- [ ] Replace destructive delete with deactivate/archive.
- [ ] Add explicit business methods:
  - `deactivateTheater`
  - `archiveProduct`
  - `publishPost`
  - `unpublishPost`
  - `retirePromotion`
  - `replaceSeatLayoutForFutureShowtimes`
- [ ] Require confirmation for irreversible operations.
- [ ] Add domain exceptions for invalid lifecycle transitions.

### 3.3 Implement Audit Logging

- [ ] Create immutable audit logging service.
- [ ] Record:
  - actor ID
  - actor role
  - action
  - target type/id
  - before values
  - after values
  - IP address
  - user agent
  - request ID/correlation ID
- [ ] Redact sensitive fields.
- [ ] Prevent audit log mass assignment.
- [ ] Add audit logs for:
  - admin CRUD
  - role/permission changes
  - payment/order status changes
  - promotion changes
  - seat layout changes
  - login/security events

### 3.4 Harden File Uploads

- [ ] Validate MIME type and extension.
- [ ] Limit file size.
- [ ] Generate random filenames.
- [ ] Store outside public path when possible.
- [ ] Use storage disk abstraction.
- [ ] Delete old files after DB commit only.
- [ ] Roll back DB if file operation fails.
- [ ] Prevent stored XSS in uploaded content.
- [ ] Sanitize post/banner/movie content fields.

## Required Tests

- [ ] Cannot modify seats/layout if active bookings exist.
- [ ] Cannot delete product used in paid order.
- [ ] Cannot reset used promotion counters unsafely.
- [ ] Admin action creates audit log.
- [ ] Sensitive audit fields are redacted.
- [ ] Invalid upload MIME is rejected.
- [ ] Failed upload does not create broken DB record.

## Exit Criteria

- [ ] Admin destructive operations are guarded.
- [ ] Audit logging is implemented for critical actions.
- [ ] Uploads are validated and transactionally safe.
- [ ] Admin safety tests pass.

---

# Phase 4 - API Consistency, Exception Handling, Logging

## Priority

🟠 High

## Goal

Standardize API behavior, prevent sensitive error disclosure, and make production incidents observable.

## Primary Review References

- `REVIEWS/files/ApiResponse_trait_review.md`
- `REVIEWS/files/AuthController_review.md`
- controller review files under `REVIEWS/files/*Controller_review.md`
- `REVIEWS/files/PayOSGateway_review.md`
- `REVIEWS/files/ProcessPayOSWebhook_job_review.md`

## Problems to Fix

1. API responses are inconsistent.
2. Raw exception messages are returned to clients.
3. Logging is incomplete or leaks sensitive data.
4. Error handling is duplicated across controllers.
5. Pagination/filtering contracts are inconsistent.

## Tasks

### 4.1 Define API Response Standard

- [ ] Standard success envelope.
- [ ] Standard error envelope.
- [ ] Standard validation error shape.
- [ ] Standard pagination metadata.
- [ ] Standard error codes.
- [ ] Standard localization strategy.
- [ ] Document response examples.

### 4.2 Centralize Exception Handling

- [ ] Move exception mapping into Laravel exception handler.
- [ ] Create domain exceptions:
  - `SeatUnavailableException`
  - `PaymentAlreadyProcessedException`
  - `OrderNotPayableException`
  - `InvalidStateTransitionException`
  - `PromotionNotApplicableException`
  - `UnauthorizedDomainActionException`
- [ ] Return generic messages to clients.
- [ ] Log internal details server-side.
- [ ] Add correlation/request ID to logs and responses.

### 4.3 Logging Standards

- [ ] Add structured logs for:
  - payment creation
  - payment webhook
  - order creation
  - order expiration
  - seat hold/release
  - admin changes
  - auth failures
  - security events
- [ ] Redact:
  - tokens
  - passwords
  - card/payment secrets
  - webhook secrets
  - personal data where not needed
- [ ] Add log levels and alert rules.

### 4.4 Refactor Controller Error Handling

- [ ] Remove duplicated try/catch where framework handler can manage.
- [ ] Replace raw `$e->getMessage()` responses.
- [ ] Ensure HTTP status codes are correct.
- [ ] Use API Resources for serialized models.
- [ ] Avoid returning raw Eloquent models for sensitive resources.

## Required Tests

- [ ] Validation errors follow standard structure.
- [ ] Domain errors map to correct HTTP status.
- [ ] Raw exception messages are not exposed.
- [ ] Logs contain correlation IDs.
- [ ] Sensitive values are redacted in logs.

## Exit Criteria

- [ ] API response format is consistent.
- [ ] Exception handling is centralized.
- [ ] Production logs support incident investigation.
- [ ] No sensitive exception disclosure remains.

---

# Phase 5 - Data Model Integrity & Database Constraints

## Priority

🟠 High

## Goal

Move critical invariants from application-only assumptions into database constraints and model-level domain methods.

## Primary Review References

- model review files under `REVIEWS/files/*_model_review.md`
- `REVIEWS/files/Payment_model_review.md`
- `REVIEWS/files/Order_model_review.md`
- `REVIEWS/files/Ticket_model_review.md`
- `REVIEWS/files/Promotion_model_review.md`
- `REVIEWS/files/Product_model_review.md`
- `REVIEWS/files/ShowtimeSeatLayoutSnapshot_model_review.md`

## Problems to Fix

1. Missing uniqueness constraints.
2. Weak monetary invariants.
3. Mass-assignable lifecycle fields.
4. Duplicate ticket/seat entitlement risks.
5. Missing immutable snapshots.
6. Nullable fields and relationship integrity are inconsistent.

## Tasks

### 5.1 Add Database Constraints

- [ ] Unique order/payment references.
- [ ] Unique ticket code/QR code.
- [ ] Unique active seat booking per showtime/seat.
- [ ] Unique active hold per showtime/seat.
- [ ] Unique promotion code.
- [ ] Unique slug/code fields where required.
- [ ] Foreign key constraints for all critical relationships.
- [ ] Check constraints where supported:
  - positive price
  - non-negative stock
  - non-negative surcharge
  - valid capacity
  - valid quantity

### 5.2 Normalize Money Handling

- [ ] Decide money representation:
  - preferred: integer minor units
  - alternative: decimal with strict precision
- [ ] Remove float money calculations.
- [ ] Centralize rounding.
- [ ] Store currency explicitly if needed.
- [ ] Recalculate totals server-side.

### 5.3 Harden Model Fillable Fields

- [ ] Remove lifecycle fields from `$fillable`.
- [ ] Remove counters from `$fillable`.
- [ ] Remove audit/security metadata from `$fillable`.
- [ ] Use guarded domain methods.
- [ ] Add casts for booleans, arrays, dates, money where appropriate.

### 5.4 Add Snapshot and Immutability Rules

- [ ] Make ticket/order line item pricing immutable after payment.
- [ ] Make showtime seat layout snapshot immutable after first sale/hold.
- [ ] Version promotion rules instead of mutating used promotions.
- [ ] Preserve product/combo names and prices at purchase time.
- [ ] Preserve seat label/type at purchase time.

### 5.5 Data Cleanup and Migration Safety

- [ ] Detect duplicate slugs/codes before adding unique constraints.
- [ ] Detect invalid null relationships.
- [ ] Detect negative/invalid prices.
- [ ] Detect duplicate tickets.
- [ ] Write safe migration scripts.
- [ ] Add rollback plans.

## Required Tests

- [ ] Database rejects duplicate ticket for same showtime/seat.
- [ ] Database rejects duplicate idempotency key.
- [ ] Database rejects invalid monetary values.
- [ ] Model cannot mass-assign restricted fields.
- [ ] Snapshots do not mutate after payment.
- [ ] Promotion versioning preserves historical orders.

## Exit Criteria

- [ ] Critical invariants enforced at DB level.
- [ ] Money calculation is deterministic.
- [ ] Model mass assignment is safe.
- [ ] Migration scripts are tested on copy of production-like data.

---

# Phase 6 - Service/Controller Refactor & Architecture Cleanup

## Priority

🟡 Medium

## Goal

Reduce fat controllers, move business logic into services/domain actions, improve maintainability and testability.

## Primary Review References

- all controller review files under `REVIEWS/files/*Controller_review.md`
- all service review files under `REVIEWS/files/*Service_review.md`

## Problems to Fix

1. Controllers contain business logic.
2. Services are inconsistent and sometimes coupled to `Request`.
3. Some logic bypasses service layer.
4. Duplicated response and validation logic.
5. Hard-to-test static/facade-heavy code.

## Tasks

### 6.1 Define Application Service Boundaries

- [ ] Create dedicated services/actions:
  - `CreateOrderAction`
  - `LockSeatsAction`
  - `ReleaseSeatHoldAction`
  - `CreatePaymentAction`
  - `ProcessPaymentWebhookAction`
  - `ApplyPromotionAction`
  - `CreateShowtimeAction`
  - `UpdateSeatLayoutAction`
- [ ] Keep controllers thin:
  - authorize
  - validate
  - call action/service
  - return resource/response

### 6.2 Remove Request Coupling from Services

- [ ] Services should accept DTOs/arrays/value objects, not raw `Request`.
- [ ] Create DTOs for complex flows:
  - `CreateOrderData`
  - `PaymentData`
  - `SeatLockData`
  - `PromotionApplicationData`
  - `ShowtimeScheduleData`

### 6.3 Introduce API Resources

- [ ] Add resources for:
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
- [ ] Prevent sensitive raw model serialization.
- [ ] Standardize date and money formatting.

### 6.4 Reduce Duplication

- [ ] Extract common pagination/filtering helpers.
- [ ] Extract file upload service.
- [ ] Extract audit service.
- [ ] Extract status transition logic.
- [ ] Extract admin CRUD patterns only where abstraction remains clear.

### 6.5 Improve Dependency Injection

- [ ] Inject gateway interfaces instead of concrete gateways.
- [ ] Inject clock/time provider for testability where needed.
- [ ] Inject ID/code generators for deterministic tests.
- [ ] Avoid hidden static dependencies for core business logic.

## Required Tests

- [ ] Controller tests confirm thin-controller behavior.
- [ ] Service tests cover domain actions.
- [ ] DTO validation/mapping tests.
- [ ] API Resource tests ensure no sensitive fields leak.
- [ ] Mock payment gateway tests.

## Exit Criteria

- [ ] Critical controllers are thin.
- [ ] Business logic is centralized.
- [ ] Services are testable without HTTP request objects.
- [ ] API resources protect response shape.

---

# Phase 7 - Performance, Analytics Accuracy, Caching

## Priority

🟡 Medium

## Goal

Fix analytics correctness, reduce N+1 queries, avoid unbounded queries, and add safe caching.

## Primary Review References

- `REVIEWS/files/DashboardService_review.md`
- `REVIEWS/files/RevenueService_review.md`
- `REVIEWS/files/TicketAnalyticsService_review.md`
- `REVIEWS/files/FoodAnalyticsService_review.md`
- `REVIEWS/files/ComboAnalyticsService_review.md`
- `REVIEWS/files/ProductService_review.md`
- `REVIEWS/files/HomeController_review.md`

## Problems to Fix

1. Revenue and ticket analytics can overcount/misattribute data.
2. Date semantics are inconsistent.
3. Refunds/cancellations may be ignored.
4. Queries can be unbounded.
5. Cache invalidation is unreliable.
6. N+1 and heavy joins exist in analytics/reporting.

## Tasks

### 7.1 Define Reporting Semantics

- [ ] Define revenue source of truth:
  - paid orders
  - payment transactions
  - tickets
  - refunds
- [ ] Define date boundaries:
  - payment date
  - order date
  - showtime date
- [ ] Define sold ticket state.
- [ ] Define cancelled/refunded handling.
- [ ] Document analytics assumptions.

### 7.2 Fix Analytics Queries

- [ ] Remove revenue overcounting from joined ticket/order item queries.
- [ ] Count distinct orders/tickets correctly.
- [ ] Include refunds where applicable.
- [ ] Filter only paid/confirmed records.
- [ ] Validate date ranges.
- [ ] Limit maximum reporting window.
- [ ] Use database aggregation instead of in-memory collection loops.

### 7.3 Add Pagination and Query Limits

- [ ] Enforce max `per_page`.
- [ ] Add default pagination.
- [ ] Whitelist sortable columns.
- [ ] Whitelist filter fields.
- [ ] Reject arbitrary sorting/filtering.

### 7.4 Add Caching Safely

- [ ] Cache public homepage/catalog data.
- [ ] Cache analytics only with clear invalidation.
- [ ] Use cache tags if supported.
- [ ] Invalidate on relevant writes.
- [ ] Avoid caching user-sensitive data globally.

### 7.5 Add Database Indexes

- [ ] Add indexes based on query plans:
  - orders status/date
  - payments status/date/order
  - tickets showtime/seat/status
  - showtimes movie/theater/screen/start_time
  - products type/status
  - promotions code/status/date
  - order_items order/type/item
- [ ] Review MySQL EXPLAIN output.

## Required Tests

- [ ] Analytics numbers match known seeded data.
- [ ] Refunds/cancellations are handled correctly.
- [ ] Date range filters are deterministic.
- [ ] Large date range is rejected or paginated.
- [ ] Sorting whitelist blocks unsafe columns.
- [ ] Cache invalidation works after relevant writes.

## Exit Criteria

- [ ] Analytics are financially reliable.
- [ ] Public/list endpoints are bounded.
- [ ] Query plans are acceptable.
- [ ] Cache behavior is deterministic and tested.

---

# Phase 8 - Test Hardening, Security Verification, Release Readiness

## Priority

🔴 Critical

## Goal

Prove the system is safe to release after remediation.

## Scope

- Regression testing
- Security testing
- Concurrency testing
- Load testing
- Release checklist
- Re-review

## Tasks

### 8.1 Expand Automated Test Coverage

- [ ] Unit tests for domain services/actions.
- [ ] Feature tests for all critical endpoints.
- [ ] Authorization tests for admin/user endpoints.
- [ ] Validation tests for all FormRequests.
- [ ] Payment gateway mock tests.
- [ ] Webhook replay tests.
- [ ] Queue job retry/failure tests.

### 8.2 Add Concurrency Tests

- [ ] Concurrent seat hold attempts.
- [ ] Concurrent order creation attempts.
- [ ] Concurrent payment creation attempts.
- [ ] Webhook duplication/replay.
- [ ] Order expiration racing payment success.
- [ ] Promotion usage limit race.
- [ ] Product stock race.

### 8.3 Security Verification

- [ ] Run dependency audit.
- [ ] Review `.env.example` safety.
- [ ] Verify security headers.
- [ ] Verify cookie flags.
- [ ] Verify rate limits.
- [ ] Verify no raw exception messages.
- [ ] Verify logs redact secrets.
- [ ] Verify IDOR test suite.
- [ ] Verify mass assignment test suite.

### 8.4 Load and Performance Testing

- [ ] Simulate seat-selection traffic.
- [ ] Simulate checkout traffic.
- [ ] Simulate webhook spikes.
- [ ] Simulate analytics dashboard queries.
- [ ] Confirm DB locks do not cause unacceptable deadlocks.
- [ ] Add deadlock retry where needed.

### 8.5 Release Checklist

- [ ] All blocking issues closed.
- [ ] All high issues either fixed or explicitly accepted by product/security owner.
- [ ] All migrations tested.
- [ ] Rollback plan tested.
- [ ] Payment sandbox verified.
- [ ] Webhook replay verified.
- [ ] Monitoring dashboards configured.
- [ ] Alerts configured.
- [ ] Final code review completed.
- [ ] Final security review completed.

## Exit Criteria

- [ ] Test suite passes.
- [ ] Concurrency tests pass.
- [ ] Security checks pass.
- [ ] Load test meets acceptance threshold.
- [ ] Final re-review approves production release.

---

## 3. Recommended Implementation Order

The recommended order is strict:

1. **Phase 0** - Baseline and tickets.
2. **Phase 1** - Payment/booking/seat concurrency.
3. **Phase 2** - Authorization/authentication/validation.
4. **Phase 3** - Admin safety/audit/uploads.
5. **Phase 4** - API consistency/exceptions/logging.
6. **Phase 5** - Database/model constraints.
7. **Phase 6** - Refactor controllers/services.
8. **Phase 7** - Performance/analytics/caching.
9. **Phase 8** - Final test hardening and release readiness.

Do not start broad refactoring before Phase 1 and Phase 2 are stable.

---

## 4. Production Release Gates

### Minimum Required Before Any Production Launch

- [ ] Phase 0 completed.
- [ ] Phase 1 completed.
- [ ] Phase 2 completed.
- [ ] Critical parts of Phase 3 completed:
  - [ ] destructive admin operations blocked
  - [ ] audit logs for payment/order/admin security actions
  - [ ] file uploads hardened
- [ ] Critical parts of Phase 4 completed:
  - [ ] raw exception disclosure removed
  - [ ] payment/booking/webhook logs implemented
- [ ] Phase 8 concurrency and security tests passed.

### Must Not Launch If Any Are True

- [ ] Payment creation can be retried and create duplicate charge.
- [ ] Same seat can be held/booked by two users.
- [ ] Order can be marked paid without verified payment.
- [ ] User can access another user's order/payment/profile.
- [ ] Admin can mutate seats/layouts that affect sold tickets.
- [ ] Raw exception messages are returned to API clients.
- [ ] Payment/webhook events are not auditable.
- [ ] Critical migrations are not rollback-tested.

---

## 5. Suggested Team Allocation

### Backend Lead

- Own Phase 1 architecture.
- Review transaction and locking design.
- Approve state machines.

### Security Engineer / Senior Reviewer

- Own Phase 2 and Phase 8 security verification.
- Review authorization, token/cookie behavior, and logging redaction.

### Backend Engineer 1

- Implement payment idempotency.
- Implement webhook idempotency.
- Add payment tests.

### Backend Engineer 2

- Implement seat hold redesign.
- Implement booking/order transaction flow.
- Add concurrency tests.

### Backend Engineer 3

- Implement policies/FormRequest authorization.
- Harden validation.
- Add authorization tests.

### Backend Engineer 4

- Implement admin safety/audit/file upload hardening.
- Add audit/admin tests.

### QA Engineer

- Build regression checklist.
- Run booking/payment/webhook/manual admin scenarios.
- Verify release gates.

---

## 6. Risk Register

| Risk | Impact | Mitigation |
| ---- | ------ | ---------- |
| Seat-hold migration breaks existing flows | High | Develop behind feature branch, seed tests, migration rehearsal |
| Payment idempotency conflicts with gateway behavior | High | Use sandbox, replay tests, gateway abstraction |
| Locking introduces deadlocks | High | Deterministic lock ordering, retry deadlocks, load tests |
| Authorization changes break admin workflows | Medium | Permission matrix, admin regression tests |
| DB constraints fail due to dirty data | High | Pre-migration data audit scripts |
| Audit logging stores sensitive data | Medium | Redaction allowlist and tests |
| Refactor introduces regressions | Medium | Refactor only after critical tests exist |

---

## 7. Tracking Template

Use this template for each remediation ticket:

```markdown
## Ticket: [Short Title]

Severity:
Blocking / High / Medium / Low

Review Reference:
- `REVIEWS/...`

Affected Files:
- `app/...`

Problem:
...

Target Fix:
...

Acceptance Criteria:
- [ ] ...
- [ ] ...

Required Tests:
- [ ] Unit
- [ ] Feature
- [ ] Concurrency
- [ ] Security
- [ ] Regression

Rollback Plan:
...

Status:
Todo / In Progress / Review / Done
```

---

## 8. Final Success Definition

The remediation is successful only when:

- Payment flow is idempotent and retry-safe.
- Booking flow cannot double-book seats under concurrency.
- Order/payment/seat state transitions are transactional and explicit.
- Admin operations cannot corrupt existing bookings.
- Authorization is enforced consistently.
- Validation prevents invalid business input.
- Sensitive fields cannot be mass-assigned.
- API errors are consistent and do not leak internals.
- Audit and operational logs support incident investigation.
- Database constraints enforce critical invariants.
- Tests prove critical flows under normal, failure, retry, and concurrent conditions.
- Final re-review changes project status from **BLOCKING** to **APPROVE WITH COMMENTS** or better.
