<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * ShowtimeSeatLayoutSnapshot represents an immutable snapshot of a showtime's seat layout.
 *
 * Snapshots are booking-critical: once created, they must not be modified or deleted
 * because tickets, seat holds, and order items reference the snapshot used at purchase time.
 *
 * Database should enforce unique constraint on (showtime_id, version).
 */
class ShowtimeSeatLayoutSnapshot extends Model
{
    use HasFactory;

    protected $table = 'showtime_seat_layout_snapshots';

    /**
     * All fields are guarded. Use factory methods to create snapshots.
     */
    protected array $fillable = [];

    protected array $casts = [
        'layout_data' => 'array',
        'version' => 'integer',
    ];

    protected static function booted(): void
    {
        // Snapshots are immutable once created
        static::updating(function (): void {
            throw new LogicException('Seat layout snapshots are immutable and cannot be updated.');
        });

        // Snapshots cannot be deleted once created (booking history depends on them)
        static::deleting(function (): void {
            throw new LogicException('Seat layout snapshots cannot be deleted.');
        });
    }

    /**
     * Create a new seat layout snapshot for a showtime.
     *
     * @param Showtime $showtime
     * @param array $layoutData Validated seat layout structure
     * @param int $version Version number (should be derived from max existing version + 1)
     * @return static
     */
    public static function createSnapshot(Showtime $showtime, array $layoutData, int $version): self
    {
        // Generate checksum from canonical JSON representation
        $checksum = self::generateChecksum($layoutData);

        $snapshot = new self();
        $snapshot->forceFill([
            'showtime_id' => $showtime->id,
            'layout_data' => $layoutData,
            'checksum' => $checksum,
            'version' => $version,
        ]);
        $snapshot->save();

        return $snapshot;
    }

    /**
     * Generate a SHA-256 checksum from layout data.
     *
     * @param array $layoutData
     * @return string
     */
    public static function generateChecksum(array $layoutData): string
    {
        $canonical = json_encode($layoutData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        return hash('sha256', $canonical);
    }

    /**
     * Verify that the stored checksum matches the layout data.
     *
     * @return bool
     */
    public function verifyChecksum(): bool
    {
        if (empty($this->checksum)) {
            return false;
        }

        return $this->checksum === self::generateChecksum($this->layout_data ?? []);
    }

    /**
     * Get the showtime this snapshot belongs to.
     */
    public function showtime(): BelongsTo
    {
        return $this->belongsTo(Showtime::class);
    }
}