<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Services\PayOS\PayOSService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    private const ORDER_STATUS_CANCELLED = 0;
    private const ORDER_STATUS_PENDING = 1;
    private const ORDER_STATUS_CONFIRMED = 2;

    private const PAYMENT_STATUS_PENDING = 1;
    private const PAYMENT_STATUS_COMPLETED = 2;
    private const PAYMENT_STATUS_FAILED = 3;

    public function __construct(
        private readonly OrderExpirationService $orderExpirationService,
        private readonly PayOSService $payOSService
    ) {
    }

    public function create(array $data, $user): Payment
    {
        return DB::transaction(function () use ($data, $user) {
            $order = Order::with('payment')
                ->lockForUpdate()
                ->findOrFail($data['order_id']);

            if ((int) $order->user_id !== (int) $user->id) {
                throw new \RuntimeException('Unauthorized', 403);
            }

            $order = $this->orderExpirationService->expireOrder($order);

            if (!$this->orderExpirationService->isPayable($order)) {
                throw new \RuntimeException('Đơn hàng không còn khả dụng để thanh toán hoặc đã hết hạn.', 422);
            }

            if (round((float) $order->total_amount, 2) !== round((float) $data['amount'], 2)) {
                throw new \RuntimeException('Số tiền thanh toán không khớp với đơn hàng.', 422);
            }

            if ($order->payment && (int) $order->payment->status === self::PAYMENT_STATUS_COMPLETED) {
                throw new \RuntimeException('Đơn hàng đã được thanh toán.', 422);
            }

            $paymentMethod = $data['payment_method'];
            $transactionCode = $order->payment?->transaction_code ?: 'TXN-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6));

            $payload = [
                'source' => 'web',
                'method' => $paymentMethod,
                'gateway' => $paymentMethod === 'payos' ? 'payos' : 'mock',
                'created_ip' => request()->ip(),
            ];

            if ($paymentMethod === 'payos') {
                $payOSResult = $this->payOSService->createPaymentLink([
                    'order_code' => (int) $order->id,
                    'amount' => (int) round((float) $order->total_amount),
                    'description' => 'Ve phim #' . $order->id,
                ]);

                $payload = array_merge($payload, [
                    'checkout_url' => $payOSResult['checkout_url'] ?? null,
                    'qr_code' => $payOSResult['qr_code'] ?? null,
                    'payment_link_id' => $payOSResult['payment_link_id'] ?? null,
                ]);
            }

            $payment = Payment::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'method' => $paymentMethod,
                    'transaction_code' => $transactionCode,
                    'amount' => $data['amount'],
                    'status' => self::PAYMENT_STATUS_PENDING,
                    'payload' => $payload,
                    'paid_at' => null,
                ]
            );

            $order->update([
                'payment_provider' => $data['payment_method'],
                'payment_status' => 'pending',
            ]);

            return $payment->load('order');
        });
    }

    public function findForUser(int $id, $user): Payment
    {
        $payment = Payment::with('order')->findOrFail($id);

        if ($payment->order->user_id !== $user->id && !$this->isStaffUser($user)) {
            throw new \RuntimeException('Unauthorized', 403);
        }

        return $payment;
    }

    public function verify(int $id, array $data, $user): Payment
    {
        return DB::transaction(function () use ($id, $data, $user) {
            $payment = Payment::with('order')
                ->lockForUpdate()
                ->findOrFail($id);

            if ($payment->order->user_id !== $user->id && !$this->isStaffUser($user)) {
                throw new \RuntimeException('Unauthorized', 403);
            }

            $order = $this->orderExpirationService->expireOrder($payment->order);

            if ((int) $order->status === self::ORDER_STATUS_CANCELLED) {
                throw new \RuntimeException('Đơn hàng đã bị hủy hoặc hết hạn, không thể xác nhận thanh toán.', 422);
            }

            if ((int) $order->status === self::ORDER_STATUS_CONFIRMED || (int) $payment->status === self::PAYMENT_STATUS_COMPLETED) {
                return $payment->fresh('order');
            }

            $isCompleted = $data['status'] === 'completed';
            $paidAt = $isCompleted ? now() : null;

            $payment->update([
                'status' => $isCompleted ? self::PAYMENT_STATUS_COMPLETED : self::PAYMENT_STATUS_FAILED,
                'paid_at' => $paidAt,
                'payload' => array_merge($payment->payload ?? [], [
                    'verified_at' => now()->toISOString(),
                    'verified_by' => $user->id,
                    'verified_status' => $data['status'],
                ]),
            ]);

            $order->update([
                'status' => $isCompleted ? self::ORDER_STATUS_CONFIRMED : self::ORDER_STATUS_PENDING,
                'payment_status' => $isCompleted ? 'paid' : 'failed',
                'paid_at' => $paidAt,
            ]);

            return $payment->fresh('order');
        });
    }

    public function format(Payment $payment): array
    {
        $statusMap = [
            self::PAYMENT_STATUS_PENDING => 'pending',
            self::PAYMENT_STATUS_COMPLETED => 'completed',
            self::PAYMENT_STATUS_FAILED => 'failed',
        ];

        return [
            'id' => $payment->id,
            'order_id' => $payment->order_id,
            'payment_method' => $payment->method,
            'method' => $payment->method,
            'transaction_code' => $payment->transaction_code,
            'payment_code' => $payment->transaction_code,
            'amount' => (float) $payment->amount,
            'status' => $statusMap[(int) $payment->status] ?? 'unknown',
            'status_code' => (int) $payment->status,
            'checkout_url' => $payment->payload['checkout_url'] ?? null,
            'qr_code' => $payment->payload['qr_code'] ?? null,
            'payment_link_id' => $payment->payload['payment_link_id'] ?? null,
            'payload' => $payment->payload,
            'paid_at' => $payment->paid_at,
            'created_at' => $payment->created_at,
            'order' => $payment->order,
        ];
    }

    private function isStaffUser($user): bool
    {
        return method_exists($user, 'roles')
            && $user->roles()
                ->whereIn('name', ['admin', 'manager', 'staff'])
                ->exists();
    }
}
