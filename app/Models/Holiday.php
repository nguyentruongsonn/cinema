<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'date',
        'year',
        'surcharge',
        'status',
    ];

    protected $casts = [
        'year' => 'integer',
        'surcharge' => 'integer',
        'status' => 'integer',
    ];
}
