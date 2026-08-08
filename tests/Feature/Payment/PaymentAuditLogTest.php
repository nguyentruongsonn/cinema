<?php

namespace Tests\Feature\Payment;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\SeatHoldItem;
use App\Models\Showtime;
use App\Models\User;
use App\Services\OrderFulfillmentService;
use App\Services\PaymentService;
use App\Services\PayOSGateway;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Showtime $showtime;
    private array $seatIds;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(PayOSGateway::class, function ($mock) {
            $mock->shouldReceive('createPaymentLink')->andReturn([
                'checkoutUrl' => 'https://checkout.url',
                'orderCode' => '123456',
            ]);
            $mock->shouldReceive('cancelPaymentLink')->andReturn([
                'success' => true,
            ]);
        });

        $this->mock(PricingService::class, function ($mock) {
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

        $this->user = User::factory()->create();
        $this->showtime = Showtime::factory()->create();
        $this->seatIds = Seat::factory()
            ->count(2)
            ->create(['screen_id' => $this->showtime->screen_id])
            ->pluck('id')
            ->all();
    }

    #[Test]
    public function payment_initiation_writes_order_and_payment_audits_once_per_idempotency_key(): void
    {
        $this->createValidSeatHold();

        $items = array_map(fn ($id) => ['type' => 'seat', 'id' => $id], $this->seatIds);
        $idempotencyKey = Str::uuid()->toString();

        $firstResult = app(PaymentService::class)->initiate(
            $this->user,
            $this->showtime,
            ['items' => $items],
            url(''),
            $idempotencyKey
        );

        $secondResult = app(PaymentService::class)->initiate(
            $this->user,
            $this->showtime,
            ['items' => $items],
            url(''),
            $idempotencyKey
        );

        $this->assertEquals($firstResult, $secondResult);

        $order = Order::query()->where('code', $firstResult['order_number'])->firstOrFail();
        $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();

        $orderAudit = $this->auditFor('order.created', 'order', $order->id);
        $paymentAudit = $this->auditFor('payment.created', 'payment', $payment->id);

        $this->assertSame($this->user->id, $orderAudit->user_id);
        $this->assertSame([], $orderAudit->old_values);
        $this->assertSame('pending', $orderAudit->new_values['payment_status']);
        $this->assertSame($this->user->id, $paymentAudit->user_id);
        $this->assertSame(Payment::STATUS_PENDING, $paymentAudit->new_values['status']);
        $this->assertSame(1, AuditLog::query()->where('action', 'order.created')->where('auditable_id', $order->id)->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'payment.created')->where('auditable_id', $payment->id)->count());
    }

    #[Test]
    public function fulfillment_success_writes_system_order_and_payment_audits(): void
    {
        [$order, $payment] = $this->makePendingOrderAndPayment();

        $result = app(OrderFulfillmentService::class)->finalize((int) $order->gateway_order_code);

        $this->assertSame(['already_processed' => false, 'skipped' => false], $result);

        $orderAudit = $this->auditFor('order.paid', 'order', $order->id);
        $paymentAudit = $this->auditFor('payment.succeeded', 'payment', $payment->id);

        $this->assertNull($orderAudit->user_id);
        $this->assertSame('pending', $orderAudit->old_values['payment_status']);
        $this->assertSame('paid', $orderAudit->new_values['payment_status']);
        $this->assertNull($paymentAudit->user_id);
        $this->assertSame(Payment::STATUS_PENDING, $paymentAudit->old_values['status']);
        $this->assertSame(Payment::STATUS_SUCCESS, $paymentAudit->new_values['status']);
    }

    #[Test]
    public function unsuccessful_gateway_return_writes_system_cancel_audits(): void
    {
        [$order, $payment] = $this->makePendingOrderAndPayment();

        app(PaymentService::class)->markCancelledFromReturn($order);

        $orderAudit = $this->auditFor('order.cancelled', 'order', $order->id);
        $paymentAudit = $this->auditFor('payment.cancelled', 'payment', $payment->id);

        $this->assertNull($orderAudit->user_id);
        $this->assertSame('pending', $orderAudit->old_values['payment_status']);
        $this->assertSame('cancelled', $orderAudit->new_values['payment_status']);
        $this->assertNull($paymentAudit->user_id);
        $this->assertSame(Payment::STATUS_PENDING, $paymentAudit->old_values['status']);
        $this->assertSame(Payment::STATUS_CANCELLED, $paymentAudit->new_values['status']);
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

    private function makePendingOrderAndPayment(): array
    {
        $gatewayOrderCode = random_int(100000, 999999);

        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'showtime_id' => $this->showtime->id,
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
            'user_id' => $this->user->id,
            'method' => 'payos',
            'transaction_code' => 'TXN-' . $gatewayOrderCode,
            'gateway_order_code' => $gatewayOrderCode,
            'amount' => 100000,
            'payload' => [],
        ]);

        return [$order, $payment];
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
