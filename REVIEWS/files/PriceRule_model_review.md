====================================================

File:
app/Models/PriceRule.php

Overall Score:
5.4/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Defines a dedicated model for configurable pricing rules.
- Casts monetary adjustment, priority, date windows, days of week, and status.
- Provides a `showtimes()` relationship.
- Provides scopes for active rules and rule type filtering.
- Uses `decimal:2` for `price_adjustment` instead of a float cast.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Authorization / Mass Assignment

Location:
app/Models/PriceRule.php:10-22

Problem

All pricing rule fields are mass assignable:

```php
protected $fillable = [
    'name',
    'type',
    'condition_type',
    'condition_value',
    'price_adjustment',
    'adjustment_type',
    'priority',
    'start_date',
    'end_date',
    'days_of_week',
    'status',
];
```

Why this matters

Pricing rules directly affect ticket revenue. If request data is passed into `create()` or `update()` without strict authorization and validation, a caller can change pricing types, discounts/surcharges, active windows, priority ordering, or enable/disable rules. This can undercharge customers, create invalid discounts, or change active pricing in production.

How to fix

Do not mass assign pricing-impacting fields from raw request payloads. Use a validated admin-only command/service layer and explicitly map allowed fields.

Example

```php
$priceRule->forceFill([
    'name' => $validated['name'],
    'type' => $validated['type'],
    'condition_type' => $validated['condition_type'],
    'condition_value' => $validated['condition_value'],
    'price_adjustment' => $validated['price_adjustment'],
    'adjustment_type' => $validated['adjustment_type'],
    'priority' => $validated['priority'],
])->save();
```

Authorization must be enforced before mutation.

----------------------------------------------------

### Issue #2

Severity:
High

Category:
Business Logic / Monetary Correctness

Location:
app/Models/PriceRule.php:15-16, 24-31

Problem

`price_adjustment` and `adjustment_type` are persisted without any invariant tying them together:

```php
'price_adjustment',
'adjustment_type',
```

```php
'price_adjustment' => 'decimal:2',
```

Why this matters

The model does not prevent negative adjustments, invalid adjustment types, percentage values over 100, or nonsensical values for fixed adjustments. Invalid pricing rules can produce free tickets, negative ticket prices, extreme discounts, or overcharging.

How to fix

Validate and enforce domain constraints based on adjustment type.

Example

```php
if ($adjustmentType === 'percentage' && ($amount < 0 || $amount > 100)) {
    throw new InvalidArgumentException('Percentage adjustment must be between 0 and 100.');
}

if ($adjustmentType === 'fixed' && $amount < 0) {
    throw new InvalidArgumentException('Fixed adjustment must be non-negative.');
}
```

Also enforce final ticket price never falls below zero in the pricing calculation layer.

----------------------------------------------------

### Issue #3

Severity:
High

Category:
Business Logic / Rule Semantics

Location:
app/Models/PriceRule.php:12-16

Problem

The rule type and condition fields are free-form strings/values:

```php
'type',
'condition_type',
'condition_value',
'price_adjustment',
'adjustment_type',
```

There are no constants, enum casts, validation hooks, or typed value objects defining valid combinations.

Why this matters

Pricing rules are business-critical. Free-form rule definitions allow invalid combinations such as a seat-type condition on a showtime-only rule, unsupported adjustment types, unknown condition types, or unparseable condition values. This creates hidden production bugs where rules silently do not apply or apply incorrectly.

How to fix

Use enums/constants and validate allowed combinations before persistence.

Example

```php
enum PriceRuleType: string
{
    case Showtime = 'showtime';
    case SeatType = 'seat_type';
    case DayOfWeek = 'day_of_week';
}

enum AdjustmentType: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';
}
```

Reject unsupported combinations in a service or custom validation rule.

----------------------------------------------------

### Issue #4

Severity:
High

Category:
Database Correctness / Date Invariants

Location:
app/Models/PriceRule.php:18-19, 38-49

Problem

The model has start/end date fields and an active scope:

```php
'start_date',
'end_date',
```

```php
return $query->where('status', 1)
    ->where(function ($q) {
        $q->whereNull('start_date')
          ->orWhere('start_date', '<=', now());
    })
    ->where(function ($q) {
        $q->whereNull('end_date')
          ->orWhere('end_date', '>=', now());
    });
```

but it does not enforce `start_date <= end_date`.

Why this matters

Invalid date ranges can create pricing rules that never activate, activate unpredictably depending on null handling, or confuse admin users. For revenue-affecting pricing, invalid temporal rules should be impossible to persist.

How to fix

Validate date range on write and add a database check where supported.

Example

```php
'start_date' => ['nullable', 'date'],
'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
```

Database-level check:

```php
$table->check('end_date is null or start_date is null or end_date >= start_date');
```

----------------------------------------------------

### Issue #5

Severity:
High

Category:
Business Logic / Rule Conflict Handling

Location:
app/Models/PriceRule.php:17, 38-53

Problem

The model has a `priority` field:

```php
'priority',
```

but neither the active scope nor the type scope orders rules by priority:

```php
public function scopeActive($query)
{
    return $query->where('status', 1)
        // ...
}

public function scopeByType($query, $type)
{
    return $query->where('type', $type);
}
```

Why this matters

When multiple rules match the same ticket/showtime, deterministic ordering is required. Without ordering by priority, the database can return matching rules in arbitrary order, causing inconsistent prices across requests, database engines, or deployments.

How to fix

Define deterministic rule resolution.

Example

```php
public function scopeActive(Builder $query): Builder
{
    return $query->where('status', true)
        ->where(fn (Builder $q) => $q->whereNull('start_date')->orWhere('start_date', '<=', now()))
        ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()))
        ->orderByDesc('priority')
        ->orderBy('id');
}
```

Also define whether rules stack or whether only the highest-priority matching rule applies.

----------------------------------------------------

### Issue #6

Severity:
Medium

Category:
Validation / days_of_week Correctness

Location:
app/Models/PriceRule.php:20, 24-31

Problem

`days_of_week` is cast as an array:

```php
'days_of_week',
```

```php
'days_of_week' => 'array',
```

but there is no validation that it contains only valid day values, no duplicate prevention, and no standard representation.

Why this matters

Pricing rules based on weekdays can silently break if values are stored as mixed strings/integers, invalid day numbers, localized names, duplicates, or empty arrays. This affects scheduled pricing and can cause wrong ticket prices.

How to fix

Normalize and validate the array before persistence.

Example

```php
'days_of_week' => ['nullable', 'array', 'max:7'],
'days_of_week.*' => ['integer', 'between:0,6', 'distinct'],
```

Document whether `0` is Sunday or Monday.

----------------------------------------------------

### Issue #7

Severity:
Medium

Category:
Performance / Database Indexing

Location:
app/Models/PriceRule.php:21, 38-53

Problem

The active and type scopes query by status, date windows, and type:

```php
return $query->where('status', 1)
```

```php
return $query->where('type', $type);
```

There is no indication of supporting indexes.

Why this matters

Pricing is likely evaluated during showtime listing and checkout. If active pricing rules are scanned repeatedly without indexes, checkout and listing latency will degrade as rule history grows.

How to fix

Add indexes aligned with rule lookup patterns.

Example

```php
$table->index(['status', 'type', 'start_date', 'end_date']);
$table->index(['type', 'priority']);
```

If historical rules are retained, consider partial indexes where supported.

----------------------------------------------------

### Issue #8

Severity:
Medium

Category:
Concurrency / Pricing Consistency

Location:
app/Models/PriceRule.php:10-22, 33-36

Problem

The model allows pricing rules to be changed directly while showtimes can reference them:

```php
public function showtimes(): HasMany
{
    return $this->hasMany(Showtime::class, 'price_rule_id');
}
```

There is no versioning, snapshotting, immutability, or effective-date policy.

Why this matters

If a price rule is edited after tickets are sold or while customers are checking out, different customers can see or pay different prices for the same showtime. It also makes historical reconciliation difficult because past ticket prices may no longer match the rule configuration used at purchase time.

How to fix

Use immutable/versioned pricing rules or snapshot the applied pricing rule data onto orders/tickets at purchase time.

Example

```php
// On checkout
$ticket->pricing_snapshot = [
    'price_rule_id' => $rule->id,
    'price_rule_name' => $rule->name,
    'adjustment_type' => $rule->adjustment_type,
    'price_adjustment' => $rule->price_adjustment,
];
```

Do not mutate rules that have been used for sold tickets; create a new version instead.

----------------------------------------------------

### Issue #9

Severity:
Medium

Category:
Laravel Best Practices / Type Safety

Location:
app/Models/PriceRule.php:38-53

Problem

The query scopes lack typed `Builder` parameters and return types:

```php
public function scopeActive($query)
public function scopeByType($query, $type)
```

Why this matters

Untyped scopes reduce static analysis quality and make pricing query refactors more error-prone.

How to fix

Import and use `Builder`.

Example

```php
use Illuminate\Database\Eloquent\Builder;

public function scopeActive(Builder $query): Builder
{
    return $query->where('status', true);
}

public function scopeByType(Builder $query, string $type): Builder
{
    return $query->where('type', $type);
}
```

----------------------------------------------------

### Issue #10

Severity:
Medium

Category:
Database Correctness / Referential Safety

Location:
app/Models/PriceRule.php:33-36

Problem

The model declares that showtimes reference price rules:

```php
return $this->hasMany(Showtime::class, 'price_rule_id');
```

but the model contains no deletion/lifecycle guard for rules that are attached to showtimes.

Why this matters

Deleting or disabling a pricing rule that is referenced by future showtimes can break pricing display, checkout calculation, or historical audit. Even if the database has a nullable foreign key, business behavior becomes undefined.

How to fix

Prevent deletion of referenced pricing rules or require migration to a replacement rule.

Example

```php
protected static function booted(): void
{
    static::deleting(function (PriceRule $rule) {
        if ($rule->showtimes()->exists()) {
            throw new DomainException('Cannot delete a price rule assigned to showtimes.');
        }
    });
}
```

Prefer soft-deactivation over deletion for financial configuration.

----------------------------------------------------

### Issue #11

Severity:
Low

Category:
Laravel Best Practices / Testing

Location:
app/Models/PriceRule.php:5-9

Problem

The model does not use `HasFactory`:

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceRule extends Model
```

Why this matters

Pricing rules need strong automated test coverage because they directly impact checkout totals. Missing factories makes pricing scenarios harder to test.

How to fix

Add `HasFactory`.

Example

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PriceRule extends Model
{
    use HasFactory;
}
```

----------------------------------------------------

### Issue #12

Severity:
Low

Category:
Clean Code / Magic Values

Location:
app/Models/PriceRule.php:40

Problem

The active scope uses a magic integer for a boolean-cast field:

```php
return $query->where('status', 1)
```

Why this matters

`status` is cast to boolean, but the query uses `1`. This is minor but inconsistent and less expressive. It also hides whether the domain means active/inactive, published/unpublished, or enabled/disabled.

How to fix

Use a boolean and consider a clearer column name such as `is_active`.

Example

```php
return $query->where('status', true);
```

----------------------------------------------------

### Issue #13

Severity:
Low

Category:
Maintainability / Type Documentation

Location:
app/Models/PriceRule.php:8-55

Problem

The model has no PHPDoc annotations for dynamic properties and relationships.

Why this matters

Pricing fields are sensitive and heavily reused. Static analysis should understand fields like `type`, `condition_type`, `price_adjustment`, `adjustment_type`, `priority`, `days_of_week`, and `status`.

How to fix

Add PHPDoc or Laravel IDE helper metadata.

Example

```php
/**
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string $condition_type
 * @property mixed $condition_value
 * @property string $price_adjustment
 * @property string $adjustment_type
 * @property int $priority
 * @property array|null $days_of_week
 * @property bool $status
 */
class PriceRule extends Model
{
    // ...
}
```

----------------------------------------------------

Final Assessment

`PriceRule` is too under-specified for a production pricing engine. It stores revenue-impacting configuration with broad mass assignment, free-form rule semantics, weak monetary/date invariants, no deterministic priority ordering, and no versioning or lifecycle protection for rules referenced by showtimes. These issues can produce inconsistent checkout totals, invalid discounts, broken historical reconciliation, and operationally unsafe pricing changes.
