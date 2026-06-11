<?php

namespace App\Services;

use App\Exceptions\PaymentGatewayException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Orchestrator: điều phối toàn bộ luồng tạo và xử lý thanh toán.
 */
class PaymentService
{
    public function __construct(
        private readonly PayOSGateway            $gateway,
        private readonly PricingService          $pricing,
        private readonly OrderFulfillmentService $fulfillment,
    ) {}

    /**
     * Tạo đơn hàng và link thanh toán.
     * Phase 1: Added SeatHold validation and full transaction wrapping
     */
    public function initiate(
        User      $user,
        Showtime  $showtime,
        array     $validated,
        string    $baseUrl
    ): array {
        return DB::transaction(function () use ($user, $showtime, $validated, $baseUrl) {
            $items = collect($validated['items'] ?? []);

            $seatRequests    = $items->where('type', 'seat')->all();
            $productRequests = $items->where('type', 'product')->all();

            // PHASE 1: Validate seat hold BEFORE creating order
            $seatIds = collect($seatRequests)
                ->map(fn($seat) => (int)($seat['id'] ?? $seat))
                ->filter()
                ->values()
                ->all();

            if (!empty($seatIds)) {
                $this->validateSeatHold($user, $showtime, $seatIds);
            }

            $pricing = $this->pricing->buildSnapshot(
                $user,
                $showtime,
                $seatRequests,
                $productRequests,
                $validated['voucher_code'] ?? null,
                (int) ($validated['points_used'] ?? 0),
            );

            $order = Order::create([
                'code'               => $this->generateOrderNumber(),
                'gateway_order_code' => $this->generateOrderCode(),
                'payment_provider'   => 'payos',
                'user_id'            => $user->id,
                'showtime_id'        => $showtime->id,
                'total_amount'       => $pricing['final_amount'],
                'payload'            => [
                    'subtotal'         => $pricing['subtotal'],
                    'discount_amount'  => $pricing['discount_amount'],
                    'voucher_discount' => $pricing['voucher_discount'],
                    'point_discount'   => $pricing['point_discount'],
                    'points_used'      => $pricing['points_used'],
                    'voucher'          => $pricing['voucher'],
                    'seats'            => $pricing['seats'],
                    'products'         => $pricing['products'],
                ],
                'status'         => Order::STATUS_PENDING,
                'payment_status' => 'pending',
                'expired_at'     => now()->addMinutes(15),
            ]);

            // PHASE 2: Create payment record for audit trail
            $payment = Payment::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'method' => 'payos',
                'gateway_order_code' => $order->gateway_order_code,
                'amount' => $order->total_amount,
                'status' => Payment::STATUS_PENDING,
                'payload' => [
                    'showtime_id' => $showtime->id,
                    'seat_ids' => $seatIds,
                    'created_at' => now()->toISOString(),
                ],
            ]);

            try {
                $response = $this->gateway->createPaymentLink([
                'orderCode'   => $order->gateway_order_code,
                'amount'      => (int) round($order->total_amount),
                'description' => substr('DH ' . $order->code, 0, 25),
                'cancelUrl'   => $baseUrl . '/booking/' . $showtime->encrypted_id . '?paymentStatus=cancelled&orderCode=' . $order->gateway_order_code,
                'returnUrl'   => $baseUrl . '/booking/' . $showtime->encrypted_id . '?paymentStatus=success&orderCode=' . $order->gateway_order_code,
                'items'       => [[
                    'name'     => 'Don hang ' . $order->code,
                    'quantity' => 1,
                    'price'    => (int) round($order->total_amount),
                ]],
            ]);
            } catch (PaymentGatewayException $e) {
                // Hủy đơn nếu tạo link thất bại
                $order->update(['status' => Order::STATUS_CANCELLED, 'payment_status' => 'failed']);
                throw $e;
            }

            $checkoutUrl = $response['checkoutUrl'] ?? null;
            $order->forceFill(['checkout_url' => $checkoutUrl])->save();

            return [
                'checkout_url'       => $checkoutUrl,
                'gateway_order_code' => $order->gateway_order_code,
                'order_number'       => $order->code,
            ];
        });
    }

    /**
     * PHASE 1: Validate user has valid seat hold for payment
     * Prevents bypass of seat locking mechanism
     */
    private function validateSeatHold(User $user, Showtime $showtime, array $seatIds): void
    {
        // 1. Normalize and validate seat IDs
        $seatIds = array_values(array_unique(array_map('intval', $seatIds)));
        sort($seatIds);

        if (empty($seatIds)) {
            throw new \RuntimeException('Danh sách ghế trống.');
        }

        // 2. Validate seats belong to showtime's screen with lock
        $seats = Seat::query()
            ->whereIn('id', $seatIds)
            ->where('screen_id', $showtime->screen_id)
            ->lockForUpdate()
            ->get();

        if ($seats->count() !== count($seatIds)) {
            throw new \RuntimeException('Một hoặc nhiều ghế không thuộc phòng chiếu này.');
        }

        // 3. Validate user has valid hold for these seats
        $hold = SeatHold::query()
            ->valid()
            ->where('user_id', $user->id)
            ->where('showtime_id', $showtime->id)
            ->lockForUpdate()
            ->first();

        if (!$hold) {
            throw new \RuntimeException('Phiên giữ ghế đã hết hạn. Vui lòng chọn lại ghế.');
        }

        $heldSeatIds = array_values(array_unique(array_map('intval', (array) $hold->seat_ids)));
        sort($heldSeatIds);

        if ($seatIds !== $heldSeatIds) {
            throw new \RuntimeException('Danh sách ghế không khớp với phiên giữ ghế.');
        }

        // 4. Check seats not already booked (double-booking protection)
        $bookedSeatIds = OrderItem::query()
            ->where('item_type', Seat::class)
            ->whereIntegerInRaw('item_id', $seatIds)
            ->whereHas('order', function ($query) use ($showtime) {
                $query->where('showtime_id', $showtime->id)
                    ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED]);
            })
            ->pluck('item_id')
            ->all();

        if (!empty($bookedSeatIds)) {
            throw new \RuntimeException('Một số ghế đã được đặt bởi người dùng khác.');
        }
    }

    /**
     * Xử lý webhook PayOS.
     */
    public function handleWebhook(array $rawData): array
    {
        $webhookData = $this->gateway->verifyWebhook($rawData);

        $orderCode = $webhookData['orderCode'] ?? $webhookData['order_code'] ?? null;
        $status    = strtoupper((string) ($webhookData['status'] ?? ''));

        if (! $orderCode) {
            throw new \InvalidArgumentException('Thiếu orderCode trong webhook.');
        }

        if ($status !== 'PAID' && $status !== 'COMPLETED') {
            Order::where('gateway_order_code', (int) $orderCode)
                ->where('status', Order::STATUS_PENDING)
                ->update(['payment_status' => strtolower($status ?: 'failed')]);

            return ['already_processed' => false, 'skipped' => true];
        }

        return $this->fulfillment->finalize((int) $orderCode);
    }

    /**
     * Đồng bộ trạng thái đơn hàng với PayOS (polling từ frontend).
     */
    public function syncFromGateway(Order $order): void
    {
        try {
            $info   = $this->gateway->getPaymentInfo($order->gateway_order_code);
            $status = strtoupper((string) ($info['status'] ?? ''));

            if ($status === 'PAID') {
                $this->fulfillment->finalize((int) $order->gateway_order_code);
            } elseif (in_array($status, ['CANCELLED', 'EXPIRED'], true)) {
                $lower = strtolower($status);
                $order->forceFill([
                    'status'         => $lower === 'cancelled' ? Order::STATUS_CANCELLED : Order::STATUS_PENDING, // Nếu expired vẫn để pending nhưng hết hạn check at finalize
                    'payment_status' => $lower,
                    'cancelled_at'   => $lower === 'cancelled' ? now() : $order->cancelled_at,
                ])->save();
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function generateOrderNumber(): string
    {
        return 'DH' . strtoupper(Str::random(10));
    }

    private function generateOrderCode(): int
    {
        do {
            $value = (int) (now()->format('ymdHis') . random_int(10, 99));
        } while (Order::where('gateway_order_code', $value)->exists());

        return $value;
    }
}
