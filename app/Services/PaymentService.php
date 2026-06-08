<?php

namespace App\Services;

use App\Exceptions\PaymentGatewayException;
use App\Models\Order;
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
     */
    public function initiate(
        User      $user,
        Showtime  $showtime,
        array     $validated,
        string    $baseUrl
    ): array {
        $items = collect($validated['items'] ?? []);

        $seatRequests    = $items->where('type', 'seat')->all();
        $productRequests = $items->where('type', 'product')->all();

        $pricing = $this->pricing->buildSnapshot(
            $user,
            $showtime,
            $seatRequests,
            $productRequests,
            $validated['voucher_code'] ?? null,
            (int) ($validated['points_used'] ?? 0),
        );

        $order = DB::transaction(function () use ($user, $showtime, $pricing) {
            return Order::create([
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
        });

        try {
            $response = $this->gateway->createPaymentLink([
                'orderCode'   => $order->gateway_order_code,
                'amount'      => (int) round($order->total_amount),
                'description' => substr('DH ' . $order->code, 0, 25),
                'cancelUrl'   => $baseUrl . '/booking/' . $order->showtime_id . '?paymentStatus=cancelled&orderCode=' . $order->gateway_order_code,
                'returnUrl'   => $baseUrl . '/booking/' . $order->showtime_id . '?paymentStatus=success&orderCode=' . $order->gateway_order_code,
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
