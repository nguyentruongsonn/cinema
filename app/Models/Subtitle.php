<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subtitle extends Model
{
    protected $fillable = [
        'name',
    ];

    public function movies(): BelongsToMany
    {
        return $this->belongsToMany(Movie::class, 'movie_subtitle')
            ->withTimestamps();
    }

    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class, 'subtitle_id');
    }
}
