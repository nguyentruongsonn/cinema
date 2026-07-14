====================================================

File:
app/Http/Requests/Admin/StatFilterRequest.php

Overall Score:
7.0/10

Decision:
APPROVE WITH COMMENTS

----------------------------------------------------

Strengths

- Authorization is not blanket; it checks authenticated admin/super-admin roles.
- Date inputs are constrained to `Y-m-d`.
- `end_date` is validated relative to `start_date`.

----------------------------------------------------

Issues

### Issue #1

Severity:
Medium

Category:
Authorization / Maintainability

Location:
app/Http/Requests/Admin/StatFilterRequest.php:15-18

Problem

Authorization is implemented by calling `method_exists($user, 'hasAnyRole')` and role names directly in the request.

Why this matters

This couples every request to a specific user method and hard-coded role strings. Policy/gate authorization would be more testable and consistent.

How to fix

Move this to a policy/gate such as `viewAnalytics`.

### Issue #2

Severity:
Medium

Category:
Performance / Validation

Location:
app/Http/Requests/Admin/StatFilterRequest.php:23-26

Problem

The request does not bound the maximum reporting date range.

Why this matters

Analytics endpoints can become expensive. A valid request can ask for years of data and cause large scans or slow aggregation.

How to fix

Add a maximum range rule in `withValidator`, for example 31/90/365 days depending on endpoint needs.

### Issue #3

Severity:
Low

Category:
Validation

Location:
app/Http/Requests/Admin/StatFilterRequest.php:24-25

Problem

Rules combine `date` and `date_format:Y-m-d`.

Why this matters

This is redundant and can produce confusing validation behavior/messages. `date_format` is sufficient for strict input format.

How to fix

Use `date_format:Y-m-d` and convert to immutable date boundaries downstream.

----------------------------------------------------

Summary

This is one of the better request classes because it includes authorization, but range bounding and policy-based authorization should be added before production analytics exposure.