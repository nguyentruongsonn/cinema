<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\LoyaltyHistory;
use App\Models\OrderItem;
use App\Models\Combo;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\SeatHold;
use App\Models\SeatHoldItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderExpirationService
{
    private const ORDER_STATUS_PENDING = 1;
    private const ORDER_STATUS_CONFIRMED = 2;
    private const TRANSACTION_ATTEMPTS = 3;
    private const EXPIRATION_BATCH_SIZE = 100;

    public function __construct(
        private readonly AuditSnapshotService $auditSnapshots
    ) {}

    public function expirePendingOrders(?int $showtimeId = null): int
    {
        $totalExpired = 0;

        do {
            $orderIds = Order::query()
                ->where('status', self::ORDER_STATUS_PENDING)
                ->whereNull('paid_at')
                ->whereNotNull('expired_at')
                ->where('expired_at', '<=', now())
                ->when($showtimeId !== null, fn ($q) => $q->where('showtime_id', $showtimeId))
                ->orderBy('expired_at')
                ->orderBy('id')
                ->limit(self::EXPIRATION_BATCH_SIZE)
                ->pluck('id')
                ->all();

            if ($orderIds === []) {
                break;
            }

            $expiredCount = DB::transaction(function () use ($orderIds) {
                $orders = Order::query()
                ->where('status', self::ORDER_STATUS_PENDING)
                ->whereNull('paid_at')
                ->whereNotNull('expired_at')
                    ->where('expired_at', '<=', now())
                    ->whereIn('id', $orderIds)
                    ->orderBy('expired_at')
                    ->orderBy('id')
                ->lockForUpdate()
                ->get();

                $expiredCount = 0;

                foreach ($orders as $order) {
                    if ($this->canExpireOrder($order)) {
                        $this->expireOrderAtomic($order);
                        $expiredCount++;
                    }
                }

                return $expiredCount;
            }, self::TRANSACTION_ATTEMPTS);

            $totalExpired += $expiredCount;
        } while (count($orderIds) === self::EXPIRATION_BATCH_SIZE);

        if ($totalExpired > 0) {
            Log::info('Expired pending orders cleaned up', [
                'showtime_id' => $showtimeId,
                'expired_count' => $totalExpired,
            ]);
        }

        return $totalExpired;
    }

    private function canExpireOrder(Order $order): bool
    {
        $order->refresh();

        return (int) $order->status === self::ORDER_STATUS_PENDING
            && $order->paid_at === null
            && $order->expired_at !== null
            && $order->expired_at->isPast();
    }

    private function expireOrderAtomic(Order $order): void
    {
        $oldOrderValues = $this->auditSnapshots->order($order);
        $payment = $order->payment;
        $oldPaymentValues = $payment ? $this->auditSnapshots->payment($payment) : null;

        $order->markExpired();

        if ($payment) {
            $payment->markCancelled(now());
        }

        $this->restoreProductStock($order);

        $this->restorePromotionUsage($order);

        $this->restoreLoyaltyPoints($order);

        $this->cleanupSeatHolds($order);

        $payload = (array) $order->payload;
        $payload['product_stock_reserved'] = false;
        $payload['voucher_reserved'] = false;
        $payload['points_reserved'] = false;
        $order->forceFill(['payload' => $payload])->save();

        Log::info('Order expired and resources restored', [
            'order_id' => $order->id,
            'order_code' => $order->code,
            'total_amount' => $order->total_amount,
            'expired_at' => $order->expired_at?->toIso8601String(),
        ]);

        app(AuditLogService::class)->recordSystemChange(
            'order.expired',
            $order->fresh(),
            $oldOrderValues,
            $this->auditSnapshots->order($order->fresh())
        );

        if ($payment && $oldPaymentValues) {
            app(AuditLogService::class)->recordSystemChange(
                'payment.cancelled',
                $payment->fresh(),
                $oldPaymentValues,
                $this->auditSnapshots->payment($payment->fresh())
            );
        }
    }

    private function restoreProductStock(Order $order): void
    {
        $productItems = OrderItem::query()
            ->where('order_id', $order->id)
            ->where('item_type', Product::class)
            ->get();

        foreach ($productItems as $item) {
            $product = Product::query()
                ->where('id', $item->item_id)
                ->lockForUpdate()
                ->first();

            if ($product) {
                $product->increment('stock', $item->quantity);

                Log::info('Stock restored after order expiration', [
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity_restored' => $item->quantity,
                    'new_stock' => $product->stock,
                ]);
            }

            $item->delete();
        }

        $comboItems = OrderItem::query()
            ->where('order_id', $order->id)
            ->where('item_type', Combo::class)
            ->lockForUpdate()
            ->get();

        foreach ($comboItems as $item) {
            $combo = Combo::query()->with('comboItems')->whereKey($item->item_id)->lockForUpdate()->first();
            if ($combo === null) {
                continue;
            }

            foreach ($combo->comboItems as $comboItem) {
                Product::query()
                    ->whereKey($comboItem->product_id)
                    ->lockForUpdate()
                    ->first()?->increment('stock', (int) $comboItem->quantity * (int) $item->quantity);
            }
            $item->delete();
        }
    }

    private function restorePromotionUsage(Order $order): void
    {
        $payload = (array) $order->payload;
        $promotion = $payload['voucher'] ?? $payload['promotion'] ?? null;
        $wasReserved = $payload['voucher_reserved'] ?? array_key_exists('promotion', $payload);

        if ($wasReserved && $promotion && isset($promotion['id'])) {
            $promotionModel = Promotion::query()
                ->where('id', $promotion['id'])
                ->lockForUpdate()
                ->first();

            if ($promotionModel && $promotionModel->usage_count > 0) {
                $promotionModel->decrement('usage_count');

                Log::info('Promotion usage decremented after order expiration', [
                    'order_id' => $order->id,
                    'promotion_id' => $promotionModel->id,
                    'promotion_code' => $promotionModel->code,
                    'new_usage_count' => $promotionModel->usage_count,
                ]);
            }

            DB::table('user_promotion')
                ->where('user_id', $order->user_id)
                ->where('promotion_id', $promotion['id'])
                ->where('order_id', $order->id)
                ->where('status', 2)
                ->update([
                    'status' => 1,
                    'order_id' => null,
                    'updated_at' => now(),
                ]);
        }
    }

    private function restoreLoyaltyPoints(Order $order): void
    {
        $payload = (array) $order->payload;
        $pointsUsed = (int) ($payload['points_used'] ?? 0);

        if (! ($payload['points_reserved'] ?? false) || $pointsUsed <= 0 || ! $order->user_id) {
            return;
        }

        $user = User::query()->whereKey($order->user_id)->lockForUpdate()->first();
        $user?->increment('loyalty_points', $pointsUsed);
        LoyaltyHistory::query()
            ->where('order_id', $order->id)
            ->where('user_id', $order->user_id)
            ->where('type', 'redeem')
            ->delete();
    }

    private function cleanupSeatHolds(Order $order): void
    {
        $seatHoldId = $this->getOrderSeatHoldId($order);

        if ($seatHoldId === null) {
            Log::warning('Order expiration skipped seat-hold cleanup because no seat_hold_id was recorded', [
                'order_id' => $order->id,
                'order_code' => $order->code,
                'user_id' => $order->user_id,
                'showtime_id' => $order->showtime_id,
            ]);

            return;
        }

        $seatHoldUserId = (int) data_get($order->payload, 'seat_hold_user_id', $order->user_id);
        $seatHold = SeatHold::query()
            ->whereKey($seatHoldId)
            ->where('user_id', $seatHoldUserId)
            ->where('showtime_id', $order->showtime_id)
            ->lockForUpdate()
            ->first();

        if (!$seatHold) {
            return;
        }

        SeatHoldItem::query()
            ->where('seat_hold_id', $seatHold->id)
            ->where('status', SeatHoldItem::STATUS_ACTIVE)
            ->lockForUpdate()
            ->get()
            ->each(fn (SeatHoldItem $item) => $item->markExpired());

        $seatHold->delete();
    }

    private function getOrderSeatHoldId(Order $order): ?int
    {
        $payload = (array) $order->payload;
        $seatHoldId = $payload['seat_hold_id'] ?? null;

        return is_numeric($seatHoldId) ? (int) $seatHoldId : null;
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
        return DB::transaction(function () use ($order) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (!$this->canExpireOrder($order)) {
                return $order;
            }

            $this->expireOrderAtomic($order);

            return $order->fresh();
        }, self::TRANSACTION_ATTEMPTS);
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
