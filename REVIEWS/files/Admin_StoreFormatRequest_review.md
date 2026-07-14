====================================================

File:
app/Http/Requests/Admin/StoreFormatRequest.php

Overall Score:
6.0/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Enforces required name and surcharge.
- Validates non-negative numeric surcharge.
- Checks format name uniqueness.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Authorization

Location:
app/Http/Requests/Admin/StoreFormatRequest.php:9-12

Problem

`authorize()` always returns `true`.

Why this matters

Formats affect ticket pricing through `surcharge`; unauthorized creation can alter revenue.

How to fix

Use a policy/gate such as `create Format`.

### Issue #2

Severity:
Medium

Category:
Money / Validation

Location:
app/Http/Requests/Admin/StoreFormatRequest.php:18

Problem

`surcharge` is `numeric|min:0` with no decimal scale or upper bound.

Why this matters

Money-like fields require deterministic precision and sane bounds. Arbitrary numeric input can introduce rounding and pricing errors.

How to fix

Use integer minor units or `decimal:0,2` with a maximum and database decimal constraints.

### Issue #3

Severity:
Low

Category:
Readability / Encoding

Location:
app/Http/Requests/Admin/StoreFormatRequest.php:25-29

Problem

Validation messages are corrupted mojibake.

Why this matters

Clients/admins receive unreadable validation errors.

How to fix

Repair file encoding and localization files.

----------------------------------------------------

Summary

Validation is better than most admin requests due to uniqueness and non-negative surcharge, but money precision and authorization are not production-grade.
