<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPaid implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $orderCode,
        public readonly string $orderNumber,
        public readonly int    $userId,
    ) {}

    /**
     * Phát sóng trên private channel theo orderCode.
     * Chỉ user sở hữu đơn hàng mới nhận được.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("order.{$this->orderCode}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.paid';
    }

    public function broadcastWith(): array
    {
        return [
            'order_code'   => $this->orderCode,
            'order_number' => $this->orderNumber,
            'status'       => 'paid',
        ];
    }
}
