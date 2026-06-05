<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'method',
        'transaction_code',
        'amount',
        'status',
        'payload',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => 'integer',
        'payload' => 'json',
        'paid_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Scope: successful payments
    public function scopeSuccessful($query)
    {
        return $query->where('status', 2);
    }

    // Scope: pending payments
    public function scopePending($query)
    {
        return $query->where('status', 1);
    }

    // Scope: failed payments
    public function scopeFailed($query)
    {
        return $query->where('status', 3);
    }
}
