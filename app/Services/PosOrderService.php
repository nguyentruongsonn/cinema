<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PosOrderService
 *
 * Handles order creation from POS kiosk.
 * Differs from online OrderService:
 * - No seat_hold_id required (staff selects directly)
 * - Supports customerType (student/adult)
 * - Supports loyalty points discount
 * - Supports cash payment
 * - Records served_by_user_id
 */
class PosOrderService
{
    /**
     * Create a POS order.
     */
    public function createPosOrder(array $data, User $staff, User $customer): Order
    {
        // 1. Load showtime (optional)
        $showtime = null;
        if (!empty($data['showtime_id'])) {
            $showtime = Showtime::with(['format', 'movie', 'screen.theater'])
                ->findOrFail($data['showtime_id']);

            if ($showtime->scheduled_at <= now()) {
                throw new \RuntimeException('Suất chiếu này đã bắt đầu hoặc kết thúc.');
            }
        }

        $theaterId = $showtime?->screen->theater_id ?? ($data['theater_id'] ?? null);
        if (! $theaterId) {
            throw new \RuntimeException('Đơn POS phải thuộc một rạp chiếu.');
        }

        if ($showtime && ! empty($data['theater_id']) && (int) $data['theater_id'] !== (int) $theaterId) {
            throw new \RuntimeException('Rạp của đơn không khớp với suất chiếu.');
        }

        if (! $staff->isAdmin() && ! $staff->isAssignedToTheater((int) $theaterId)) {
            throw new \RuntimeException('Bạn không có quyền tạo đơn tại rạp này.', 403);
        }

        $tickets = collect($data['tickets'] ?? [])
            ->map(fn (array $ticket): array => [
                'seat_id' => (int) $ticket['seat_id'],
                'audience_type' => $ticket['audience_type'] ?? 'adult',
                'student_card_verified' => (bool) ($ticket['student_card_verified'] ?? false),
            ])->values()->all();
        $seatIds = $tickets
            ? array_column($tickets, 'seat_id')
            : (isset($data['seat_ids']) ? array_values(array_map('intval', $data['seat_ids'])) : []);
        sort($seatIds, SORT_NUMERIC);

        // 2. Build items array matching Client payment initiation format
        $items = [];
        foreach ($tickets ?: array_map(fn (int $seatId): array => [
            'seat_id' => $seatId,
            'audience_type' => 'adult',
            'student_card_verified' => false,
        ], $seatIds) as $ticket) {
            $items[] = [
                'type' => 'seat',
                'id' => $ticket['seat_id'],
                'quantity' => 1,
                'audience_type' => $ticket['audience_type'],
                'student_card_verified' => $ticket['student_card_verified'],
            ];
        }
        foreach ($data['products'] ?? [] as $p) {
            $items[] = [
                'type' => $p['type'] ?? 'product',
                'id' => (int) $p['id'],
                'quantity' => (int) ($p['quantity'] ?? 1),
            ];
        }

        if (empty($items)) {
            throw new \RuntimeException('Giỏ hàng trống. Vui lòng chọn ghế hoặc sản phẩm.');
        }

        $posIdempotencyKey = (string) Str::uuid();

        $validated = [
            'idempotency_key' => $posIdempotencyKey,
            'showtime_id' => $showtime?->id,
            'theater_id' => (int) $theaterId,
            'source' => 'pos',
            'served_by_user_id' => $staff->id,
            'items' => $items,
            'points_used' => (int) ($data['loyalty_points_to_use'] ?? 0),
            'voucher_code' => $data['promotion_code'] ?? null,
        ];

        $customerType = $data['customer_type'] ?? 'adult';
        $paymentMethod = ($data['payment_method'] ?? 'cash') === 'payos_qr'
            ? 'qr_online'
            : ($data['payment_method'] ?? 'cash');
        $validated['payment_method'] = $paymentMethod;

        // 3. Delegate to core PaymentService engine (used by Client)
        // The customer owns the order and benefits; the staff member is the actor
        // and the owner of the POS seat hold.
        // IMPORTANT: always pass the explicit random key as 5th arg to prevent
        // collision with content-based auto-generation when retrying after cancel
        $initiateResult = app(\App\Services\PaymentService::class)->initiate(
            $customer,
            $showtime,
            $validated,
            url(''),
            $posIdempotencyKey,
            $staff,
            $staff
        );

        $gatewayOrderCode = (int) $initiateResult['gateway_order_code'];
        $order = Order::where('gateway_order_code', $gatewayOrderCode)->firstOrFail();

        // 4. Enrich POS specific metadata on order
        $payload = array_merge($order->payload ?? [], [
            'source' => 'pos',
            'theater_id' => (int) $theaterId,
            'seat_ids' => $seatIds,
            'customer_type' => $customerType,
            'customer_mode' => $data['customer_mode'] ?? ($customer->isSystemGuest() ? 'guest' : 'member'),
            'tickets' => $tickets,
            'customer_phone' => $customer->phone ?? null,
            'customer_name' => $customer->name ?? 'Khách vãng lai',
            'staff_id' => $staff->id,
            'staff_name' => $staff->name,
            'served_by_user_id' => $staff->id,
            'payment_method' => $paymentMethod,
        ]);

        $order->forceFill([
            'code' => 'POS-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4)),
            'theater_id' => (int) $theaterId,
            'served_by_user_id' => $staff->id,
            'source' => 'pos',
            'payment_method' => $paymentMethod,
            'payload' => $payload,
        ])->save();

        Log::info('POS: Order created via core PaymentService', [
            'order_id' => $order->id,
            'order_code' => $order->code,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'total_amount' => $order->total_amount,
            'payment_method' => $paymentMethod,
        ]);

        return $order->load(['orderItems', 'showtime.movie', 'showtime.screen.theater']);
    }

    /**
     * Confirm cash payment and award loyalty points.
     */
    public function confirmCashPayment(Order $order, User $staff, ?User $customer = null): Order
    {
        // Delegate fulfillment (marks paid, creates tickets, triggers events, audits, emails, awards points)
        app(OrderFulfillmentService::class)->finalize((int) $order->gateway_order_code);

        Log::info('POS: Cash payment confirmed via OrderFulfillmentService', [
            'order_id'   => $order->id,
            'staff_id'   => $staff->id,
            'amount'     => $order->total_amount,
        ]);

        return $order->fresh(['orderItems', 'showtime.movie', 'showtime.screen.theater']);
    }

    /**
     * Get formatted POS order details.
     */
    public function getPosOrderDetails(Order $order): array
    {
        $order->load(['user', 'orderItems', 'showtime.movie', 'showtime.screen.theater', 'payment']);
        $payload = (array) $order->payload;

        return [
            'id'             => $order->id,
            'code'           => $order->code,
            'checkout_url'   => $order->checkout_url,
            'status'         => $order->payment_status,
            'payment_method' => $order->payment_method ?? 'cash',
            'customer_type'  => $order->customer_type ?? 'adult',
            'total_amount'   => (float) $order->total_amount,
            'subtotal'       => (float) ($payload['subtotal'] ?? $order->total_amount),
            'seat_total'     => (float) ($payload['seat_total'] ?? 0),
            'product_total'  => (float) ($payload['product_total'] ?? 0),
            'loyalty_discount'    => (float) ($payload['loyalty_discount'] ?? 0),
            'loyalty_points_used' => (int) ($payload['loyalty_points_used'] ?? 0),
            'loyalty_points_earned' => (int) ($payload['loyalty_points_earned'] ?? 0),
            'customer_name'  => $payload['customer_name'] ?? $order->user?->name,
            'customer_phone' => $payload['customer_phone'] ?? $order->user?->phone,
            'staff_name'     => $payload['staff_name'] ?? null,
            'movie_title'    => $order->showtime?->movie?->title,
            'theater_name'   => $order->showtime?->screen?->theater?->name,
            'screen_name'    => $order->showtime?->screen?->name,
            'showtime'       => $order->showtime?->scheduled_at,
            'seats'          => $order->orderItems
                ->filter(fn ($item) => $item->item_type === Seat::class)
                ->map(fn ($item) => [
                    'label'  => $item->metadata['seat_label'] ?? '?',
                    'type'   => $item->metadata['seat_type'] ?? 'Thường',
                    'price'  => (float) $item->unit_price,
                ])->values()->toArray(),
            'products'       => $order->orderItems
                ->filter(fn ($item) => $item->item_type === Product::class)
                ->map(fn ($item) => [
                    'name'     => $item->metadata['product_name'] ?? '?',
                    'quantity' => $item->quantity,
                    'price'    => (float) $item->unit_price,
                    'total'    => (float) $item->total_price,
                ])->values()->toArray(),
            'created_at'  => $order->created_at,
            'paid_at'     => $order->paid_at,
        ];
    }
}
