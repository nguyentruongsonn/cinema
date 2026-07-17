# Frontend Remediation Plan

**Source review:** `REVIEWS/FRONTEND_REVIEW_REPORT.md`  
**Created:** 2026-07-17  
**Current release decision:** Blocked  
**Execution principle:** Lock Critical/High behavior with tests before architecture migration.

## 1. Priority Summary

| Order | Ticket | Severity | Owner profile | Estimate | Blocking |
|---:|---|---:|---|---:|---:|
| 1 | FE-001 Stored user-to-admin DOM XSS | Critical | Frontend + Security | M | Yes |
| 2 | FE-002 Checkout intent idempotency | Critical | Frontend + Backend | M/L | Yes |
| 3 | FE-003 Systemic unsafe DOM rendering | High | Frontend | L | Yes |
| 4 | FE-004 Reliable seat-hold release | High | Frontend + Backend | S/M | Yes |
| 5 | FE-005 Admin order API/UI contract | High | Full-stack | M | Yes |
| 6 | FE-006 Ticket scanner completion | High | Frontend + Backend | M | Yes |
| 7 | FE-007 Critical-flow browser release gate | High | QA + Frontend | L | Yes |
| 8 | FE-008 Unified request lifecycle | Medium | Frontend | M/L | No |
| 9 | FE-009 Auth validation mapping | Medium | Frontend | S | No |
| 10 | FE-010 Remove mock/misleading behavior | Medium | Frontend + Product | M | No |
| 11 | FE-011 Keyboard accessibility | Medium | Frontend + QA | M | No |
| 12 | FE-012 Vite/cache/CSP migration | Medium | Frontend + Platform | L | No |
| 13 | FE-013 Module boundary refactor | Medium | Frontend | L | No |
| 14 | FE-014 Dead path cleanup | Low | Frontend | S | No |

## 2. Phase 0 - Safety Baseline

### Goal

Create deterministic tests that reproduce the blockers before implementation changes.

### Tasks

- Add a browser security fixture that returns hostile strings in user, theater, movie, post, product, ticket, and order fields.
- Add a checkout test where the first `/payments` response is lost after server commit.
- Add a page-close test after a successful seat hold.
- Add authenticated admin fixtures for users, orders, and ticket verification.
- Split browser test startup/teardown from test assertions so the process exits reliably.
- Record current API payload/response fixtures for `/payments`, `/seats/lock`, `/seats/unlock`, `/orders/user/me`, and `/admin/tickets/verify`.

### Exit criteria

- FE-001, FE-002, FE-004, FE-005, and FE-006 each have a failing regression test.
- Browser tests terminate with a reliable exit code.
- Test data is isolated and can run repeatedly without manual cleanup.

### Rollback

- Test-only phase; remove unstable fixtures without changing runtime behavior.

## 3. Phase 1 - Critical Security

## 1.1 Fix FE-001 user-to-admin XSS

### Implementation

- Replace `tr.innerHTML` in admin users with element creation and `textContent`.
- Store only numeric user IDs in action button datasets.
- Resolve names from in-memory data by ID when opening dialogs.
- Never place user-controlled strings in inline event handlers or HTML attributes.
- Add a small safe DOM helper with explicit functions for text, attributes, and allowlisted URLs.

### Acceptance criteria

- Names containing `<`, `>`, quotes, SVG, image handlers, and encoded variants render as literal text.
- Admin actions still target the correct user.
- No script/event handler runs in the browser test.
- Admin user CRUD tests remain green.

## 1.2 Fix FE-003 systemic unsafe sinks

### Implementation order

1. Admin users, posts, movies, products, combos, promotions, theaters, branches, screens, banners.
2. Public theaters, movies, home, movie detail.
3. Profile order/ticket invoice and booking product/voucher rendering.
4. Scanner result rendering and SVG/barcode boundaries.

### Rendering rules

- Text always uses `textContent`.
- URLs pass through protocol and origin allowlists before assignment.
- Rich text uses one reviewed sanitizer configuration.
- Static developer markup may use templates, but dynamic values are inserted afterward.
- No `onclick`, `onerror`, or other inline handlers.
- Add lint/static scan failure for unsafe dynamic sinks.

### Acceptance criteria

- Browser payload suite passes on public and admin pages.
- CSP can remove `'unsafe-inline'` for scripts without breaking pages.
- `SecurityUtils` has one implementation and real consumers, or is removed in favor of a module alternative.

### Rollback

- Migrate one page per change set; preserve prior renderer behind no runtime flag only if regression risk requires it.

## 4. Phase 2 - Payment and Booking Invariants

## 2.1 Fix FE-002 checkout intent idempotency

### Frontend design

- Introduce a `CheckoutIntent` state object containing:
  - stable UUID;
  - hold ID;
  - canonical payload fingerprint;
  - state: idle/submitting/created/redirecting/terminal;
  - returned order and gateway codes.
- Generate a key when the checkout payload becomes ready, not inside every submit call.
- Reuse the key after timeout, disconnect, response loss, or ambiguous 5xx.
- Generate a new key only after the user changes seats/products/voucher/points or the server returns a definitive terminal failure.
- Disable every payment trigger while state is submitting/created/redirecting.
- Redirect immediately or keep the guard active until navigation/pagehide.
- Persist the intent in `sessionStorage` only if refresh recovery is required; never store auth tokens.

### Backend invariant

- Bind payment initiation to an explicit hold/checkout intent.
- Enforce at most one active pending order/payment per hold and payload.
- Return the existing checkout URL for a replay of the same intent.
- Define behavior when the gateway link exists but the client never received it.

### Acceptance criteria

- Double click, two visible payment buttons, delayed navigation, request timeout, and response loss create exactly one order/payment/gateway link.
- Payload changes cannot reuse an old key.
- Same key with a different payload is rejected deterministically.
- Browser displays a recoverable state instead of allowing blind duplicate submission.

## 2.2 Fix FE-004 seat release lifecycle

### Preferred contract

- Add an idempotent `POST /api/v1/seats/holds/{hold}/release` endpoint suitable for `sendBeacon`, or prove `fetch` DELETE with `keepalive` works in supported browsers.
- Trigger on `pagehide`; avoid relying only on `beforeunload`.
- Keep hold expiration cleanup as mandatory fallback.
- Do not release a hold after the server has bound it to an active checkout/order.

### Acceptance criteria

- Closing, refreshing, back navigation, and tab discard release an uncommitted hold within the agreed SLA.
- Repeated release calls are harmless.
- Navigating to PayOS does not incorrectly release a hold required by checkout.

### Rollback

- Server expiration remains the fallback if lifecycle release is temporarily disabled.

## 5. Phase 3 - Operational Admin Flows

## 3.1 Fix FE-005 admin orders

### Backend contract

- Add authorized `/api/v1/admin/orders` list/detail endpoints.
- Bound `per_page`, date ranges, sort fields, and filters.
- Return one stable resource schema with pagination metadata.
- Include only fields needed by the admin UI.

### Frontend work

- Remove `/orders/user/me` fallback.
- Use the shared API client.
- Ensure filter changes are latest-wins and abort obsolete requests.
- Add loading, empty, partial error, and retry states.

### Acceptance criteria

- Admin sees orders across multiple users.
- Search/status/date/branch/theater/movie filters produce authoritative counts.
- Non-admin receives 403 and no admin data.
- No stale response overwrites a newer filter result.

## 3.2 Fix FE-006 ticket scanner

### Frontend work

- Use `/api/v1/admin/tickets/verify` through shared cookie-based auth.
- Remove localStorage token reads.
- Import `jsqr` through Vite and decode camera frames.
- Throttle scanning and pause after a code is detected.
- Escape all ticket result fields.
- Handle camera denied, no camera, invalid QR, used ticket, duplicate scan, and network failure.

### Acceptance criteria

- Manual verification succeeds for valid tickets and fails correctly for invalid/used tickets.
- A deterministic QR image is decoded and verified once.
- Camera tracks and animation loop stop on modal close.
- No ticket payload can execute markup.

## 6. Phase 4 - UX and Accessibility

## 4.1 Fix FE-009 validation mapping

- Map `error.errors` fields to controls.
- Focus the first invalid control.
- Keep a general summary with `aria-live` for non-field errors.
- Preserve backend messages without exposing internal error text.

### Acceptance criteria

- Multiple registration errors appear beside the correct fields.
- Screen reader announces the summary and focus moves predictably.

## 4.2 Fix FE-010 product correctness

- Decide whether quick booking should navigate to filtered movie showtimes or a dedicated results route.
- Pass movie/date/cinema through a documented contract.
- Remove fake theater badges immediately; display only source-backed capabilities.

### Acceptance criteria

- Every visible selection changes the result or is removed from UI.
- Capability badges match seeded API data exactly.

## 4.3 Fix FE-011 keyboard accessibility

- Replace custom-select triggers with native controls where possible.
- Otherwise implement combobox/listbox semantics and keyboard interaction.
- Add Enter/Space activation and `aria-pressed`/selected state for seats.
- Ensure disabled seats are not focusable.
- Preserve visible `:focus-visible` indicators.
- Respect `prefers-reduced-motion` in page-specific animation files.

### Acceptance criteria

- Keyboard-only user can select movie/date/cinema, choose seats, apply voucher/points, review, and initiate payment.
- Focus is retained/restored across modal, toast, and step changes.
- Automated accessibility scan has no critical/serious issue on core pages.

## 7. Phase 5 - Request and Module Architecture

## 5.1 Fix FE-008 request lifecycle

### Target API client behavior

- One base URL and credentials policy.
- One structured error shape.
- Coordinated token refresh.
- Timeout and `AbortController` support.
- Request cancellation on page/filter change.
- Latest-wins helper for list/search requests.
- Request ID propagation and safe client logging.

### Migration order

1. Auth and booking/payment.
2. Admin orders and scanner.
3. Remaining admin CRUD pages.
4. Public catalog pages.

### Acceptance criteria

- No direct `fetch()` remains outside the API client and approved adapters.
- 401/403/422/429/5xx/network/timeout behavior is consistent.
- Slow/out-of-order test suite passes.

## 5.2 Fix FE-013 module boundaries

- Extract booking session state from DOM rendering.
- Extract checkout intent from booking page controller.
- Extract realtime and polling adapters behind one seat-status interface.
- Extract profile order normalization/render models.
- Standardize admin table/form/pagination primitives.
- Remove debug globals from production builds.

### Acceptance criteria

- Critical domain state is unit-testable without a browser DOM.
- Page entry modules orchestrate modules rather than contain business logic.
- No broad rewrite is required; each extraction is behavior-preserving and covered by tests.

## 8. Phase 6 - Asset Pipeline, CSP, and Performance

## 6.1 Fix FE-012 asset delivery

- Move actual public/admin/page entries into Vite.
- Reference assets with `@vite` and hashed manifest output.
- Remove all `?v={{ time() }}` cache busting.
- Bundle installed dependencies instead of mixing npm and CDN versions.
- Self-host critical third-party assets or add pinned versions and SRI where CDN remains.
- Remove inline scripts and handlers; introduce CSP nonce only where unavoidable.
- Remove `'unsafe-eval'`, then `'unsafe-inline'` from `script-src`.

### Performance acceptance criteria

- Repeat navigation uses cached immutable assets.
- No duplicate Bootstrap/Echo/Pusher runtime source.
- Production assets are minified and hashed.
- LCP, CLS, and INP budgets are measured on home, movie detail, booking, and admin dashboard.
- Booking polling pauses when page is hidden and cannot overlap requests.

## 6.2 Clean FE-014 dead paths

- Remove legacy payment page JS/view if no compatibility consumer exists.
- Hide unfinished Google OAuth behind a disabled feature flag.
- Remove stale comments, placeholder warnings, and unused security modules.

### Acceptance criteria

- Every shipped control has a working contract.
- Every shipped page script is reachable and tested.

## 9. Phase 7 - Browser Release Gate

### Required E2E scenarios

- Guest registration, login, refresh, logout, session expiry.
- Stored XSS payloads across public and admin rendering.
- Seat hold, replace hold, conflict, expiry, and page-close release.
- Voucher/points success and validation failure.
- Checkout double-click, response loss, retry, two tabs, and gateway failure.
- PayOS callback pending/success/cancel and delayed webhook.
- Profile order/ticket detail ownership.
- Admin order list/filter/detail.
- Manual and QR ticket verification.
- Keyboard-only booking.
- Mobile 390px and tablet layout.

### Browser matrix

- Chromium/Edge: required on every change.
- Firefox: required before release.
- WebKit/Safari: required for booking, payment return, page lifecycle, and camera constraints; any unavailable physical-device test must be an explicit release exception.

### Failure policy

- Unexpected page error, console error, 4xx/5xx, unhandled rejection, or failed critical request fails the test.
- Known third-party failures must be mocked or explicitly allowlisted with an owner and expiry date.
- Test runner must terminate cleanly and preserve traces/screenshots on failure.

## 10. Production Release Gates

Release is blocked until all conditions below are true:

- [ ] FE-001 exploit test passes on admin users.
- [ ] FE-003 sink suite passes on all audited dynamic renderers.
- [ ] FE-002 ambiguous retry creates one order/payment/link.
- [ ] One active checkout/order is enforced per hold/intent.
- [ ] FE-004 page-close seat release test passes.
- [ ] FE-005 admin order page uses an authorized admin contract.
- [ ] FE-006 manual ticket verification works; camera behavior is complete or removed from release UI.
- [ ] Critical browser suite passes and exits cleanly.
- [ ] PHP tests, Vite build, npm audit, and browser tests pass in CI.
- [ ] No Critical or High frontend finding remains open without explicit security/product acceptance.

## 11. Suggested Delivery Sequence

1. Add failing blocker tests.
2. Fix user-to-admin XSS.
3. Fix checkout intent idempotency and server invariant.
4. Complete remaining unsafe sink migration.
5. Fix seat lifecycle release.
6. Deliver admin orders and ticket scanner.
7. Repair accessibility and request lifecycle.
8. Migrate assets/CSP.
9. Refactor large modules behind passing tests.
10. Run final cross-browser release gate.

## 12. Definition of Done

A ticket is complete only when:

- Root cause is fixed, not hidden by UI state.
- Unit/feature/browser regression coverage exists at the correct layer.
- Slow network, retry, duplicate input, and authorization behavior are defined.
- Accessibility impact is checked.
- No new unsafe DOM sink or direct request policy is introduced.
- Documentation and browser fixtures match the actual API contract.
- Rollback behavior is known and does not violate payment/seat invariants.
