====================================================

File:
app/Http/Requests/Admin/UpdateBranchRequest.php

Overall Score:
5.3/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Uses a dedicated FormRequest.
- Validates name and active flag.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Authorization

Location:
app/Http/Requests/Admin/UpdateBranchRequest.php:10-13

Problem

`authorize()` always returns `true`.

Why this matters

Updating branch active state can affect theaters and availability. Middleware-only authorization is not sufficient for production safety.

How to fix

Use a Branch policy/gate.

### Issue #2

Severity:
Medium

Category:
Clean Code

Location:
app/Http/Requests/Admin/UpdateBranchRequest.php:6

Problem

`use Illuminate\Validation\Rule;` is imported but unused.

Why this matters

Unused imports add noise and indicate incomplete validation work.

How to fix

Remove the import or use `Rule` for uniqueness validation.

### Issue #3

Severity:
Medium

Category:
Database Correctness

Location:
app/Http/Requests/Admin/UpdateBranchRequest.php:18

Problem

Branch name has no uniqueness rule for update.

Why this matters

An update can create duplicate branch names.

How to fix

Use `Rule::unique('branches', 'name')->ignore($this->route('branch'))` and a database unique index.

### Issue #4

Severity:
Medium

Category:
Business Logic

Location:
app/Http/Requests/Admin/UpdateBranchRequest.php:19

Problem

`is_active` can be set to false without validating whether active theaters/showtimes depend on the branch.

Why this matters

Deactivating a branch with active schedules can break booking visibility and fulfillment.

How to fix

Enforce lifecycle constraints in a policy/service and surface validation errors.

### Issue #5

Severity:
Low

Category:
Readability / Encoding

Location:
app/Http/Requests/Admin/UpdateBranchRequest.php:26

Problem

Message text is corrupted.

Why this matters

Unreadable validation error.

How to fix

Repair encoding/localization.

----------------------------------------------------

Summary

The request is minimal and misses authorization, duplicate prevention, and operational lifecycle checks.
