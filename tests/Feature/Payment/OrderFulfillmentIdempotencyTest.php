<?php

namespace Tests\Feature\Payment;

use App\Events\OrderPaid;
use App\Jobs\SendIssuedTicketsEmail;
use App\Mail\TicketsIssuedMail;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\Ticket;
use App\Models\User;
use App\Services\InvoicePdfService;
use App\Services\OrderFulfillmentService;
use App\Services\OrderPrintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
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
            'transaction_code' => 'TXN-'.$gatewayOrderCode,
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
            'transaction_code' => 'TXN-'.$gatewayOrderCode,
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
            'transaction_code' => 'TXN-'.$gatewayOrderCode,
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
    public function fulfillment_emails_issued_tickets_once_after_payment_success(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'customer@example.test']);
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
            'transaction_code' => 'TXN-'.$gatewayOrderCode,
            'gateway_order_code' => $gatewayOrderCode,
            'amount' => 100000,
            'payload' => [],
        ]);

        $service = app(OrderFulfillmentService::class);
        $service->finalize((int) $gatewayOrderCode);
        $service->finalize((int) $gatewayOrderCode);

        $ticket = Ticket::query()->where('order_id', $order->id)->sole();
        $freshOrder = $order->fresh()->load([
                'user:id,name,email',
                'showtime:id,scheduled_at,screen_id,movie_id',
                'showtime.movie:id,title',
                'showtime.screen:id,name,theater_id',
                'showtime.screen.theater:id,name,address',
                'tickets:id,order_id,ticket_code,seat_id,status',
                'tickets.seat:id,label',
            ]);
        $mail = new TicketsIssuedMail($freshOrder);
        $renderedMail = $mail->render();
        $pdf = app(InvoicePdfService::class)->render($freshOrder);
        $attachment = $mail->attachments()[0];

        $this->assertStringNotContainsString($ticket->ticket_code, $renderedMail);
        $this->assertStringContainsString('đính kèm trong email dưới dạng PDF', $renderedMail);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(5000, strlen($pdf));
        $this->assertSame('hoa-don-'.$order->code.'.pdf', $attachment->as);
        $this->assertSame('application/pdf', $attachment->mime);

        Mail::assertSent(TicketsIssuedMail::class, 1);
        Mail::assertSent(TicketsIssuedMail::class, function (TicketsIssuedMail $mail) use ($order) {
            return $mail->order->id === $order->id
                && $mail->hasTo('customer@example.test');
        });
        $this->assertNotNull($order->fresh()->ticket_email_sent_at);

        $order->forceFill(['ticket_email_sent_at' => null])->save();
        Mail::fake();

        $service->finalize((int) $gatewayOrderCode);

        Mail::assertSent(TicketsIssuedMail::class, 1);
        $this->assertNotNull($order->fresh()->ticket_email_sent_at);
    }

    #[Test]
    public function payment_success_is_persisted_and_broadcast_before_invoice_email_is_processed(): void
    {
        Event::fake([OrderPaid::class]);
        Queue::fake();
        Mail::fake();

        $user = User::factory()->create(['email' => 'queued-invoice@example.test']);
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
            'transaction_code' => 'TXN-'.$gatewayOrderCode,
            'gateway_order_code' => $gatewayOrderCode,
            'amount' => 100000,
            'payload' => [],
        ]);

        app(OrderFulfillmentService::class)->finalize((int) $gatewayOrderCode);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertNull($order->fresh()->ticket_email_sent_at);
        Event::assertDispatched(OrderPaid::class);
        Queue::assertPushedOn('emails', SendIssuedTicketsEmail::class);
        Mail::assertNothingSent();
    }

    #[Test]
    public function fulfillment_lists_each_reserved_seat_once_without_emailing_official_tickets(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'two-seats@example.test']);
        $showtime = Showtime::factory()->create();
        $seats = collect([
            ['row' => 'B', 'number' => 9, 'label' => 'B9'],
            ['row' => 'B', 'number' => 10, 'label' => 'B10'],
        ])->map(fn (array $attributes) => Seat::factory()->create([
            'screen_id' => $showtime->screen_id,
            'row' => $attributes['row'],
            'number' => $attributes['number'],
            'row_index' => 2,
            'column_index' => $attributes['number'],
            'label' => $attributes['label'],
        ]));
        $gatewayOrderCode = (string) random_int(100000, 999999);
        $payloadSeats = $seats->map(fn (Seat $seat): array => [
            'id' => $seat->id,
            'name' => $seat->label,
            'row' => $seat->row,
            'number' => $seat->number,
            'type' => 'standard',
            'audience_type' => 'adult',
            'price' => '80000.00',
        ])->all();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'showtime_id' => $showtime->id,
            'status' => Order::STATUS_PENDING,
            'payment_status' => 'pending',
            'gateway_order_code' => $gatewayOrderCode,
            'payload' => [
                'seats' => $payloadSeats,
                'products' => [],
                'subtotal' => 160000,
                'discount_amount' => 0,
                'points_used' => 0,
            ],
            'total_amount' => 160000,
        ]);

        foreach ($seats as $seat) {
            OrderItem::query()->create([
                'order_id' => $order->id,
                'item_type' => Seat::class,
                'item_id' => $seat->id,
                'quantity' => 1,
                'unit_price' => '80000.00',
                'total_price' => '80000.00',
                'metadata' => [
                    'seat_label' => $seat->label,
                    'row' => $seat->row,
                    'number' => $seat->number,
                    'seat_type' => 'standard',
                    'audience_type' => 'adult',
                ],
            ]);
        }

        Payment::createPending([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => 'payos',
            'transaction_code' => 'TXN-'.$gatewayOrderCode,
            'gateway_order_code' => $gatewayOrderCode,
            'amount' => 160000,
            'payload' => [],
        ]);

        app(OrderFulfillmentService::class)->finalize((int) $gatewayOrderCode);

        $freshOrder = $order->fresh()->load([
            'user:id,name,email',
            'showtime:id,scheduled_at,screen_id,movie_id,format_id,version_type_id',
            'showtime.movie:id,title,duration,age_rating',
            'showtime.format:id,name',
            'showtime.versionType:id,name',
            'showtime.screen:id,name,theater_id',
            'showtime.screen.theater:id,name,address',
            'tickets:id,order_id,ticket_code,seat_id,status',
            'tickets.seat:id,label',
            'orderItems:id,order_id,item_type,item_id,quantity,unit_price,total_price,metadata,fulfillment_status',
            'payment:id,order_id,method',
        ]);
        $printData = app(OrderPrintService::class)->printData($freshOrder);
        $pdf = app(InvoicePdfService::class)->render($freshOrder);

        $this->assertSame(2, $freshOrder->tickets->count());
        $this->assertSame(0, $freshOrder->orderItems->where('item_type', Seat::class)->count());
        $this->assertSame(2, $freshOrder->orderItems->where('item_type', Ticket::class)->count());
        $this->assertSame(['B10', 'B9'], collect($printData['tickets'])->pluck('seat_label')->sort()->values()->all());
        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    #[Test]
    public function fulfillment_lists_concessions_on_the_invoice_without_emailing_a_receipt(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'concession@example.test']);
        $showtime = Showtime::factory()->create();
        $product = Product::createManaged([
            'name' => 'Bắp rang caramel',
            'type' => Product::TYPE_FOOD,
            'price' => 45000,
            'stock' => 10,
            'status' => true,
        ]);
        $gatewayOrderCode = (string) random_int(100000, 999999);

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
                    'quantity' => 2,
                    'price' => '45000.00',
                    'type' => 'food',
                    'item_type' => 'product',
                ]],
                'product_stock_reserved' => true,
                'points_used' => 0,
            ],
            'total_amount' => 90000,
        ]);

        OrderItem::createFromProduct($order, $product, 2, '45000.00')->save();
        Payment::createPending([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => 'payos',
            'transaction_code' => 'TXN-'.$gatewayOrderCode,
            'gateway_order_code' => $gatewayOrderCode,
            'amount' => 90000,
            'payload' => [],
        ]);

        app(OrderFulfillmentService::class)->finalize((int) $gatewayOrderCode);

        Mail::assertSent(TicketsIssuedMail::class, function (TicketsIssuedMail $mail): bool {
            $rendered = $mail->render();
            $attachment = $mail->attachments()[0];

            return $mail->hasTo('concession@example.test')
                && str_contains($rendered, 'đính kèm trong email dưới dạng PDF')
                && $attachment->as === 'hoa-don-'.$mail->order->code.'.pdf'
                && $attachment->mime === 'application/pdf'
                && ! str_contains($rendered, 'PHIẾU NHẬN SẢN PHẨM')
                && ! str_contains($rendered, 'RECEIPT SẢN PHẨM');
        });
        $this->assertNotNull($order->fresh()->ticket_email_sent_at);
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
            'transaction_code' => 'TXN-'.$gatewayOrderCode,
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
