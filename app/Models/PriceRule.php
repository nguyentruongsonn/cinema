<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceRule extends Model
{
    protected $fillable = [
        'name',
        'type',
        'condition_type',
        'condition_value',
        'price_adjustment',
        'adjustment_type',
        'priority',
        'start_date',
        'end_date',
        'days_of_week',
        'status',
    ];

    protected $casts = [
        'price_adjustment' => 'decimal:2',
        'priority' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'days_of_week' => 'array',
        'status' => 'boolean',
    ];

    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class, 'price_rule_id');
    }

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

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
