<?php

namespace Tests\Feature\Order;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\Showtime;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderServiceBookingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orderService;
    private User $user;
    private Showtime $showtime;
    private Seat $seat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderService = app(OrderService::class);
        $this->user = User::factory()->create();
        $this->showtime = Showtime::factory()->create([
            'scheduled_at' => now()->addDay(),
        ]);
        $this->seat = Seat::factory()->create([
            'screen_id' => $this->showtime->screen_id,
        ]);
    }

    #[Test]
    public function it_rejects_a_second_order_for_an_already_booked_showtime_seat(): void
    {
        $existingOrder = Order::factory()->create([
            'user_id' => User::factory()->create()->id,
            'showtime_id' => $this->showtime->id,
            'status' => Order::STATUS_PENDING,
            'expired_at' => now()->addMinutes(15),
        ]);

        $existingOrder->orderItems()->forceCreate([
            'item_type' => Seat::class,
            'item_id' => $this->seat->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'total_price' => 100000,
        ]);

        $hold = $this->createHold([$this->seat->id]);
        $orderCountBefore = Order::query()->count();

        try {
            $this->orderService->create($this->orderPayload($hold), $this->user);
            $this->fail('A second active order for the same showtime seat should be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('đã được đặt hoặc đang chờ thanh toán', $exception->getMessage());
        }

        $this->assertSame($orderCountBefore, Order::query()->count());
        $this->assertDatabaseHas('seat_holds', ['id' => $hold->id]);
    }

    #[Test]
    public function it_rejects_a_second_checkout_attempt_after_the_first_order_consumes_the_seat(): void
    {
        $firstHold = $this->createHold([$this->seat->id]);
        $firstOrder = $this->orderService->create($this->orderPayload($firstHold), $this->user);

        $secondUser = User::factory()->create();
        $secondHold = $this->createHoldForUser($secondUser, [$this->seat->id]);
        $orderCountAfterFirstCheckout = Order::query()->count();
        $orderItemCountAfterFirstCheckout = OrderItem::query()->count();

        try {
            $this->orderService->create($this->orderPayload($secondHold), $secondUser);
            $this->fail('A second checkout attempt for the same showtime seat should be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('đã được đặt hoặc đang chờ thanh toán', $exception->getMessage());
        }

        $this->assertSame($orderCountAfterFirstCheckout, Order::query()->count());
        $this->assertSame($orderItemCountAfterFirstCheckout, OrderItem::query()->count());
        $this->assertDatabaseMissing('seat_holds', ['id' => $firstHold->id]);
        $this->assertDatabaseHas('seat_holds', ['id' => $secondHold->id]);
        $this->assertDatabaseHas('orders', ['id' => $firstOrder->id, 'user_id' => $this->user->id]);
    }

    #[Test]
    public function it_rolls_back_order_items_stock_and_hold_consumption_when_checkout_fails(): void
    {
        $hold = $this->createHold([$this->seat->id]);
        $product = Product::createManaged([
            'name' => 'Rollback Test Product',
            'type' => Product::TYPE_FOOD,
            'status' => true,
            'stock' => 5,
            'price' => 50000,
        ]);
        $orderCountBefore = Order::query()->count();

        try {
            $this->orderService->create(
                $this->orderPayload($hold, [
                    'products' => [
                        ['id' => $product->id, 'quantity' => 2],
                    ],
                    'promotion_code' => 'PROMOTION-DOES-NOT-EXIST',
                ]),
                $this->user
            );

            $this->fail('An invalid promotion should abort the complete checkout transaction.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Mã khuyến mãi không hợp lệ', $exception->getMessage());
        }

        $this->assertSame($orderCountBefore, Order::query()->count());
        $this->assertSame(5, (int) $product->fresh()->stock);
        $this->assertDatabaseHas('seat_holds', ['id' => $hold->id]);
        $this->assertSame(0, OrderItem::query()->count());
    }

    #[Test]
    public function it_rejects_duplicate_seat_ids_without_creating_an_order(): void
    {
        $hold = $this->createHold([$this->seat->id]);
        $orderCountBefore = Order::query()->count();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Danh sách ghế đặt không khớp');

        try {
            $this->orderService->create(
                $this->orderPayload($hold, [
                    'seat_ids' => [$this->seat->id, $this->seat->id],
                ]),
                $this->user
            );
        } finally {
            $this->assertSame($orderCountBefore, Order::query()->count());
            $this->assertDatabaseHas('seat_holds', ['id' => $hold->id]);
        }
    }

    private function createHold(array $seatIds): SeatHold
    {
        return $this->createHoldForUser($this->user, $seatIds);
    }

    private function createHoldForUser(User $user, array $seatIds): SeatHold
    {
        return SeatHold::factory()->create([
            'user_id' => $user->id,
            'showtime_id' => $this->showtime->id,
            'seat_ids' => $seatIds,
            'held_until' => now()->addMinutes(10),
        ]);
    }

    private function orderPayload(SeatHold $hold, array $overrides = []): array
    {
        return array_merge([
            'showtime_id' => $this->showtime->id,
            'seat_hold_id' => $hold->id,
            'seat_ids' => [$this->seat->id],
            'products' => [],
        ], $overrides);
    }
}