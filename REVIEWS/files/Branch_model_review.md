====================================================

File:
app/Models/Branch.php

Overall Score:
6.4/10

Decision:
APPROVE WITH COMMENTS

----------------------------------------------------

Strengths

- Uses `SoftDeletes`, which is safer than hard-deleting branch records that may be referenced by theaters, screens, showtimes, orders, or historical reports.
- Keeps the model small and readable.
- Casts `is_active` to boolean.
- Provides an `active()` scope for common filtering.

----------------------------------------------------

Issues

### Issue #1

Severity:
Medium

Category:
Data Integrity / Business Logic

Location:
app/Models/Branch.php:12-15

Problem

The model allows mass assignment of `is_active`:

```php
protected $fillable = [
    'name',
    'is_active',
];
```

Why this matters

Branch activation/deactivation is an operational control. If any controller or service uses broad request mass assignment, a caller can toggle branch availability through payload data. In a cinema system, branch deactivation can affect public showtime visibility, booking availability, and reporting.

How to fix

Do not expose operational state changes through generic create/update payloads. Move state transitions behind explicit service methods with authorization and audit logging.

Example

```php
protected $fillable = [
    'name',
];

public function activate(): void
{
    $this->forceFill(['is_active' => true])->save();
}

public function deactivate(): void
{
    $this->forceFill(['is_active' => false])->save();
}
```

----------------------------------------------------

### Issue #2

Severity:
Medium

Category:
Database Correctness / Domain Modeling

Location:
app/Models/Branch.php:8-25

Problem

The model does not define relationships to dependent domain entities.

```php
class Branch extends Model
{
    use SoftDeletes;

    // ...
}
```

Why this matters

A branch is likely a parent aggregate for theaters/screens/showtimes. Without relationships on the model, deletion/deactivation safety checks must be duplicated elsewhere or skipped. This makes it easier to soft-delete or deactivate a branch that still has active theaters, screens, showtimes, or future bookings.

How to fix

Define explicit relationships and use them in service-layer deletion/deactivation rules.

Example

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function theaters(): HasMany
{
    return $this->hasMany(Theater::class);
}
```

Before deletion/deactivation:

```php
if ($branch->theaters()->where('is_active', true)->exists()) {
    throw new DomainException('Cannot deactivate a branch with active theaters.');
}
```

----------------------------------------------------

### Issue #3

Severity:
Medium

Category:
Data Integrity / Soft Deletes

Location:
app/Models/Branch.php:10

Problem

The model uses soft deletes but does not show any domain guard preventing deletion of referenced branches:

```php
use SoftDeletes;
```

Why this matters

Soft deletion prevents physical deletion but does not automatically protect business integrity. If a branch with active theaters/showtimes is soft-deleted, public listings, admin views, and historical analytics can become inconsistent depending on whether queries include trashed records.

How to fix

Add service-level or model-event guards to prevent deleting branches with active dependent records. Keep database foreign keys intact and require explicit archival workflows.

Example

```php
protected static function booted(): void
{
    static::deleting(function (Branch $branch) {
        if (! $branch->isForceDeleting() && $branch->theaters()->exists()) {
            throw new DomainException('Cannot delete a branch with theaters.');
        }
    });
}
```

----------------------------------------------------

### Issue #4

Severity:
Medium

Category:
Performance / Database Indexing

Location:
app/Models/Branch.php:21-24

Problem

The model defines an `active()` scope filtering by `is_active`:

```php
public function scopeActive($query)
{
    return $query->where('is_active', true);
}
```

The model cannot confirm that `is_active` is indexed.

Why this matters

Branch selection is likely used in public filters and admin lists. Without an index, repeated active-branch queries can become table scans as data grows.

How to fix

Ensure the migration includes indexes for expected access patterns.

Example

```php
$table->index('is_active');
$table->index(['is_active', 'deleted_at']);
```

----------------------------------------------------

### Issue #5

Severity:
Low

Category:
Laravel Best Practices / Type Safety

Location:
app/Models/Branch.php:21-24

Problem

The scope has no query builder type hint or return type:

```php
public function scopeActive($query)
{
    return $query->where('is_active', true);
}
```

Why this matters

Untyped scopes weaken static analysis and make the model API less clear.

How to fix

Use `Builder` type hints.

Example

```php
use Illuminate\Database\Eloquent\Builder;

public function scopeActive(Builder $query): Builder
{
    return $query->where('is_active', true);
}
```

----------------------------------------------------

### Issue #6

Severity:
Low

Category:
Validation / Data Quality

Location:
app/Models/Branch.php:12-15

Problem

The model stores only a free-form `name` and does not enforce a normalization or uniqueness strategy:

```php
'name',
```

Why this matters

Branch names can drift through whitespace, case differences, or duplicates. This creates poor admin UX and can make reporting ambiguous.

How to fix

Normalize names in request/service code and enforce uniqueness at the database level if business rules require unique branch names.

Example

```php
'name' => ['required', 'string', 'max:255', Rule::unique('branches', 'name')->whereNull('deleted_at')],
```

----------------------------------------------------

Final Assessment

`Branch` is simple and mostly understandable, but it is under-modeled for production domain safety. The main concerns are mass-assignable operational state, missing relationships for integrity checks, and no visible deletion/deactivation guard around dependent records. These are not necessarily blocking in the model alone, but they should be addressed in the service/controller flow before branch administration is considered production-ready.
