====================================================

File:
app/Http/Requests/Admin/StoreBranchRequest.php

Overall Score:
5.5/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Uses FormRequest validation.
- Validates branch name and active flag basics.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Authorization

Location:
app/Http/Requests/Admin/StoreBranchRequest.php:9-12

Problem

`authorize()` returns `true`.

Why this matters

Branch creation is administrative and affects theater organization. Middleware-only authorization is fragile.

How to fix

Authorize with a branch policy or gate.

### Issue #2

Severity:
Medium

Category:
Database Correctness

Location:
app/Http/Requests/Admin/StoreBranchRequest.php:17

Problem

Branch name has no uniqueness or normalization rule.

Why this matters

Duplicate branch names make admin selection and reporting ambiguous.

How to fix

Normalize names and enforce uniqueness with validation plus database index.

### Issue #3

Severity:
Low

Category:
Readability / Encoding

Location:
app/Http/Requests/Admin/StoreBranchRequest.php:11,25

Problem

Vietnamese text appears mojibake/corrupted in comments/messages.

Why this matters

Broken localized messages degrade API/admin UX and suggest encoding issues in source control.

How to fix

Ensure files are stored as UTF-8 and repair localized strings.

----------------------------------------------------

Summary

The request handles minimal shape validation but lacks authorization and branch identity invariants.
