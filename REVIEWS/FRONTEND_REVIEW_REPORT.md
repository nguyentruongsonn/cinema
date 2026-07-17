# Frontend Comprehensive Review Report

**Project:** Cinema Booking System  
**Review date:** 2026-07-17  
**Reviewer posture:** Senior Frontend Engineer / Frontend Architect  
**Scope:** Blade view shell, public/admin JavaScript, CSS, REST contracts, booking/payment flow, browser behavior, accessibility, security, performance, and frontend test coverage.  
**Decision:** **NOT PRODUCTION-READY**

## A. Executive Summary

Frontend hiện render được các trang public cơ bản và toàn bộ PHP test suite đang xanh, nhưng chưa đạt release gate vì còn hai lỗi Critical đã được xác nhận:

1. Người dùng thường có thể lưu payload HTML trong tên và payload này được đưa thẳng vào `innerHTML` trên trang quản trị người dùng, tạo đường leo thang từ tài khoản thường sang thực thi JavaScript trong phiên admin.
2. Idempotency phía browser chỉ tồn tại trong từng request. Mỗi lần retry tạo UUID mới và cờ chống submit được mở lại trước khi redirect hoàn tất, nên cùng một hold/payload có thể sinh nhiều order/payment link.

### Top risks

| Rank | Finding | Severity | Release impact |
|---|---|---:|---|
| 1 | FE-001 - Stored DOM XSS từ user vào admin | Critical | Có thể chiếm quyền thao tác trong phiên admin |
| 2 | FE-002 - Retry checkout tạo payment operation mới | Critical | Có thể tạo nhiều order/payment link cho cùng giao dịch |
| 3 | FE-004 - Beacon rời trang không release seat hold | High | Ghế tiếp tục bị khóa đến khi hết hạn |
| 4 | FE-005 - Trang admin orders dùng endpoint của user | High | Admin không thể quản trị toàn bộ đơn hàng |
| 5 | FE-006 - Ticket scanner chưa hoạt động | High | Không thể quét/xác thực vé tại vận hành rạp |

### Coverage completed

- Public layout, admin layout, all page entry scripts, shared API/auth/security modules.
- Booking flow from seat load/hold to payment initiation and PayOS redirect.
- Admin user, movie, post, theater, order, and ticket scanner rendering paths.
- DOM XSS sinks, URL handling, storage/token usage, timers, polling, WebSocket wiring, request wrappers.
- Keyboard accessibility for custom selects and seats.
- Vite build, npm/composer advisory audit, PHP suite, existing browser smoke, and an isolated Edge/Playwright browser probe.

### Not fully verified

- Real PayOS payment completion with a production-like sandbox account.
- Real Reverb multi-tab event delivery under concurrent users.
- Camera scanning on a physical mobile device.
- Firefox and Safari/WebKit behavior.
- Screen-reader output and formal WCAG contrast measurement.
- Core Web Vitals on production infrastructure.

## B. Architecture Map

### Runtime structure

```text
Blade layouts
├── resources/views/layouts/app.blade.php
│   ├── window.APP_CONFIG / window.REVERB_CONFIG
│   ├── security-standalone.js
│   ├── api-client.js
│   ├── auth.js
│   └── user page module
└── resources/views/layouts/admin.blade.php
    ├── admin-core.js
    ├── api-client.js + auth.js
    ├── admin/app.js
    └── admin page module

Browser state and requests
├── window.apiClient
├── window.authManager
├── window.AdminCore
├── page-specific raw fetch wrappers
└── page globals such as window.bookingManager

Backend contract
└── /api/v1 public, authenticated user, and admin endpoints
```

### Main entry points

- Public shell: `resources/views/layouts/app.blade.php:59`.
- Admin shell: `resources/views/layouts/admin.blade.php:338`.
- Shared API client: `public/js/core/api-client.js:106`.
- Authentication state/refresh: `public/js/users/auth.js:269`.
- Booking orchestration: `public/js/users/pages/booking.js:6`.
- Profile/ticket/order UI: `public/js/users/pages/profile.js:1`.
- Admin request wrapper: `public/js/admin/admin-core.js:5`.
- Admin orders has another local request wrapper: `public/js/admin/pages/orders.js:494`.
- Vite entry exists at `resources/js/main.js:1`, but Blade layouts do not reference `@vite`.

### Primary architecture risks

- Request behavior is fragmented between `ApiClient`, `AuthManager`, `AdminCore`, local `apiRequest()` methods, and raw `fetch()`.
- Shared `SecurityUtils` is loaded globally but page renderers do not use it.
- Large page controllers combine transport, state, pricing UI, DOM rendering, timers, polling, WebSocket, and navigation.
- Security and rendering conventions are voluntary; there is no lint rule, template abstraction, or Trusted Types boundary enforcing safe sinks.
- Build tooling and runtime asset delivery are disconnected.

## C. Findings

## FE-001 - Stored DOM XSS từ tài khoản thường vào trang admin users

- **Severity:** Critical
- **Confidence:** High
- **Category:** Frontend security / privilege escalation
- **Evidence:**
  - Registration accepts any string for `name`: `app/Http/Requests/RegisterRequest.php:51`.
  - Admin table assigns API data directly to `tr.innerHTML`: `public/js/admin/pages/users.js:133`.
  - `user.name`, `user.email`, role names, and `data-name` are interpolated without escaping: `public/js/admin/pages/users.js:135`, `public/js/admin/pages/users.js:136`, `public/js/admin/pages/users.js:156`.
  - CSP permits inline script execution: `app/Http/Middleware/SecurityHeaders.php:33`.
- **Reproduction:** Register a user whose name contains an element with an event handler, then open `/admin/users` as admin. The payload is parsed as markup when the row is rendered.
- **Affected flow:** User registration/profile update → admin user list.
- **Root cause:** API strings are treated as trusted HTML; output encoding is not enforced at the rendering boundary.
- **Impact:** A low-privilege account can execute JavaScript in an admin-origin page and perform authenticated admin actions.
- **Recommended fix:** Build cells with DOM nodes and `textContent`; use encoded `data-*` values or event delegation with IDs only; remove inline handlers; introduce an automated rule banning dynamic `innerHTML`.
- **Required test:** Register/update a user with XSS payloads and assert that admin UI renders literal text and no marker function executes.
- **Effort:** M
- **Dependencies/risks:** Must audit all admin table renderers, not only users.

## FE-002 - Checkout retry không giữ nguyên idempotency operation

- **Severity:** Critical
- **Confidence:** High
- **Category:** Payment correctness / race condition
- **Evidence:**
  - Every call generates a new UUID in the request: `public/js/users/pages/booking.js:1484`, `public/js/users/pages/booking.js:1994`.
  - Redirect is delayed by one second: `public/js/users/pages/booking.js:1505`.
  - `finally` immediately resets `isCreatingPayment` and hides the overlay before navigation finishes: `public/js/users/pages/booking.js:1518`.
  - Multiple visible controls can call the same method: `public/js/users/pages/booking.js:391`, `public/js/users/pages/booking.js:398`.
  - Backend idempotency protects only repeated use of the same key: `app/Models/IdempotencyKey.php:102`.
  - Order items are created during fulfillment rather than payment-link creation; current test documents that multiple distinct payment initiations can succeed: `tests/Feature/Payment/PaymentSecurityTest.php:271`.
- **Reproduction:** Create a valid hold, initiate payment, prevent/interrupt navigation after the API succeeds, then initiate again. The second request carries a different key and can create a new order/payment link.
- **Affected flow:** Confirm booking → create order/payment → redirect PayOS.
- **Root cause:** Idempotency key lifetime equals one function invocation instead of one business operation; server does not bind one active pending payment to the hold/payload.
- **Impact:** Duplicate pending orders/payment links, reconciliation complexity, and possible duplicate customer payment attempts.
- **Recommended fix:** Generate and persist one key per checkout intent; reuse it for ambiguous failures/retries; clear it only after a definitive payload change or terminal response. Keep submit state locked through navigation. Add a server invariant that a seat hold/checkout intent has at most one active pending order/payment.
- **Required test:** Simulate response loss after server commit, retry with the same key, and assert one order, one payment, and one gateway link.
- **Effort:** M frontend + M/L backend
- **Dependencies/risks:** Requires an explicit cross-layer checkout-intent contract.

## FE-003 - DOM XSS là vấn đề hệ thống, không chỉ một màn hình

- **Severity:** High
- **Confidence:** High
- **Category:** Frontend security
- **Evidence:**
  - Public theater page injects branch, theater name, address, and image URL into HTML: `public/js/users/pages/theaters.js:114`, `public/js/users/pages/theaters.js:165`, `public/js/users/pages/theaters.js:174`, `public/js/users/pages/theaters.js:178`.
  - Profile invoice injects ticket/product snapshot data: `public/js/users/pages/profile.js:1178`, `public/js/users/pages/profile.js:1191`, `public/js/users/pages/profile.js:1210`.
  - Admin movies/posts interpolate stored content: `public/js/admin/pages/movies.js:124`, `public/js/admin/pages/posts.js:73`.
  - `SecurityUtils` is loaded at `resources/views/layouts/app.blade.php:153` but has no runtime consumers.
  - Browser probe mocked the theaters API and confirmed `theaterDomXssExecuted: true`.
- **Reproduction:** Return `<img src=x onerror="window.__probe=1">` as a theater name; load `/theaters`; verify `window.__probe === 1`.
- **Affected flow:** Public catalog, profile invoices, and most admin CRUD tables.
- **Root cause:** HTML string templating is the default rendering pattern and `escapeHtml` is inconsistently implemented/used. HTML escaping is also incorrectly used as URL validation in several files.
- **Impact:** Stored content or a compromised API response can execute script in user/admin sessions.
- **Recommended fix:** Inventory every dynamic sink; replace text with `textContent`; validate URLs through a strict allowlist; sanitize only intentional rich text; tighten CSP after inline code is removed.
- **Required test:** Browser sink suite covering text, attribute, URL, SVG, and rich-text payloads across public/admin views.
- **Effort:** L
- **Dependencies/risks:** Broad change; should be delivered by reusable renderer helpers and page-by-page regression tests.

## FE-004 - Seat hold không được release đáng tin cậy khi rời trang

- **Severity:** High
- **Confidence:** High
- **Category:** Booking reliability
- **Evidence:**
  - `beforeunload` sends JSON through `navigator.sendBeacon`: `public/js/users/pages/booking.js:470`.
  - It attempts method override inside a JSON body: `public/js/users/pages/booking.js:475`.
  - The server exposes only `DELETE /seats/unlock/{holdId}`: `routes/api.php:100`.
- **Reproduction:** Hold a seat, close/refresh the page, then inspect the network/database. `sendBeacon` is POST and its JSON `_method` is not a form method override for the DELETE-only route.
- **Affected flow:** Any booking page refresh, tab close, browser close, or navigation away.
- **Root cause:** Client lifecycle transport does not match the API method contract.
- **Impact:** Seats remain unavailable until hold expiration, reducing inventory and creating false contention during traffic peaks.
- **Recommended fix:** Add an idempotent POST release endpoint designed for beacon use, or use a tested `fetch(..., {method:'DELETE', keepalive:true})` contract; retain server expiry as fallback.
- **Required test:** Playwright closes the page after a hold and asserts the hold becomes released within an agreed SLA.
- **Effort:** S/M
- **Dependencies/risks:** Authentication and CSRF behavior must be verified for lifecycle requests.

## FE-005 - Admin orders đang tải dữ liệu từ endpoint của người dùng hiện tại

- **Severity:** High
- **Confidence:** High
- **Category:** Functional correctness / admin operations
- **Evidence:**
  - Filters are built for admin use, but request URL is `/orders/user/me`: `public/js/admin/pages/orders.js:139`, `public/js/admin/pages/orders.js:159`.
  - Source explicitly warns that the admin endpoint does not exist: `public/js/admin/pages/orders.js:160`.
  - API route returns only the authenticated user's orders: `routes/api.php:106`.
- **Reproduction:** Open `/admin/orders`; compare displayed rows/counts with database orders from other users.
- **Affected flow:** Admin order search, status filtering, customer support, reconciliation.
- **Root cause:** UI shipped before its required admin API contract.
- **Impact:** Admin cannot reliably view or manage business-wide orders.
- **Recommended fix:** Define paginated authorized admin order endpoint/resource; point UI to it; remove fallback and warning.
- **Required test:** Seed multiple users/orders and assert admin filters/search/counts include all authorized records.
- **Effort:** M
- **Dependencies/risks:** Requires backend query bounds, authorization, and response schema.

## FE-006 - Ticket scanner chưa thực hiện được chức năng quét/xác thực

- **Severity:** High
- **Confidence:** High
- **Category:** Functional correctness / operations
- **Evidence:**
  - Scanner posts to `/api/admin/tickets/verify`: `public/js/admin/ticket-scanner.js:133`.
  - Actual route is `/api/v1/admin/tickets/verify`: `routes/api.php:256`.
  - Scanner reads `localStorage.access_token`: `public/js/admin/ticket-scanner.js:138`, while auth uses HttpOnly cookies and no code writes this key.
  - Camera loop captures frames but contains no decoder: `public/js/admin/ticket-scanner.js:109`.
- **Reproduction:** Enter a valid ticket manually or select camera mode. Manual mode targets a missing route; camera mode never decodes a frame.
- **Affected flow:** Theater admission and ticket validation.
- **Root cause:** Placeholder implementation and auth/API contract drift.
- **Impact:** Staff cannot depend on the scanner at the gate.
- **Recommended fix:** Use shared `apiClient`; import the installed `jsqr` package through the real build pipeline; debounce duplicate scans; stop camera after a successful decode; escape result data.
- **Required test:** Manual valid/invalid/used ticket tests plus a deterministic QR image decode test.
- **Effort:** M
- **Dependencies/risks:** Physical camera permissions still require device testing.

## FE-007 - Browser release gate không bao phủ các luồng release-blocking

- **Severity:** High
- **Confidence:** High
- **Category:** Testing / release readiness
- **Evidence:**
  - Smoke suite covers only five public paths: `scripts/release-browser-smoke.mjs:18`.
  - It does not cover authentication, booking, payment, profile, admin, WebSocket, or scanner.
  - Browser HTTP 4xx responses are warnings rather than failures: `scripts/release-browser-smoke.mjs:143`.
  - Current run printed “Browser smoke passed” but the npm command did not terminate and timed out during shutdown.
- **Reproduction:** Run `npm run test:browser:smoke`; observe public-only coverage and process lifecycle behavior.
- **Affected flow:** Release confidence across all critical frontend functions.
- **Root cause:** Smoke test checks page presence rather than business outcomes.
- **Impact:** Critical frontend bugs remain undetected while tracker reports browser verification complete.
- **Recommended fix:** Split smoke and E2E suites; fail on unexpected console/page/HTTP errors; add authenticated fixtures and deterministic gateway/WebSocket mocks; repair server teardown.
- **Required test:** CI must exercise register/login, hold conflict, retry-safe checkout, callback state, profile order view, admin orders, and ticket verification.
- **Effort:** L
- **Dependencies/risks:** Requires stable test data and mocked external services.

## FE-008 - Request layer bị phân mảnh, thiếu timeout/abort và chống stale response

- **Severity:** Medium
- **Confidence:** High
- **Category:** Architecture / reliability
- **Evidence:**
  - Shared `ApiClient`: `public/js/core/api-client.js:106`.
  - Separate admin wrapper: `public/js/admin/admin-core.js:11`.
  - Admin orders defines another wrapper: `public/js/admin/pages/orders.js:494`.
  - Multiple public/admin pages call raw `fetch()` directly.
  - No `AbortController` usage exists under `public/js` or `resources/js`.
  - Theater page discards a new filter request while another is running: `public/js/users/pages/theaters.js:67`.
- **Reproduction:** Throttle a list request, change the filter/search while it is pending, and observe stale or ignored UI state. Stall payment API and observe indefinite loading.
- **Affected flow:** Lists, filters, auth refresh, booking polling, payment, and admin CRUD.
- **Root cause:** No single transport policy or request lifecycle abstraction.
- **Impact:** Hanging UI, stale data, inconsistent 401/403/error behavior, and difficult testing.
- **Recommended fix:** Standardize one client with timeout, abort, request IDs, latest-wins semantics, refresh coordination, and normalized errors.
- **Required test:** Slow/out-of-order request tests and abort-on-navigation tests.
- **Effort:** M/L
- **Dependencies/risks:** Migrate incrementally to avoid changing every page at once.

## FE-009 - Auth validation errors không map đúng vào field

- **Severity:** Medium
- **Confidence:** High
- **Category:** UX / API contract
- **Evidence:**
  - API client stores structured errors in `error.errors`: `public/js/core/api-client.js:100`.
  - Auth handler ignores that property and searches strings for “validation/errors”: `public/js/users/auth.js:328`.
  - It then attempts `JSON.parse(error.message)`: `public/js/users/auth.js:331`.
- **Reproduction:** Submit registration with an email already used or invalid password; field-level feedback is not reliably attached to the correct field.
- **Affected flow:** Login and registration.
- **Root cause:** Auth code still expects an older error format.
- **Impact:** Users receive a generic alert and cannot easily identify fields requiring correction.
- **Recommended fix:** Iterate `error.errors` directly and map API field names to DOM IDs; use general alert only for non-validation failures.
- **Required test:** Browser tests for multiple simultaneous validation errors.
- **Effort:** S
- **Dependencies/risks:** Define one field-name mapping convention.

## FE-010 - Public UI hiển thị lựa chọn và capability không phản ánh hành vi thật

- **Severity:** Medium
- **Confidence:** High
- **Category:** Product correctness / UX
- **Evidence:**
  - Home reads movie, date, and cinema: `public/js/users/pages/home.js:172`.
  - Navigation uses only movie and ignores date/cinema: `public/js/users/pages/home.js:185`.
  - Source contains TODO for loading showtimes: `public/js/users/pages/home.js:301`.
  - Theater page fabricates IMAX/Gold Class/Dolby badges from theater ID: `public/js/users/pages/theaters.js:145`.
- **Reproduction:** Select a date and cinema on home, click Find Seats, and observe that both selections are discarded. View a theater with no capability data and observe pseudo-random badges.
- **Affected flow:** Discovery and quick booking.
- **Root cause:** Mock visual behavior remained in production code.
- **Impact:** Misleading customer expectations and incorrect booking context.
- **Recommended fix:** Either implement the complete query contract or remove/disable unsupported controls; only display capability data from authoritative API fields.
- **Required test:** Selected movie/date/cinema must constrain the resulting showtimes; no badge without source data.
- **Effort:** M
- **Dependencies/risks:** Needs product decision on quick-booking destination.

## FE-011 - Keyboard accessibility blocks key booking interactions

- **Severity:** Medium
- **Confidence:** High
- **Category:** Accessibility
- **Evidence:**
  - Home custom select trigger is a plain `div`: `resources/views/users/home.blade.php:58`.
  - Browser probe found all three triggers with `tabIndex: -1`, no role, and no `aria-expanded`.
  - Seats receive `role=button` and `tabindex=0`: `public/js/users/pages/booking.js:1037`.
  - Only click is handled; no Enter/Space key handler exists: `public/js/users/pages/booking.js:1036`.
- **Reproduction:** Navigate only with Tab/Enter/Space. Home selects cannot be focused; booking seats can be focused but not activated.
- **Affected flow:** Quick booking and seat selection.
- **Root cause:** Visual div controls without native semantics or complete keyboard behavior.
- **Impact:** Keyboard and assistive-technology users cannot complete the primary transaction.
- **Recommended fix:** Prefer native `select`/`button`; otherwise implement combobox/listbox semantics, roving focus, Escape/arrow handling, `aria-expanded`, and Enter/Space activation for seats.
- **Required test:** Playwright keyboard-only booking path and automated axe checks.
- **Effort:** M
- **Dependencies/risks:** Custom-select rewrite may affect styling.

## FE-012 - Asset delivery bypasses Vite and disables effective browser caching

- **Severity:** Medium
- **Confidence:** High
- **Category:** Performance / supply chain
- **Evidence:**
  - 81 Blade asset references use `?v={{ time() }}`, including booking/admin entry assets.
  - Vite is configured at `vite.config.js:5`, and `npm run build` creates hashed assets, but layouts do not use `@vite`.
  - Bootstrap, Echo/Pusher, ApexCharts, icons, and fonts are loaded from CDN without SRI, e.g. `resources/views/layouts/app.blade.php:81` and `resources/views/layouts/admin.blade.php:22`.
  - CSP permits `'unsafe-inline'` and `'unsafe-eval'`: `app/Http/Middleware/SecurityHeaders.php:33`.
- **Reproduction:** Reload an admin page and compare asset URLs; the timestamp changes on every HTML render, forcing cache misses.
- **Affected flow:** All admin pages and booking/profile assets.
- **Root cause:** Transitional asset strategy was not completed.
- **Impact:** Higher latency/bandwidth, weak cache predictability, no bundle-level tree shaking, and broader third-party compromise exposure.
- **Recommended fix:** Move real entry points into Vite, use manifest hashes, remove timestamp cache busting, pin/self-host or add integrity metadata, then remove unsafe CSP directives.
- **Required test:** Build manifest referenced by Blade; repeat loads return cache hits; CSP regression test blocks inline handlers/eval.
- **Effort:** L
- **Dependencies/risks:** Must migrate inline scripts and global dependencies in stages.

## FE-013 - Page modules are oversized global controllers with duplicated policies

- **Severity:** Medium
- **Confidence:** High
- **Category:** Maintainability / testability
- **Evidence:**
  - `booking.js` is approximately 86 KB and combines all booking responsibilities.
  - `profile.js` is approximately 58 KB; `showtimes.js` approximately 38 KB.
  - Globals include `window.authManager`, `window.apiClient`, `window.AdminCore`, and `window.bookingManager`.
  - Security escaping, request behavior, toast behavior, and pagination are reimplemented in page files.
- **Reproduction:** Trace a simple payment error or XSS rule; behavior crosses globals and page-local helpers with no unit seam.
- **Affected flow:** Most user/admin features.
- **Root cause:** Incremental page scripting without module ownership boundaries.
- **Impact:** Regression risk, slow review, poor unit-testability, and inconsistent behavior.
- **Recommended fix:** Extract domain-neutral modules: transport, safe renderer, async state, booking session, checkout intent, realtime adapter, and admin table primitives.
- **Required test:** Unit tests for extracted state/transport/render functions before page migration.
- **Effort:** L
- **Dependencies/risks:** Refactor only after Critical/High behavioral fixes are locked by tests.

## FE-014 - Dead/unfinished frontend paths remain shipped

- **Severity:** Low
- **Confidence:** High
- **Category:** Code health
- **Evidence:**
  - `public/js/users/pages/payment.js:129` calls an obsolete order-payment endpoint and expects `payment_url`.
  - Web payment route now redirects home for compatibility: `app/Http/Controllers/PaymentController.php:29`.
  - Google login is presented as a placeholder: `public/js/users/auth.js:213`.
- **Reproduction:** Inspect the route and shipped asset behavior.
- **Affected flow:** Maintenance and feature discoverability.
- **Root cause:** Legacy UI was disabled at the controller but not removed from frontend assets/views.
- **Impact:** Confusion, drift, and accidental reactivation of broken contracts.
- **Recommended fix:** Remove dead payment page assets/view or restore through one supported contract; hide unfinished OAuth action behind a feature flag.
- **Required test:** Route/asset inventory test or explicit feature-flag behavior.
- **Effort:** S
- **Dependencies/risks:** Confirm no external links depend on legacy payment route.

## D. Browser Test Results

### Commands and outcomes

| Check | Result | Notes |
|---|---|---|
| `npm run build` | Pass | Vite 7.3.6, 61 modules transformed |
| `npm audit --omit=dev` | Pass | 0 vulnerabilities |
| `composer audit --locked` | Pass | No advisories; local Composer cache was read-only |
| `php artisan test` | Pass | 181 tests, 1305 assertions |
| `npm run test:browser:smoke` | Functional checks pass / runner fail | Printed pass for 5 pages and 4 endpoints, then command timed out during shutdown |
| Isolated Edge/Playwright XSS probe | Fail | Mocked theater name executed JavaScript |
| Desktop keyboard probe | Fail | 3 custom-select triggers are not focusable and have no ARIA state |
| Mobile 390x844 public probe | Pass with limits | `/`, `/movies`, `/theaters`, `/prices` returned 200; no measured horizontal overflow |

### Browser scope

- Browser engine: Microsoft Edge/Chromium through Playwright.
- Desktop viewport: 1440x900.
- Mobile viewport: 390x844 with touch/mobile emulation.
- Public pages tested: home, movies, theaters, prices, login redirect.
- Standard smoke did not test authenticated/user/admin/payment outcomes.

### Console/network observations

- Standard smoke did not report an application page exception before completing its assertions.
- The isolated security probe intentionally blocked third-party CDN resources; resulting CDN and `bootstrap is not defined` messages are test-harness artifacts, not product findings.
- Existing smoke treats HTTP 4xx as warnings, so a broken client endpoint can still leave the suite green.

## E. Remediation Roadmap

### Phase 1 - Critical security and payment invariants

- Fix FE-001 and complete the sink inventory from FE-003.
- Persist checkout idempotency intent and add one-active-payment-per-hold server invariant.
- Add exploit/retry regression tests before any refactor.
- **Exit:** No dynamic payload executes script; response-loss retry creates exactly one order/payment/link.

### Phase 2 - Booking and operational correctness

- Fix lifecycle seat release.
- Implement admin order contract.
- Complete manual and camera ticket scanner.
- Correct quick-booking and theater capability behavior.
- **Exit:** Browser tests prove seat release, admin-wide order access, scanner verification, and authoritative booking filters.

### Phase 3 - Accessibility and UX reliability

- Make custom selects and seats keyboard-operable.
- Map structured validation errors to fields.
- Add request cancellation, timeout, latest-wins list behavior, and actionable network states.
- **Exit:** Keyboard-only user can complete booking; slow/offline tests reach deterministic recovery states.

### Phase 4 - Architecture, performance, and release gate

- Consolidate request/render/security primitives.
- Migrate runtime entries to Vite and remove timestamp cache busting.
- Tighten CSP and third-party asset policy.
- Expand browser suite and fix process teardown.
- **Exit:** All Critical/High tests pass in CI across desktop/mobile Chromium and at least Firefox; Safari/WebKit tracked or explicitly accepted.

## F. Quick Wins

- Change auth error handling to consume `error.errors` directly.
- Remove fabricated theater badges immediately.
- Disable or hide camera scanning until decode works.
- Remove `?v={{ time() }}` after each entry is migrated to a manifest hash.
- Fail browser smoke on page errors and unexpected 4xx responses.
- Add Enter/Space handlers to seat controls while the broader accessibility rewrite is planned.
- Remove unused legacy payment JS/view after route-dependency confirmation.

## G. Test Matrix

| Area | Guest | Auth user | Admin | Desktop | Mobile | Slow/offline | Multi-tab/concurrency | Browsers |
|---|---:|---:|---:|---:|---:|---:|---:|---|
| Home/catalog | Required | Required | N/A | Required | Required | Required | N/A | Chromium, Firefox, WebKit |
| Auth/register | Required | Refresh/logout | N/A | Required | Required | Required | Refresh race | Chromium, Firefox, WebKit |
| Seat selection | Redirect/auth prompt | Required | N/A | Required | Required | Required | Hold conflict/expiry | Chromium, Firefox, WebKit |
| Voucher/points | N/A | Required | N/A | Required | Required | Required | Payload change | Chromium, Firefox |
| Checkout | N/A | Required | N/A | Required | Required | Response loss/retry | Double click/two tabs | Chromium, Firefox, WebKit |
| PayOS return | N/A | Owner only | N/A | Required | Required | Callback delay | Webhook race/replay | Chromium, mobile WebKit |
| Profile/orders | N/A | Own data only | N/A | Required | Required | Partial failures | Refresh during sync | Chromium, Firefox |
| Admin orders | N/A | Forbidden | Required | Required | Responsive check | Required | Concurrent updates | Chromium, Firefox |
| Ticket scanner | N/A | Forbidden | Required | Manual | Camera/manual | Camera denied | Duplicate scan | Chromium desktop/mobile |
| Security sinks | Required | Required | Required | Required | Required | API error bodies | Stored payloads | Chromium + Firefox |
| Accessibility | Required | Required | Required | Keyboard/SR | Touch/SR | Loading announcements | N/A | Chromium + WebKit |

## Final Assessment

The backend remediation and test suite provide a stronger foundation, but frontend release readiness is overstated. The confirmed admin XSS path and checkout-operation idempotency gap are release blockers. The system should remain **not production-ready** until FE-001 and FE-002 are fixed and verified with browser-level regression tests; FE-004 through FE-007 should also be closed before operational launch.
