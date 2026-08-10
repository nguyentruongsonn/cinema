<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPrintingTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_lookup_prepares_printing_without_consuming_tickets(): void
    {
        [$admin, $order, $ticket] = $this->paidOrderWithTicket();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/staff/orders/print-lookup', ['identifier' => $order->code])
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.code', $order->code)
            ->assertJsonPath('data.tickets.0.ticket_code', $ticket->ticket_code)
            ->assertJsonPath('data.print_count', 0)
            ->assertJsonPath('data.available_sections.0', 'invoice');

        $this->assertSame(Ticket::STATUS_VALID, $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->checked_in_at);
    }

    public function test_booking_id_cannot_be_used_as_ticket_check_in(): void
    {
        [$admin, $order, $ticket] = $this->paidOrderWithTicket();

        $this->actingAs($admin, 'api')
            ->postJson('/api/v1/staff/tickets/verify', ['ticket_code' => $order->code])
            ->assertStatus(422);

        $this->assertSame(Ticket::STATUS_VALID, $ticket->fresh()->status);
    }

    public function test_print_page_records_an_auditable_print_request(): void
    {
        [$admin, $order] = $this->paidOrderWithTicket();

        $response = $this->actingAs($admin)
            ->get("/staff/orders/{$order->id}/print?sections=invoice,tickets,concessions")
            ->assertOk()
            ->assertSee('data-invoice-template="shared"', false)
            ->assertSee('slip-watermark-pattern', false)
            ->assertSee('HÓA ĐƠN THANH TOÁN')
            ->assertSee('VÉ XEM PHIM')
            ->assertSee($order->code)
            ->assertDontSee('Email:')
            ->assertDontSee((string) $order->user->email);

        $this->assertSame(3, substr_count($response->getContent(), 'slip-watermark-pattern'));
        $this->assertSame(3, substr_count($response->getContent(), 'data-watermark-mode="print"'));
        $this->assertGreaterThanOrEqual(216, substr_count($response->getContent(), '>CINEMA</span>'));

        $this->assertDatabaseHas('order_print_logs', [
            'order_id' => $order->id,
            'printed_by_user_id' => $admin->id,
            'print_type' => 'invoice,tickets,concessions',
            'copy_number' => 1,
            'is_reprint' => false,
            'status' => 'requested',
        ]);
    }

    private function paidOrderWithTicket(): array
    {
        $role = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        $admin = User::factory()->create(['role_id' => $role->id, 'status' => true]);
        $customer = User::factory()->create();
        $showtime = Showtime::factory()->create();
        $seat = Seat::factory()->create(['screen_id' => $showtime->screen_id]);
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'showtime_id' => $showtime->id,
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => 'paid',
            'payment_method' => 'payos',
            'source' => 'web',
            'total_amount' => 120000,
            'paid_at' => now(),
        ]);
        $ticket = Ticket::forceCreate([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'showtime_id' => $showtime->id,
            'seat_id' => $seat->id,
            'ticket_code' => Ticket::generateTicketCode(),
            'status' => Ticket::STATUS_VALID,
        ]);
        OrderItem::createFromTicket($order, $ticket, '120000', [
            'seat_label' => $seat->label,
            'seat_type' => 'Thường',
            'audience_type' => 'adult',
            'ticket_code' => $ticket->ticket_code,
        ])->save();
        $product = Product::createManaged([
            'name' => 'Bắp rang',
            'type' => Product::TYPE_FOOD,
            'price' => 35000,
            'stock' => 10,
            'status' => true,
        ]);
        OrderItem::createFromProduct($order, $product, 1, '35000')->save();

        return [$admin, $order, $ticket];
    }
}
