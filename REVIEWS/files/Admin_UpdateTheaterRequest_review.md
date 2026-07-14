====================================================

File:
app/Http/Requests/Admin/UpdateTheaterRequest.php

Overall Score:
5.2/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Validates core theater fields.
- Requires branch and address.
- Validates email format.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Authorization

Location:
app/Http/Requests/Admin/UpdateTheaterRequest.php:9-12

Problem

`authorize()` always returns `true`.

Why this matters

Theater updates affect operational availability and booking context.

How to fix

Use a Theater policy/gate.

### Issue #2

Severity:
Medium

Category:
Business Logic

Location:
app/Http/Requests/Admin/UpdateTheaterRequest.php:17-22

Problem

The request allows branch/status/address changes without validating active screens, showtimes, or sold tickets.

Why this matters

Changing or disabling a theater with future bookings can break customer and operational workflows.

How to fix

Add service-level lifecycle validation and block unsafe transitions.

### Issue #3

Severity:
Medium

Category:
API Consistency

Location:
app/Http/Requests/Admin/UpdateTheaterRequest.php:22

Problem

Admin request uses nullable boolean `status`, while root theater requests use `active,inactive` strings.

Why this matters

Inconsistent domain representation creates API inconsistency and mapping bugs.

How to fix

Standardize one status representation.

### Issue #4

Severity:
Medium

Category:
Database Correctness

Location:
app/Http/Requests/Admin/UpdateTheaterRequest.php:17-21

Problem

No uniqueness validation for theater name/email.

Why this matters

Updates can create duplicate theater identities.

How to fix

Add scoped unique rules and database constraints.

### Issue #5

Severity:
Low

Category:
Encoding

Location:
app/Http/Requests/Admin/UpdateTheaterRequest.php:29-33

Problem

Messages are corrupted.

Why this matters

Unreadable validation errors.

How to fix

Repair UTF-8 localization.

----------------------------------------------------

Summary

The request validates basic fields but does not protect critical theater lifecycle updates or enforce consistent domain rules.
