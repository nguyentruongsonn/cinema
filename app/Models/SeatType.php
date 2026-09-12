<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeatType extends Model
{
    protected $fillable = [
        'name',
        'surcharge',
        'color',
    ];

    protected $casts = [
        'surcharge' => 'decimal:2',
    ];

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }
}
