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

        return array_values(array_unique(array_map('intval', (array) $this->seat_ids)));
    }

    public function isValid(): bool
    {
        return $this->held_until->isFuture();
    }

    public function scopeValid($query)
    {
        return $query->where('held_until', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('held_until', '<=', now());
    }
}
