# System Structure Review — Remediation Closure

**Reviewed:** 2026-07-18  
**Status:** Local repository remediation complete; external release verification remains environment-specific.

## 1. Completed Remediation

### Application boundaries

- Restored the Sound management vertical slice: model relation, policy, requests, controller actions, routes, admin UI, and feature tests.
- Split API routes into `public`, `auth`, `customer`, and `admin` domain files while preserving URIs and middleware.
- Added a route/controller reflection gate; all 176 controller actions resolve to existing methods.
- Added policies and authorization regression coverage for affected admin resources.

### Admin frontend

- Added Turbo Drive navigation for admin pages without full document reloads.
- Added page load/cleanup lifecycle handling to prevent duplicate listeners, polling, requests, camera work, and modal artifacts.
- Added account-scoped API caching, stale-request cancellation, reference-data caching, and database indexes for admin listings.
- Standardized statistics controls, skeleton styling, filter bars, and modal behavior.
- Fixed modal stacking/backdrop lock failures by moving active dialogs outside the view-transition stacking context.
- Removed per-request `time()` asset cache busting and use stable application asset versions or Vite hashes.
- Removed unused experimental admin base/page implementations.

### Repository and release gates

- Removed the public authentication configuration debug endpoint.
- Removed tracked scratch scripts and ignored `/scratch`.
- Added CI, JavaScript syntax checks, frontend security checks, repository hygiene checks, Vite build, and PHP tests.
- Added browser regressions for all 17 admin routes, modal lifecycle, request counts, and ticket scanning.
- Added database rehearsal, public browser smoke, readiness, dependency audit, and bounded load smoke commands.

## 2. Current Frontend Boundary

The Vite-owned shell lives under `resources/js` and `resources/css`. User/admin shared runtimes, booking, and profile now use hashed Vite entries. Existing admin page controllers remain page-scoped because Turbo revisits require re-evaluation; moving them mechanically into cached ES modules would change runtime semantics.

This boundary is now explicit and guarded:

- shell/navigation/scanner, booking, and profile entries are Vite-built;
- legacy page modules are syntax-checked and browser-regression tested;
- dead experimental modules are rejected by the hygiene gate;
- cache keys no longer use `time()`;
- new shared shell functionality belongs in `resources/`.

Additional admin module migration is an architectural evolution, not an open production defect. It should continue page-by-page with Turbo lifecycle tests rather than as a bulk file move.

## 3. Large Files

Large booking, profile, showtime, and payment files were reviewed. Booking product rendering and profile barcode generation were extracted into Vite modules. Remaining business-critical files are not split solely by line count because that would introduce broad behavioral risk; future decomposition must extract one responsibility at a time while preserving the existing gates.

## 4. Verification Result

- Full PHP suite: **210 tests, 1758 assertions**.
- Route inventory: **182 routes**.
- Route/controller integrity: **176 controller actions passed**.
- Frontend syntax gate: **66 JavaScript files passed**.
- Larastan level 5, scoped Pint, and ESLint: **passed**.
- OpenAPI route contract: **all API paths and methods covered**.
- Health, protected metrics, request correlation, Sentry integration, Redis queue configuration, and queue monitoring: **implemented**.
- Frontend security gate: **passed**.
- Vite production build: **passed**.
- Admin browser regression: **17 Turbo routes passed**, including modal lifecycle and request-count assertions.
- Ticket scanner browser regression: **passed**.

## 5. External Release Actions

The following cannot be completed safely from the local repository alone:

1. Run a real PayOS sandbox transaction, callback, cancellation, and signed webhook replay.
2. Run migrations, rollback rehearsal, and load tests against staging-scale data and infrastructure.
3. Connect and verify production monitoring/alert destinations.
4. Run strict Firefox browser matrix on a CI runner with working headless graphics.
5. Obtain final human release approval.

These are deployment acceptance tasks, not unfinished source-code remediation.
