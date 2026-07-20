<?php

namespace Tests\Feature\Payment;

use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\Ticket;
use App\Models\User;
use App\Services\OrderFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderFulfillmentIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function duplicate_fulfillment_replays_the_original_result_without_repeating_side_effects(): void
    {
        $user = User::factory()->create();
        $showtime = Showtime::factory()->create();
        $gatewayOrderCode = (string) random_int(100000, 999999);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'showtime_id' => $showtime->id,
            'status' => Order::STATUS_PENDING,
            'payment_status' => 'pending',
            'gateway_order_code' => $gatewayOrderCode,
            'payload' => [
                'seats' => [],
                'products' => [],
                'points_used' => 0,
            ],
            'total_amount' => 100000,
        ]);

        $payment = Payment::createPending([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => 'payos',
            'transaction_code' => 'TXN-' . $gatewayOrderCode,
            'gateway_order_code' => $gatewayOrderCode,
            'amount' => 100000,
            'payload' => [],
        ]);

        $service = app(OrderFulfillmentService::class);

        $firstResult = $service->finalize((int) $gatewayOrderCode);
        $secondResult = $service->finalize((int) $gatewayOrderCode);

        $this->assertSame(
            ['already_processed' => false, 'skipped' => false],
            $firstResult
        );
        $this->assertSame($firstResult, $secondResult);

        $order->refresh();
        $payment->refresh();

        $this->assertSame(Order::STATUS_CONFIRMED, (int) $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->status);
        $this->assertNotNull($payment->paid_at);

        $this->assertSame(0, Ticket::query()->where('order_id', $order->id)->count());
        $this->assertSame(
            1,
            IdempotencyKey::query()
                ->where('key', "webhook:finalize:{$gatewayOrderCode}")
                ->where('status', 'completed')
                ->count()
        );
    }

    #[Test]
    public function completed_order_without_an_idempotency_record_is_reconciled_without_creating_tickets(): void
    {
        $user = User::factory()->create();
        $showtime = Showtime::factory()->create();
        $gatewayOrderCode = (string) random_int(100000, 999999);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'showtime_id' => $showtime->id,
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => 'paid',
            'paid_at' => now()->subMinute(),
            'gateway_order_code' => $gatewayOrderCode,
            'payload' => [
                'seats' => [],
                'products' => [],
            ],
            'total_amount' => 100000,
        ]);

        $payment = Payment::createPending([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => 'payos',
            'transaction_code' => 'TXN-' . $gatewayOrderCode,
            'gateway_order_code' => $gatewayOrderCode,
            'amount' => 100000,
            'payload' => [],
        ]);

        $result = app(OrderFulfillmentService::class)
            ->finalize((int) $gatewayOrderCode);

        $this->assertSame(
            ['already_processed' => true, 'skipped' => false],
            $result
        );
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertSame(0, Ticket::query()->where('order_id', $order->id)->count());

        $this->assertDatabaseHas('idempotency_keys', [
            'key' => "webhook:finalize:{$gatewayOrderCode}",
            'status' => 'completed',
        ]);
    }

    #[Test]
    public function fulfillment_issues_a_ticket_and_ticket_order_item_for_each_paid_seat(): void
    {
        $user = User::factory()->create();
        $showtime = Showtime::factory()->create();
        $seat = Seat::factory()->create(['screen_id' => $showtime->screen_id]);
        $gatewayOrderCode = (string) random_int(100000, 999999);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'showtime_id' => $showtime->id,
            'status' => Order::STATUS_PENDING,
            'payment_status' => 'pending',
            'gateway_order_code' => $gatewayOrderCode,
            'payload' => [
                'seats' => [[
                    'id' => $seat->id,
                    'name' => $seat->label,
                    'row' => $seat->row,
                    'number' => $seat->number,
                    'type' => 'standard',
                    'price' => '100000.00',
                ]],
                'products' => [],
                'points_used' => 0,
            ],
            'total_amount' => 100000,
        ]);

        Payment::createPending([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => 'payos',
            'transaction_code' => 'TXN-' . $gatewayOrderCode,
            'gateway_order_code' => $gatewayOrderCode,
            'amount' => 100000,
            'payload' => [],
        ]);

        app(OrderFulfillmentService::class)->finalize((int) $gatewayOrderCode);

        $ticket = Ticket::query()->where('order_id', $order->id)->sole();
        $orderItem = OrderItem::query()
            ->where('order_id', $order->id)
            ->where('item_type', Ticket::class)
            ->sole();

        $this->assertSame($ticket->id, $orderItem->item_id);
        $this->assertSame(1, $orderItem->quantity);
        $this->assertSame('100000.00', $orderItem->unit_price);
        $this->assertSame($seat->label, $orderItem->metadata['seat_label']);
        $this->assertSame(Order::STATUS_CONFIRMED, (int) $order->fresh()->status);
        $this->assertSame(Payment::STATUS_SUCCESS, $order->payment->fresh()->status);
    }

    #[Test]
    public function fulfillment_rejects_stale_payload_when_stock_was_not_reserved(): void
    {
        $user = User::factory()->create();
        $showtime = Showtime::factory()->create();
        $gatewayOrderCode = (string) random_int(100000, 999999);
        $product = Product::createManaged([
            'name' => 'Low stock fulfillment product',
            'type' => Product::TYPE_FOOD,
            'price' => 50000,
            'stock' => 1,
            'status' => true,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'showtime_id' => $showtime->id,
            'status' => Order::STATUS_PENDING,
            'payment_status' => 'pending',
            'gateway_order_code' => $gatewayOrderCode,
            'payload' => [
                'seats' => [],
                'products' => [[
                    'id' => $product->id,
                    'name' => $product->name,
                    'type' => $product->type,
                    'price' => (string) $product->price,
                    'quantity' => 3,
                ]],
            ],
            'total_amount' => 150000,
        ]);

        Payment::createPending([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => 'payos',
            'transaction_code' => 'TXN-' . $gatewayOrderCode,
            'gateway_order_code' => $gatewayOrderCode,
            'amount' => 150000,
            'payload' => [],
        ]);

        try {
            app(OrderFulfillmentService::class)->finalize((int) $gatewayOrderCode);
            $this->fail('Expected fulfillment to reject an unreserved stale stock payload.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('tồn kho', $exception->getMessage());
        }

        $this->assertSame(1, (int) $product->fresh()->stock);
        $this->assertSame(0, OrderItem::query()
            ->where('order_id', $order->id)
            ->where('item_type', Product::class)
            ->count());
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_PENDING,
            'payment_status' => 'pending',
        ]);
    }
}
