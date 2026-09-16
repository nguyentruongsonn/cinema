<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeatLayoutTemplate extends Model
{
    protected $fillable = [
        'template_name',
        'seat_matrix',
        'regular_seat_rows',
        'vip_seat_rows',
        'couple_seat_rows',
        'custom_matrix',
        'description',
        'status',
    ];

    protected $casts = [
        'regular_seat_rows' => 'integer',
        'vip_seat_rows' => 'integer',
        'couple_seat_rows' => 'integer',
        'status' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function screens(): HasMany
    {
        return $this->hasMany(Screen::class, 'seat_layout_template_id');
    }
}
