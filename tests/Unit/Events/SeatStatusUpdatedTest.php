<?php

namespace Tests\Unit\Events;

use App\Events\SeatStatusUpdated;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use PHPUnit\Framework\TestCase;

class SeatStatusUpdatedTest extends TestCase
{
    public function test_seat_updates_are_queued_after_commit(): void
    {
        $event = new SeatStatusUpdated(10, 20, 'locked', 30);

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $event);
        $this->assertSame('broadcasts', $event->broadcastQueue());
    }
}
