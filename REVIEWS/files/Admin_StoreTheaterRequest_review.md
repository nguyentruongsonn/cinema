====================================================

File:
app/Http/Requests/Admin/StoreTheaterRequest.php

Overall Score:
5.3/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Requires theater name, branch, and address.
- Validates branch existence and email format.
- Uses a dedicated admin request class.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Authorization

Location:
app/Http/Requests/Admin/StoreTheaterRequest.php:9-12

Problem

`authorize()` returns `true`.

Why this matters

Creating theaters is a privileged operation affecting screens, showtimes, and revenue.

How to fix

Use a Theater policy/gate.

### Issue #2

Severity:
Medium

Category:
API Consistency

Location:
app/Http/Requests/Admin/StoreTheaterRequest.php:22

Problem

Admin theater request accepts nullable boolean `status`, while root `StoreTheaterRequest` uses required `active,inactive` strings.

Why this matters

Inconsistent status contracts cause client and service ambiguity.

How to fix

Standardize status representation across all theater endpoints.

### Issue #3

Severity:
Medium

Category:
Database Correctness

Location:
app/Http/Requests/Admin/StoreTheaterRequest.php:17-21

Problem

No uniqueness rules exist for theater identity such as branch/name or email.

Why this matters

Duplicate theater records can break reporting and admin workflows.

How to fix

Add business-specific uniqueness validation backed by database constraints.

### Issue #4

Severity:
Medium

Category:
Validation

Location:
app/Http/Requests/Admin/StoreTheaterRequest.php:20

Problem

Phone validation is only `string|max:20`.

Why this matters

Invalid phone values will persist.

How to fix

Normalize and validate with a strict phone rule.

### Issue #5

Severity:
Low

Category:
Readability / Encoding

Location:
app/Http/Requests/Admin/StoreTheaterRequest.php:29-33

Problem

Messages are corrupted mojibake.

Why this matters

Validation output is unreadable.

How to fix

Repair UTF-8 encoding/localization.

----------------------------------------------------

Summary

The admin theater create request validates shape but not authorization, uniqueness, or consistent lifecycle semantics.
