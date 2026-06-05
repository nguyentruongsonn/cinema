<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $fillable = [
        'key',
        'response',
        'expires_at',
    ];

    protected $casts = [
        'response' => 'json',
        'expires_at' => 'datetime',
    ];

    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeByKey($query, $key)
    {
        return $query->where('key', $key);
    }
}
