====================================================

File:
app/Models/ComboItem.php

Overall Score:
6.1/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Defines explicit `combo()` and `product()` `BelongsTo` relationships.
- Casts `quantity` to integer.
- Small model with limited responsibilities.
- Uses explicit foreign-key names in relationships, which makes the mapping clear.

----------------------------------------------------

Issues

### Issue #1

Severity:
High

Category:
Data Integrity / Validation

Location:
app/Models/ComboItem.php:10-14

Problem

`quantity` is mass assignable but the model does not enforce that it is positive:

```php
protected $fillable = [
    'combo_id',
    'product_id',
    'quantity',
];
```

The only local constraint is an integer cast:

```php
protected $casts = [
    'quantity' => 'integer',
];
```

Why this matters

A `quantity` of `0` or a negative value breaks combo inventory calculation. `Combo::getAvailableStockAttribute()` divides product stock by `$item->quantity`, so zero can cause a division-by-zero error and negative quantities can produce invalid stock availability. This can break APIs and corrupt commerce calculations.

How to fix

Validate `quantity` at request/service level and enforce it at database level. Add defensive domain rules if combo items are modified through services.

Example

```php
'items.*.quantity' => ['required', 'integer', 'min:1']
```

Database constraint:

```php
$table->unsignedInteger('quantity');
```

For MySQL 8+, add a check constraint where supported:

```php
$table->check('quantity > 0');
```

----------------------------------------------------

### Issue #2

Severity:
High

Category:
Business Logic / Data Integrity

Location:
app/Models/ComboItem.php:10-14

Problem

The model allows mass assignment of both foreign keys:

```php
'combo_id',
'product_id',
```

Why this matters

If controllers or services pass request payloads directly to `ComboItem::create()` or `update()`, a caller may re-parent combo items to a different combo or product if endpoint authorization/validation is incomplete. In catalog management, changing `product_id` or `combo_id` changes sellable product composition and inventory behavior.

How to fix

Do not mass assign relationship ownership fields from arbitrary request data. Create combo items through aggregate methods on the owning combo or through a service that explicitly validates authorization and product existence.

Example

```php
$combo->comboItems()->create([
    'product_id' => $validatedProductId,
    'quantity' => $validatedQuantity,
]);
```

For updates, usually allow quantity changes only, and recreate composition changes through a controlled service operation.

----------------------------------------------------

### Issue #3

Severity:
High

Category:
Database Correctness / Duplicate Data

Location:
app/Models/ComboItem.php:8-31

Problem

The model does not declare or imply any uniqueness invariant preventing duplicate product rows within the same combo.

Why this matters

If the database allows multiple rows with the same `combo_id` and `product_id`, one combo can contain duplicate item definitions for the same product. This makes stock availability, price composition, order fulfillment, and admin UI behavior ambiguous. Duplicate rows can also cause double deduction or inconsistent display depending on which part of the system aggregates quantities.

How to fix

Add a composite unique index on `(combo_id, product_id)` and merge quantities intentionally through service logic.

Example

```php
$table->unique(['combo_id', 'product_id']);
```

Service behavior:

```php
$combo->comboItems()->updateOrCreate(
    ['product_id' => $productId],
    ['quantity' => $quantity]
);
```

----------------------------------------------------

### Issue #4

Severity:
Medium

Category:
Database Correctness / Referential Integrity

Location:
app/Models/ComboItem.php:21-30

Problem

The relationships are declared, but the model itself does not show any safeguards for missing parent records:

```php
public function combo(): BelongsTo
{
    return $this->belongsTo(Combo::class, 'combo_id');
}

public function product(): BelongsTo
{
    return $this->belongsTo(Product::class, 'product_id');
}
```

Why this matters

If the database does not enforce foreign keys, orphaned combo items can exist. Orphaned product references break stock calculations and can make active combos unsellable. Since the source file alone does not show database constraints, this model should not be considered production-safe without verifying migrations.

How to fix

Enforce foreign keys in migrations with appropriate delete behavior.

Example

```php
$table->foreignId('combo_id')
    ->constrained('combos')
    ->cascadeOnDelete();

$table->foreignId('product_id')
    ->constrained('products')
    ->restrictOnDelete();
```

Use `restrictOnDelete()` for products if historical combo definitions should not silently lose product composition.

----------------------------------------------------

### Issue #5

Severity:
Medium

Category:
Commerce Correctness / Soft Deletes

Location:
app/Models/ComboItem.php:8-31

Problem

`ComboItem` does not use `SoftDeletes` while `Combo` does.

Why this matters

If combo composition is changed after orders have been placed, historical reporting or reconciliation may need to know what the combo contained at purchase time. Hard-deleting combo item rows loses composition history unless order items store a full immutable snapshot elsewhere. For commerce, catalog changes should not destroy data needed to explain historical sales.

How to fix

Either store complete combo composition snapshots on order items at purchase time or add versioning/auditability for combo composition changes. Do not rely on mutable current `combo_items` rows to explain historical orders.

Example

```php
// On checkout/order creation:
[
    'combo_id' => $combo->id,
    'combo_name' => $combo->name,
    'combo_price' => $combo->price,
    'combo_items_snapshot' => $combo->comboItems()
        ->with('product:id,name')
        ->get()
        ->map(fn ($item) => [
            'product_id' => $item->product_id,
            'product_name' => $item->product?->name,
            'quantity' => $item->quantity,
        ]),
]
```

----------------------------------------------------

### Issue #6

Severity:
Medium

Category:
Concurrency / Inventory

Location:
app/Models/ComboItem.php:27-30

Problem

The `product()` relationship is a normal `belongsTo()` relationship with no domain method for locking product rows during stock deduction:

```php
public function product(): BelongsTo
{
    return $this->belongsTo(Product::class, 'product_id');
}
```

Why this matters

This model participates directly in stock-sensitive combo inventory. Reading related products without a transaction and row locks is not safe for checkout. Concurrent orders can both see enough stock and both deduct unless the service layer locks products consistently.

How to fix

Keep the relationship simple, but ensure order/checkout services use a dedicated method that locks all combo products in a deterministic order inside a transaction.

Example

```php
$items = $combo->comboItems()
    ->orderBy('product_id')
    ->with(['product' => fn ($query) => $query->lockForUpdate()])
    ->get();
```

Then validate and decrement stock inside the same transaction.

----------------------------------------------------

### Issue #7

Severity:
Low

Category:
Laravel Best Practices / Type Safety

Location:
app/Models/ComboItem.php:8-31

Problem

The model does not document its properties or relationship return types beyond method declarations.

Why this matters

For small Eloquent models this is common, but property annotations improve static analysis, IDE completion, and maintainability for commerce-critical fields.

How to fix

Add model PHPDoc or use typed DTOs at service boundaries.

Example

```php
/**
 * @property int $combo_id
 * @property int $product_id
 * @property int $quantity
 * @property-read Combo $combo
 * @property-read Product $product
 */
class ComboItem extends Model
{
    // ...
}
```

----------------------------------------------------

### Issue #8

Severity:
Low

Category:
Clean Code / Formatting

Location:
app/Models/ComboItem.php:20-27

Problem

There are unnecessary blank lines before relationship methods:

```php


public function combo(): BelongsTo
```

and:

```php


public function product(): BelongsTo
```

Why this matters

Minor formatting inconsistency reduces polish and makes the codebase look less maintained. It should be enforced by Pint/PHPCS.

How to fix

Run Laravel Pint and keep one blank line between class members.

Example

```php
protected $casts = [
    'quantity' => 'integer',
];

public function combo(): BelongsTo
{
    return $this->belongsTo(Combo::class, 'combo_id');
}
```

----------------------------------------------------

Final Assessment

`ComboItem` is structurally simple, but it sits on a commerce-critical path because it defines combo composition and product stock requirements. The current model is missing explicit invariants around positive quantities, duplicate product rows per combo, safe ownership assignment, and historical composition integrity. These gaps can lead to broken stock calculations, duplicate inventory deduction, and unreliable order history unless the service and database layers enforce the missing rules.
