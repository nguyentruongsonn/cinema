<?php

namespace Tests\Feature\Order;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderDetailInvoiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function detail_merges_payload_seats_with_reserved_product_items_and_discounts(): void
    {
        $user = User::factory()->create();
        $showtime = Showtime::factory()->create();
        $seat = Seat::factory()->create(['screen_id' => $showtime->screen_id]);
        $product = Product::createManaged([
            'name' => 'Bắp rang',
            'type' => Product::TYPE_FOOD,
            'price' => 45000,
            'stock' => 10,
            'status' => true,
        ]);
        $order = Order::factory()->pending()->create([
            'user_id' => $user->id,
            'showtime_id' => $showtime->id,
            'total_amount' => 150000,
            'expired_at' => now()->addMinutes(15),
            'payload' => [
                'subtotal' => 165000,
                'discount_amount' => 15000,
                'voucher_discount' => 10000,
                'point_discount' => 5000,
                'points_used' => 5,
                'voucher' => ['code' => 'SAVE10'],
                'seats' => [[
                    'id' => $seat->id,
                    'name' => 'A1',
                    'row' => 'A',
                    'number' => 1,
                    'type' => 'VIP',
                    'price' => 120000,
                ]],
            ],
        ]);

        OrderItem::createFromProduct(
            $order,
            $product,
            1,
            '45000',
            ['product_name' => 'Bắp rang']
        )->save();

        $detail = app(OrderService::class)->format(
            app(OrderService::class)->findForUser($order->id, $user)
        );

        $this->assertSame('A1', $detail['invoice']['tickets'][0]['metadata']['seat_label']);
        $this->assertSame('Bắp rang', $detail['invoice']['products'][0]['metadata']['product_name']);
        $this->assertSame(165000.0, $detail['invoice']['subtotal']);
        $this->assertSame(10000.0, $detail['invoice']['voucher_discount']);
        $this->assertSame(5000.0, $detail['invoice']['point_discount']);
        $this->assertSame('SAVE10', $detail['invoice']['promotion']['code']);
    }

    #[Test]
    public function payment_result_endpoint_returns_complete_local_snapshot_without_gateway_sync(): void
    {
        $user = User::factory()->create();
        $showtime = Showtime::factory()->create();
        $seat = Seat::factory()->create(['screen_id' => $showtime->screen_id]);
        $order = Order::factory()->confirmed()->create([
            'user_id' => $user->id,
            'showtime_id' => $showtime->id,
            'payment_status' => 'paid',
            'total_amount' => 120000,
            'payload' => [
                'subtotal' => 120000,
                'seats' => [[
                    'id' => $seat->id,
                    'name' => 'B5',
                    'type' => 'Thường',
                    'price' => 120000,
                ]],
            ],
        ]);

        $paymentService = \Mockery::mock(PaymentService::class);
        $paymentService->shouldNotReceive('syncFromGateway');
        $this->app->instance(PaymentService::class, $paymentService);

        $response = $this->actingAs($user, 'api')
            ->getJson("/api/v1/payments/orders/{$order->gateway_order_code}");

        $response->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.invoice.tickets.0.metadata.seat_label', 'B5')
            ->assertJsonPath('data.invoice.subtotal', 120000)
            ->assertJsonPath('data.invoice.total', 120000);
    }
}
