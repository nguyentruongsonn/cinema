<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Showtime extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'movie_id',
        'screen_id',
        'format_id',
        'version_type_id',
        'price_rule_id',
        'scheduled_at',
        'pricing_snapshot',
        'status',
    ];

    protected $appends = ['encrypted_id'];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'pricing_snapshot' => 'json',
        'status' => 'boolean',
    ];



    public function getEncryptedIdAttribute()
    {
        return Crypt::encryptString($this->id);
    }

    public function getFormattedStartTimeAttribute()
    {
        return $this->scheduled_at ? $this->scheduled_at->format('H:i') : null;
    }

    public function getFormattedStartDateAttribute()
    {
        return $this->scheduled_at ? $this->scheduled_at->format('H:i, d/m/Y') : null;
    }

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }

    public function format(): BelongsTo
    {
        return $this->belongsTo(Format::class);
    }

    public function versionType(): BelongsTo
    {
        return $this->belongsTo(VersionType::class);
    }

    public function priceRule(): BelongsTo
    {
        return $this->belongsTo(PriceRule::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function seatLayoutSnapshot(): HasOne
    {
        return $this->hasOne(ShowtimeSeatLayoutSnapshot::class);
    }

    public function seatHolds(): HasMany
    {
        return $this->hasMany(SeatHold::class);
    }

    // Scope: available showtimes
    public function scopeAvailable($query)
    {
        return $query->where('status', 1);
    }

    // Scope: upcoming showtimes
    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_at', '>=', now());
    }

    // Scope: showtimes by movie
    public function scopeForMovie($query, $movieId)
    {
        return $query->where('movie_id', $movieId);
    }

    // Scope: showtimes by screen
    public function scopeForScreen($query, $screenId)
    {
        return $query->where('screen_id', $screenId);
    }

    // Scope: showtimes by date
    public function scopeOnDate($query, $date)
    {
        return $query->whereDate('scheduled_at', $date);
    }
}
