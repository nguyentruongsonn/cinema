====================================================

File:
app/Http/Requests/Admin/StoreScreenRequest.php

Overall Score:
5.2/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Requires core screen relationships.
- Validates foreign key existence.
- Has basic type limits for name/code.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Authorization

Location:
app/Http/Requests/Admin/StoreScreenRequest.php:9-12

Problem

`authorize()` always returns `true`.

Why this matters

Screen creation controls seating capacity and showtime assignment. Missing request-level authorization is a privileged-operation risk.

How to fix

Use a Screen policy/gate.

### Issue #2

Severity:
High

Category:
Business Logic / Database Correctness

Location:
app/Http/Requests/Admin/StoreScreenRequest.php:17-22

Problem

The request validates that `theater_id`, `format_id`, `sound_id`, and `seat_layout_template_id` exist independently, but not that the selected combination is valid/active.

Why this matters

A screen can be created using inactive/deleted catalog entries or incompatible layout/format data, causing invalid showtime configuration.

How to fix

Use `Rule::exists()->where(...)` for active records and service-level compatibility validation.

### Issue #3

Severity:
Medium

Category:
Database Correctness

Location:
app/Http/Requests/Admin/StoreScreenRequest.php:19

Problem

`code` is not unique within a theater.

Why this matters

Duplicate screen codes make operations, reporting, and integrations ambiguous.

How to fix

Enforce unique `(theater_id, code)` in validation and database.

### Issue #4

Severity:
Low

Category:
Readability / Encoding

Location:
app/Http/Requests/Admin/StoreScreenRequest.php:30-39

Problem

Validation messages are corrupted.

Why this matters

Validation responses are not production quality for Vietnamese users/admins.

How to fix

Repair UTF-8 encoding/localization.

----------------------------------------------------

Summary

The request validates existence but not operational correctness. It needs authorization, active-record constraints, and screen code uniqueness.
