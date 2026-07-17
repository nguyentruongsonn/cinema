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

    /**
     * Items included in the combo.
     */
    public function comboItems(): HasMany
    {
        return $this->hasMany(ComboItem::class, 'combo_id');
    }

    /**
     * Order items that reference this combo.
     */
    public function orderItems(): MorphMany
    {
        return $this->morphMany(OrderItem::class, 'item');
    }

    /**
     * Calculate available stock based on child product stock and quantities.
     * This is a display-only estimate. Checkout must verify and lock product stock atomically.
     */
    public function getAvailableStockAttribute(): int
    {
        // Use already-loaded relation to avoid N+1 if available
        $items = $this->relationLoaded('comboItems')
            ? $this->comboItems
            : $this->comboItems()->with('product')->get();

        if ($items->isEmpty()) {
            return 0;
        }

        $minStock = PHP_INT_MAX;
        foreach ($items as $item) {
            // Guard against missing product
            if (!$item->product) {
                return 0;
            }

            // Guard against zero or negative quantity (division by zero)
            if ($item->quantity <= 0) {
                return 0;
            }

            // Guard against negative product stock
            if ($item->product->stock <= 0) {
                return 0;
            }

            // Calculate how many combos this product can support
            $availableCombo = intdiv((int) $item->product->stock, (int) $item->quantity);
            $minStock = min($minStock, $availableCombo);
        }

        return max(0, $minStock);
    }

    /**
     * Check if combo is currently in stock.
     * Verifies all combo items have sufficient product stock for their required quantities.
     */
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

    /**
     * Scope: Active combos only (status = true).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /**
     * Scope: Combos with at least one product in stock.
     * WARNING: This is a coarse filter. Final availability must be verified using isInStock() or available_stock accessor.
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->whereHas('comboItems', function (Builder $query): void {
            $query->whereHas('product', function (Builder $query): void {
                $query->where('stock', '>', 0);
            });
        });
    }

    /**
     * Create a new combo with managed field access.
     */
    public static function createManaged(array $data): self
    {
        return static::forceCreate([
            'name' => $data['name'],
            'price' => $data['price'],
            'original_price' => $data['original_price'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? true,
        ]);
    }

    /**
     * Update combo with managed field access.
     */
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

    /**
     * Update combo pricing with explicit intent.
     */
    public function updatePrice(string $price, ?string $originalPrice = null): bool
    {
        return $this->forceFill([
            'price' => $price,
            'original_price' => $originalPrice ?? $this->original_price,
        ])->save();
    }

    /**
     * Toggle combo active status.
     */
    public function toggleActive(): bool
    {
        return $this->forceFill([
            'status' => ! $this->status,
        ])->save();
    }

    /**
     * Boot model events.
     */
    protected static function booted(): void
    {
        static::deleting(function (Combo $combo): void {
            // Prevent deletion of combos referenced by order items
            if (! $combo->isForceDeleting() && $combo->orderItems()->exists()) {
                throw new \DomainException('Cannot delete combo that has been ordered.');
            }
        });
    }
}