<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Format extends Model
{
    protected $fillable = [
        'name',
        'surcharge',
    ];

    protected $casts = [
        'surcharge' => 'decimal:2',
    ];

    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class, 'format_id');
    }
}
