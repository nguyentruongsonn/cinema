<?php

namespace App\Models;

use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

class OrderItem extends Model
{
    use HasFactory;

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

    public const ALLOWED_ITEM_TYPES = [
        Product::class,
        Combo::class,
        Seat::class,
        Ticket::class,
    ];

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

    public static function createFromSeat(Order $order, Seat $seat, string $unitPrice, array $metadata = []): self
    {
        self::validatePrice($unitPrice);

        $item = new self;
        $item->order_id = $order->id;
        $item->item_type = Seat::class;
        $item->item_id = $seat->id;
        $item->quantity = 1;
        $item->unit_price = $unitPrice;
        $item->total_price = $unitPrice;
        $item->metadata = array_merge([
            'seat_label' => $seat->label,
            'row' => $seat->row,
            'number' => $seat->number,
            'seat_type' => data_get($seat, 'seatType.name'),
        ], $metadata);

        return $item;
    }

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

    protected static function calculateTotal(string $unitPrice, int $quantity): string
    {
        return bcmul($unitPrice, (string) $quantity, 2);
    }

    protected static function validateQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }
    }

    protected static function validatePrice(string $price): void
    {
        if (bccomp($price, '0', 2) < 0) {
            throw new InvalidArgumentException('Price cannot be negative.');
        }
    }

    public function assertMutable(): void
    {
        if ($this->order->status === Order::STATUS_CONFIRMED) {
            throw new DomainException('Order items from paid orders cannot be modified.');
        }
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function item(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function boot(): void
    {
        parent::boot();

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
