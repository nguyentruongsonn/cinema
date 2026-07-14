====================================================

File:
app/Models/Promotion.php

Overall Score:
4.9/10

Decision:
REQUEST CHANGES

---

Strengths

- Uses `HasFactory`, which supports test data creation.
- Defines decimal casts for monetary promotion fields.
- Defines datetime casts for promotion availability windows.
- Provides a many-to-many relationship to users through `user_promotion`.
- Provides basic scopes for active, valid, and code-based promotion lookup.

---

Issues

### Issue #1

Severity:
Critical

Category:
Security / Mass Assignment

Location:
app/Models/Promotion.php:13-28

Problem

The model allows all promotion lifecycle and redemption-control fields to be mass assigned:

```php
protected $fillable = [
    'code',
    'name',
    'category',
    'description',
    'discount_type',
    'discount_value',
    'min_order_value',
    'max_discount_amount',
    'start_date',
    'end_date',
    'usage_limit',
    'usage_count',
    'daily_usage_limit',
    'status',
];
```

This includes `usage_count`, limits, discount amount, discount type, and status.

Why this matters

Promotions directly affect revenue. If untrusted or loosely authorized request data reaches `create()` or `update()`, a caller can reset usage counts, increase usage limits, activate inactive promotions, create 100% discounts, or remove maximum discount caps. This can cause direct financial loss.

How to fix

Do not mass assign operational counters or lifecycle state. Separate campaign configuration from redemption state. Mutate sensitive fields only through authorized service methods.

Example

```php
protected $fillable = [
    'code',
    'name',
    'category',
    'description',
    'discount_type',
    'discount_value',
    'min_order_value',
    'max_discount_amount',
    'start_date',
    'end_date',
];

// usage_count/status/limits updated only through explicit domain methods
```

---

### Issue #2

Severity:
Critical

Category:
Concurrency / Duplicate Redemption

Location:
app/Models/Promotion.php:24-26, 63-70

Problem

The model stores usage counters and exposes validity using a non-locking read condition:

```php
'usage_limit',
'usage_count',
'daily_usage_limit',
```

```php
public function scopeValid($query)
{
    return $query->where('status', 1)
        ->where(function ($q) {
            $q->whereNull('usage_limit')
              ->orWhereColumn('usage_count', '<', 'usage_limit');
        });
}
```

Why this matters

Two concurrent checkout requests can both pass `usage_count < usage_limit`, then both redeem the promotion. This overshoots campaign limits and creates duplicate discount usage. For limited-use promotions, this is a direct revenue-loss and reconciliation issue.

How to fix

Promotion redemption must be atomic. Use a transaction with `lockForUpdate()` or a conditional update.

Example

```php
$updated = Promotion::whereKey($promotionId)
    ->where('status', true)
    ->where(function ($query) {
        $query->whereNull('usage_limit')
            ->orWhereColumn('usage_count', '<', 'usage_limit');
    })
    ->increment('usage_count');

if ($updated === 0) {
    throw new DomainException('Promotion usage limit reached.');
}
```

Also create an immutable redemption record tied to the order.

---

### Issue #3

Severity:
High

Category:
Business Logic / Daily Usage Limit

Location:
app/Models/Promotion.php:26, 63-70

Problem

The model defines `daily_usage_limit`:

```php
'daily_usage_limit',
```

but `scopeValid()` ignores it entirely:

```php
public function scopeValid($query)
{
    return $query->where('status', 1)
        ->where(function ($q) {
            $q->whereNull('usage_limit')
              ->orWhereColumn('usage_count', '<', 'usage_limit');
        });
}
```

Why this matters

A daily limit is a business contract. If it is stored but not enforced in validity checks, promotions can be overused in a single day even when the campaign configuration says otherwise.

How to fix

Track redemptions by date in a redemption table and enforce daily limits atomically.

Example

```php
$todayUsage = PromotionRedemption::where('promotion_id', $promotion->id)
    ->whereDate('created_at', today())
    ->lockForUpdate()
    ->count();

if ($promotion->daily_usage_limit !== null && $todayUsage >= $promotion->daily_usage_limit) {
    throw new DomainException('Daily promotion limit reached.');
}
```

Do not rely only on aggregate counters for daily limits.

---

### Issue #4

Severity:
High

Category:
Business Logic / Monetary Correctness

Location:
app/Models/Promotion.php:18-21, 30-33

Problem

The model casts discount and order-value fields as decimals but does not enforce valid monetary ranges:

```php
'discount_type',
'discount_value',
'min_order_value',
'max_discount_amount',
```

```php
'discount_value' => 'decimal:2',
'min_order_value' => 'decimal:2',
'max_discount_amount' => 'decimal:2',
```

Why this matters

A negative discount, negative minimum order value, missing maximum discount for a percent promotion, or a percent discount over 100 can produce invalid totals or free/over-discounted orders. Casts do not enforce correctness.

How to fix

Enforce invariants in validation, service logic, and database constraints.

Example

```php
'discount_type' => ['required', Rule::in(['fixed', 'percent'])],
'discount_value' => ['required', 'decimal:0,2', 'min:0.01'],
'min_order_value' => ['nullable', 'decimal:0,2', 'min:0'],
'max_discount_amount' => ['nullable', 'decimal:0,2', 'min:0'],
```

For percent discounts:

```php
if ($type === 'percent' && $discountValue > 100) {
    throw new DomainException('Percent discount cannot exceed 100.');
}
```

---

### Issue #5

Severity:
High

Category:
Database Correctness / Campaign Date Invariants

Location:
app/Models/Promotion.php:22-23, 49-60

Problem

The model stores start and end dates:

```php
'start_date',
'end_date',
```

and filters active promotions:

```php
$q->whereNull('start_date')
  ->orWhere('start_date', '<=', now());
```

```php
$q->whereNull('end_date')
  ->orWhere('end_date', '>=', now());
```

but it does not enforce `end_date >= start_date`.

Why this matters

Invalid campaign windows can make promotions permanently inactive, unexpectedly active, or difficult to reason about. This is especially dangerous when admin tools allow campaign setup close to release time.

How to fix

Validate and enforce date ordering.

Example

```php
'start_date' => ['nullable', 'date'],
'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
```

Add a database check constraint where supported.

---

### Issue #6

Severity:
High

Category:
Database Correctness / Code Uniqueness

Location:
app/Models/Promotion.php:14, 73-77

Problem

The model exposes code lookup:

```php
public function scopeByCode($query, $code)
{
    return $query->where('code', $code);
}
```

but the model itself does not normalize code values or indicate uniqueness guarantees.

Why this matters

Promotion code lookup must be deterministic. If codes differ by case or whitespace, users can receive inconsistent behavior. If duplicate codes exist, the application may apply the wrong discount or fail unpredictably.

How to fix

Normalize codes before persistence and enforce a unique index.

Example

```php
protected static function booted(): void
{
    static::saving(function (Promotion $promotion): void {
        $promotion->code = strtoupper(trim($promotion->code));
    });
}
```

Database:

```php
$table->unique('code');
```

---

### Issue #7

Severity:
Medium

Category:
Security / Authorization

Location:
app/Models/Promotion.php:42-46

Problem

The relationship exposes user-promotion pivot data including status, order ID, and usage count:

```php
return $this->belongsToMany(User::class, 'user_promotion')
    ->withPivot(['status', 'used_at', 'order_id', 'usage_count'])
    ->withTimestamps();
```

There is no custom pivot model to protect mutation rules.

Why this matters

Promotion assignment and redemption are sensitive business events. Without a custom pivot model and service-level write boundaries, application code can easily mutate pivot status, order association, or usage counts inconsistently.

How to fix

Introduce a dedicated pivot model or redemption model with guarded attributes and explicit methods.

Example

```php
return $this->belongsToMany(User::class, 'user_promotion')
    ->using(UserPromotion::class)
    ->withPivot(['status', 'used_at', 'order_id', 'usage_count'])
    ->withTimestamps();
```

Move redemption writes into a transaction-backed service.

---

### Issue #8

Severity:
Medium

Category:
Business Logic / Promotion Lifecycle

Location:
app/Models/Promotion.php:27, 49-70

Problem

The status field is a boolean:

```php
'status',
```

```php
'status' => 'boolean',
```

Scopes only distinguish active/inactive state:

```php
return $query->where('status', 1)
```

Why this matters

Promotion lifecycle is more complex than a boolean. Real campaigns may be draft, scheduled, active, paused, expired, exhausted, archived, or revoked. A boolean makes lifecycle transitions ambiguous and pushes business rules into scattered application code.

How to fix

Use an enum-backed status and explicit lifecycle transitions.

Example

```php
enum PromotionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Expired = 'expired';
    case Archived = 'archived';
}
```

---

### Issue #9

Severity:
Medium

Category:
Business Logic / Free-form Domain Values

Location:
app/Models/Promotion.php:16, 18

Problem

`category` and `discount_type` are free-form strings:

```php
'category',
```

```php
'discount_type',
```

Why this matters

Free-form categories and discount types make promotion behavior fragile. A typo such as `percentage`, `percent`, or `Percent` can produce inconsistent calculations downstream.

How to fix

Use enums or lookup tables for both fields.

Example

```php
enum DiscountType: string
{
    case Fixed = 'fixed';
    case Percent = 'percent';
}
```

Cast the field to the enum where possible.

---

### Issue #10

Severity:
Medium

Category:
Performance / Database Indexing

Location:
app/Models/Promotion.php:49-77

Problem

The model provides high-traffic scopes over status, date windows, usage counts, and code:

```php
scopeActive()
scopeValid()
scopeByCode()
```

There is no indication in the model of required supporting indexes.

Why this matters

Promotion code lookup and active campaign discovery occur during checkout. Slow promotion queries directly affect payment conversion and checkout latency.

How to fix

Ensure database indexes match query patterns.

Example

```php
$table->unique('code');
$table->index(['status', 'start_date', 'end_date']);
$table->index(['status', 'usage_count', 'usage_limit']);
```

For case-insensitive code lookup, use a generated normalized column or a collation/index strategy appropriate for MySQL.

---

### Issue #11

Severity:
Medium

Category:
Laravel Best Practices / Type Safety

Location:
app/Models/Promotion.php:49-77

Problem

Scopes do not type their query parameter or return type:

```php
public function scopeActive($query)
public function scopeValid($query)
public function scopeByCode($query, $code)
```

Why this matters

Untyped scopes reduce static analysis value and make refactoring checkout/promotion code riskier.

How to fix

Use `Builder` types.

Example

```php
use Illuminate\Database\Eloquent\Builder;

public function scopeActive(Builder $query): Builder
{
    return $query->where('status', true);
}
```

---

### Issue #12

Severity:
Medium

Category:
API Consistency / Time Semantics

Location:
app/Models/Promotion.php:55, 59

Problem

The scopes call `now()` directly:

```php
->orWhere('start_date', '<=', now());
```

```php
->orWhere('end_date', '>=', now());
```

Why this matters

Direct `now()` calls make time semantics implicit. In checkout, campaign validity should be evaluated against a consistent clock value, especially inside a longer transaction. Repeated `now()` calls can also make tests less deterministic.

How to fix

Pass a clock value into the query or encapsulate validity with an explicit timestamp.

Example

```php
public function scopeActiveAt(Builder $query, CarbonInterface $at): Builder
{
    return $query->where('status', true)
        ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $at))
        ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $at));
}
```

---

### Issue #13

Severity:
Low

Category:
Clean Code / Magic Values

Location:
app/Models/Promotion.php:52, 66

Problem

The scopes use integer literals for a boolean-cast field:

```php
return $query->where('status', 1)
```

Why this matters

The field is cast to boolean, but the query uses integer values. This is minor but inconsistent and less expressive.

How to fix

Use boolean values or rename the field to `is_active`.

Example

```php
return $query->where('status', true);
```

---

### Issue #14

Severity:
Low

Category:
Readability / Comment Quality

Location:
app/Models/Promotion.php:49, 63, 73

Problem

The comments repeat what the method names already say:

```php
// Scope: active promotions
// Scope: valid for use
// Scope: by code
```

Why this matters

Redundant comments add noise and do not explain business rules. The important details are what "active" and "valid" mean, not that the methods are scopes.

How to fix

Remove these comments or replace them with domain-specific explanations.

Example

```php
// A promotion is active when it is enabled and the current timestamp is inside its campaign window.
```

---

### Issue #15

Severity:
Low

Category:
Maintainability / Type Documentation

Location:
app/Models/Promotion.php:9-78

Problem

The model has no PHPDoc annotations for dynamic properties or pivot relationship metadata.

Why this matters

Promotion code is revenue-sensitive. Static analysis and IDE support should make money fields, counters, and relationship metadata explicit.

How to fix

Add PHPDoc or Laravel IDE helper metadata.

Example

```php
/**
 * @property int $id
 * @property string $code
 * @property string $discount_type
 * @property string $discount_value
 * @property string|null $min_order_value
 * @property string|null $max_discount_amount
 * @property int|null $usage_limit
 * @property int $usage_count
 * @property bool $status
 */
class Promotion extends Model
{
    // ...
}
```

---

Final Assessment

`Promotion` is not production-ready for a revenue-impacting checkout system. The most serious issues are mass assignment of discount and usage-control fields, race-prone usage-limit checks, missing daily-limit enforcement, weak monetary invariants, and ambiguous promotion lifecycle modeling. Promotion redemption must be implemented as an atomic, auditable domain operation rather than a loose combination of scopes and mutable counters.
