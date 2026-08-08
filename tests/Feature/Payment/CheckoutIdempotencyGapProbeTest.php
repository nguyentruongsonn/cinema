<?php

namespace Tests\Feature\Payment;

use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\SeatHoldItem;
use App\Models\Showtime;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\PayOSGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckoutIdempotencyGapProbeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function distinct_keys_replay_the_same_checkout_for_the_same_hold_and_payload(): void
    {
        $this->mock(PayOSGateway::class, function ($mock) {
            $mock->shouldReceive('createPaymentLink')
                ->once()
                ->andReturnUsing(fn (array $payload) => [
                    'checkoutUrl' => 'https://test.payos.vn/checkout/' . $payload['orderCode'],
                    'orderCode' => $payload['orderCode'],
                ]);
        });

        $this->mock(\App\Services\PricingService::class, function ($mock) {
            $mock->shouldReceive('buildSnapshot')->andReturn([
                'subtotal' => 100000,
                'discount_amount' => 0,
                'voucher_discount' => 0,
                'point_discount' => 0,
                'points_used' => 0,
                'voucher' => null,
                'seats' => [],
                'products' => [],
                'final_amount' => 100000,
            ]);
        });

        $user = User::factory()->create();
        $showtime = Showtime::factory()->create();
        $seat = Seat::factory()->create(['screen_id' => $showtime->screen_id]);
        $hold = SeatHold::create([
            'user_id' => $user->id,
            'showtime_id' => $showtime->id,
            'held_until' => now()->addMinutes(10),
        ]);

        SeatHoldItem::create([
            'seat_hold_id' => $hold->id,
            'showtime_id' => $showtime->id,
            'seat_id' => $seat->id,
            'status' => SeatHoldItem::STATUS_ACTIVE,
            'active_lock_key' => SeatHoldItem::activeLockKey($showtime->id, $seat->id),
            'expires_at' => $hold->held_until,
        ]);

        $payload = [
            'items' => [[
                'type' => 'seat',
                'id' => $seat->id,
                'quantity' => 1,
            ]],
        ];

        $paymentService = app(PaymentService::class);
        $firstResult = $paymentService->initiate(
            $user,
            $showtime,
            $payload,
            url(''),
            Str::uuid()->toString(),
        );
        $secondResult = $paymentService->initiate(
            $user,
            $showtime,
            $payload,
            url(''),
            Str::uuid()->toString(),
        );

        $this->assertSame($firstResult['gateway_order_code'], $secondResult['gateway_order_code']);
        $this->assertSame($firstResult['checkout_url'], $secondResult['checkout_url']);
        $this->assertDatabaseCount('idempotency_keys', 2);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(2, IdempotencyKey::query()->completed()->count());
        $this->assertSame(1, Order::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, Payment::query()->where('user_id', $user->id)->count());
    }
}
