<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Combo extends Model
{
    use SoftDeletes;

    protected $guarded = ['*'];

    protected $casts = [
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function comboItems(): HasMany
    {
        return $this->hasMany(ComboItem::class, 'combo_id');
    }

    public function orderItems(): MorphMany
    {
        return $this->morphMany(OrderItem::class, 'item');
    }

    public function getAvailableStockAttribute(): int
    {
        $items = $this->relationLoaded('comboItems')
            ? $this->comboItems
            : $this->comboItems()->with('product')->get();

        if ($items->isEmpty()) {
            return 0;
        }

        $minStock = PHP_INT_MAX;
        foreach ($items as $item) {
            if (! $item->product) {
                return 0;
            }

            if ($item->quantity <= 0) {
                return 0;
            }

            if ($item->product->stock <= 0) {
                return 0;
            }

            $availableCombo = intdiv((int) $item->product->stock, (int) $item->quantity);
            $minStock = min($minStock, $availableCombo);
        }

        return max(0, $minStock);
    }

    public function isInStock(): bool
    {
        $items = $this->comboItems()->with('product')->get();

        if ($items->isEmpty()) {
            return false;
        }

        return $items->every(function ($item) {
            return $item->product !== null
                && $item->quantity > 0
                && $item->product->stock >= $item->quantity;
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->whereHas('comboItems', function (Builder $query): void {
            $query->whereHas('product', function (Builder $query): void {
                $query->where('stock', '>', 0);
            });
        });
    }

    public static function createManaged(array $data): self
    {
        return static::forceCreate([
            'name' => $data['name'],
            'price' => $data['price'],
            'original_price' => $data['original_price'] ?? 0,
            'image_url' => $data['image_url'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? true,
        ]);
    }

    public function updateManaged(array $data): bool
    {
        return $this->forceFill([
            'name' => $data['name'] ?? $this->name,
            'price' => $data['price'] ?? $this->price,
            'original_price' => $data['original_price'] ?? $this->original_price,
            'image_url' => $data['image_url'] ?? $this->image_url,
            'description' => $data['description'] ?? $this->description,
            'status' => $data['status'] ?? $this->status,
        ])->save();
    }

    public function updatePrice(string $price, ?string $originalPrice = null): bool
    {
        return $this->forceFill([
            'price' => $price,
            'original_price' => $originalPrice ?? $this->original_price,
        ])->save();
    }

    public function toggleActive(): bool
    {
        return $this->forceFill([
            'status' => ! $this->status,
        ])->save();
    }

    protected static function booted(): void
    {
        static::deleting(function (Combo $combo): void {
            if (! $combo->isForceDeleting() && $combo->orderItems()->exists()) {
                throw new \DomainException('Cannot delete combo that has been ordered.');
            }
        });
    }
}
