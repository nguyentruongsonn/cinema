====================================================

File:
app/Models/Format.php

Overall Score:
6.6/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Defines a clear `showtimes()` `HasMany` relationship.
- Casts `surcharge` as `decimal:2`, which is appropriate for display precision.
- Keeps the model small and focused.
- Uses an explicit foreign key for the showtime relationship.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Business Logic / Pricing Correctness

Location:
app/Models/Format.php:10-13

Problem

`surcharge` is mass assignable:

```php
protected $fillable = [
    'name',
    'surcharge',
];
```

There is no model-level invariant preventing negative surcharge values.

Why this matters

A negative format surcharge can reduce ticket prices unexpectedly. In a cinema booking system, format pricing affects revenue directly. If a malformed admin request or insufficiently protected endpoint stores a negative value, orders can be undercharged.

How to fix

Validate surcharge as a non-negative decimal in FormRequests/services and enforce it in the database.

Example

```php
'surcharge' => ['required', 'numeric', 'min:0', 'decimal:0,2']
```

Database safeguard:

```php
$table->decimal('surcharge', 10, 2)->default(0);
$table->check('surcharge >= 0');
```

----------------------------------------------------

### Issue #2

Severity:
High

Category:
Authorization / Mass Assignment

Location:
app/Models/Format.php:10-13

Problem

The model allows direct mass assignment of pricing data:

```php
'surcharge',
```

Why this matters

Format surcharge is a pricing-control field. If controllers pass request payloads directly into `Format::create()` or `update()` without strict authorization, users with unintended access could alter pricing. This can cause financial loss.

How to fix

Restrict surcharge changes to authorized admin pricing workflows. Use dedicated validated DTOs/services for pricing updates and audit every pricing change.

Example

```php
if (! $user->can('managePricing', Format::class)) {
    abort(403);
}

$format->update([
    'name' => $validated['name'],
    'surcharge' => $validated['surcharge'],
]);
```

----------------------------------------------------

### Issue #3

Severity:
Medium

Category:
Database Correctness / Uniqueness

Location:
app/Models/Format.php:10-13

Problem

`name` is mass assignable, but the model does not enforce uniqueness or normalization:

```php
'name',
```

Why this matters

Duplicate format names such as `IMAX`, `imax`, and `IMAX ` can fragment reporting, confuse admins, and create inconsistent pricing selection in showtime creation. If format names are presented to customers, duplicates reduce API clarity.

How to fix

Normalize names and enforce uniqueness at validation and database level.

Example

```php
'name' => ['required', 'string', 'max:100', Rule::unique('formats', 'name')->ignore($format?->id)]
```

Normalize before persistence:

```php
$validated['name'] = trim($validated['name']);
```

Database:

```php
$table->string('name')->unique();
```

----------------------------------------------------

### Issue #4

Severity:
Medium

Category:
Currency Precision / Laravel Best Practices

Location:
app/Models/Format.php:15-17

Problem

`surcharge` is cast using Eloquent decimal casting:

```php
protected $casts = [
    'surcharge' => 'decimal:2',
];
```

Laravel decimal casts return strings, not floats. The model does not provide a money-safe value object or integer-minor-unit representation.

Why this matters

Pricing code may accidentally treat `surcharge` as a numeric float/string interchangeably. This can introduce precision issues or inconsistent calculations if services add values without explicit money handling. Ticket pricing should be deterministic.

How to fix

Prefer storing money in integer minor units, or centralize money arithmetic in a pricing service/value object. If decimal storage remains, keep all calculations in database decimals or precise string/BCMath operations.

Example

```php
// Prefer:
$table->unsignedInteger('surcharge_cents')->default(0);
```

Then expose formatted values through resources, not model arithmetic.

----------------------------------------------------

### Issue #5

Severity:
Medium

Category:
Database Correctness / Referential Integrity

Location:
app/Models/Format.php:19-22

Problem

The model declares showtimes referencing this format:

```php
public function showtimes(): HasMany
{
    return $this->hasMany(Showtime::class, 'format_id');
}
```

The model does not indicate any lifecycle policy for deleting or modifying a format that already has showtimes.

Why this matters

If a format used by future or historical showtimes is deleted or its surcharge is changed, pricing and reporting can become inconsistent. Existing orders may need the format name/surcharge as they were at purchase time.

How to fix

Prevent deletion of formats used by showtimes, or use soft deletes/versioning. Snapshot applied format surcharge into showtime/pricing/order records when prices are calculated.

Example

```php
if ($format->showtimes()->exists()) {
    throw ValidationException::withMessages([
        'format' => 'Cannot delete a format used by showtimes.',
    ]);
}
```

----------------------------------------------------

### Issue #6

Severity:
Medium

Category:
API Consistency / Query Design

Location:
app/Models/Format.php:8-23

Problem

The model has no `status`/`is_active` field or scope for controlling whether a format is selectable for new showtimes.

Why this matters

Operationally, formats may need to be retired without breaking historical showtimes. Without an active flag or versioning, the application may either keep obsolete formats selectable forever or delete them and damage history.

How to fix

Add an `is_active` flag and an `active()` scope for admin/showtime creation APIs, while retaining old records for historical references.

Example

```php
public function scopeActive($query)
{
    return $query->where('is_active', true);
}
```

----------------------------------------------------

### Issue #7

Severity:
Low

Category:
Maintainability / Type Documentation

Location:
app/Models/Format.php:8-23

Problem

The model has no PHPDoc annotations for dynamic Eloquent properties.

Why this matters

Fields like `surcharge` have pricing implications. Without property annotations, static analysis and IDE support are weaker, increasing the chance of misuse in pricing services.

How to fix

Add PHPDoc or adopt Laravel IDE Helper/static analysis conventions.

Example

```php
/**
 * @property int $id
 * @property string $name
 * @property string $surcharge
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Showtime> $showtimes
 */
class Format extends Model
{
    // ...
}
```

----------------------------------------------------

Final Assessment

`Format` is simple, but it directly influences ticket pricing through `surcharge`. The main production concerns are missing non-negative pricing invariants, pricing mass assignment exposure, weak uniqueness rules for format names, and no lifecycle policy for formats already referenced by showtimes. These should be addressed before production approval.
