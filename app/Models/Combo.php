<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Combo extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'price',
        'original_price',
        'image_url',
        'description',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'status' => 'boolean',
    ];

    /**
     * Các món trong combo
     */
    public function comboItems(): HasMany
    {
        return $this->hasMany(ComboItem::class, 'combo_id');
    }

    /**
     * Tính tồn kho khả dụng (dựa trên món con)
     */
    public function getAvailableStockAttribute(): int
    {
        $items = $this->comboItems()->with('product')->get();
        
        if ($items->isEmpty()) {
            return 0;
        }

        $minStock = PHP_INT_MAX;
        foreach ($items as $item) {
            if (!$item->product || $item->product->stock <= 0) {
                return 0;
            }
            $availableCombo = floor($item->product->stock / $item->quantity);
            $minStock = min($minStock, $availableCombo);
        }

        return max(0, $minStock);
    }

    /**
     * Scope: Chỉ combo đang bán
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope: Combo có tồn kho
     */
    public function scopeInStock($query)
    {
        return $query->whereHas('comboItems.product', function ($q) {
            $q->where('stock', '>', 0);
        });
    }
}