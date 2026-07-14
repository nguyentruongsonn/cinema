====================================================

File:
app/Models/Product.php

Overall Score:
5.7/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Uses `SoftDeletes`, which is appropriate for products referenced by historical orders.
- Defines casts for price, stock, and active status.
- Provides relationships to polymorphic order items and combo composition records.
- Provides basic scopes for active products, type filtering, and stock availability.
- Uses `decimal:2` for price instead of a float cast.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Authorization / Mass Assignment

Location:
app/Models/Product.php:13-21

Problem

The model allows all commerce-critical fields to be mass assigned:

```php
protected $fillable = [
    'name',
    'type',
    'price',
    'stock',
    'image_url',
    'description',
    'status',
];
```

Why this matters

Products affect checkout totals and inventory availability. If request data is passed to `create()` or `update()`, a caller or compromised admin endpoint can directly change price, stock, type, image URL, and active status. This can cause undercharging, overselling, hidden products becoming available, or inventory tampering.

How to fix

Only mutate product financial and inventory fields through explicitly authorized service methods with validated inputs.

Example

```php
$product->forceFill([
    'name' => $validated['name'],
    'type' => $validated['type'],
    'description' => $validated['description'] ?? null,
])->save();

$product->updatePrice($validatedPrice, $actor);
$product->adjustStock($delta, $actor);
```

Audit price and stock changes.

----------------------------------------------------

### Issue #2

Severity:
High

Category:
Business Logic / Monetary Correctness

Location:
app/Models/Product.php:16, 23-27

Problem

The product price is cast as decimal, but the model does not enforce non-negative or minimum-price invariants:

```php
'price',
```

```php
'price' => 'decimal:2',
```

Why this matters

A negative or zero price can produce invalid order totals, free concessions, refund/accounting errors, or payment gateway amount mismatches. Casting to `decimal:2` only controls representation; it does not validate business correctness.

How to fix

Validate at request/service level and enforce invariants in the domain layer.

Example

```php
'price' => ['required', 'decimal:0,2', 'min:0.01'];
```

For stricter protection, add a database check constraint:

```php
$table->check('price >= 0');
```

----------------------------------------------------

### Issue #3

Severity:
High

Category:
Inventory / Database Correctness

Location:
app/Models/Product.php:17, 23-27, 44-47

Problem

Stock is mutable and only cast to integer:

```php
'stock',
```

```php
'stock' => 'integer',
```

The in-stock scope only checks positive stock:

```php
public function scopeInStock($query)
{
    return $query->where('stock', '>', 0);
}
```

There is no invariant preventing negative stock.

Why this matters

Negative stock indicates overselling or invalid inventory adjustments. In a cinema ordering flow, concurrent checkout requests can decrement stock below zero unless stock updates are atomic and constrained.

How to fix

Enforce non-negative stock and perform stock deductions atomically.

Example

```php
$updated = Product::whereKey($productId)
    ->where('stock', '>=', $quantity)
    ->decrement('stock', $quantity);

if ($updated === 0) {
    throw new RuntimeException('Insufficient stock.');
}
```

Add a database check:

```php
$table->check('stock >= 0');
```

----------------------------------------------------

### Issue #4

Severity:
High

Category:
Concurrency / Overselling

Location:
app/Models/Product.php:17, 44-47

Problem

The model exposes stock availability via a read scope:

```php
public function scopeInStock($query)
{
    return $query->where('stock', '>', 0);
}
```

but there is no model-level atomic reservation/decrement API.

Why this matters

A read-then-write inventory flow is race-prone. Two checkout requests can both observe stock as available and both complete, overselling products or combos. This directly impacts customer experience and refund operations.

How to fix

Inventory changes must be done in a transaction using conditional updates or row locks.

Example

```php
DB::transaction(function () use ($product, $quantity) {
    $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

    if ($product->stock < $quantity) {
        throw new RuntimeException('Insufficient stock.');
    }

    $product->decrement('stock', $quantity);
});
```

Use conditional `decrement` for higher throughput where appropriate.

----------------------------------------------------

### Issue #5

Severity:
Medium

Category:
Security / Unsafe File Reference

Location:
app/Models/Product.php:18

Problem

`image_url` is mass assignable:

```php
'image_url',
```

Why this matters

If arbitrary URLs or paths are accepted, stored product records can reference external tracking URLs, unsafe content, or files outside the intended storage flow. Product images are public-facing and can become a persistent content security and privacy issue.

How to fix

Only store image paths produced by a validated upload service or allowlisted CDN URL.

Example

```php
$product->forceFill([
    'image_url' => $storedPath,
])->save();
```

Validate MIME type, size, extension, storage disk, and URL host when external URLs are allowed.

----------------------------------------------------

### Issue #6

Severity:
Medium

Category:
Business Logic / Type Safety

Location:
app/Models/Product.php:15, 39-42

Problem

Product type is a free-form field and the scope accepts arbitrary input:

```php
'type',
```

```php
public function scopeByType($query, $type)
{
    return $query->where('type', $type);
}
```

Why this matters

Product type drives catalog filtering and may determine fulfillment behavior. Free-form values can create inconsistent categories, broken filters, and unhandled product behavior in checkout or combo composition.

How to fix

Use an enum or controlled reference table for product types.

Example

```php
enum ProductType: string
{
    case Food = 'food';
    case Drink = 'drink';
    case Merchandise = 'merchandise';
}
```

Validate and normalize type before persistence.

----------------------------------------------------

### Issue #7

Severity:
Medium

Category:
Database Correctness / Historical Order Integrity

Location:
app/Models/Product.php:29-32

Problem

The model is linked to historical order items through a polymorphic relationship:

```php
public function orderItems(): MorphMany
{
    return $this->morphMany(OrderItem::class, 'item');
}
```

but the model allows price/name/type to be changed in place.

Why this matters

Historical orders must preserve what was sold and at what price at purchase time. If order items depend on current product attributes instead of a complete snapshot, product edits can corrupt order history, receipts, analytics, and financial reconciliation.

How to fix

Ensure order items store immutable snapshots of product name, type, unit price, and relevant metadata at checkout time. Do not calculate historical revenue from mutable product state.

Example

```php
$orderItem->forceFill([
    'item_id' => $product->id,
    'item_type' => Product::class,
    'item_name' => $product->name,
    'unit_price' => $product->price,
    'quantity' => $quantity,
])->save();
```

----------------------------------------------------

### Issue #8

Severity:
Medium

Category:
Database Correctness / Deletion Lifecycle

Location:
app/Models/Product.php:7, 11, 29-32, 52-55

Problem

The model uses soft deletes:

```php
use SoftDeletes;
```

and is referenced by order items and combo items:

```php
return $this->morphMany(OrderItem::class, 'item');
```

```php
return $this->hasMany(ComboItem::class, 'product_id');
```

but there is no lifecycle guard for deleting products that are part of active combos or pending orders.

Why this matters

Soft deleting a product can break active combo availability, checkout flows, admin inventory views, and fulfillment logic. Historical order references are preserved, but current commerce records can still become inconsistent.

How to fix

Prevent deletion of products used by active combos or pending orders. Prefer deactivation over deletion for catalog items involved in commerce flows.

Example

```php
protected static function booted(): void
{
    static::deleting(function (Product $product) {
        if ($product->usedInCombos()->whereHas('combo', fn ($q) => $q->where('status', true))->exists()) {
            throw new DomainException('Cannot delete product used by active combos.');
        }
    });
}
```

----------------------------------------------------

### Issue #9

Severity:
Medium

Category:
Laravel Best Practices / Type Safety

Location:
app/Models/Product.php:34-47, 52-55

Problem

Several model methods lack typed `Builder` parameters and relationship return types:

```php
public function scopeActive($query)
public function scopeByType($query, $type)
public function scopeInStock($query)
public function usedInCombos()
```

Why this matters

Untyped scopes and relationships reduce static analysis quality and make refactoring commerce logic more error-prone.

How to fix

Add imports and return types.

Example

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

public function scopeActive(Builder $query): Builder
{
    return $query->where('status', true);
}

public function usedInCombos(): HasMany
{
    return $this->hasMany(ComboItem::class, 'product_id');
}
```

----------------------------------------------------

### Issue #10

Severity:
Medium

Category:
Performance / Database Indexing

Location:
app/Models/Product.php:34-47

Problem

The model exposes common catalog scopes:

```php
public function scopeActive($query)
{
    return $query->where('status', 1);
}

public function scopeByType($query, $type)
{
    return $query->where('type', $type);
}

public function scopeInStock($query)
{
    return $query->where('stock', '>', 0);
}
```

There is no indication of supporting indexes for active catalog lookups.

Why this matters

Product catalog and checkout selection queries commonly filter by `status`, `type`, and stock availability. Without indexes, public menu pages and checkout flows can degrade as product history grows.

How to fix

Add indexes aligned with query patterns.

Example

```php
$table->index(['status', 'type']);
$table->index(['status', 'stock']);
$table->index(['type', 'status', 'stock']);
```

----------------------------------------------------

### Issue #11

Severity:
Low

Category:
Laravel Best Practices / Testing

Location:
app/Models/Product.php:5-11

Problem

The model does not use `HasFactory`:

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
```

Why this matters

Products are central to checkout and combo testing. Missing factories makes it harder to test inventory, order item snapshots, and combo composition scenarios.

How to fix

Add `HasFactory`.

Example

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;
}
```

----------------------------------------------------

### Issue #12

Severity:
Low

Category:
Clean Code / Magic Values

Location:
app/Models/Product.php:36

Problem

The active scope uses a magic integer for a boolean-cast field:

```php
return $query->where('status', 1);
```

Why this matters

The column is cast to boolean, but the query uses an integer. This is inconsistent and less expressive. The field name `status` is also vague for a boolean.

How to fix

Use boolean semantics and consider renaming the column to `is_active`.

Example

```php
return $query->where('status', true);
```

----------------------------------------------------

### Issue #13

Severity:
Low

Category:
Readability / Comment Consistency

Location:
app/Models/Product.php:49-51

Problem

The model contains a non-English inline comment:

```php
/**
 * Các combo có chứa món này (product được dùng trong combo nào)
 */
```

Why this matters

In a shared production codebase, comments should use a consistent project language so all maintainers can understand domain intent.

How to fix

Use a consistent language for comments or remove comments when the method name is self-explanatory.

Example

```php
/**
 * Combos that include this product.
 */
```

----------------------------------------------------

### Issue #14

Severity:
Low

Category:
Maintainability / Type Documentation

Location:
app/Models/Product.php:9-56

Problem

The model has no PHPDoc annotations for dynamic properties and relationships.

Why this matters

Static analysis cannot infer important commerce fields such as `price`, `stock`, `status`, and polymorphic order item relationships without helper metadata.

How to fix

Add PHPDoc or Laravel IDE helper metadata.

Example

```php
/**
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string $price
 * @property int $stock
 * @property string|null $image_url
 * @property bool $status
 */
class Product extends Model
{
    // ...
}
```

----------------------------------------------------

Final Assessment

`Product` is not production-ready for a commerce flow without stronger invariants. It allows broad mass assignment of price, stock, type, image URL, and active status; has no atomic inventory API; does not enforce non-negative price/stock; and lacks lifecycle protection for products used by combos or orders. These weaknesses can cause overselling, invalid checkout totals, inconsistent catalog behavior, and broken historical reconciliation.
