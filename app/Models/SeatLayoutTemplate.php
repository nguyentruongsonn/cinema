<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeatLayoutTemplate extends Model
{
    protected $fillable = [
        'name',
        'screen_type',
        'rows',
        'columns',
        'seat_matrix',
        'status',
    ];

    protected $casts = [
        'rows' => 'integer',
        'columns' => 'integer',
        'seat_matrix' => 'json',
        'status' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeByScreenType($query, $type)
    {
        return $query->where('screen_type', $type);
    }
}
