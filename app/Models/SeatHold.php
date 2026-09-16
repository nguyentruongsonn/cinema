<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeatHold extends Model
{
    use HasFactory;

    protected $fillable = [
        'showtime_id',
        'user_id',
        // 'seat_ids' - DEPRECATED: Use normalized SeatHoldItem relationship instead
        'held_until',
    ];

    protected $casts = [
        'seat_ids' => 'json',
        'held_until' => 'datetime',
    ];

    public function showtime(): BelongsTo
    {
        return $this->belongsTo(Showtime::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SeatHoldItem::class);
    }

    /**
     * Return normalized seat IDs when available, with legacy JSON fallback.
     */
    public function normalizedSeatIds(): array
    {
        if ($this->relationLoaded('items') && $this->items->isNotEmpty()) {
            return $this->items
                ->filter(fn ($item): bool => $item instanceof SeatHoldItem
                    && $item->status === SeatHoldItem::STATUS_ACTIVE
                    && $item->expires_at->isFuture())
                ->pluck('seat_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        // Fallback to legacy JSON seat_ids for backward compatibility
        return array_values(array_unique(array_map('intval', (array) $this->seat_ids)));
    }

    /**
     * Check if the hold is still valid
     */
    public function isValid(): bool
    {
        return $this->held_until->isFuture();
    }

    /**
     * Scope to get only valid holds
     */
    public function scopeValid($query)
    {
        return $query->where('held_until', '>', now());
    }

    /**
     * Scope to get only expired holds
     */
    public function scopeExpired($query)
    {
        return $query->where('held_until', '<=', now());
    }
}
