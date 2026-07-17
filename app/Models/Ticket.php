<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Ticket Model
 *
 * Represents a ticket entitlement for a specific seat in a showtime.
 * Tickets are issued after successful payment and represent the customer's
 * right to occupy a specific seat. Ticket identity and lifecycle fields
 * are protected and mutations require explicit domain service calls.
 */
class Ticket extends Model
{
    use HasFactory;

    const STATUS_VALID = 'valid';
    const STATUS_USED = 'used';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    /**
     * SECURITY: Ticket entitlement fields are NOT mass assignable.
     * Use forceCreate() or explicit domain service methods.
     */
    protected $guarded = ['*'];

    protected $casts = [
        'checked_in_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_VALID,
    ];

    /**
     * Hide sensitive QR credentials from default serialization.
     * Use API Resources for controlled ticket responses.
     */
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

    /**
     * Atomically mark ticket as used (check-in).
     *
     * Returns true if successful, false if ticket was not valid or already used.
     * Caller must handle rejection and log unauthorized scan attempts.
     *
     * @return bool True if ticket was successfully marked as used
     */
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

    /**
     * Cancel a valid ticket.
     *
     * Only valid tickets can be cancelled. Used/refunded tickets cannot be cancelled.
     * Caller should wrap in transaction and create audit log.
     *
     * @return bool True if cancellation succeeded
     */
    public function cancel(): bool
    {
        return static::whereKey($this->getKey())
            ->where('status', self::STATUS_VALID)
            ->update(['status' => self::STATUS_CANCELLED]) === 1;
    }

    /**
     * Refund a ticket.
     *
     * Valid or cancelled tickets can be refunded. Used tickets cannot be refunded.
     * Caller should wrap in transaction, update payment record, and create audit log.
     *
     * @return bool True if refund succeeded
     */
    public function refund(): bool
    {
        return static::whereKey($this->getKey())
            ->whereIn('status', [self::STATUS_VALID, self::STATUS_CANCELLED])
            ->update(['status' => self::STATUS_REFUNDED]) === 1;
    }

    /**
     * Generate cryptographically secure unique ticket code.
     *
     * Relies on database unique constraint for collision detection.
     * Caller should retry on duplicate key exception.
     */
    public static function generateTicketCode(): string
    {
        return 'TKT-' . strtoupper(Str::random(16));
    }
}
