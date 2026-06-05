<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Theater extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'city',
        'phone',
        'email',
        'status',
        'pricing_profile',
    ];

    protected $casts = [
        'status' => 'boolean',
        'pricing_profile' => 'json',
    ];

    public function screens(): HasMany
    {
        return $this->hasMany(Screen::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeInCity($query, $city)
    {
        return $query->where('city', $city);
    }
}
