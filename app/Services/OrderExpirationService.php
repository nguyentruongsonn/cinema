<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

class OrderExpirationService
{
    private const ORDER_STATUS_CANCELLED = 0;
    private const ORDER_STATUS_PENDING = 1;
    private const ORDER_STATUS_CONFIRMED = 2;

    public function expirePendingOrders(?int $showtimeId = null): int
    {
        $query = Order::query()
            ->where('status', self::ORDER_STATUS_PENDING)
            ->whereNull('paid_at')
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', now());

        if ($showtimeId !== null) {
            $query->where('showtime_id', $showtimeId);
        }

        $expiredCount = $query->update([
            'status' => self::ORDER_STATUS_CANCELLED,
            'payment_status' => 'expired',
            'cancelled_at' => now(),
            'updated_at' => now(),
        ]);

        if ($expiredCount > 0) {
            Log::info('Expired pending orders cleaned up', [
                'showtime_id' => $showtimeId,
                'expired_count' => $expiredCount,
            ]);
        }

        return $expiredCount;
    }

    public function isExpired(Order $order): bool
    {
        return (int) $order->status === self::ORDER_STATUS_PENDING
            && $order->expired_at !== null
            && $order->expired_at->isPast()
            && $order->paid_at === null;
    }

    public function expireOrder(Order $order): Order
    {
        if (!$this->isExpired($order)) {
            return $order;
        }

        $order->update([
            'status' => self::ORDER_STATUS_CANCELLED,
            'payment_status' => 'expired',
            'cancelled_at' => now(),
        ]);

        Log::info('Pending order expired', [
            'order_id' => $order->id,
            'order_code' => $order->code,
        ]);

        return $order->fresh();
    }

    public function isPayable(Order $order): bool
    {
        if ((int) $order->status !== self::ORDER_STATUS_PENDING) {
            return false;
        }

        if ($order->paid_at !== null || $order->payment_status === 'paid') {
            return false;
        }

        return !$this->isExpired($order);
    }

    public function getActiveBookingStatuses(): array
    {
        return [
            self::ORDER_STATUS_PENDING,
            self::ORDER_STATUS_CONFIRMED,
        ];
    }
}
