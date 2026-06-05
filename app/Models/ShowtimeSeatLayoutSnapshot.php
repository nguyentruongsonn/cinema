<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowtimeSeatLayoutSnapshot extends Model
{
    protected $table = 'showtime_seat_layout_snapshots';

    protected $fillable = [
        'showtime_id',
        'layout_data',
        'checksum',
        'version',
    ];

    protected $casts = [
        'layout_data' => 'json',
        'version' => 'integer',
    ];

    public function showtime(): BelongsTo
    {
        return $this->belongsTo(Showtime::class);
    }
}
