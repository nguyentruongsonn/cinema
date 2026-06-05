<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatHold extends Model
{
    protected $fillable = [
        'showtime_id',
        'user_id',
        'seat_ids',
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
