====================================================

File:
app/Http/Requests/Admin/UpdateFormatRequest.php

Overall Score:
5.8/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Validates name uniqueness on update.
- Requires non-negative surcharge.
- Keeps validation focused.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Authorization

Location:
app/Http/Requests/Admin/UpdateFormatRequest.php:9-12

Problem

`authorize()` returns `true`.

Why this matters

Updating format surcharge directly affects ticket pricing and revenue.

How to fix

Use a Format policy/gate.

### Issue #2

Severity:
Medium

Category:
Validation / Correctness

Location:
app/Http/Requests/Admin/UpdateFormatRequest.php:16-18

Problem

`$this->route('format')?->id` assumes route model binding. If the route parameter is an id string, `$id` becomes null and the unique rule becomes `unique:formats,name,`.

Why this matters

The current record may not be ignored, causing false validation failures or inconsistent behavior across routes.

How to fix

Resolve route parameter robustly and prefer `Rule::unique()->ignore($format)`.

### Issue #3

Severity:
Medium

Category:
Money

Location:
app/Http/Requests/Admin/UpdateFormatRequest.php:19

Problem

`surcharge` has no precision or max bound.

Why this matters

Pricing fields require strict precision and bounded values to avoid accidental extreme surcharges.

How to fix

Use integer minor units or decimal validation with maximum.

### Issue #4

Severity:
Medium

Category:
Business Logic

Location:
app/Http/Requests/Admin/UpdateFormatRequest.php:19

Problem

Existing formats can have surcharge changed without validating active/future showtimes using the format.

Why this matters

Changing a referenced pricing component can alter already published prices.

How to fix

Use versioned pricing or block changes when referenced by active schedules.

### Issue #5

Severity:
Low

Category:
Encoding

Location:
app/Http/Requests/Admin/UpdateFormatRequest.php:26-30

Problem

Messages are mojibake/corrupted.

Why this matters

Unreadable validation response.

How to fix

Repair localization encoding.

----------------------------------------------------

Summary

The request has useful basic validation but is unsafe for pricing changes without stronger authorization, route-safe uniqueness, and lifecycle constraints.
