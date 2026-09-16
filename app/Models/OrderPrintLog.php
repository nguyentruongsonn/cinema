<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPrintLog extends Model
{
    protected $fillable = [
        'order_id',
        'printed_by_user_id',
        'print_type',
        'status',
        'copy_number',
        'is_reprint',
        'reason',
        'printer_name',
        'metadata',
        'printed_at',
    ];

    protected $casts = [
        'copy_number' => 'integer',
        'is_reprint' => 'boolean',
        'metadata' => 'array',
        'printed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by_user_id');
    }
}
