<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatHoldItem extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CONSUMED = 'consumed';

    protected $fillable = [
        'seat_hold_id',
        'showtime_id',
        'seat_id',
        'status',
        'active_lock_key',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public static function activeLockKey(int $showtimeId, int $seatId): string
    {
        return $showtimeId . ':' . $seatId;
    }

    public function seatHold(): BelongsTo
    {
        return $this->belongsTo(SeatHold::class);
    }

    public function showtime(): BelongsTo
    {
        return $this->belongsTo(Showtime::class);
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where('expires_at', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where('expires_at', '<=', now());
    }

    public function markExpired(): void
    {
        $this->forceFill([
            'status' => self::STATUS_EXPIRED,
            'active_lock_key' => null,
        ])->save();
    }

    public function markConsumed(): void
    {
        $this->forceFill([
            'status' => self::STATUS_CONSUMED,
            'active_lock_key' => null,
        ])->save();
    }
}
