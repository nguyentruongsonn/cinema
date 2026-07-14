====================================================

File:
app/Http/Requests/Admin/StoreSeatLayoutTemplateRequest.php

Overall Score:
5.4/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Restricts `seat_matrix` to known values.
- Validates row counts as non-negative integers.
- Adds cross-field validation so total configured rows do not exceed matrix rows.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Authorization

Location:
app/Http/Requests/Admin/StoreSeatLayoutTemplateRequest.php:9-12

Problem

`authorize()` returns `true`.

Why this matters

Seat layout templates control capacity and seat typing. Unauthorized changes can affect revenue and booking correctness.

How to fix

Use a policy/gate.

### Issue #2

Severity:
Medium

Category:
Business Logic / Validation

Location:
app/Http/Requests/Admin/StoreSeatLayoutTemplateRequest.php:19-22,51-62

Problem

The validator only checks that row total is not greater than max rows. It allows total rows below max rows.

Why this matters

A `12x12` matrix with only a few typed rows leaves undefined rows unless downstream code has a guaranteed default. This can corrupt generated seats or pricing.

How to fix

Require total rows to equal the matrix row count, or explicitly define default behavior for remaining rows.

### Issue #3

Severity:
Medium

Category:
Validation

Location:
app/Http/Requests/Admin/StoreSeatLayoutTemplateRequest.php:17

Problem

`template_name` has no uniqueness rule.

Why this matters

Duplicate layout template names make admin selection unsafe and can lead to applying the wrong layout.

How to fix

Add normalized unique validation plus a database unique index.

### Issue #4

Severity:
Medium

Category:
Maintainability / Duplicate Code

Location:
app/Http/Requests/Admin/StoreSeatLayoutTemplateRequest.php:39-65

Problem

The same matrix row validation appears in the update request.

Why this matters

Duplicated validation rules drift over time.

How to fix

Extract shared rules/after-validation logic to a reusable rule object or base request method.

### Issue #5

Severity:
Low

Category:
Readability / Encoding

Location:
app/Http/Requests/Admin/StoreSeatLayoutTemplateRequest.php:30-35,60

Problem

Messages are mojibake/corrupted.

Why this matters

Validation errors are unreadable in production.

How to fix

Repair encoding and move localized text to language files.

----------------------------------------------------

Summary

The request includes useful cross-field validation, but it is incomplete for layout correctness and lacks authorization and unique template identity.
