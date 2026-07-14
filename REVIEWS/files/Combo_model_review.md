====================================================

File:
app/Models/Combo.php

Overall Score:
5.7/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Uses `SoftDeletes`, which helps preserve historical commerce/catalog references.
- Defines a clear `comboItems()` relationship.
- Casts monetary columns to two-decimal strings via Eloquent decimal casts.
- Provides reusable `active()` and `inStock()` scopes.
- `available_stock` accessor attempts to derive combo availability from child product stock instead of storing duplicated stock directly.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Business Logic / Correctness

Location:
app/Models/Combo.php:70-75

Problem

`scopeInStock()` only checks that at least one product in the combo has stock greater than zero:

```php
public function scopeInStock($query)
{
    return $query->whereHas('comboItems.product', function ($q) {
        $q->where('stock', '>', 0);
    });
}
```

This does not guarantee the combo itself can be sold. A combo requires all child items to have enough stock for their required quantities.

Why this matters

This can display unavailable combos as purchasable. Example: a combo has popcorn quantity 2 and drink quantity 1. If popcorn stock is 1 and drink stock is 100, `scopeInStock()` still returns the combo because at least one related product has stock `> 0`. That creates checkout failures, overselling risk, and inconsistent public catalog behavior.

How to fix

Do not use a simple `whereHas()` to determine combo stock. Use service-layer availability calculation, database constraints, or a query that verifies every combo item has sufficient product stock relative to `combo_items.quantity`.

Example

```php
public function isInStock(): bool
{
    return $this->comboItems()
        ->with('product')
        ->get()
        ->every(fn ($item) =>
            $item->product !== null
            && $item->quantity > 0
            && $item->product->stock >= $item->quantity
        );
}
```

For list queries, consider joining `combo_items` and products and excluding combos with any insufficient child product.

----------------------------------------------------

### Issue #2

Severity:
High

Category:
Data Integrity / Validation

Location:
app/Models/Combo.php:48-53

Problem

`getAvailableStockAttribute()` divides by `$item->quantity` without guarding against zero or negative values:

```php
$availableCombo = floor($item->product->stock / $item->quantity);
```

Why this matters

If a `ComboItem` has `quantity` equal to `0`, this accessor can throw a division-by-zero error in production. If quantity is negative, it produces invalid availability. Since this accessor may be called during API serialization or list rendering, one bad child row can break catalog responses.

How to fix

Enforce positive quantities at validation and database levels, and defensively guard in this accessor.

Example

```php
foreach ($items as $item) {
    if ($item->quantity <= 0) {
        return 0;
    }

    if (! $item->product || $item->product->stock <= 0) {
        return 0;
    }

    $availableCombo = intdiv((int) $item->product->stock, (int) $item->quantity);
    $minStock = min($minStock, $availableCombo);
}
```

Also enforce:

```php
$table->unsignedInteger('quantity');
```

and request validation:

```php
'items.*.quantity' => ['required', 'integer', 'min:1']
```

----------------------------------------------------

### Issue #3

Severity:
High

Category:
Concurrency / Commerce Correctness

Location:
app/Models/Combo.php:39-57

Problem

`available_stock` is calculated from current product stock without any locking:

```php
$items = $this->comboItems()->with('product')->get();
```

Why this matters

This value is only a snapshot. It is not safe for checkout or stock deduction. Concurrent orders can both see available stock and both proceed unless the purchase flow locks product rows and decrements stock atomically. For a cinema commerce flow, this can oversell food/combo inventory.

How to fix

Keep this accessor for display only, and ensure checkout/order creation performs stock validation and deduction inside a database transaction using row locks on all required product records.

Example

```php
DB::transaction(function () use ($combo) {
    $items = $combo->comboItems()
        ->with(['product' => fn ($query) => $query->lockForUpdate()])
        ->get();

    foreach ($items as $item) {
        if ($item->product->stock < $item->quantity) {
            throw new DomainException('Insufficient combo stock.');
        }

        $item->product->decrement('stock', $item->quantity);
    }
});
```

----------------------------------------------------

### Issue #4

Severity:
Medium

Category:
Mass Assignment / Business Logic

Location:
app/Models/Combo.php:13-20

Problem

The model allows mass assignment of price and status fields:

```php
protected $fillable = [
    'name',
    'price',
    'original_price',
    'image_url',
    'description',
    'status',
];
```

Why this matters

`price`, `original_price`, and `status` are business-sensitive fields. If controllers/services pass broad request payloads to `create()` or `update()`, a caller with access to a weak endpoint can alter sell price, fake discount values, or activate/deactivate a combo. In commerce systems, price mutation must be validated, authorized, auditable, and preferably centralized.

How to fix

Avoid raw request mass assignment for commerce fields. Use explicit DTOs/service methods and audit price/status changes.

Example

```php
$combo->forceFill([
    'name' => $data->name,
    'description' => $data->description,
])->save();

$comboPricingService->changePrice($combo, $data->price, $actor);
```

----------------------------------------------------

### Issue #5

Severity:
Medium

Category:
Money Handling / Database Correctness

Location:
app/Models/Combo.php:22-24

Problem

The model casts money values using Eloquent decimal strings:

```php
protected $casts = [
    'price' => 'decimal:2',
    'original_price' => 'decimal:2',
];
```

Why this matters

Laravel decimal casts return strings, not integer minor units. If other code performs arithmetic using these values as floats, rounding bugs can appear in order totals, discounts, and revenue reports. The model also does not enforce that `price <= original_price`, that values are non-negative, or that precision matches the database schema.

How to fix

Use integer minor units for money where possible, or use a dedicated Money value object. Enforce invariants in validation and the database.

Example

```php
'price' => ['required', 'integer', 'min:0'],
'original_price' => ['nullable', 'integer', 'gte:price'],
```

If keeping decimals, enforce decimal precision and compare using string-safe decimal handling.

----------------------------------------------------

### Issue #6

Severity:
Medium

Category:
Performance / N+1 Queries

Location:
app/Models/Combo.php:39-42

Problem

The accessor performs a query every time `available_stock` is accessed:

```php
public function getAvailableStockAttribute(): int
{
    $items = $this->comboItems()->with('product')->get();
```

Why this matters

If a combo collection is serialized and includes `available_stock`, this creates one query per combo plus relationship/product loading. On catalog pages, admin lists, or analytics screens, this becomes an N+1 performance issue.

How to fix

Prefer preloading `comboItems.product` in the service/query layer and make the accessor use the already-loaded relation when available.

Example

```php
public function getAvailableStockAttribute(): int
{
    $items = $this->relationLoaded('comboItems')
        ? $this->comboItems
        : $this->comboItems()->with('product')->get();

    // calculate...
}
```

Service/query layer:

```php
Combo::with('comboItems.product')->active()->get();
```

----------------------------------------------------

### Issue #7

Severity:
Medium

Category:
Data Integrity / Soft Deletes

Location:
app/Models/Combo.php:11

Problem

The model uses soft deletes but does not define guards against deleting combos that may be referenced by orders or order items:

```php
use SoftDeletes;
```

Why this matters

Soft deletion preserves the row, but API queries may exclude soft-deleted combos by default. Historical orders, invoices, revenue reports, and analytics can become inconsistent if order records depend on live `Combo` lookups rather than denormalized purchase snapshots.

How to fix

Define relationships to order items if applicable, prevent unsafe deletion when referenced, and store immutable combo name/price snapshots on order items.

Example

```php
protected static function booted(): void
{
    static::deleting(function (Combo $combo) {
        if (! $combo->isForceDeleting() && $combo->orderItems()->exists()) {
            throw new DomainException('Cannot delete combo referenced by orders.');
        }
    });
}
```

----------------------------------------------------

### Issue #8

Severity:
Medium

Category:
API / Security

Location:
app/Models/Combo.php:17-18

Problem

The model stores raw `image_url` and `description` fields as fillable:

```php
'image_url',
'description',
```

Why this matters

If these values are returned directly through APIs or rendered in an admin/frontend without sanitization, they can become stored-XSS or unsafe URL vectors. `image_url` can also point to untrusted external resources if not constrained.

How to fix

Validate and normalize content at request/service boundaries. Restrict image references to controlled storage paths or validated URL schemes. Sanitize rich text descriptions before persistence or before rendering.

Example

```php
'image_url' => ['nullable', 'string', 'max:2048', 'starts_with:/storage/'],
'description' => ['nullable', 'string', 'max:5000'],
```

If HTML descriptions are supported, use a strict sanitizer and document allowed tags.

----------------------------------------------------

### Issue #9

Severity:
Low

Category:
Laravel Best Practices / Type Safety

Location:
app/Models/Combo.php:62-75

Problem

The scopes do not type-hint the Eloquent builder or return type:

```php
public function scopeActive($query)
{
    return $query->where('status', 1);
}

public function scopeInStock($query)
{
    return $query->whereHas('comboItems.product', function ($q) {
        $q->where('stock', '>', 0);
    });
}
```

Why this matters

Untyped scopes reduce static-analysis quality and make model APIs less explicit.

How to fix

Type the query builder and closure parameter.

Example

```php
use Illuminate\Database\Eloquent\Builder;

public function scopeActive(Builder $query): Builder
{
    return $query->where('status', true);
}

public function scopeInStock(Builder $query): Builder
{
    return $query->whereHas('comboItems.product', function (Builder $query): void {
        $query->where('stock', '>', 0);
    });
}
```

----------------------------------------------------

### Issue #10

Severity:
Low

Category:
Readability / Maintainability

Location:
app/Models/Combo.php:28-38, 59-69

Problem

The model comments are written in Vietnamese:

```php
/**
 * Các món trong combo
 */
```

```php
/**
 * Tính tồn kho khả dụng (dựa trên món con)
 */
```

Why this matters

Mixed-language code comments reduce maintainability for distributed teams unless the project has explicitly standardized on that language. Production code should have a consistent language policy for comments, API messages, and documentation.

How to fix

Use the team's standard language consistently. If the codebase standard is English, translate comments.

Example

```php
/**
 * Items included in the combo.
 */
```

----------------------------------------------------

Final Assessment

`Combo` contains useful relationship and availability logic, but the current implementation is not production-safe for commerce. The biggest problems are the incorrect `inStock()` scope, division-by-zero risk, non-atomic stock availability, and mass-assignable price/status fields. Combo availability and stock deduction must be handled transactionally in the order flow, and this model should not be treated as the source of checkout-safe inventory decisions without locks and stronger invariants.
