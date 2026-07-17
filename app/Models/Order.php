<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    public const STATUS_CANCELLED = 0;
    public const STATUS_PENDING = 1;
    public const STATUS_CONFIRMED = 2;

    /**
     * Mass-assignable fields.
     *
     * SECURITY: Lifecycle/financial fields removed from fillable to prevent privilege escalation.
     * Use explicit methods instead:
     * - Order::createPending() for new orders
     * - $order->markPaid() to mark as paid
     * - $order->markCancelled() to cancel
     * - $order->markFailed() for payment failure
     * - $order->markExpired() for expiration
     * - $order->setCheckoutUrl() for payment links
     */
    protected $fillable = [
        'code',
        'user_id',
        'showtime_id',
        'payload',
        // REMOVED from fillable (use explicit methods):
        // - 'gateway_order_code' (set via createPending)
        // - 'payment_provider' (set via createPending)
        // - 'total_amount' (set via createPending, immutable after payment)
        // - 'status' (use markPaid/markCancelled/markFailed/markExpired methods)
        // - 'payment_status' (managed by state transition methods)
        // - 'checkout_url' (use setCheckoutUrl method)
        // - 'expired_at' (set via createPending)
    ];

    protected $casts = [
        'gateway_order_code' => 'integer',
        'total_amount' => 'decimal:2',
        'payload' => 'json',
        'status' => 'integer',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'payment_status' => 'pending',
        'paid_at' => null,
        'cancelled_at' => null,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function showtime(): BelongsTo
    {
        return $this->belongsTo(Showtime::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function markPending(?\DateTimeInterface $expiresAt = null): self
    {
        $this->forceFill([
            'status' => self::STATUS_PENDING,
            'payment_status' => 'pending',
            'paid_at' => null,
            'cancelled_at' => null,
            'expired_at' => $expiresAt ?? $this->expired_at,
        ])->save();

        return $this;
    }

    public function markPaid(?\DateTimeInterface $paidAt = null): self
    {
        if ($this->isPaid()) {
            return $this;
        }

        $this->forceFill([
            'status' => self::STATUS_CONFIRMED,
            'payment_status' => 'paid',
            'paid_at' => $paidAt ?? now(),
            'cancelled_at' => null,
        ])->save();

        return $this;
    }

    public function markCancelled(?\DateTimeInterface $cancelledAt = null): self
    {
        if ($this->isPaid()) {
            throw new \RuntimeException('Cannot cancel a paid order.');
        }

        if ($this->isCancelled()) {
            return $this;
        }

        $this->forceFill([
            'status' => self::STATUS_CANCELLED,
            'payment_status' => 'cancelled',
            'cancelled_at' => $cancelledAt ?? now(),
        ])->save();

        return $this;
    }

    public function markFailed(?\DateTimeInterface $failedAt = null): self
    {
        if ($this->isPaid()) {
            return $this;
        }

        $this->forceFill([
            'status' => self::STATUS_CANCELLED,
            'payment_status' => 'failed',
            'cancelled_at' => $failedAt ?? now(),
        ])->save();

        return $this;
    }

    public function markExpired(?\DateTimeInterface $expiredAt = null): self
    {
        if ($this->isPaid()) {
            return $this;
        }

        $this->forceFill([
            'status' => self::STATUS_CANCELLED,
            'payment_status' => 'expired',
            'cancelled_at' => $expiredAt ?? now(),
        ])->save();

        return $this;
    }

    public function isPaid(): bool
    {
        return (int) $this->status === self::STATUS_CONFIRMED || $this->payment_status === 'paid';
    }

    /**
     * Factory method: Create a new pending order with all required fields.
     * Uses forceFill to bypass mass assignment protection for trusted internal creation.
     *
     * @param array $attributes Required: code, gateway_order_code, payment_provider, user_id, showtime_id, total_amount, expired_at
     * @return self
     */
    public static function createPending(array $attributes): self
    {
        $required = ['code', 'gateway_order_code', 'payment_provider', 'user_id', 'showtime_id', 'total_amount', 'expired_at'];

        foreach ($required as $field) {
            if (!isset($attributes[$field])) {
                throw new \InvalidArgumentException("Missing required field for order creation: {$field}");
            }
        }

        $order = new static();
        $order->forceFill([
            'code' => $attributes['code'],
            'gateway_order_code' => $attributes['gateway_order_code'],
            'payment_provider' => $attributes['payment_provider'],
            'user_id' => $attributes['user_id'],
            'showtime_id' => $attributes['showtime_id'],
            'checkout_fingerprint' => $attributes['checkout_fingerprint'] ?? null,
            'total_amount' => $attributes['total_amount'],
            'payload' => $attributes['payload'] ?? null,
            'status' => self::STATUS_PENDING,
            'payment_status' => 'pending',
            'expired_at' => $attributes['expired_at'],
            'paid_at' => null,
            'cancelled_at' => null,
        ]);
        $order->save();

        return $order;
    }

    /**
     * Set checkout URL (for payment gateway links).
     * Can only be set once on pending orders.
     */
    public function setCheckoutUrl(string $url): self
    {
        if ($this->checkout_url !== null) {
            throw new \RuntimeException('Checkout URL already set for this order.');
        }

        if ((int) $this->status !== self::STATUS_PENDING) {
            throw new \RuntimeException('Can only set checkout URL on pending orders.');
        }

        $this->forceFill(['checkout_url' => $url])->save();

        return $this;
    }

    /**
     * Update order total during pending state (before payment).
     * Cannot update after payment confirmed.
     */
    public function updateTotal(float $newTotal): self
    {
        if ((int) $this->status !== self::STATUS_PENDING) {
            throw new \RuntimeException('Cannot update total on non-pending order.');
        }

        if ($this->payment_status === 'paid') {
            throw new \RuntimeException('Cannot update total on paid order.');
        }

        $this->forceFill(['total_amount' => $newTotal])->save();

        return $this;
    }

    public function isCancelled(): bool
    {
        return (int) $this->status === self::STATUS_CANCELLED;
    }

    public function isPending(): bool
    {
        return (int) $this->status === self::STATUS_PENDING;
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByOrderCode($query, $code)
    {
        return $query->where('code', $code);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
