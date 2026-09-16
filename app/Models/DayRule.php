<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DayRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'day_of_week',
        'day_type',
        'surcharge',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'surcharge' => 'integer',
    ];
}
