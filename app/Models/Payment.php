<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    const STATUS_PENDING = 'pending';

    const STATUS_PROCESSING = 'processing';

    const STATUS_SUCCESS = 'success';

    const STATUS_FAILED = 'failed';

    const STATUS_CANCELLED = 'cancelled';

    const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'order_id',
        'user_id',
        'method',
        'transaction_code',
        'gateway_order_code',
        'amount',
        'payload',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payload' => 'json',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public static function createPending(array $attributes): self
    {
        $payment = new self($attributes);
        $payment->status = self::STATUS_PENDING;
        $payment->save();

        return $payment;
    }

    public function markPending(): self
    {
        if ($this->status !== self::STATUS_PENDING) {
            $this->status = self::STATUS_PENDING;
            $this->paid_at = null;
            $this->failed_at = null;
            $this->save();
        }

        return $this;
    }

    public function markProcessing(): self
    {
        if (! in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true)) {
            throw new \LogicException('Payment không ở trạng thái có thể xử lý.');
        }

        $this->status = self::STATUS_PROCESSING;
        $this->save();

        return $this;
    }

    public function markSuccessful(?\DateTimeInterface $paidAt = null): self
    {
        if ($this->status === self::STATUS_SUCCESS) {
            return $this;
        }

        if ($this->status === self::STATUS_REFUNDED) {
            throw new \LogicException('Payment không ở trạng thái có thể chuyển thành công.');
        }

        $this->status = self::STATUS_SUCCESS;
        $this->paid_at = $paidAt ? Carbon::instance($paidAt) : now();
        $this->failed_at = null;
        $this->save();

        return $this;
    }

    public function markFailed(?\DateTimeInterface $failedAt = null): self
    {
        if ($this->status === self::STATUS_SUCCESS || $this->status === self::STATUS_REFUNDED) {
            throw new \LogicException('Không thể đánh dấu payment đã hoàn tất là thất bại.');
        }

        $this->status = self::STATUS_FAILED;
        $this->failed_at = $failedAt ? Carbon::instance($failedAt) : now();
        $this->save();

        return $this;
    }

    public function markCancelled(?\DateTimeInterface $cancelledAt = null): self
    {
        if ($this->status === self::STATUS_SUCCESS || $this->status === self::STATUS_REFUNDED) {
            throw new \LogicException('Không thể hủy payment đã hoàn tất.');
        }

        $this->status = self::STATUS_CANCELLED;
        $this->failed_at = $cancelledAt ? Carbon::instance($cancelledAt) : now();
        $this->save();

        return $this;
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
