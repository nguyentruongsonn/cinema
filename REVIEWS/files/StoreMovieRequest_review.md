====================================================

File:
app/Http/Requests/StoreMovieRequest.php

Overall Score:
5.7/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Uses a dedicated `FormRequest` for movie creation validation.
- Uses array rule syntax for most fields.
- Enforces uniqueness for `slug`.
- Validates `end_date` relative to `release_date`.
- Validates category IDs exist.
- Provides custom validation messages and attributes.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Authorization

Location:
app/Http/Requests/StoreMovieRequest.php:19-23

Problem

The request authorizes all callers and relies on an external middleware comment:

```php
public function authorize(): bool
{
    // Authorization is handled by middleware (admin role required)
    return true;
}
```

Why this matters

Creating movies is an administrative content-management operation. A `FormRequest` is part of the authorization boundary in Laravel. Returning `true` makes the request reusable in unsafe contexts and hides the permission requirement outside the class. If the route is accidentally registered without the expected middleware, this request will not prevent unauthorized movie creation.

How to fix

Enforce the permission or policy in `authorize()`.

Example

Before

```php
public function authorize(): bool
{
    return true;
}
```

After

```php
public function authorize(): bool
{
    return $this->user()?->can('create', \App\Models\Movie::class) === true;
}
```

----------------------------------------------------

### Issue #2

Severity:
High

Category:
Validation / Security

Location:
app/Http/Requests/StoreMovieRequest.php:41-43

Problem

`description`, `poster_url`, and `trailer_url` are accepted as generic strings:

```php
'description' => ['nullable', 'string'],
'poster_url' => ['nullable', 'string', 'max:255'],
'trailer_url' => ['nullable', 'string', 'max:255'],
```

Why this matters

`description` has no maximum length and may contain HTML/script content depending on how the frontend renders it. `poster_url` and `trailer_url` are not validated as URLs, do not restrict schemes, and do not validate trusted media domains. This creates stored-XSS and unsafe content injection risk if these values are rendered into pages or clients without strict escaping.

How to fix

Add explicit bounds and URL validation, and sanitize or restrict rich text fields.

Example

```php
'description' => ['nullable', 'string', 'max:5000'],
'poster_url' => ['nullable', 'url', 'max:2048'],
'trailer_url' => ['nullable', 'url', 'max:2048'],
```

If only internal upload paths are allowed, validate them as storage paths instead of arbitrary URLs.

----------------------------------------------------

### Issue #3

Severity:
Medium

Category:
Validation / Business Logic

Location:
app/Http/Requests/StoreMovieRequest.php:44

Problem

`duration` only has a lower bound:

```php
'duration' => ['required', 'integer', 'min:1'],
```

Why this matters

A movie duration of millions of minutes would pass validation. Extreme values can break scheduling, pricing, UI rendering, reporting, and showtime overlap calculations.

How to fix

Add a realistic upper bound based on business rules.

Example

```php
'duration' => ['required', 'integer', 'min:1', 'max:600'],
```

Use a configuration constant if the value is business-controlled.

----------------------------------------------------

### Issue #4

Severity:
Medium

Category:
Validation / Business Logic

Location:
app/Http/Requests/StoreMovieRequest.php:45-46

Problem

The request allows any release date and does not enforce a booking/display lifecycle rule:

```php
'release_date' => ['required', 'date'],
'end_date' => ['nullable', 'date', 'after_or_equal:release_date'],
```

Why this matters

Movie creation can accept dates far in the past or nonsensical operational dates. For cinema scheduling, date rules affect visibility, showtime creation, and catalog state. At minimum, business rules should define whether historical movies are allowed and how `end_date` affects active showtimes.

How to fix

Make the lifecycle rule explicit.

Example

```php
'release_date' => ['required', 'date'],
'end_date' => ['nullable', 'date', 'after_or_equal:release_date'],
```

If only future/current movies can be created:

```php
'release_date' => ['required', 'date', 'after_or_equal:today'],
```

----------------------------------------------------

### Issue #5

Severity:
Medium

Category:
Validation / Money Correctness

Location:
app/Http/Requests/StoreMovieRequest.php:48

Problem

`surcharge` is validated as a generic numeric value with no upper bound or decimal precision rule:

```php
'surcharge' => ['nullable', 'numeric', 'min:0'],
```

Why this matters

Money fields should be constrained. This accepts extremely large values and arbitrary decimal precision. That can cause incorrect pricing, rounding inconsistencies, database precision errors, or abusive admin mistakes.

How to fix

Use a decimal/precision-aware validation rule and a maximum.

Example

```php
'surcharge' => ['nullable', 'decimal:0,2', 'min:0', 'max:1000000'],
```

Prefer storing monetary values as integer minor units.

----------------------------------------------------

### Issue #6

Severity:
Medium

Category:
Validation / Data Integrity

Location:
app/Http/Requests/StoreMovieRequest.php:47,54-55

Problem

Several domain fields are under-constrained:

```php
'age_rating' => ['nullable', 'string', 'max:50'],
'manual_override_status' => ['nullable', 'integer', 'min:0'],
'is_hot' => ['sometimes', 'boolean'],
```

Why this matters

`age_rating` should be limited to a controlled set. `manual_override_status` accepts any non-negative integer, which can store invalid states. `is_hot` allows editorial promotion without any additional governance or lifecycle constraints.

How to fix

Use enums or explicit allowed values.

Example

```php
'age_rating' => ['nullable', Rule::in(['P', 'K', 'T13', 'T16', 'T18'])],
'manual_override_status' => ['nullable', Rule::in([0, 1, 2])],
```

----------------------------------------------------

### Issue #7

Severity:
Medium

Category:
Validation / Performance

Location:
app/Http/Requests/StoreMovieRequest.php:51

Problem

`backdrops` is only validated as an array. Its items are not validated and the array is unbounded.

```php
'backdrops' => ['nullable', 'array'],
```

Why this matters

Clients can submit a huge array or arbitrary nested values. This can cause excessive payload sizes, invalid persisted data, rendering failures, and unexpected storage shape.

How to fix

Set a maximum array size and validate every item.

Example

```php
'backdrops' => ['nullable', 'array', 'max:10'],
'backdrops.*' => ['string', 'url', 'max:2048'],
```

If backdrops are uploaded files or storage paths, validate accordingly.

----------------------------------------------------

### Issue #8

Severity:
Medium

Category:
Validation / Data Integrity

Location:
app/Http/Requests/StoreMovieRequest.php:56-57

Problem

`category_ids` has no maximum size and does not require distinct IDs:

```php
'category_ids' => ['nullable', 'array'],
'category_ids.*' => ['integer', 'exists:categories,id'],
```

Why this matters

Duplicate category IDs can create duplicate pivot inserts unless downstream code deduplicates. An unbounded category array can also create large validation queries and expensive sync operations.

How to fix

Add `max` and `distinct`.

Example

```php
'category_ids' => ['nullable', 'array', 'max:20'],
'category_ids.*' => ['integer', 'distinct', Rule::exists('categories', 'id')],
```

----------------------------------------------------

### Issue #9

Severity:
Low

Category:
Clean Code / Comment Quality

Location:
app/Http/Requests/StoreMovieRequest.php:8-13

Problem

The class-level comment claims "Clean Architecture principles":

```php
/**
 * StoreMovieRequest - Validation for creating a new movie
 *
 * Handles validation logic for movie creation following Clean Architecture principles.
 * Separates validation from controllers for better maintainability.
 */
```

Why this matters

This is promotional documentation rather than useful implementation documentation. A Laravel `FormRequest` is framework infrastructure, not Clean Architecture by itself. Comments should clarify non-obvious business rules, not restate the class name or overstate design quality.

How to fix

Remove the comment or replace it with concrete domain constraints.

----------------------------------------------------

Final Assessment

`StoreMovieRequest` is better than raw controller validation, but it is not production-ready for an admin movie creation endpoint. It lacks explicit authorization, accepts unsafe/unbounded content fields, under-constrains domain state fields, allows weak money validation, and does not bound array inputs. These issues should be fixed before approval.