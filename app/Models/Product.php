<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class Product extends Model
{
    use SoftDeletes;

    public const TYPE_FOOD = 'food';
    public const TYPE_DRINK = 'drink';

    protected $fillable = [
        'name',
        'type',
        'image_url',
        'description',
        // price removed - use createManaged()/updateManaged() after authorization and validation
        // stock removed - use setStock()/increaseStock()/decreaseStock()
        // status removed - use activate()/deactivate()/toggleActive()
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'status' => 'boolean',
    ];

    public function orderItems(): MorphMany
    {
        return $this->morphMany(OrderItem::class, 'item');
    }

    public function comboItems(): HasMany
    {
        return $this->hasMany(ComboItem::class, 'product_id');
    }

    /**
     * Các combo có chứa món này (product được dùng trong combo nào)
     */
    public function usedInCombos(): HasMany
    {
        return $this->comboItems();
    }

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

    /**
     * Create a product from already-authorized and validated admin input.
     */
    public static function createManaged(array $attributes): self
    {
        $product = new self();
        $product->fill(Arr::only($attributes, [
            'name',
            'type',
            'image_url',
            'description',
        ]));

        $product->forceFill([
            'price' => $attributes['price'] ?? 0,
            'stock' => $attributes['stock'] ?? 0,
            'status' => $attributes['status'] ?? true,
        ]);

        $product->save();

        return $product;
    }

    /**
     * Update product fields from already-authorized and validated admin input.
     */
    public function updateManaged(array $attributes): bool
    {
        $this->fill(Arr::only($attributes, [
            'name',
            'type',
            'image_url',
            'description',
        ]));

        $sensitiveAttributes = Arr::only($attributes, [
            'price',
            'stock',
            'status',
        ]);

        if ($sensitiveAttributes !== []) {
            $this->forceFill($sensitiveAttributes);
        }

        return $this->save();
    }

    public function setStock(int $stock): bool
    {
        if ($stock < 0) {
            throw new InvalidArgumentException('Product stock cannot be negative.');
        }

        $this->forceFill(['stock' => $stock]);

        return $this->save();
    }

    public function increaseStock(int $quantity): bool
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Stock increase quantity must be positive.');
        }

        return $this->increment('stock', $quantity, []);
    }

    public function decreaseStock(int $quantity): bool
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Stock decrease quantity must be positive.');
        }

        if ((int) $this->stock < $quantity) {
            throw new InvalidArgumentException('Insufficient product stock.');
        }

        return $this->decrement('stock', $quantity, []);
    }

    public function activate(): bool
    {
        $this->forceFill(['status' => true]);

        return $this->save();
    }

    public function deactivate(): bool
    {
        $this->forceFill(['status' => false]);

        return $this->save();
    }

    public function toggleActive(): bool
    {
        return $this->status ? $this->deactivate() : $this->activate();
    }
}