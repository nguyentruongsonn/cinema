====================================================

File:
app/Http/Requests/Admin/UpdateScreenRequest.php

Overall Score:
5.0/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Validates required screen dependencies.
- Applies basic string length limits.
- Uses FormRequest.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Authorization

Location:
app/Http/Requests/Admin/UpdateScreenRequest.php:9-12

Problem

`authorize()` always returns `true`.

Why this matters

Screen updates can change seat layouts, formats, and showtime availability.

How to fix

Use a Screen policy/gate.

### Issue #2

Severity:
High

Category:
Business Logic / Booking Correctness

Location:
app/Http/Requests/Admin/UpdateScreenRequest.php:17-22

Problem

The request allows changing `seat_layout_template_id`, `format_id`, `sound_id`, and `theater_id` without checking active/future showtimes or sold tickets.

Why this matters

Changing screen structure or venue assignment under active schedules can corrupt booking seat maps and customer expectations.

How to fix

Block structural changes for screens with active/future showtimes or require versioned screen/layout snapshots.

### Issue #3

Severity:
Medium

Category:
Database Correctness

Location:
app/Http/Requests/Admin/UpdateScreenRequest.php:19

Problem

`code` has no uniqueness rule within theater.

Why this matters

Duplicate screen codes are operationally ambiguous.

How to fix

Validate unique `(theater_id, code)` and back with database constraint.

### Issue #4

Severity:
Medium

Category:
Validation

Location:
app/Http/Requests/Admin/UpdateScreenRequest.php:17-22

Problem

Foreign key `exists` rules do not filter active records.

Why this matters

A screen can be updated to use inactive catalog entries/templates.

How to fix

Use `Rule::exists(...)->where('status', true)` or domain-specific active columns.

### Issue #5

Severity:
Low

Category:
Encoding

Location:
app/Http/Requests/Admin/UpdateScreenRequest.php:30-39

Problem

Messages are corrupted.

Why this matters

Unreadable API/admin errors.

How to fix

Repair encoding/localization.

----------------------------------------------------

Summary

This request is not safe for production screen updates because it allows structural changes without booking/showtime lifecycle validation.
