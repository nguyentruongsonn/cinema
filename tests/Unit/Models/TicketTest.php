<?php

namespace Tests\Unit\Models;

use App\Models\Order;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ticket Model Security & Integrity Tests
 * Based on: REVIEWS/files/Ticket_model_review.md
 * Tests duplicate prevention, atomic transitions, mass assignment protection
 */
class TicketTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Showtime $showtime;
    protected Seat $seat;
    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->showtime = Showtime::factory()->create();
        $this->seat = Seat::factory()->create();
        $this->order = Order::factory()->create([
            'user_id' => $this->user->id,
            'showtime_id' => $this->showtime->id,
        ]);
    }

    #[Test]
    public function it_prevents_duplicate_ticket_for_same_showtime_and_seat()
    {
        // Create first ticket
        Ticket::forceCreate([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'showtime_id' => $this->showtime->id,
            'seat_id' => $this->seat->id,
            'ticket_code' => Ticket::generateTicketCode(),
            'status' => Ticket::STATUS_VALID,
        ]);

        // Attempt to create duplicate ticket for same showtime+seat
        $this->expectException(\Illuminate\Database\QueryException::class);

        Ticket::forceCreate([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'showtime_id' => $this->showtime->id,
            'seat_id' => $this->seat->id, // SAME SEAT
            'ticket_code' => Ticket::generateTicketCode(),
            'status' => Ticket::STATUS_VALID,
        ]);
    }

    #[Test]
    public function it_allows_same_seat_for_different_showtimes()
    {
        $showtime2 = Showtime::factory()->create();
        $order2 = Order::factory()->create([
            'user_id' => $this->user->id,
            'showtime_id' => $showtime2->id,
        ]);

        // First ticket for showtime 1
        $ticket1 = Ticket::forceCreate([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'showtime_id' => $this->showtime->id,
            'seat_id' => $this->seat->id,
            'ticket_code' => Ticket::generateTicketCode(),
            'status' => Ticket::STATUS_VALID,
        ]);

        // Second ticket for showtime 2 with SAME SEAT
        $ticket2 = Ticket::forceCreate([
            'order_id' => $order2->id,
            'user_id' => $this->user->id,
            'showtime_id' => $showtime2->id,
            'seat_id' => $this->seat->id, // Same seat, different showtime
            'ticket_code' => Ticket::generateTicketCode(),
            'status' => Ticket::STATUS_VALID,
        ]);

        $this->assertNotEquals($ticket1->id, $ticket2->id);
        $this->assertEquals($ticket1->seat_id, $ticket2->seat_id);
        $this->assertNotEquals($ticket1->showtime_id, $ticket2->showtime_id);
    }

    #[Test]
    public function mark_as_used_is_atomic_and_conditional()
    {
        $ticket = Ticket::factory()->create([
            'user_id' => $this->user->id,
            'status' => Ticket::STATUS_VALID,
        ]);

        // First mark should succeed
        $result = $ticket->markAsUsed();
        $this->assertTrue($result);

        $ticket->refresh();
        $this->assertEquals(Ticket::STATUS_USED, $ticket->status);
        $this->assertNotNull($ticket->checked_in_at);

        // Second mark should fail (already used)
        $result2 = $ticket->markAsUsed();
        $this->assertFalse($result2);
    }

    #[Test]
    public function mark_as_used_only_works_on_valid_tickets()
    {
        $cancelledTicket = Ticket::factory()->cancelled()->create([
            'user_id' => $this->user->id,
        ]);

        $result = $cancelledTicket->markAsUsed();
        $this->assertFalse($result);

        $cancelledTicket->refresh();
        $this->assertEquals(Ticket::STATUS_CANCELLED, $cancelledTicket->status);
        $this->assertNull($cancelledTicket->checked_in_at);
    }

    #[Test]
    public function cancel_only_works_on_valid_tickets()
    {
        $validTicket = Ticket::factory()->create([
            'user_id' => $this->user->id,
            'status' => Ticket::STATUS_VALID,
        ]);

        $result = $validTicket->cancel();
        $this->assertTrue($result);

        $validTicket->refresh();
        $this->assertEquals(Ticket::STATUS_CANCELLED, $validTicket->status);
    }

    #[Test]
    public function cancel_fails_on_already_used_tickets()
    {
        $usedTicket = Ticket::factory()->used()->create([
            'user_id' => $this->user->id,
        ]);

        $result = $usedTicket->cancel();
        $this->assertFalse($result);

        $usedTicket->refresh();
        $this->assertEquals(Ticket::STATUS_USED, $usedTicket->status);
    }

    #[Test]
    public function refund_works_on_valid_and_cancelled_tickets()
    {
        // Valid ticket can be refunded
        $validTicket = Ticket::factory()->create([
            'user_id' => $this->user->id,
            'status' => Ticket::STATUS_VALID,
        ]);

        $result1 = $validTicket->refund();
        $this->assertTrue($result1);
        $validTicket->refresh();
        $this->assertEquals(Ticket::STATUS_REFUNDED, $validTicket->status);

        // Cancelled ticket can be refunded
        $cancelledTicket = Ticket::factory()->cancelled()->create([
            'user_id' => $this->user->id,
        ]);

        $result2 = $cancelledTicket->refund();
        $this->assertTrue($result2);
        $cancelledTicket->refresh();
        $this->assertEquals(Ticket::STATUS_REFUNDED, $cancelledTicket->status);
    }

    #[Test]
    public function refund_fails_on_already_used_tickets()
    {
        $usedTicket = Ticket::factory()->used()->create([
            'user_id' => $this->user->id,
        ]);

        $result = $usedTicket->refund();
        $this->assertFalse($result);

        $usedTicket->refresh();
        $this->assertEquals(Ticket::STATUS_USED, $usedTicket->status);
    }

    #[Test]
    public function ticket_code_is_generated_securely()
    {
        $codes = [];
        for ($i = 0; $i < 100; $i++) {
            $codes[] = Ticket::generateTicketCode();
        }

        // All codes should be unique
        $this->assertCount(100, array_unique($codes));

        // Codes contain the TKT- prefix and 16 cryptographically random characters.
        foreach ($codes as $code) {
            $this->assertStringStartsWith('TKT-', $code);
            $this->assertEquals(20, strlen($code));
            $this->assertMatchesRegularExpression('/^TKT-[A-Z0-9]{16}$/', $code);
        }
    }

    #[Test]
    public function mass_assignment_is_blocked()
    {
        $this->expectException(\Illuminate\Database\Eloquent\MassAssignmentException::class);

        Ticket::create([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'showtime_id' => $this->showtime->id,
            'seat_id' => $this->seat->id,
            'ticket_code' => 'HACKED-CODE',
            'status' => Ticket::STATUS_VALID,
        ]);
    }

    #[Test]
    public function force_create_bypasses_mass_assignment_protection()
    {
        $ticket = Ticket::forceCreate([
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'showtime_id' => $this->showtime->id,
            'seat_id' => $this->seat->id,
            'ticket_code' => Ticket::generateTicketCode(),
            'status' => Ticket::STATUS_VALID,
        ]);

        $this->assertInstanceOf(Ticket::class, $ticket);
        $this->assertEquals(Ticket::STATUS_VALID, $ticket->status);
    }

    #[Test]
    public function qr_code_is_hidden_by_default()
    {
        $ticket = Ticket::factory()->create([
            'user_id' => $this->user->id,
            'qr_code' => 'secret-qr-data',
        ]);

        $array = $ticket->toArray();
        $this->assertArrayNotHasKey('qr_code', $array);
    }

    #[Test]
    public function valid_scope_filters_correctly()
    {
        Ticket::factory()->create([
            'user_id' => $this->user->id,
            'status' => Ticket::STATUS_VALID,
        ]);

        Ticket::factory()->create([
            'user_id' => $this->user->id,
            'status' => Ticket::STATUS_USED,
        ]);

        $validTickets = Ticket::valid()->get();
        $this->assertCount(1, $validTickets);
        $this->assertEquals(Ticket::STATUS_VALID, $validTickets->first()->status);
    }

    #[Test]
    public function used_scope_filters_correctly()
    {
        Ticket::factory()->create([
            'user_id' => $this->user->id,
            'status' => Ticket::STATUS_VALID,
        ]);

        Ticket::factory()->used()->create([
            'user_id' => $this->user->id,
        ]);

        $usedTickets = Ticket::used()->get();
        $this->assertCount(1, $usedTickets);
        $this->assertEquals(Ticket::STATUS_USED, $usedTickets->first()->status);
    }
}
