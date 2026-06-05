<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'code',
        'gateway_order_code',
        'user_id',
        'showtime_id',
        'total_amount',
        'payload',
        'status',
        'payment_provider',
        'payment_status',
        'checkout_url',
        'paid_at',
        'cancelled_at',
        'expired_at',
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
