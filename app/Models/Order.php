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

    protected $fillable = [
        'code',
        'user_id',
        'showtime_id',
        'payload',
    ];

    protected $casts = [
        'gateway_order_code' => 'integer',
        'total_amount' => 'decimal:2',
        'payload' => 'json',
        'status' => 'integer',
        'paid_at' => 'datetime',
        'ticket_email_sent_at' => 'datetime',
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

    public function theater(): BelongsTo
    {
        return $this->belongsTo(Theater::class);
    }

    public function servedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'served_by_user_id');
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

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function printLogs(): HasMany
    {
        return $this->hasMany(OrderPrintLog::class);
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

    public static function createPending(array $attributes): self
    {
        $required = ['code', 'gateway_order_code', 'payment_provider', 'user_id', 'total_amount', 'expired_at'];

        foreach ($required as $field) {
            if (! isset($attributes[$field])) {
                throw new \InvalidArgumentException("Missing required field for order creation: {$field}");
            }
        }

        if (! array_key_exists('showtime_id', $attributes)) {
            throw new \InvalidArgumentException('Missing required field for order creation: showtime_id');
        }

        $order = new self;
        $order->forceFill([
            'code' => $attributes['code'],
            'gateway_order_code' => $attributes['gateway_order_code'],
            'payment_provider' => $attributes['payment_provider'],
            'user_id' => $attributes['user_id'],
            'showtime_id' => $attributes['showtime_id'],
            'theater_id' => $attributes['theater_id'] ?? null,
            'served_by_user_id' => $attributes['served_by_user_id'] ?? null,
            'source' => $attributes['source'] ?? 'web',
            'payment_method' => $attributes['payment_method'] ?? null,
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

    public function isPos(): bool
    {
        return str_starts_with((string) $this->code, 'POS-')
            || $this->source === 'pos'
            || data_get($this->payload, 'source') === 'pos';
    }

    public function posTheaterId(): ?int
    {
        if (is_numeric($this->theater_id)) {
            return (int) $this->theater_id;
        }

        $theaterId = data_get($this->payload, 'theater_id');

        if (is_numeric($theaterId)) {
            return (int) $theaterId;
        }

        if (! $this->relationLoaded('showtime')) {
            $this->load('showtime.screen:id,theater_id');
        } elseif ($this->showtime && ! $this->showtime->relationLoaded('screen')) {
            $this->showtime->load('screen:id,theater_id');
        }

        $showtime = $this->showtime;
        $screen = $showtime?->screen;

        return $screen?->theater_id
            ? (int) $screen->theater_id
            : null;
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
