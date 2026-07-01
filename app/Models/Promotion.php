<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'category',
        'description',
        'discount_type',
        'discount_value',
        'min_order_value',
        'max_discount_amount',
        'start_date',
        'end_date',
        'usage_limit',
        'usage_count',
        'daily_usage_limit',
        'status',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_order_value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'daily_usage_limit' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'status' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_promotion')
            ->withPivot(['status', 'used_at', 'order_id', 'usage_count'])
            ->withTimestamps();
    }

    // Scope: active promotions
    public function scopeActive($query)
    {
        return $query->where('status', 1)
            ->where(function ($q) {
                $q->whereNull('start_date')
                  ->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            });
    }

    // Scope: valid for use
    public function scopeValid($query)
    {
        return $query->where('status', 1)
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                  ->orWhereColumn('usage_count', '<', 'usage_limit');
            });
    }

    // Scope: by code
    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }
}
