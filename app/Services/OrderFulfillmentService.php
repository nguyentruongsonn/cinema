<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\OrderPaid;
use App\Jobs\SendIssuedTicketsEmail;
use App\Models\Combo;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\SeatHoldItem;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderFulfillmentService
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly AuditSnapshotService $auditSnapshots
    ) {}

    /**
     * Finalize the order after successful payment.
     * PHASE 1: Added idempotency protection against webhook replay
     */
    public function finalize(int $gatewayOrderCode): array
    {
        return DB::transaction(function () use ($gatewayOrderCode) {
            $idempotencyKeyValue = "webhook:finalize:{$gatewayOrderCode}";

            $idempotencyKey = IdempotencyKey::query()
                ->byKey($idempotencyKeyValue)
                ->lockForUpdate()
                ->first();

            if ($idempotencyKey && $idempotencyKey->status === 'completed') {
                Log::info('Webhook already processed (idempotency)', [
                    'gateway_order_code' => $gatewayOrderCode,
                    'idempotency_key' => $idempotencyKeyValue,
                ]);

                $completedOrderId = Order::query()
                    ->where('gateway_order_code', $gatewayOrderCode)
                    ->value('id');

                if ($completedOrderId) {
                    $this->sendTicketsEmailAfterCommit((int) $completedOrderId);
                }

                return is_array($idempotencyKey->response_data)
                    ? $idempotencyKey->response_data
                    : ['already_processed' => true, 'skipped' => false];
            }

            if ($idempotencyKey && $idempotencyKey->status === 'pending') {
                Log::warning('Concurrent webhook detected', [
                    'gateway_order_code' => $gatewayOrderCode,
                    'idempotency_key' => $idempotencyKeyValue,
                ]);
                throw new \RuntimeException('Webhook đang được xử lý bởi yêu cầu khác. Vui lòng thử lại sau.');
            }

            if (!$idempotencyKey) {
                $idempotencyKey = IdempotencyKey::create([
                    'key' => $idempotencyKeyValue,
                    'request_data' => ['gateway_order_code' => $gatewayOrderCode],
                    'status' => 'pending',
                    'expires_at' => now()->addHours(24),
                ]);
            } else {
                $idempotencyKey->update(['status' => 'pending']);
            }

            $order = $this->orderService->findByGatewayCode($gatewayOrderCode, lock: true);

            if (!$order) {
                Log::warning('Fulfillment failed: Order not found', ['gateway_order_code' => $gatewayOrderCode]);
                $idempotencyKey->update(['status' => 'failed']);
                throw new \InvalidArgumentException('Không tìm thấy đơn hàng.');
            }

            if ((int)$order->status === Order::STATUS_CONFIRMED || $order->payment_status === 'paid') {
                $paidAt = $order->paid_at ?? now();
                $oldOrderValues = $this->auditSnapshots->order($order);

                $order->forceFill([
                    'status' => Order::STATUS_CONFIRMED,
                    'payment_status' => 'paid',
                    'paid_at' => $paidAt,
                ])->save();

                $payment = Payment::query()
                    ->where('order_id', $order->id)
                    ->lockForUpdate()
                    ->first();

                $oldPaymentValues = $payment ? $this->auditSnapshots->payment($payment) : null;

                if ($payment) {
                    $payment->markSuccessful($paidAt);
                }

                // Mark seat hold items as consumed (normalized model)
                $seatHoldUserId = (int) data_get($order->payload, 'seat_hold_user_id', $order->user_id);
                $seatHold = SeatHold::query()
                    ->whereKey(data_get($order->payload, 'seat_hold_id'))
                    ->where('user_id', $seatHoldUserId)
                    ->where('showtime_id', $order->showtime_id)
                    ->first();

                if ($seatHold) {
                    SeatHoldItem::query()
                        ->where('seat_hold_id', $seatHold->id)
                        ->where('status', SeatHoldItem::STATUS_ACTIVE)
                        ->get()
                        ->each(fn($item) => $item->markConsumed());

                    $seatHold->delete();
                }

                $result = ['already_processed' => true, 'skipped' => false];
                $idempotencyKey->update([
                    'status' => 'completed',
                    'response_data' => $result,
                ]);

                app(AuditLogService::class)->recordSystemChange(
                    'order.payment_reconciled',
                    $order->fresh(),
                    $oldOrderValues,
                    $this->auditSnapshots->order($order->fresh())
                );

                if ($payment && $oldPaymentValues) {
                    app(AuditLogService::class)->recordSystemChange(
                        'payment.succeeded',
                        $payment->fresh(),
                        $oldPaymentValues,
                        $this->auditSnapshots->payment($payment->fresh())
                    );
                }

                $this->sendTicketsEmailAfterCommit($order->id);

                return $result;
            }

            $paidAt = now();
            $oldOrderValues = $this->auditSnapshots->order($order);

            // PHASE 1 FIX: Use markPaid() state transition method
            $order->markPaid($paidAt);

            // PHASE 2: Update payment record to success
            $payment = Payment::query()
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->first();

            $oldPaymentValues = $payment ? $this->auditSnapshots->payment($payment) : null;

            if ($payment) {
                $payment->markSuccessful($paidAt);
            }

            $payload = $order->payload ?? [];
            $isPos = str_starts_with($order->code, 'POS-') || ($payload['source'] ?? null) === 'pos';

            if ($isPos) {
                // For POS orders, convert existing Seat order items to Ticket order items
                $orderItems = $order->orderItems()
                    ->where('item_type', Seat::class)
                    ->get()
                    ->unique('item_id');
                foreach ($orderItems as $item) {
                    $ticket = Ticket::forceCreate([
                        'order_id' => $order->id,
                        'user_id' => $order->user_id,
                        'showtime_id' => $order->showtime_id,
                        'seat_id' => $item->item_id,
                        'ticket_code' => Ticket::generateTicketCode(),
                        'status' => Ticket::STATUS_VALID,
                    ]);

                    $item->forceFill([
                        'item_type' => Ticket::class,
                        'item_id' => $ticket->id,
                        'metadata' => array_merge($item->metadata ?? [], [
                            'ticket_code' => $ticket->ticket_code,
                        ])
                    ])->save();
                }
            } else {
                // Create one ticket per distinct seat and convert the reserved seat item when present.
                $seats = collect($payload['seats'] ?? [])
                    ->unique(fn (array $seatData): int => (int) $seatData['id'])
                    ->values();
                foreach ($seats as $seatData) {
                    $ticket = Ticket::forceCreate([
                        'order_id' => $order->id,
                        'user_id' => $order->user_id,
                        'showtime_id' => $order->showtime_id,
                        'seat_id' => $seatData['id'],
                        'ticket_code' => Ticket::generateTicketCode(),
                        'status' => Ticket::STATUS_VALID,
                    ]);

                    $seatItem = $order->orderItems()
                        ->where('item_type', Seat::class)
                        ->where('item_id', $seatData['id'])
                        ->lockForUpdate()
                        ->first();
                    $ticketMetadata = [
                        'seat_label' => $seatData['name'],
                        'row' => $seatData['row'] ?? null,
                        'number' => $seatData['number'] ?? null,
                        'seat_type' => $seatData['type'] ?? null,
                        'audience_type' => $seatData['audience_type'] ?? 'adult',
                        'ticket_code' => $ticket->ticket_code,
                    ];

                    if ($seatItem) {
                        $seatItem->forceFill([
                            'item_type' => Ticket::class,
                            'item_id' => $ticket->id,
                            'metadata' => array_merge($seatItem->metadata ?? [], $ticketMetadata),
                        ])->save();
                    } else {
                        OrderItem::createFromTicket(
                            $order,
                            $ticket,
                            (string) $seatData['price'],
                            $ticketMetadata,
                        )->save();
                    }
                }
            }

            // 2. Create Product OrderItems and decrement stock SAFELY (PHASE 5)
            $products = $payload['products'] ?? [];
            foreach ($products as $productData) {
                $quantity = (int)$productData['quantity'];
                $isCombo = ($productData['item_type'] ?? $productData['type'] ?? null) === 'combo';
                $itemClass = $isCombo ? Combo::class : Product::class;

                $reservedItem = OrderItem::query()
                    ->where('order_id', $order->id)
                    ->where('item_type', $itemClass)
                    ->where('item_id', $productData['id'])
                    ->lockForUpdate()
                    ->first();

                if ($reservedItem) {
                    continue;
                }

                if ($isCombo) {
                    Log::warning('Reserved combo item missing during fulfillment', [
                        'order_id' => $order->id,
                        'combo_id' => $productData['id'],
                    ]);
                    continue;
                }

                // PHASE 5: Safe stock decrement with pessimistic lock FIRST
                $product = Product::query()
                    ->where('id', $productData['id'])
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    Log::warning('Product not found during fulfillment', [
                        'product_id' => $productData['id'],
                    ]);
                    continue;
                }

                if ($product->stock < $quantity) {
                    throw new \RuntimeException('Không đủ tồn kho để hoàn tất đơn hàng đã thanh toán.');
                }

                $product->decrement('stock', $quantity);

                // Create OrderItem with factory method
                $orderItem = OrderItem::createFromProduct(
                    $order,
                    $product,
                    $quantity,
                    (string) $productData['price'],
                    [
                        'product_name' => $productData['name'],
                        'product_type' => $productData['type'] ?? null,
                        'image_url'    => $productData['image_url'] ?? null,
                    ]
                );
                $orderItem->save();
            }

            // 4. PHASE 1.4: Release seat holds after successful payment (normalized model)
            $seatHoldUserId = (int) data_get($order->payload, 'seat_hold_user_id', $order->user_id);
            $seatHold = SeatHold::query()
                ->whereKey(data_get($order->payload, 'seat_hold_id'))
                ->where('user_id', $seatHoldUserId)
                ->where('showtime_id', $order->showtime_id)
                ->first();

            if ($seatHold) {
                // Mark all active items as consumed to release locks
                SeatHoldItem::query()
                    ->where('seat_hold_id', $seatHold->id)
                    ->where('status', SeatHoldItem::STATUS_ACTIVE)
                    ->get()
                    ->each(fn($item) => $item->markConsumed());

                // Delete parent hold record
                $seatHold->delete();
            }

            // 5. Increment promotion usage_count if any and mark user's voucher as used
            $voucher = $payload['voucher'] ?? null;
            if ($voucher && isset($voucher['id'])) {
                if (! ($payload['voucher_reserved'] ?? false)) {
                    Promotion::query()->where('id', $voucher['id'])->increment('usage_count');
                }

                if ($order->user_id) {
                    DB::table('user_promotion')
                        ->where('user_id', $order->user_id)
                        ->where('promotion_id', $voucher['id'])
                        ->update([
                            'status' => 0,
                            'used_at' => $paidAt,
                            'order_id' => $order->id,
                            'usage_count' => DB::raw('COALESCE(usage_count, 0) + 1'),
                            'updated_at' => now(),
                        ]);
                }
            }

            // 6. Deduct points if any
            $pointsUsed = $payload['points_used'] ?? 0;
            if ($pointsUsed > 0 && $order->user_id && ! ($payload['points_reserved'] ?? false)) {
                $user = $order->user;
                if ($user) {
                    $user->decrement('loyalty_points', $pointsUsed);
                    \App\Models\LoyaltyHistory::create([
                        'user_id' => $user->id,
                        'order_id' => $order->id,
                        'type' => 'redeem',
                        'points' => $pointsUsed,
                        'description' => "Trừ điểm dùng cho đơn hàng #{$order->code}",
                    ]);
                }
            }

            // 6.5. Add points based on total_amount
            if ($order->user_id && $order->total_amount > 0) {
                $pointsEarned = (int) floor($order->total_amount / 10000);
                if ($pointsEarned > 0) {
                    $order->user->increment('loyalty_points', $pointsEarned);
                    \App\Models\LoyaltyHistory::create([
                        'user_id' => $order->user_id,
                        'order_id' => $order->id,
                        'type' => 'earn',
                        'points' => $pointsEarned,
                        'description' => "Tích điểm đơn hàng #{$order->code}",
                    ]);
                }
            }

            // 7. Broadcast real-time payment success to the buyer's browser
            try {
                broadcast(new OrderPaid(
                    orderCode:   (int) $order->gateway_order_code,
                    orderNumber: $order->code,
                    userId:      (int) $order->user_id,
                ));
            } catch (\Throwable $e) {
                Log::warning('Order broadcast failed (non-critical): ' . $e->getMessage());
            }

            $result = ['already_processed' => false, 'skipped' => false];

            $idempotencyKey->update([
                'status' => 'completed',
                'response_data' => $result,
            ]);

            app(AuditLogService::class)->recordSystemChange(
                'order.paid',
                $order->fresh(),
                $oldOrderValues,
                $this->auditSnapshots->order($order->fresh())
            );

            if ($payment && $oldPaymentValues) {
                app(AuditLogService::class)->recordSystemChange(
                    'payment.succeeded',
                    $payment->fresh(),
                    $oldPaymentValues,
                    $this->auditSnapshots->payment($payment->fresh())
                );
            }

            $this->sendTicketsEmailAfterCommit($order->id);

            return $result;
        });
    }

    private function sendTicketsEmailAfterCommit(int $orderId): void
    {
        DB::afterCommit(function () use ($orderId): void {
            if ((bool) config('mail.invoice.after_response', false)) {
                SendIssuedTicketsEmail::dispatchAfterResponse($orderId)
                    ->onConnection('sync');

                return;
            }

            SendIssuedTicketsEmail::dispatch($orderId)
                ->onQueue((string) config('mail.invoice.queue', 'emails'))
                ->delay(now()->addSeconds((int) config('mail.invoice.dispatch_delay', 3)));
        });
    }

}
