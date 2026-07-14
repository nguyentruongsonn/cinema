====================================================

File:
app/Http/Requests/Admin/UpdateSeatLayoutTemplateRequest.php

Overall Score:
5.2/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Restricts seat matrix to supported values.
- Validates row counts and total rows.
- Uses cross-field after validation.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Authorization

Location:
app/Http/Requests/Admin/UpdateSeatLayoutTemplateRequest.php:9-12

Problem

`authorize()` always returns `true`.

Why this matters

Updating seat layout templates affects capacity and booking integrity.

How to fix

Use a policy/gate.

### Issue #2

Severity:
High

Category:
Business Logic / Booking Correctness

Location:
app/Http/Requests/Admin/UpdateSeatLayoutTemplateRequest.php:17-23

Problem

The request permits changing `seat_matrix` and row composition without checking whether screens or future showtimes already use the template.

Why this matters

Changing a referenced layout can corrupt screen seat maps and future booking snapshots.

How to fix

Make layout templates immutable after use, or version templates and migrate explicitly.

### Issue #3

Severity:
Medium

Category:
Validation

Location:
app/Http/Requests/Admin/UpdateSeatLayoutTemplateRequest.php:57-61

Problem

The validator only rejects total rows greater than matrix rows; it allows fewer typed rows than matrix rows.

Why this matters

Undefined rows can produce inconsistent generated layouts.

How to fix

Require total rows to equal matrix rows or explicitly validate default row behavior.

### Issue #4

Severity:
Medium

Category:
Duplicate Code

Location:
app/Http/Requests/Admin/UpdateSeatLayoutTemplateRequest.php:39-65

Problem

The same validation logic exists in the store request.

Why this matters

Duplicated layout validation can drift.

How to fix

Extract a custom validation rule or shared method.

### Issue #5

Severity:
Low

Category:
Encoding

Location:
app/Http/Requests/Admin/UpdateSeatLayoutTemplateRequest.php:30-35,60

Problem

Messages are corrupted mojibake.

Why this matters

Unreadable production validation errors.

How to fix

Repair UTF-8 localization.

----------------------------------------------------

Summary

The request has helpful matrix validation but is unsafe for updates because seat layout templates should not be freely mutated once referenced.
