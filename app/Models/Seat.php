<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seat extends Model
{
    protected $fillable = [
        'screen_id',
        'seat_type_id',
        'row',
        'number',
        'row_index',
        'column_index',
        'label',
        'status',
    ];

    protected $casts = [
        'row_index' => 'integer',
        'column_index' => 'integer',
        'status' => 'boolean',
    ];

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    public function seatType(): BelongsTo
    {
        return $this->belongsTo(SeatType::class);
    }

    // Scope: active seats
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    // Scope: available seats
    public function scopeAvailable($query)
    {
        return $query->where('status', 1);
    }
}
