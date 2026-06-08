<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SeatStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $showtimeId,
        public readonly int    $seatId,
        public readonly string $status,  // 'locked' | 'available'
        public readonly ?int   $userId = null,
    ) {}

    /**
     * Phát sóng trên public channel của suất chiếu.
     * Dùng public channel để tất cả người dùng (kể cả chưa đăng nhập) đều nhận được.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("showtime.{$this->showtimeId}"),
        ];
    }

    /**
     * Tên event mà JS sẽ lắng nghe.
     */
    public function broadcastAs(): string
    {
        return 'seat.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'showtime_id' => $this->showtimeId,
            'seat_id'     => $this->seatId,
            'status'      => $this->status,
        ];
    }
}
