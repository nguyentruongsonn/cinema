<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;

class AuditSnapshotService
{
    public function order(Order $order): array
    {
        return [
            'code' => $order->code,
            'gateway_order_code' => $order->gateway_order_code,
            'user_id' => $order->user_id,
            'showtime_id' => $order->showtime_id,
            'status' => (int) $order->status,
            'payment_status' => $order->payment_status,
            'total_amount' => (float) $order->total_amount,
            'paid_at' => $order->paid_at?->toDateTimeString(),
            'cancelled_at' => $order->cancelled_at?->toDateTimeString(),
            'expired_at' => $order->expired_at?->toDateTimeString(),
        ];
    }

    public function payment(Payment $payment): array
    {
        return [
            'order_id' => $payment->order_id,
            'user_id' => $payment->user_id,
            'method' => $payment->method,
            'status' => $payment->status,
            'gateway_order_code' => $payment->gateway_order_code,
            'amount' => (float) $payment->amount,
            'paid_at' => $payment->paid_at?->toDateTimeString(),
            'failed_at' => $payment->failed_at?->toDateTimeString(),
        ];
    }
}
