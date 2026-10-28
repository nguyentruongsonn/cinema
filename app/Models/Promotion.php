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
        'min_order_value',
        'max_discount_amount',
        'start_date',
        'end_date',
        'usage_limit',
        'daily_usage_limit',
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

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_promotion')
            ->withPivot(['status', 'used_at', 'order_id', 'usage_count'])
            ->withTimestamps();
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

    public function scopeValid($query)
    {
        return $query->where('status', 1)
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                    ->orWhereColumn('usage_count', '<', 'usage_limit');
            });
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count', 1, []);
    }

    public function decrementUsage(): void
    {
        $this->decrement('usage_count', 1, []);
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

    public function canBeUsed(): bool
    {
        return $this->status
            && $this->isWithinDateRange()
            && $this->hasRemainingUses();
    }

    public function isWithinDateRange(): bool
    {
        $now = now();
        $startDate = $this->getAttribute('start_date');
        $endDate = $this->getAttribute('end_date');

        $afterStart = $startDate === null || $startDate <= $now;
        $beforeEnd = $endDate === null || $endDate >= $now;

        return $afterStart && $beforeEnd;
    }

    public function hasRemainingUses(): bool
    {
        if (is_null($this->usage_limit)) {
            return true;
        }

        return $this->usage_count < $this->usage_limit;
    }

    public function remainingUses(): ?int
    {
        if (is_null($this->usage_limit)) {
            return null; // unlimited
        }

        return max(0, $this->usage_limit - $this->usage_count);
    }
}
