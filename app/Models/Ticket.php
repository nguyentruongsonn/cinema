<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 3: Ticket Model
 * Represents a ticket for a specific seat in a showtime
 */
class Ticket extends Model
{
    use HasFactory;

    // Status constants
    const STATUS_VALID = 'valid';
    const STATUS_USED = 'used';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'order_id',
        'user_id',
        'showtime_id',
        'seat_id',
        'ticket_code',
        'qr_code',
        'status',
        'checked_in_at',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_VALID,
    ];

    // Relationships

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

    // Scopes

    public function scopeValid($query)
    {
        return $query->where('status', self::STATUS_VALID);
    }

    public function scopeUsed($query)
    {
        return $query->where('status', self::STATUS_USED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Helper methods

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

    public function markAsUsed(): void
    {
        $this->update([
            'status' => self::STATUS_USED,
            'checked_in_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    public function refund(): void
    {
        $this->update(['status' => self::STATUS_REFUNDED]);
    }

    /**
     * Generate unique ticket code
     */
    public static function generateTicketCode(): string
    {
        do {
            $code = 'TKT-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 12));
        } while (self::where('ticket_code', $code)->exists());

        return $code;
    }
}