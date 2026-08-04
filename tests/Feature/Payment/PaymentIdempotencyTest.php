<?php

namespace Tests\Feature\Payment;

use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\Screen;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\SeatHoldItem;
use App\Models\Showtime;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\PayOSGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 1 Test: Payment Idempotency
 *
 * Tests that payment initiation is idempotent:
 * - Same idempotency key returns cached response
 * - Same key with different payload is rejected
 * - Concurrent requests with same key are blocked
 */
class PaymentIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $paymentService;
    private User $user;
    private Showtime $showtime;
    private array $seatIds;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock PayOSGateway
        $this->mock(PayOSGateway::class, function ($mock) {
            $mock->shouldReceive('createPaymentLink')
                ->andReturn([
                    'checkoutUrl' => 'https://test.payos.vn/checkout',
                    'orderCode' => 'TEST123',
                ]);
        });

        $this->paymentService = app(PaymentService::class);
        $this->user = User::factory()->create();
        $this->showtime = Showtime::factory()->create();

        $seats = Seat::factory()
            ->count(2)
            ->create(['screen_id' => $this->showtime->screen_id]);

        $this->seatIds = $seats->pluck('id')->toArray();
    }

    private function createValidSeatHold(): void
    {
        $hold = SeatHold::create([
            'user_id' => $this->user->id,
            'showtime_id' => $this->showtime->id,
            'held_until' => now()->addMinutes(10),
        ]);

        foreach ($this->seatIds as $seatId) {
            SeatHoldItem::create([
                'seat_hold_id' => $hold->id,
                'showtime_id' => $this->showtime->id,
                'seat_id' => $seatId,
                'status' => SeatHoldItem::STATUS_ACTIVE,
                'active_lock_key' => SeatHoldItem::activeLockKey($this->showtime->id, $seatId),
                'expires_at' => $hold->held_until,
            ]);
        }
    }

    #[Test]
    public function payment_initiation_with_same_idempotency_key_returns_cached_response()
    {
        $this->createValidSeatHold();

        $idempotencyKey = Str::uuid()->toString();
        $items = array_map(fn($id) => ['type' => 'seat', 'id' => $id], $this->seatIds);

        // First request
        $result1 = $this->paymentService->initiate(
            $this->user,
            $this->showtime,
            ['items' => $items],
            url(''),
            $idempotencyKey
        );

        $this->assertArrayHasKey('checkout_url', $result1);
        $this->assertArrayHasKey('gateway_order_code', $result1);
        $originalOrderCode = $result1['gateway_order_code'];

        // Verify idempotency key was stored
        $this->assertDatabaseHas('idempotency_keys', [
            'key' => $idempotencyKey,
            'status' => IdempotencyKey::STATUS_COMPLETED,
        ]);

        // Second request with same key - should return cached response
        $result2 = $this->paymentService->initiate(
            $this->user,
            $this->showtime,
            ['items' => $items],
            url(''),
            $idempotencyKey
        );

        // Same response
        $this->assertEquals($result1, $result2);
        $this->assertEquals($originalOrderCode, $result2['gateway_order_code']);

        // Only ONE order was created
        $this->assertEquals(1, Order::count());
        $this->assertEquals(1, Payment::count());

        // Idempotency key still completed
        $key = IdempotencyKey::where('key', $idempotencyKey)->first();
        $this->assertEquals(IdempotencyKey::STATUS_COMPLETED, $key->status);
    }

    #[Test]
    public function payment_initiation_rejects_same_key_with_different_payload()
    {
        // Create hold for ALL seats to allow flexibility in test
        $this->createValidSeatHold();

        $idempotencyKey = Str::uuid()->toString();
        $items1 = array_map(fn($id) => ['type' => 'seat', 'id' => $id], $this->seatIds);

        // First request with both seats
        $result1 = $this->paymentService->initiate(
            $this->user,
            $this->showtime,
            ['items' => $items1],
            url(''),
            $idempotencyKey
        );

        $this->assertNotNull($result1['order_number']);

        // Second request with DIFFERENT payload (add voucher) but same key
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Idempotency key reused with different request payload');

        $items2 = array_map(fn($id) => ['type' => 'seat', 'id' => $id], $this->seatIds);

        $this->paymentService->initiate(
            $this->user,
            $this->showtime,
            ['items' => $items2, 'voucher_code' => 'DIFFERENT'],
            url(''),
            $idempotencyKey  // Same key, different payload
        );
    }

    #[Test]
    public function payment_initiation_blocks_concurrent_requests_with_same_key()
    {
        $this->createValidSeatHold();

        $idempotencyKey = Str::uuid()->toString();
        $items = array_map(fn($id) => ['type' => 'seat', 'id' => $id], $this->seatIds);

        // Simulate concurrent request by creating a PROCESSING idempotency key
        IdempotencyKey::create([
            'key' => $idempotencyKey,
            'user_id' => $this->user->id,
            'status' => IdempotencyKey::STATUS_PROCESSING,
            'expires_at' => now()->addHours(1),
        ]);

        // Concurrent request should be blocked
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Operation already in progress');

        $this->paymentService->initiate(
            $this->user,
            $this->showtime,
            ['items' => $items],
            url(''),
            $idempotencyKey
        );
    }

    #[Test]
    public function failed_payment_can_be_retried_with_same_idempotency_key()
    {
        $this->createValidSeatHold();

        $idempotencyKey = Str::uuid()->toString();

        // Create a FAILED idempotency key
        IdempotencyKey::create([
            'key' => $idempotencyKey,
            'user_id' => $this->user->id,
            'status' => IdempotencyKey::STATUS_FAILED,
            'response_data' => ['error' => 'Previous attempt failed'],
            'expires_at' => now()->addHours(1),
        ]);

        $items = array_map(fn($id) => ['type' => 'seat', 'id' => $id], $this->seatIds);

        // Retry with same key should succeed
        $result = $this->paymentService->initiate(
            $this->user,
            $this->showtime,
            ['items' => $items],
            url(''),
            $idempotencyKey
        );

        $this->assertArrayHasKey('checkout_url', $result);
        $this->assertArrayHasKey('order_number', $result);

        // Key should now be COMPLETED
        $key = IdempotencyKey::where('key', $idempotencyKey)->first();
        $this->assertEquals(IdempotencyKey::STATUS_COMPLETED, $key->status);
    }

    #[Test]
    public function invalid_idempotency_key_format_is_rejected()
    {
        $this->createValidSeatHold();

        $invalidKey = 'not-a-uuid';
        $items = array_map(fn($id) => ['type' => 'seat', 'id' => $id], $this->seatIds);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid idempotency key format');

        $this->paymentService->initiate(
            $this->user,
            $this->showtime,
            ['items' => $items],
            url(''),
            $invalidKey
        );
    }

    #[Test]
    public function zero_amount_checkout_is_confirmed_without_gateway_redirect()
    {
        Mail::fake();

        $this->createValidSeatHold();

        $promotion = Promotion::factory()->fixed(999999)->active()->create([
            'code' => 'FREEORDER',
            'min_order_value' => 0,
            'usage_limit' => 10,
            'usage_count' => 0,
        ]);

        DB::table('user_promotion')->insert([
            'user_id' => $this->user->id,
            'promotion_id' => $promotion->id,
            'status' => 1,
            'usage_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $items = array_map(fn($id) => ['type' => 'seat', 'id' => $id], $this->seatIds);

        $result = $this->paymentService->initiate(
            $this->user,
            $this->showtime,
            [
                'items' => $items,
                'voucher_code' => 'FREEORDER',
            ],
            url(''),
            Str::uuid()->toString()
        );

        $this->assertFalse($result['requires_payment']);
        $this->assertNull($result['checkout_url']);
        $this->assertSame('paid', $result['payment_status']);
        $this->assertSame(0.0, $result['total_amount']);

        $order = Order::query()->where('code', $result['order_number'])->firstOrFail();
        $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertTrue($order->isPaid());
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->status);
        $this->assertSame(2, $order->tickets()->count());
    }
}
