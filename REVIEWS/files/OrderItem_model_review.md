====================================================

File:
app/Models/OrderItem.php

Overall Score:
4.9/10

Decision:
REQUEST CHANGES

----------------------------------------------------

Strengths

- Keeps order item data in a dedicated model instead of embedding everything inside the order payload.
- Defines monetary casts for `unit_price` and `total_price`.
- Defines a direct `order()` relationship.
- Uses a polymorphic `item()` relationship to support multiple purchasable item types.

----------------------------------------------------

Issues

### Issue #1

Severity:
Critical

Category:
Authorization / Mass Assignment

Location:
app/Models/OrderItem.php:10-18

Problem

Critical ownership, product identity, quantity, and price fields are mass assignable:

```php
protected $fillable = [
    'order_id',
    'item_type',
    'item_id',
    'quantity',
    'unit_price',
    'total_price',
    'metadata',
];
```

Why this matters

Order items directly affect what the customer receives and what the system charges. If any controller or service uses request-driven mass assignment, a caller can attach items to another order, change item types, manipulate quantities, set arbitrary prices, or inject metadata. This can lead to undercharging, unauthorized products, corrupted orders, or IDOR-style order tampering.

How to fix

Do not allow raw request payloads to mass assign order item ownership or prices. Build order items server-side from validated catalog/seat selections and authoritative pricing.

Example

```php
protected $fillable = [
    'metadata',
];
```

Or keep guarded and create through explicit service methods:

```php
protected $guarded = ['id'];

$order->orderItems()->create([
    'item_type' => Product::class,
    'item_id' => $product->id,
    'quantity' => $quantity,
    'unit_price' => $catalogPrice,
    'total_price' => bcmul((string) $catalogPrice, (string) $quantity, 2),
]);
```

----------------------------------------------------

### Issue #2

Severity:
Critical

Category:
Business Logic / Monetary Correctness

Location:
app/Models/OrderItem.php:14-16, 20-23

Problem

The model stores both `unit_price` and `total_price` but has no invariant enforcing:

```php
total_price = unit_price * quantity
```

Relevant fields:

```php
'quantity',
'unit_price',
'total_price',
```

```php
'unit_price' => 'decimal:2',
'total_price' => 'decimal:2',
```

Why this matters

Order items are financial records. If `total_price` can diverge from `unit_price * quantity`, revenue reporting, payment reconciliation, refunds, order totals, and analytics become unreliable. This can directly lose money or cause customer disputes.

How to fix

Calculate item totals server-side and enforce non-negative values. Consider deriving `total_price` instead of accepting it as input.

Example

```php
public static function fromPricedItem(Model $item, int $quantity, string $unitPrice): self
{
    if ($quantity < 1) {
        throw new InvalidArgumentException('Quantity must be positive.');
    }

    return new self([
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'total_price' => bcmul($unitPrice, (string) $quantity, 2),
    ]);
}
```

Also add database checks where supported:

```php
$table->check('quantity > 0');
$table->check('unit_price >= 0');
$table->check('total_price >= 0');
```

----------------------------------------------------

### Issue #3

Severity:
High

Category:
Database Correctness / Polymorphic Integrity

Location:
app/Models/OrderItem.php:12-13, 31-34

Problem

The model uses a polymorphic relationship:

```php
'item_type',
'item_id',
```

```php
public function item()
{
    return $this->morphTo();
}
```

Polymorphic relationships cannot enforce database-level foreign keys for each item type.

Why this matters

Order items are transactional records. Without referential integrity, rows can point to deleted or non-existent products, combos, seats, tickets, or arbitrary model classes. This breaks fulfillment, refunds, analytics, and customer order history.

How to fix

Use explicit item snapshot columns plus controlled item type enum, or separate normalized tables for ticket items and product/combo items. If polymorphism remains, enforce a morph map and validate allowed classes before persistence.

Example

```php
Relation::enforceMorphMap([
    'product' => Product::class,
    'combo' => Combo::class,
]);
```

Add validation that `item_type` is one of the allowed morph aliases and that `item_id` exists for that type.

----------------------------------------------------

### Issue #4

Severity:
High

Category:
Security / Class Injection Risk

Location:
app/Models/OrderItem.php:12, 31-34

Problem

`item_type` is mass assignable while `item()` resolves it polymorphically:

```php
'item_type',
```

```php
return $this->morphTo();
```

Why this matters

If user-controlled input reaches `item_type`, an attacker may store unexpected model class names or unsupported morph aliases. Even when not immediately exploitable, this creates unsafe object resolution paths, data confusion, and broken API serialization.

How to fix

Never accept `item_type` from clients. Use a strict morph map and server-side mapping from request item kind to model class.

Example

```php
$allowedTypes = [
    'product' => Product::class,
    'combo' => Combo::class,
];

$itemType = $allowedTypes[$validated['type']] ?? throw new InvalidArgumentException();
```

----------------------------------------------------

### Issue #5

Severity:
High

Category:
Validation / Business Logic

Location:
app/Models/OrderItem.php:14

Problem

`quantity` is persisted without any model-level or documented database invariant:

```php
'quantity',
```

Why this matters

Zero or negative quantity order items can create free items, negative totals, inventory corruption, incorrect combo stock calculations, and refund inconsistencies.

How to fix

Validate `quantity >= 1` at request/service boundaries and enforce a database check.

Example

```php
$table->unsignedInteger('quantity');
$table->check('quantity > 0');
```

----------------------------------------------------

### Issue #6

Severity:
High

Category:
Database Correctness / Historical Pricing

Location:
app/Models/OrderItem.php:12-17, 31-34

Problem

The model stores a link to a live purchasable item and a generic metadata JSON field:

```php
'item_type',
'item_id',
'metadata',
```

but has no explicit immutable snapshot fields such as item name, SKU, seat label, showtime details, or pricing rules at purchase time.

Why this matters

Catalog products, combos, movie names, seat labels, and pricing rules can change after purchase. Order history, receipts, refunds, audit, and tax/revenue reporting must reflect what was purchased at the time, not the current state of related records.

How to fix

Store an immutable item snapshot on the order item.

Example

```php
$item_name
$item_sku
$item_category
$seat_label
$pricing_snapshot
```

If metadata is used for snapshots, validate its schema and never treat it as arbitrary JSON.

----------------------------------------------------

### Issue #7

Severity:
High

Category:
Security / Sensitive Data Exposure

Location:
app/Models/OrderItem.php:17, 20-24

Problem

The model accepts arbitrary JSON metadata:

```php
'metadata',
```

```php
'metadata' => 'json',
```

Why this matters

Unbounded metadata can store PII, internal pricing decisions, promotion abuse signals, raw request payloads, or payment information. If order items are returned by APIs or logged, this can expose sensitive data.

How to fix

Define a strict metadata schema per item type and redact sensitive values before persistence.

Example

```php
[
    'seat_label' => 'A1',
    'pricing_rule_id' => 123,
    'discount_code' => 'SUMMER10',
]
```

Do not persist raw client payloads or gateway data in `metadata`.

----------------------------------------------------

### Issue #8

Severity:
Medium

Category:
Database Correctness / Duplicate Items

Location:
app/Models/OrderItem.php:11-13

Problem

The model has no uniqueness rule preventing duplicate line items for the same order and item identity:

```php
'order_id',
'item_type',
'item_id',
```

Why this matters

Duplicate lines may be valid in some systems, but if not intentionally modeled they can break quantity aggregation, stock decrement logic, refunds, and analytics. For seats, duplicate line items can represent duplicate ticket creation attempts.

How to fix

Define domain-specific uniqueness. For catalog items, merge quantities. For seats/tickets, enforce uniqueness per order and seat/showtime.

Example

```php
$table->unique(['order_id', 'item_type', 'item_id']);
```

Only add this if duplicate rows are not a deliberate requirement.

----------------------------------------------------

### Issue #9

Severity:
Medium

Category:
Performance / Indexing

Location:
app/Models/OrderItem.php:11-13, 26-34

Problem

The model relationships and polymorphic lookup depend on:

```php
'order_id',
'item_type',
'item_id',
```

but there is no indication of supporting indexes.

Why this matters

Order details, revenue analytics, product sales reports, and fulfillment queries will frequently join/filter by order and item identity. Missing indexes will cause table scans as order volume grows.

How to fix

Add indexes in migrations.

Example

```php
$table->index('order_id');
$table->index(['item_type', 'item_id']);
$table->index(['order_id', 'item_type']);
```

----------------------------------------------------

### Issue #10

Severity:
Medium

Category:
Laravel Best Practices / Type Safety

Location:
app/Models/OrderItem.php:31-34

Problem

The `item()` relationship has no return type:

```php
public function item()
{
    return $this->morphTo();
}
```

Why this matters

Untyped relationships reduce static analysis quality and make future refactors more error-prone.

How to fix

Import and return `MorphTo`.

Example

```php
use Illuminate\Database\Eloquent\Relations\MorphTo;

public function item(): MorphTo
{
    return $this->morphTo();
}
```

----------------------------------------------------

### Issue #11

Severity:
Medium

Category:
Architecture / Domain Modeling

Location:
app/Models/OrderItem.php:8-35

Problem

The model is a passive data container and does not expose domain methods for price calculation, quantity validation, item snapshots, or immutable post-payment behavior.

Why this matters

Order item behavior will be scattered across controllers/services, increasing the risk of inconsistent price calculation and mutation after payment.

How to fix

Centralize order item creation and mutation rules in a domain/service layer and make paid order items immutable.

Example

```php
public function assertMutable(): void
{
    if ($this->order && $this->order->status === Order::STATUS_PAID) {
        throw new DomainException('Paid order items cannot be modified.');
    }
}
```

----------------------------------------------------

### Issue #12

Severity:
Low

Category:
Laravel Best Practices

Location:
app/Models/OrderItem.php:8-35

Problem

The model does not use `HasFactory`:

```php
class OrderItem extends Model
```

Why this matters

Factories make model behavior easier to test. Order items are central to checkout, fulfillment, and analytics and need strong test coverage.

How to fix

Add `HasFactory`.

Example

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderItem extends Model
{
    use HasFactory;
}
```

----------------------------------------------------

### Issue #13

Severity:
Low

Category:
Maintainability / Type Documentation

Location:
app/Models/OrderItem.php:8-35

Problem

The model has no PHPDoc annotations for dynamic properties and relationships.

Why this matters

Order item fields are financially sensitive. Static analysis should understand fields like `quantity`, `unit_price`, `total_price`, `metadata`, and the polymorphic `item` relationship.

How to fix

Add PHPDoc or Laravel IDE helper metadata.

Example

```php
/**
 * @property int $id
 * @property int $order_id
 * @property string $item_type
 * @property int $item_id
 * @property int $quantity
 * @property string $unit_price
 * @property string $total_price
 * @property array|null $metadata
 */
class OrderItem extends Model
{
    // ...
}
```

----------------------------------------------------

Final Assessment

`OrderItem` is too permissive for a production booking and commerce domain. It allows mass assignment of ownership, item identity, quantities, and monetary values; relies on polymorphic references without database integrity; and lacks invariants for quantity, totals, snapshots, and post-payment immutability. These issues can directly affect revenue, fulfillment, refunds, analytics, and order auditability.
