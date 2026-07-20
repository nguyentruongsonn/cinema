<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\SeatHold;
use App\Models\SeatHoldItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderExpirationService
{
    private const ORDER_STATUS_CANCELLED = 0;
    private const ORDER_STATUS_PENDING = 1;
    private const ORDER_STATUS_CONFIRMED = 2;
    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(
        private readonly AuditSnapshotService $auditSnapshots
    ) {}

    /**
     * Expire pending unpaid orders atomically with resource restoration.
     *
     * @param int|null $showtimeId Optional showtime filter
     * @return int Number of orders expired
     */
    public function expirePendingOrders(?int $showtimeId = null): int
    {
        return DB::transaction(function () use ($showtimeId) {
            $now = now();

            // Lock orders before expiring to prevent payment races
            $orders = Order::query()
                ->where('status', self::ORDER_STATUS_PENDING)
                ->whereNull('paid_at')
                ->whereNotNull('expired_at')
                ->where('expired_at', '<=', $now)
                ->when($showtimeId !== null, fn ($q) => $q->where('showtime_id', $showtimeId))
                ->lockForUpdate()
                ->get();

            if ($orders->isEmpty()) {
                return 0;
            }

            $expiredCount = 0;

            foreach ($orders as $order) {
                // Double-check order is still expirable after lock
                if ($this->canExpireOrder($order)) {
                    $this->expireOrderAtomic($order);
                    $expiredCount++;
                }
            }

            if ($expiredCount > 0) {
                Log::info('Expired pending orders cleaned up', [
                    'showtime_id' => $showtimeId,
                    'expired_count' => $expiredCount,
                ]);
            }

            return $expiredCount;
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Check if order can be expired (used in-transaction after locking).
     *
     * @param Order $order
     * @return bool
     */
    private function canExpireOrder(Order $order): bool
    {
        // Reload from DB to get fresh state after lock
        $order->refresh();

        return (int) $order->status === self::ORDER_STATUS_PENDING
            && $order->paid_at === null
            && $order->expired_at !== null
            && $order->expired_at->isPast();
    }

    /**
     * Expire a single order atomically with resource restoration.
     *
     * This method MUST be called within a transaction with the order already locked.
     *
     * @param Order $order
     * @return void
     */
    private function expireOrderAtomic(Order $order): void
    {
        $oldOrderValues = $this->auditSnapshots->order($order);
        $payment = $order->payment;
        $oldPaymentValues = $payment ? $this->auditSnapshots->payment($payment) : null;

        // 1. PHASE 1 FIX: Use markExpired() state transition method
        $order->markExpired();

        // 2. Update payment status if payment exists
        if ($payment) {
            $payment->markCancelled(now());
        }

        // 3. Restore product stock
        $this->restoreProductStock($order);

        // 4. Restore promotion usage
        $this->restorePromotionUsage($order);

        // 5. Restore loyalty points reserved for this checkout
        $this->restoreLoyaltyPoints($order);

        // 6. Clean up seat holds
        $this->cleanupSeatHolds($order);

        $payload = (array) $order->payload;
        $payload['product_stock_reserved'] = false;
        $payload['voucher_reserved'] = false;
        $payload['points_reserved'] = false;
        $order->forceFill(['payload' => $payload])->save();

        // 6. Audit log
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

    /**
     * Restore product stock from expired order items.
     *
     * @param Order $order
     * @return void
     */
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
    }

    /**
     * Restore promotion usage counter if promotion was used.
     *
     * @param Order $order
     * @return void
     */
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
    }

    /**
     * Clean up seat holds for expired order.
     *
     * @param Order $order
     * @return void
     */
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

        $seatHold = SeatHold::query()
            ->whereKey($seatHoldId)
            ->where('user_id', $order->user_id)
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

    /**
     * Check if order is expired (stale check - use with caution).
     *
     * @param Order $order
     * @return bool
     */
    public function isExpired(Order $order): bool
    {
        return (int) $order->status === self::ORDER_STATUS_PENDING
            && $order->expired_at !== null
            && $order->expired_at->isPast()
            && $order->paid_at === null;
    }

    /**
     * Expire a single order transaction-safely with resource restoration.
     *
     * @param Order $order
     * @return Order
     */
    public function expireOrder(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            // Lock order to prevent concurrent state changes
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if (!$this->canExpireOrder($order)) {
                return $order;
            }

            $this->expireOrderAtomic($order);

            return $order->fresh();
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Check if order is payable.
     *
     * @param Order $order
     * @return bool
     */
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

    /**
     * Get statuses considered active bookings.
     *
     * @return array
     */
    public function getActiveBookingStatuses(): array
    {
        return [
            self::ORDER_STATUS_PENDING,
            self::ORDER_STATUS_CONFIRMED,
        ];
    }

}
