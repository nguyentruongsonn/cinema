<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'price',
        'stock',
        'image_url',
        'description',
        'status',
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
     * Các combo có chứa món này (product được dùng trong combo nào)
     */
    public function usedInCombos()
    {
        return $this->hasMany(ComboItem::class, 'product_id');
    }
}
