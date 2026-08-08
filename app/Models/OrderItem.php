<?php

namespace App\Models;

use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

/**
 * Order Item Model
 *
 * Represents a line item in an order (ticket, product, or combo purchase).
 *
 * @property int $id
 * @property int $order_id
 * @property string $item_type Morph alias: 'product', 'combo', 'ticket'
 * @property int $item_id
 * @property int $quantity
 * @property string $unit_price Decimal(10,2)
 * @property string $total_price Decimal(10,2) - calculated as unit_price * quantity
 * @property array|null $metadata Item snapshot (name, SKU, seat_label, pricing_rule, etc.)
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Order $order
 * @property-read Model $item Polymorphic relation to Product, Combo, or Ticket
 */
class OrderItem extends Model
{
    use HasFactory;

    /**
     * Allowed fields for mass assignment.
     */
    protected $fillable = [
        'order_id',
        'item_type',
        'item_id',
        'quantity',
        'unit_price',
        'total_price',
        'metadata',
        'fulfillment_status',
        'fulfilled_by_user_id',
        'fulfilled_at',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'metadata' => 'json',
        'quantity' => 'integer',
        'fulfilled_at' => 'datetime',
    ];

    public const FULFILLMENT_PENDING = 'pending';

    public const FULFILLMENT_FULFILLED = 'fulfilled';

    public function fulfilledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by_user_id');
    }

    /**
     * Allowed polymorphic item types (using class names as required by existing codebase)
     */
    public const ALLOWED_ITEM_TYPES = [
        Product::class,
        Combo::class,
        Seat::class,   // Seat reservation before payment/ticket issuance
        Ticket::class, // After payment fulfillment
    ];

    /**
     * Create order item from a product purchase
     */
    public static function createFromProduct(Order $order, Product $product, int $quantity, string $unitPrice, array $metadata = []): self
    {
        self::validateQuantity($quantity);
        self::validatePrice($unitPrice);

        $item = new self;
        $item->order_id = $order->id;
        $item->item_type = Product::class;
        $item->item_id = $product->id;
        $item->quantity = $quantity;
        $item->unit_price = $unitPrice;
        $item->total_price = self::calculateTotal($unitPrice, $quantity);
        $item->metadata = array_merge([
            'name' => $product->name,
            'type' => $product->type,
            'image' => $product->image_url,
        ], $metadata);

        return $item;
    }

    /**
     * Create order item from a combo purchase
     */
    public static function createFromCombo(Order $order, Combo $combo, int $quantity, string $unitPrice, array $metadata = []): self
    {
        self::validateQuantity($quantity);
        self::validatePrice($unitPrice);

        $item = new self;
        $item->order_id = $order->id;
        $item->item_type = Combo::class;
        $item->item_id = $combo->id;
        $item->quantity = $quantity;
        $item->unit_price = $unitPrice;
        $item->total_price = self::calculateTotal($unitPrice, $quantity);
        $item->metadata = array_merge([
            'name' => $combo->name,
            'image' => $combo->image_url,
            'items' => $combo->comboItems->map(function ($comboItem) {
                return [
                    'product_name' => $comboItem->product->name,
                    'quantity' => $comboItem->quantity,
                ];
            })->toArray(),
        ], $metadata);

        return $item;
    }

    /**
     * Create order item from a seat reservation (before payment)
     */
    public static function createFromSeat(Order $order, Seat $seat, string $unitPrice, array $metadata = []): self
    {
        self::validatePrice($unitPrice);

        $item = new self;
        $item->order_id = $order->id;
        $item->item_type = Seat::class;
        $item->item_id = $seat->id;
        $item->quantity = 1; // Seats are always quantity 1
        $item->unit_price = $unitPrice;
        $item->total_price = $unitPrice;
        $item->metadata = array_merge([
            'seat_label' => $seat->label,
            'row' => $seat->row,
            'number' => $seat->number,
            'seat_type' => $seat->seatType->name,
        ], $metadata);

        return $item;
    }

    /**
     * Create order item from an issued ticket (after successful payment)
     */
    public static function createFromTicket(Order $order, Ticket $ticket, string $unitPrice, array $metadata = []): self
    {
        self::validatePrice($unitPrice);

        $item = new self;
        $item->order_id = $order->id;
        $item->item_type = Ticket::class;
        $item->item_id = $ticket->id;
        $item->quantity = 1;
        $item->unit_price = $unitPrice;
        $item->total_price = $unitPrice;
        $item->metadata = $metadata;

        return $item;
    }

    /**
     * Calculate item total price from unit price and quantity
     */
    protected static function calculateTotal(string $unitPrice, int $quantity): string
    {
        return bcmul($unitPrice, (string) $quantity, 2);
    }

    /**
     * Validate quantity is positive
     */
    protected static function validateQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }
    }

    /**
     * Validate price is non-negative
     */
    protected static function validatePrice(string $price): void
    {
        if (bccomp($price, '0', 2) < 0) {
            throw new InvalidArgumentException('Price cannot be negative.');
        }
    }

    /**
     * Assert that this order item can be modified
     *
     * @throws DomainException if order is paid
     */
    public function assertMutable(): void
    {
        if ($this->order->status === Order::STATUS_CONFIRMED) {
            throw new DomainException('Order items from paid orders cannot be modified.');
        }
    }

    /**
     * Get the order this item belongs to
     */
    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the polymorphic purchasable item (Product, Combo, or Ticket)
     */
    public function item(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Boot model and enforce morph map
     */
    protected static function boot(): void
    {
        parent::boot();

        // Enforce allowed morph types on creation
        static::creating(function (OrderItem $item) {
            if (! in_array($item->item_type, self::ALLOWED_ITEM_TYPES, true)) {
                throw new InvalidArgumentException(
                    "Invalid item_type: {$item->item_type}. Allowed types: "
                    .implode(', ', self::ALLOWED_ITEM_TYPES)
                );
            }
        });
    }
}
