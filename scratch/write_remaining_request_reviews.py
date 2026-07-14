from pathlib import Path

reviews = {
"Admin_StoreBranchRequest_review.md": """====================================================

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
""",

"Admin_StoreFormatRequest_review.md": """====================================================

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
""",

"Admin_StoreScreenRequest_review.md": """====================================================

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
""",

"Admin_StoreSeatLayoutTemplateRequest_review.md": """====================================================

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
""",

"Admin_StoreSoundRequest_review.md": """====================================================

File:
app/Http/Requests/Admin/StoreSoundRequest.php

Overall Score:
6.1/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Requires sound name.
- Checks uniqueness.
- Keeps request small.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Authorization

Location:
app/Http/Requests/Admin/StoreSoundRequest.php:9-12

Problem

`authorize()` always returns `true`.

Why this matters

Sound formats are admin-controlled catalog data used by screens/showtimes. The request is unsafe without route middleware.

How to fix

Authorize with a policy/gate.

### Issue #2

Severity:
Medium

Category:
Validation / Database Correctness

Location:
app/Http/Requests/Admin/StoreSoundRequest.php:17

Problem

Uniqueness is case/collation dependent and no normalization is applied.

Why this matters

Depending on database collation, `Dolby Atmos` and `dolby atmos` may be duplicated.

How to fix

Normalize names before validation/persistence or enforce a generated normalized unique column.

### Issue #3

Severity:
Low

Category:
Readability / Encoding

Location:
app/Http/Requests/Admin/StoreSoundRequest.php:24-25

Problem

Messages are corrupted mojibake.

Why this matters

Unreadable validation responses are not production quality.

How to fix

Repair UTF-8 encoding/localization.

----------------------------------------------------

Summary

Basic validation is present, but admin authorization and normalized uniqueness are missing.
""",

"Admin_StoreTheaterRequest_review.md": """====================================================

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
""",

"Admin_UpdateBranchRequest_review.md": """====================================================

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
""",

"Admin_UpdateFormatRequest_review.md": """====================================================

File:
app/Http/Requests/Admin/UpdateFormatRequest.php

Overall Score:
5.8/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Validates name uniqueness on update.
- Requires non-negative surcharge.
- Keeps validation focused.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Authorization

Location:
app/Http/Requests/Admin/UpdateFormatRequest.php:9-12

Problem

`authorize()` returns `true`.

Why this matters

Updating format surcharge directly affects ticket pricing and revenue.

How to fix

Use a Format policy/gate.

### Issue #2

Severity:
Medium

Category:
Validation / Correctness

Location:
app/Http/Requests/Admin/UpdateFormatRequest.php:16-18

Problem

`$this->route('format')?->id` assumes route model binding. If the route parameter is an id string, `$id` becomes null and the unique rule becomes `unique:formats,name,`.

Why this matters

The current record may not be ignored, causing false validation failures or inconsistent behavior across routes.

How to fix

Resolve route parameter robustly and prefer `Rule::unique()->ignore($format)`.

### Issue #3

Severity:
Medium

Category:
Money

Location:
app/Http/Requests/Admin/UpdateFormatRequest.php:19

Problem

`surcharge` has no precision or max bound.

Why this matters

Pricing fields require strict precision and bounded values to avoid accidental extreme surcharges.

How to fix

Use integer minor units or decimal validation with maximum.

### Issue #4

Severity:
Medium

Category:
Business Logic

Location:
app/Http/Requests/Admin/UpdateFormatRequest.php:19

Problem

Existing formats can have surcharge changed without validating active/future showtimes using the format.

Why this matters

Changing a referenced pricing component can alter already published prices.

How to fix

Use versioned pricing or block changes when referenced by active schedules.

### Issue #5

Severity:
Low

Category:
Encoding

Location:
app/Http/Requests/Admin/UpdateFormatRequest.php:26-30

Problem

Messages are mojibake/corrupted.

Why this matters

Unreadable validation response.

How to fix

Repair localization encoding.

----------------------------------------------------

Summary

The request has useful basic validation but is unsafe for pricing changes without stronger authorization, route-safe uniqueness, and lifecycle constraints.
""",

"Admin_UpdateScreenRequest_review.md": """====================================================

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
""",

"Admin_UpdateSeatLayoutTemplateRequest_review.md": """====================================================

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
""",

"Admin_UpdateSoundRequest_review.md": """====================================================

File:
app/Http/Requests/Admin/UpdateSoundRequest.php

Overall Score:
5.9/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Requires sound name.
- Attempts uniqueness ignoring current route model.
- Small and focused.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Authorization

Location:
app/Http/Requests/Admin/UpdateSoundRequest.php:9-12

Problem

`authorize()` returns `true`.

Why this matters

Sound catalog mutation is privileged configuration.

How to fix

Use a Sound policy/gate.

### Issue #2

Severity:
Medium

Category:
Validation / Correctness

Location:
app/Http/Requests/Admin/UpdateSoundRequest.php:16-18

Problem

The unique ignore id assumes `$this->route('sound')` is a model. If it is an id string, `$id` is null.

Why this matters

Validation may fail against the current record or behave inconsistently across route definitions.

How to fix

Use `Rule::unique('sounds', 'name')->ignore($this->route('sound'))` with proper route-model binding or explicit id handling.

### Issue #3

Severity:
Medium

Category:
Database Correctness

Location:
app/Http/Requests/Admin/UpdateSoundRequest.php:18

Problem

Name uniqueness is not normalized.

Why this matters

Case/space variants can create duplicates depending on database collation.

How to fix

Normalize names and enforce normalized uniqueness.

### Issue #4

Severity:
Low

Category:
Encoding

Location:
app/Http/Requests/Admin/UpdateSoundRequest.php:25-26

Problem

Messages are corrupted.

Why this matters

Unreadable validation output.

How to fix

Repair localization encoding.

----------------------------------------------------

Summary

Basic validation exists, but authorization and route-safe normalized uniqueness need improvement.
""",

"Admin_UpdateTheaterRequest_review.md": """====================================================

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
""",
}

out = Path("REVIEWS/files")
out.mkdir(parents=True, exist_ok=True)
for filename, content in reviews.items():
    (out / filename).write_text(content.rstrip() + "\n", encoding="utf-8")
print(f"Wrote {len(reviews)} remaining admin request review files.")
