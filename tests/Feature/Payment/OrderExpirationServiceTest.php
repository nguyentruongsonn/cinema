<?php

namespace Tests\Feature\Payment;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\SeatHoldItem;
use App\Models\Showtime;
use App\Models\User;
use App\Services\OrderExpirationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderExpirationServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function expired_unpaid_order_is_cancelled_and_all_reserved_resources_are_restored(): void
    {
        config(['broadcasting.default' => 'null']);

        $user = User::factory()->create();
        $showtime = Showtime::factory()->create();
        $seat = Seat::factory()->create([
            'screen_id' => $showtime->screen_id,
        ]);
        $product = Product::createManaged([
            'name' => 'Expiration test product',
            'type' => Product::TYPE_FOOD,
            'price' => 50000,
            'stock' => 3,
            'status' => true,
        ]);
        $promotion = Promotion::query()->forceCreate([
            'name' => 'Expiration test promotion',
            'code' => 'EXPIRATION10',
            'discount_type' => 'fixed_amount',
            'discount_value' => 10000,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'usage_count' => 2,
            'status' => true,
        ]);

        $hold = SeatHold::create([
            'showtime_id' => $showtime->id,
            'user_id' => $user->id,
            'held_until' => now()->subMinute(),
        ]);

        $holdItem = SeatHoldItem::create([
            'seat_hold_id' => $hold->id,
            'showtime_id' => $showtime->id,
            'seat_id' => $seat->id,
            'status' => SeatHoldItem::STATUS_ACTIVE,
            'active_lock_key' => SeatHoldItem::activeLockKey($showtime->id, $seat->id),
            'expires_at' => now()->subMinute(),
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'showtime_id' => $showtime->id,
            'status' => Order::STATUS_PENDING,
            'payment_status' => 'pending',
            'paid_at' => null,
            'expired_at' => now()->subMinute(),
            'payload' => [
                'seat_hold_id' => $hold->id,
                'promotion' => ['id' => $promotion->id],
            ],
        ]);

        $productItem = OrderItem::createFromProduct(
            $order,
            $product,
            2,
            (string) $product->price
        );
        $productItem->save();

        $payment = Payment::createPending([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => 'payos',
            'transaction_code' => 'EXP-' . $order->id,
            'gateway_order_code' => (string) $order->gateway_order_code,
            'amount' => $order->total_amount,
            'payload' => [],
        ]);

        $expiredCount = app(OrderExpirationService::class)->expirePendingOrders();

        $this->assertSame(1, $expiredCount);
        $this->assertSame(Order::STATUS_CANCELLED, (int) $order->fresh()->status);
        $this->assertSame(Payment::STATUS_CANCELLED, $payment->fresh()->status);
        $this->assertSame(5, (int) $product->fresh()->stock);
        $this->assertSame(1, (int) $promotion->fresh()->usage_count);
        $this->assertDatabaseMissing('seat_holds', ['id' => $hold->id]);

        // Deleting the parent hold cascades to normalized hold items. The absence
        // of both rows proves that no active lock survives expiration cleanup.
        $this->assertDatabaseMissing('seat_hold_items', ['id' => $holdItem->id]);
        $this->assertDatabaseMissing('seat_hold_items', [
            'active_lock_key' => SeatHoldItem::activeLockKey($showtime->id, $seat->id),
        ]);

        $orderAudit = $this->auditFor('order.expired', 'order', $order->id);
        $paymentAudit = $this->auditFor('payment.cancelled', 'payment', $payment->id);

        $this->assertNull($orderAudit->user_id);
        $this->assertSame('pending', $orderAudit->old_values['payment_status']);
        $this->assertSame('expired', $orderAudit->new_values['payment_status']);
        $this->assertNull($paymentAudit->user_id);
        $this->assertSame(Payment::STATUS_PENDING, $paymentAudit->old_values['status']);
        $this->assertSame(Payment::STATUS_CANCELLED, $paymentAudit->new_values['status']);
    }

    #[Test]
    public function paid_order_is_not_expired_or_restored_even_when_expiration_time_has_passed(): void
    {
        $user = User::factory()->create();
        $showtime = Showtime::factory()->create();
        $product = Product::createManaged([
            'name' => 'Paid order test product',
            'type' => Product::TYPE_FOOD,
            'price' => 50000,
            'stock' => 3,
            'status' => true,
        ]);
        $promotion = Promotion::query()->forceCreate([
            'name' => 'Paid order test promotion',
            'code' => 'PAIDORDER10',
            'discount_type' => 'fixed_amount',
            'discount_value' => 10000,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'usage_count' => 2,
            'status' => true,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'showtime_id' => $showtime->id,
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => 'paid',
            'paid_at' => now()->subSecond(),
            'expired_at' => now()->subMinute(),
            'payload' => [
                'promotion' => ['id' => $promotion->id],
            ],
        ]);

        $productItem = OrderItem::createFromProduct(
            $order,
            $product,
            2,
            (string) $product->price
        );
        $productItem->save();

        $payment = Payment::createPending([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'method' => 'payos',
            'transaction_code' => 'PAID-' . $order->id,
            'gateway_order_code' => (string) $order->gateway_order_code,
            'amount' => $order->total_amount,
            'payload' => [],
        ]);
        $payment->markSuccessful(now());

        $expiredCount = app(OrderExpirationService::class)->expirePendingOrders();

        $this->assertSame(0, $expiredCount);
        $this->assertSame(Order::STATUS_CONFIRMED, (int) $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertSame(2, (int) $promotion->fresh()->usage_count);
    }

    #[Test]
    public function expiration_is_idempotent_and_does_not_restore_resources_twice(): void
    {
        $user = User::factory()->create();
        $showtime = Showtime::factory()->create();
        $product = Product::createManaged([
            'name' => 'Idempotent expiration test product',
            'type' => Product::TYPE_FOOD,
            'price' => 50000,
            'stock' => 4,
            'status' => true,
        ]);
        $promotion = Promotion::query()->forceCreate([
            'name' => 'Idempotent expiration promotion',
            'code' => 'IDEMPOTENTEXP',
            'discount_type' => 'fixed_amount',
            'discount_value' => 10000,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'usage_count' => 1,
            'status' => true,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'showtime_id' => $showtime->id,
            'status' => Order::STATUS_PENDING,
            'payment_status' => 'pending',
            'paid_at' => null,
            'expired_at' => now()->subMinute(),
            'payload' => [
                'promotion' => ['id' => $promotion->id],
            ],
        ]);

        $productItem = OrderItem::createFromProduct(
            $order,
            $product,
            2,
            (string) $product->price
        );
        $productItem->save();

        $service = app(OrderExpirationService::class);

        $this->assertSame(1, $service->expirePendingOrders());
        $this->assertSame(0, $service->expirePendingOrders());
        $this->assertSame(6, (int) $product->fresh()->stock);
        $this->assertSame(0, (int) $promotion->fresh()->usage_count);
        $this->assertSame(Order::STATUS_CANCELLED, (int) $order->fresh()->status);
        $this->assertSame(1, AuditLog::query()->where('action', 'order.expired')->where('auditable_id', $order->id)->count());
    }

    private function auditFor(string $action, string $auditableType, int $auditableId): AuditLog
    {
        return AuditLog::query()
            ->where('action', $action)
            ->where('auditable_type', $auditableType)
            ->where('auditable_id', $auditableId)
            ->latest('id')
            ->firstOrFail();
    }
}
