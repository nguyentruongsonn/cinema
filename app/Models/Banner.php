<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'link_url',
        'is_active',
        'start_date',
        'end_date',
        'click_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'click_count' => 'integer',
    ];

    /** @return HasMany<BannerImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(BannerImage::class)->orderBy('id');
    }

    /**
     * Scope a query to only include active banners.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->where(function ($q) {
                         $q->whereNull('start_date')
                           ->orWhere('start_date', '<=', now());
                     })
                     ->where(function ($q) {
                         $q->whereNull('end_date')
                           ->orWhere('end_date', '>=', now());
                     });
    }

    /**
     * Scope a query to order by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('created_at', 'asc')
                     ->orderBy('id', 'asc');
    }

    /**
     * Increment click count.
     */
    public function incrementClicks()
    {
        $this->increment('click_count');
    }

    /**
     * Check if banner is currently valid.
     */
    public function isValid(): bool
    {
        $now = now();
        
        $startValid = !$this->start_date || $this->start_date <= $now;
        $endValid = !$this->end_date || $this->end_date >= $now;
        
        return $this->is_active && $startValid && $endValid;
    }
}
