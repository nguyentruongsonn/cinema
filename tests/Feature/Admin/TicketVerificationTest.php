<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Screen;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\Theater;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TicketVerificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_ticket_verification_is_atomic_and_uses_checked_in_at(): void
    {
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => true]);
        $ticket = Ticket::factory()->create();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/tickets/verify', ['ticket_code' => $ticket->ticket_code])
            ->assertOk();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'status' => Ticket::STATUS_USED,
        ]);
        $this->assertNotNull($ticket->fresh()->checked_in_at);

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/tickets/verify', ['ticket_code' => $ticket->ticket_code])
            ->assertStatus(409);
    }

    #[Test]
    public function invoice_qr_cannot_consume_every_ticket_in_the_order(): void
    {
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => true]);
        [$order, $tickets] = $this->createPaidOrderWithTickets($admin);

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/admin/tickets/verify', ['ticket_code' => $order->code])
            ->assertUnprocessable();

        foreach ($tickets as $ticket) {
            $this->assertNotSame(Ticket::STATUS_USED, $ticket->fresh()->status);
            $this->assertNull($ticket->fresh()->checked_in_at);
        }
    }

    #[Test]
    public function any_assigned_staff_role_can_use_the_scanner_after_receiving_permission(): void
    {
        $sellerRole = Role::create(['name' => 'Ticket Seller', 'slug' => 'ticket_seller']);
        $verifyPermission = Permission::create([
            'name' => 'Xác thực vé',
            'slug' => 'tickets.verify',
            'group' => 'tickets',
        ]);
        $seller = User::factory()->create(['role_id' => $sellerRole->id, 'status' => true]);
        [$order, $tickets] = $this->createPaidOrderWithTickets($seller);
        $theaterId = $order->showtime->screen->theater_id;
        $seller->theaters()->attach($theaterId);

        $this->actingAs($seller, 'api')
            ->postJson('/api/v1/staff/tickets/verify', ['ticket_code' => $order->code])
            ->assertForbidden();

        $sellerRole->permissions()->attach($verifyPermission->id);

        $this->actingAs($seller->fresh(), 'web')
            ->get('/pos')
            ->assertOk()
            ->assertSee('id="scanTicketBtn"', false)
            ->assertSee('Quét mã vé');

        $this->actingAs($seller->fresh(), 'api')
            ->postJson('/api/v1/staff/tickets/verify', ['ticket_code' => $tickets->first()->ticket_code])
            ->assertOk()
            ->assertJsonPath('data.type', 'ticket')
            ->assertJsonPath('data.code', $tickets->first()->ticket_code);
    }

    /** @return array{Order, Collection<int, Ticket>} */
    private function createPaidOrderWithTickets(User $user): array
    {
        $theater = Theater::factory()->create();
        $screen = Screen::factory()->create(['theater_id' => $theater->id]);
        $showtime = Showtime::factory()->create(['screen_id' => $screen->id]);
        $order = Order::factory()->create([
            'code' => 'BOOKING-'.strtoupper(fake()->unique()->bothify('######')),
            'user_id' => $user->id,
            'showtime_id' => $showtime->id,
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
        $tickets = collect([
            ['label' => 'B9', 'number' => 9],
            ['label' => 'B10', 'number' => 10],
        ])->map(function (array $attributes) use ($order, $showtime, $user): Ticket {
            $seat = Seat::factory()->create([
                'screen_id' => $showtime->screen_id,
                'row' => 'B',
                'number' => $attributes['number'],
                'row_index' => 2,
                'column_index' => $attributes['number'],
                'label' => $attributes['label'],
            ]);

            return Ticket::factory()->create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'showtime_id' => $showtime->id,
                'seat_id' => $seat->id,
            ]);
        });

        return [$order->load('showtime.screen'), $tickets];
    }
}
