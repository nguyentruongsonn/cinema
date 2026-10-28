<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ShowtimeSeatLayoutSnapshot extends Model
{
    use HasFactory;

    protected $table = 'showtime_seat_layout_snapshots';

    protected $fillable = [];

    protected $casts = [
        'layout_data' => 'array',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Seat layout snapshots are immutable and cannot be updated.');
        });

        // Snapshots cannot be deleted once created (booking history depends on them)
        static::deleting(function (): void {
            throw new LogicException('Seat layout snapshots cannot be deleted.');
        });
    }

    public static function createSnapshot(Showtime $showtime, array $layoutData, int $version): self
    {
        $checksum = self::generateChecksum($layoutData);

        $snapshot = new self;
        $snapshot->forceFill([
            'showtime_id' => $showtime->id,
            'layout_data' => $layoutData,
            'checksum' => $checksum,
            'version' => $version,
        ]);
        $snapshot->save();

        return $snapshot;
    }

    public static function generateChecksum(array $layoutData): string
    {
        $canonical = json_encode($layoutData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $canonical);
    }

    public function verifyChecksum(): bool
    {
        if (empty($this->checksum)) {
            return false;
        }

        return $this->checksum === self::generateChecksum($this->layout_data ?? []);
    }

    public function showtime(): BelongsTo
    {
        return $this->belongsTo(Showtime::class);
    }
}
