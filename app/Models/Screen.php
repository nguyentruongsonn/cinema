<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Screen extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'theater_id',
        'name',
        'code',
        'format_id',
        'sound_id',
        'seat_layout_template_id',
        'capacity',
        'status',
        'hidden_rows',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'status' => 'integer',
        'hidden_rows' => 'array',
    ];

    /** @return BelongsTo<Theater, $this> */
    public function theater(): BelongsTo
    {
        return $this->belongsTo(Theater::class);
    }

    /** @return BelongsTo<Format, $this> */
    public function format(): BelongsTo
    {
        return $this->belongsTo(Format::class);
    }

    /** @return BelongsTo<Sound, $this> */
    public function sound(): BelongsTo
    {
        return $this->belongsTo(Sound::class);
    }

    /** @return BelongsTo<SeatLayoutTemplate, $this> */
    public function seatLayoutTemplate(): BelongsTo
    {
        return $this->belongsTo(SeatLayoutTemplate::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }
}
