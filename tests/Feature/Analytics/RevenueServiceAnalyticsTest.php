<?php

namespace Tests\Feature\Analytics;

use App\Models\Movie;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Screen;
use App\Models\Showtime;
use App\Models\Theater;
use App\Models\Ticket;
use App\Models\User;
use App\Services\RevenueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RevenueServiceAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function revenue_stats_count_only_successful_payments_and_ticket_items(): void
    {
        $paidAt = now()->startOfDay()->addHours(10);
        $movie = Movie::factory()->create(['title' => 'Revenue Movie']);
        $theater = Theater::factory()->create(['name' => 'Revenue Theater']);
        $screen = Screen::factory()->create(['theater_id' => $theater->id]);
        $showtime = Showtime::factory()->create([
            'movie_id' => $movie->id,
            'screen_id' => $screen->id,
        ]);

        $successfulOrder = $this->createPaidOrder($showtime, 100000, $paidAt, Payment::STATUS_SUCCESS);
        $failedOrder = $this->createPaidOrder($showtime, 900000, $paidAt, Payment::STATUS_FAILED);

        $this->createTicketItem($successfulOrder, 2, 50000);
        $this->createTicketItem($failedOrder, 9, 100000);

        $stats = app(RevenueService::class)->getStats(
            $paidAt->toDateString(),
            $paidAt->toDateString()
        );

        $this->assertSame(100000.0, (float) $stats['summary']['total_revenue']);
        $this->assertSame(1, $stats['summary']['total_orders']);
        $this->assertSame(2, $stats['summary']['total_tickets']);
        $this->assertSame('Revenue Theater', $stats['top_theater']['name']);
        $this->assertSame('100000.00', $stats['top_theater']['revenue']);
        $this->assertSame('Revenue Movie', $stats['top_movie']['title']);
        $this->assertSame(2, $stats['top_movie']['tickets']);
        $this->assertSame(1, $stats['payment_methods']['total_count']);
        $this->assertSame('100000.00', $stats['payment_methods']['total_amount']);
    }

    #[Test]
    public function revenue_stats_reject_unbounded_or_reversed_ranges(): void
    {
        $service = app(RevenueService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->getStats(now()->toDateString(), now()->subDay()->toDateString());
    }

    private function createPaidOrder(Showtime $showtime, int $amount, \DateTimeInterface $paidAt, string $paymentStatus): Order
    {
        $order = Order::factory()->create([
            'user_id' => User::factory()->create()->id,
            'showtime_id' => $showtime->id,
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => $paymentStatus === Payment::STATUS_SUCCESS ? 'paid' : 'failed',
            'paid_at' => $paidAt,
            'total_amount' => $amount,
        ]);

        $payment = Payment::createPending([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'method' => 'payos',
            'transaction_code' => 'REV-' . $order->id,
            'gateway_order_code' => (string) $order->gateway_order_code,
            'amount' => $amount,
            'payload' => [],
        ]);

        $payment->forceFill([
            'status' => $paymentStatus,
            'paid_at' => $paymentStatus === Payment::STATUS_SUCCESS ? $paidAt : null,
            'failed_at' => $paymentStatus === Payment::STATUS_FAILED ? $paidAt : null,
        ])->save();

        return $order;
    }

    private function createTicketItem(Order $order, int $quantity, int $unitPrice): void
    {
        OrderItem::query()->forceCreate([
            'order_id' => $order->id,
            'item_type' => Ticket::class,
            'item_id' => $order->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $quantity * $unitPrice,
        ]);
    }
}
