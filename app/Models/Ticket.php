<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory;

    const STATUS_VALID = 'valid';
    const STATUS_USED = 'used';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    protected $guarded = ['*'];

    protected $casts = [
        'checked_in_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_VALID,
    ];

    protected $hidden = [
        'qr_code',
    ];
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function showtime(): BelongsTo
    {
        return $this->belongsTo(Showtime::class);
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VALID);
    }

    public function scopeUsed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_USED);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function isValid(): bool
    {
        return $this->status === self::STATUS_VALID;
    }

    public function isUsed(): bool
    {
        return $this->status === self::STATUS_USED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function markAsUsed(): bool
    {
        return static::whereKey($this->getKey())
            ->where('status', self::STATUS_VALID)
            ->whereNull('checked_in_at')
            ->update([
                'status' => self::STATUS_USED,
                'checked_in_at' => now(),
            ]) === 1;
    }

    public function cancel(): bool
    {
        return static::whereKey($this->getKey())
            ->where('status', self::STATUS_VALID)
            ->update(['status' => self::STATUS_CANCELLED]) === 1;
    }

    public function refund(): bool
    {
        return static::whereKey($this->getKey())
            ->whereIn('status', [self::STATUS_VALID, self::STATUS_CANCELLED])
            ->update(['status' => self::STATUS_REFUNDED]) === 1;
    }

    public static function generateTicketCode(): string
    {
        return 'TKT-' . strtoupper(Str::random(16));
    }
}
