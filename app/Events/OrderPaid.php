<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPaid implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $orderCode,
        public readonly string $orderNumber,
        public readonly int $userId,
    ) {}

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

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    public function broadcastWith(): array
    {
        return [
            'order_code' => $this->orderCode,
            'order_number' => $this->orderNumber,
            'status' => 'paid',
        ];
    }
}
