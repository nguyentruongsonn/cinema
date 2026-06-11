<?php

namespace App\Services;

use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderFulfillmentService
{
    public function __construct(
        private readonly OrderService $orderService
    ) {}

    /**
     * Finalize the order after successful payment.
     */
    public function finalize(int $gatewayOrderCode): array
    {
        return DB::transaction(function () use ($gatewayOrderCode) {
            $order = $this->orderService->findByGatewayCode($gatewayOrderCode, lock: true);

            if (!$order) {
                Log::warning('Fulfillment failed: Order not found', ['gateway_order_code' => $gatewayOrderCode]);
                throw new \InvalidArgumentException('Không tìm thấy đơn hàng.');
            }

            if ((int)$order->status === Order::STATUS_CONFIRMED || $order->payment_status === 'paid') {
                return ['already_processed' => true, 'skipped' => false];
            }

            // Update order status
            $order->update([
                'status' => Order::STATUS_CONFIRMED,
                'payment_status' => 'paid',
                'paid_at' => now(),
            ]);

            // PHASE 2: Update payment record to success
            Payment::where('order_id', $order->id)
                ->update([
                    'status' => Payment::STATUS_SUCCESS,
                    'paid_at' => now(),
                ]);

            $payload = $order->payload ?? [];

            // 1. Create Seat OrderItems
            $seats = $payload['seats'] ?? [];
            foreach ($seats as $seatData) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'item_type' => Seat::class,
                    'item_id' => $seatData['id'],
                    'quantity' => 1,
                    'unit_price' => $seatData['price'],
                    'total_price' => $seatData['price'],
                    'metadata' => [
                        'seat_label' => $seatData['name'],
                        'row' => $seatData['row'] ?? null,
                        'number' => $seatData['number'] ?? null,
                        'seat_type' => $seatData['type'] ?? null,
                    ],
                ]);
            }

            // 2. Create Tickets for each seat (PHASE 3)
            foreach ($seats as $seatData) {
                Ticket::create([
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'showtime_id' => $order->showtime_id,
                    'seat_id' => $seatData['id'],
                    'ticket_code' => Ticket::generateTicketCode(),
                    'status' => Ticket::STATUS_VALID,
                ]);
            }

            // 3. Create Product OrderItems and decrement stock SAFELY (PHASE 3)
            $products = $payload['products'] ?? [];
            foreach ($products as $productData) {
                $quantity = (int)$productData['quantity'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'item_type' => Product::class,
                    'item_id' => $productData['id'],
                    'quantity' => $quantity,
                    'unit_price' => $productData['price'],
                    'total_price' => $productData['price'] * $quantity,
                    'metadata' => [
                        'product_name' => $productData['name'],
                        'product_type' => $productData['type'] ?? null,
                        'image_url' => $productData['image_url'] ?? null,
                    ],
                ]);

                // PHASE 3: Safe stock decrement with pessimistic lock
                $product = Product::where('id', $productData['id'])
                    ->lockForUpdate()
                    ->first();

                if ($product && $product->stock >= $quantity) {
                    $product->decrement('stock', $quantity);
                } else {
                    Log::warning('Insufficient product stock', [
                        'product_id' => $productData['id'],
                        'requested' => $quantity,
                        'available' => $product->stock ?? 0,
                    ]);
                }
            }

            // 4. PHASE 3: Release seat holds after successful payment
            SeatHold::where('user_id', $order->user_id)
                ->where('showtime_id', $order->showtime_id)
                ->delete();

            // 5. Increment promotion used_count if any
            $voucher = $payload['voucher'] ?? null;
            if ($voucher && isset($voucher['id'])) {
                Promotion::where('id', $voucher['id'])->increment('used_count');
            }

            // 6. Deduct points if any
            $pointsUsed = $payload['points_used'] ?? 0;
            if ($pointsUsed > 0 && $order->user_id) {
                $user = $order->user;
                if ($user && method_exists($user, 'points')) {
                    $user->decrement('points', $pointsUsed);
                }
            }

            // 7. Broadcast real-time payment success to the buyer's browser
            broadcast(new OrderPaid(
                orderCode:   (int) $order->gateway_order_code,
                orderNumber: $order->code,
                userId:      (int) $order->user_id,
            ));

            return ['already_processed' => false, 'skipped' => false];
        });
    }
}
