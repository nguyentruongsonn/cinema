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
        // discount_type and discount_value removed - must not change after first usage
        // 'discount_type',
        // 'discount_value',
        'min_order_value',
        'max_discount_amount',
        'start_date',
        'end_date',
        'usage_limit',
        // usage_count removed - use incrementUsage()/decrementUsage() methods
        // 'usage_count',
        'daily_usage_limit',
        // status removed - use activate()/deactivate() methods
        // 'status',
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

    /** @return BelongsToMany<User, $this> */
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

    /**
     * Increment usage counter atomically.
     * Should be called within a transaction with row lock.
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count', 1, []);
    }

    /**
     * Decrement usage counter (for rollback scenarios).
     * Should be called within a transaction with row lock.
     */
    public function decrementUsage(): void
    {
        $this->decrement('usage_count', 1, []);
    }

    /**
     * Activate promotion.
     */
    public function activate(): bool
    {
        $this->forceFill(['status' => true]);

        return $this->save();
    }

    /**
     * Deactivate promotion.
     */
    public function deactivate(): bool
    {
        $this->forceFill(['status' => false]);

        return $this->save();
    }

    /**
     * Check if promotion can be used.
     */
    public function canBeUsed(): bool
    {
        return $this->status
            && $this->isWithinDateRange()
            && $this->hasRemainingUses();
    }

    /**
     * Check if promotion is within valid date range.
     */
    public function isWithinDateRange(): bool
    {
        $now = now();
        $startDate = $this->getAttribute('start_date');
        $endDate = $this->getAttribute('end_date');

        $afterStart = $startDate === null || $startDate <= $now;
        $beforeEnd = $endDate === null || $endDate >= $now;

        return $afterStart && $beforeEnd;
    }

    /**
     * Check if promotion has remaining uses.
     */
    public function hasRemainingUses(): bool
    {
        if (is_null($this->usage_limit)) {
            return true;
        }

        return $this->usage_count < $this->usage_limit;
    }

    /**
     * Get remaining uses count.
     */
    public function remainingUses(): ?int
    {
        if (is_null($this->usage_limit)) {
            return null; // unlimited
        }

        return max(0, $this->usage_limit - $this->usage_count);
    }
}
