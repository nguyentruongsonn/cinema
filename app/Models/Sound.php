<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sound extends Model
{
    protected $fillable = [
        'name',
    ];

    public function screens(): HasMany
    {
        return $this->hasMany(Screen::class);
    }
}
