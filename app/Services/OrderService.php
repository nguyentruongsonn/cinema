<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderService
{
    private const STATUS_CANCELLED = 0;
    private const STATUS_PENDING = 1;
    private const STATUS_CONFIRMED = 2;
    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(
        private readonly OrderExpirationService $orderExpirationService,
        private readonly TicketPricingService $ticketPricingService,
        private readonly AuditSnapshotService $auditSnapshots
    ) {
    }

    public function create(array $data, $user): Order
    {
        return DB::transaction(function () use ($data, $user) {
            $this->orderExpirationService->expirePendingOrders((int) $data['showtime_id']);

            $showtime = Showtime::with(['format', 'movie', 'screen.theater'])->lockForUpdate()->findOrFail($data['showtime_id']);

            // Bảo mật: Không cho phép đặt vé nếu đã qua giờ chiếu
            if ($showtime->scheduled_at <= now()) {
                throw new \RuntimeException('Suất chiếu này đã bắt đầu hoặc kết thúc. Không thể đặt vé.');
            }

            $seatIds = array_values(array_map('intval', $data['seat_ids']));
            sort($seatIds, SORT_NUMERIC);

            $seatHold = $this->getValidSeatHold($showtime->id, $user->id, $data['seat_hold_id'] ?? null);

            $this->ensureSeatHoldMatches($seatIds, $seatHold->normalizedSeatIds());

            $seats = Seat::with('seatType')
                ->whereIn('id', $seatIds)
                ->where('screen_id', $showtime->screen_id)
                ->lockForUpdate()
                ->get();

            if ($seats->count() !== count($seatIds)) {
                throw new \RuntimeException('Một hoặc nhiều ghế không thuộc phòng chiếu của suất chiếu này.');
            }

            $bookedSeatIds = $this->getBookedSeatIds($showtime->id, $seatIds);

            if (!empty($bookedSeatIds)) {
                throw new \RuntimeException('Một số ghế đã được đặt hoặc đang chờ thanh toán: ' . implode(', ', $bookedSeatIds));
            }

            $order = $this->createPendingOrder($showtime, $user->id, $seatIds, $seatHold->id);
            $seatTotal = $this->createSeatOrderItems($order, $seats, $showtime);
            $productTotal = $this->createProductOrderItems($order, $data['products'] ?? []);
            $subtotal = $seatTotal + $productTotal;
            [$discountAmount, $promotionPayload] = $this->applyPromotion($data['promotion_code'] ?? null, $subtotal);

            $totalAmount = max(0, $subtotal - $discountAmount);

            // PHASE 1 FIX: Use updateTotal() for amount, forceFill for payload
            $order->updateTotal($totalAmount);
            $order->forceFill([
                'payload' => array_merge((array) $order->payload, [
                    'subtotal' => $subtotal,
                    'seat_total' => $seatTotal,
                    'product_total' => $productTotal,
                    'discount_amount' => $discountAmount,
                    'promotion' => $promotionPayload,
                ]),
            ])->save();
            $seatHold->delete();

            $order->load([
                'showtime.movie',
                'showtime.screen.theater',
                'orderItems',
                'orderItems.item',
            ]);

            if ($user instanceof User) {
                app(AuditLogService::class)->record(
                    $user,
                    'order.created',
                    $order,
                    [],
                    $this->auditSnapshots->order($order)
                );
            }

            return $order;
        }, self::TRANSACTION_ATTEMPTS);
    }

    public function findForUser(int $id, $user): Order
    {
        $order = Order::with([
            'user',
            'showtime.movie',
            'showtime.format',
            'showtime.versionType',
            'showtime.screen.theater.branch',
            'orderItems',
            'tickets.seat.seatType',
            'payment',
        ])->findOrFail($id);

        $order = $this->orderExpirationService->expireOrder($order)->load([
            'user',
            'showtime.movie',
            'showtime.format',
            'showtime.versionType',
            'showtime.screen.theater.branch',
            'orderItems',
            'tickets.seat.seatType',
            'payment',
        ]);

        $this->ensureUserCanAccess($order, $user);

        return $order;
    }

    public function getUserOrders($user, int $perPage = 15): LengthAwarePaginator
    {
        $this->orderExpirationService->expirePendingOrders();

        $orders = Order::where('user_id', $user->id)
            ->with([
                'showtime.movie',
                'showtime.format',
                'showtime.versionType',
                'showtime.screen.theater.branch',
                'orderItems.item',
                'payment',
            ])
            ->latest()
            ->paginate($perPage);

        $orders->getCollection()->transform(fn (Order $order) => $this->format($order));

        return $orders;
    }

    public function cancel(int $id, $user): Order
    {
        return DB::transaction(function () use ($id, $user) {
            // Load order with lock to prevent concurrent cancellations
            $order = Order::lockForUpdate()->findOrFail($id);

            $this->ensureUserCanAccess($order, $user);

            // Idempotency: If already cancelled, return early
            if ((int) $order->status === self::STATUS_CANCELLED) {
                return $order;
            }

            $oldOrderValues = $this->auditSnapshots->order($order);
            $payment = $order->payment;
            $oldPaymentValues = $payment ? $this->auditSnapshots->payment($payment) : null;

            // Business rule: Cannot cancel confirmed/paid orders
            if ((int) $order->status === self::STATUS_CONFIRMED) {
                throw new \RuntimeException('Không thể hủy đơn hàng đã thanh toán/xác nhận.', 422);
            }

            // 1. PHASE 1 FIX: Use markCancelled() state transition method
            $order->markCancelled();

            // 2. Update payment status if payment exists
            if ($payment) {
                $payment->markCancelled(now());
            }

            // 3. Restore product stock from order items
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

                    Log::info('Stock restored after order cancellation', [
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity_restored' => $item->quantity,
                        'new_stock' => $product->stock,
                    ]);
                }
            }

            // 4. Decrement promotion usage if promotion was used
            $payload = (array) $order->payload;
            $promotion = $payload['promotion'] ?? null;

            if ($promotion && isset($promotion['id'])) {
                $promotionModel = Promotion::query()
                    ->where('id', $promotion['id'])
                    ->lockForUpdate()
                    ->first();

                if ($promotionModel && $promotionModel->usage_count > 0) {
                    $promotionModel->decrement('usage_count');

                    Log::info('Promotion usage decremented after order cancellation', [
                        'order_id' => $order->id,
                        'promotion_id' => $promotionModel->id,
                        'promotion_code' => $promotionModel->code,
                        'new_usage_count' => $promotionModel->usage_count,
                    ]);
                }
            }

            // 5. Clean up only the hold associated with this order.
            // Never delete unrelated active holds belonging to the same user/showtime.
            $seatHoldId = is_numeric($payload['seat_hold_id'] ?? null)
                ? (int) $payload['seat_hold_id']
                : null;

            if ($seatHoldId !== null) {
                SeatHold::query()
                    ->whereKey($seatHoldId)
                    ->where('user_id', $order->user_id)
                    ->where('showtime_id', $order->showtime_id)
                    ->lockForUpdate()
                    ->delete();
            }

            // 6. Log cancellation event
            Log::info('Order cancelled successfully', [
                'order_id' => $order->id,
                'order_code' => $order->code,
                'user_id' => $user->id,
                'total_amount' => $order->total_amount,
                'products_restored' => $productItems->count(),
                'cancelled_by' => 'user',
            ]);

            $cancelledOrder = $order->fresh('payment');

            if ($user instanceof User) {
                app(AuditLogService::class)->record(
                    $user,
                    'order.cancelled',
                    $cancelledOrder,
                    $oldOrderValues,
                    $this->auditSnapshots->order($cancelledOrder)
                );

                if ($payment && $oldPaymentValues) {
                    $cancelledPayment = $payment->fresh();

                    app(AuditLogService::class)->record(
                        $user,
                        'payment.cancelled',
                        $cancelledPayment,
                        $oldPaymentValues,
                        $this->auditSnapshots->payment($cancelledPayment)
                    );
                }
            }

            return $cancelledOrder;
        }, self::TRANSACTION_ATTEMPTS);
    }

    public function findByGatewayCode(int $gatewayOrderCode, bool $lock = false): ?Order
    {
        $query = Order::where('gateway_order_code', $gatewayOrderCode);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function format(Order $order): array
    {
        $statusMap = [
            self::STATUS_CANCELLED => 'cancelled',
            self::STATUS_PENDING => 'pending',
            self::STATUS_CONFIRMED => 'confirmed',
        ];

        $payload = (array) $order->payload;
        $ticketItems = $order->orderItems
            ->filter(fn (OrderItem $item) => in_array($item->item_type, [Seat::class, \App\Models\Ticket::class], true))
            ->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'type' => class_basename($item->item_type),
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total_price' => (float) $item->total_price,
                'metadata' => (array) $item->metadata,
            ])
            ->values();

        if ($ticketItems->isEmpty()) {
            $ticketItems = collect($payload['seats'] ?? [])->map(fn (array $seat) => [
                'id' => $seat['id'] ?? null,
                'type' => 'Seat',
                'quantity' => 1,
                'unit_price' => (float) ($seat['price'] ?? 0),
                'total_price' => (float) ($seat['price'] ?? 0),
                'metadata' => [
                    'seat_label' => $seat['name'] ?? $seat['label'] ?? (($seat['row'] ?? '') . ($seat['number'] ?? '')),
                    'row' => $seat['row'] ?? null,
                    'number' => $seat['number'] ?? null,
                    'seat_type' => $seat['type'] ?? $seat['seat_type'] ?? 'Thường',
                ],
            ])->values();
        }

        $productItems = $order->orderItems
            ->reject(fn (OrderItem $item) => in_array($item->item_type, [Seat::class, \App\Models\Ticket::class], true))
            ->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'type' => class_basename($item->item_type),
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total_price' => (float) $item->total_price,
                'metadata' => (array) $item->metadata,
            ])
            ->values();

        if ($productItems->isEmpty()) {
            $productItems = collect($payload['products'] ?? [])->map(fn (array $product) => [
                'id' => $product['id'] ?? null,
                'type' => 'Product',
                'quantity' => (int) ($product['quantity'] ?? 1),
                'unit_price' => (float) ($product['price'] ?? 0),
                'total_price' => (float) ($product['total_price'] ?? (($product['price'] ?? 0) * ($product['quantity'] ?? 1))),
                'metadata' => [
                    'product_name' => $product['name'] ?? 'Sản phẩm',
                    'product_description' => $product['description'] ?? null,
                    'product_type' => $product['type'] ?? null,
                ],
            ])->values();
        }

        $seatTotal = (float) ($payload['seat_total'] ?? $ticketItems->sum('total_price'));
        $productTotal = (float) ($payload['product_total'] ?? $productItems->sum('total_price'));
        $subtotal = (float) ($payload['subtotal'] ?? ($seatTotal + $productTotal));
        $voucherDiscount = (float) ($payload['voucher_discount'] ?? 0);
        $pointDiscount = (float) ($payload['point_discount'] ?? 0);
        $discountAmount = (float) ($payload['discount_amount'] ?? ($voucherDiscount + $pointDiscount));

        return [
            'id' => $order->id,
            'code' => $order->code,
            'order_code' => $order->code,
            'gateway_order_code' => $order->gateway_order_code,
            'user_id' => $order->user_id,
            'showtime_id' => $order->showtime_id,
            'showtime' => $order->showtime,
            'items' => $order->orderItems,
            'order_items' => $order->orderItems,
            'payment' => $order->payment,
            'status' => $statusMap[(int) $order->status] ?? 'unknown',
            'status_code' => (int) $order->status,
            'payment_status' => $order->payment_status,
            'total_amount' => (float) $order->total_amount,
            'total_price' => (float) $order->total_amount,
            'total' => (float) $order->total_amount,
            'expired_at' => $order->expired_at,
            'paid_at' => $order->paid_at,
            'cancelled_at' => $order->cancelled_at,
            'created_at' => $order->created_at,
            // Flatten movie data for easier frontend access
            'movie_title' => $order->showtime?->movie?->title,
            'poster_url' => $order->showtime?->movie?->poster_url,
            'show_date' => $order->showtime?->scheduled_at,
            'show_time' => $order->showtime?->scheduled_at,
            'theater_name' => $order->showtime?->screen?->theater?->name,
            'screen_name' => $order->showtime?->screen?->name,
            'branch_name' => $order->showtime?->screen?->theater?->branch?->name,
            'theater_address' => $order->showtime?->screen?->theater?->address
                ?: $order->showtime?->screen?->theater?->branch?->address,
            'payload' => $payload,
            'invoice' => [
                'tickets' => $ticketItems->all(),
                'products' => $productItems->all(),
                'subtotal' => $subtotal,
                'seat_total' => $seatTotal,
                'product_total' => $productTotal,
                'voucher_discount' => $voucherDiscount,
                'point_discount' => $pointDiscount,
                'discount_amount' => $discountAmount,
                'points_used' => (int) ($payload['points_used'] ?? 0),
                'promotion' => $payload['voucher'] ?? $payload['promotion'] ?? null,
                'total' => (float) $order->total_amount,
            ],
        ];
    }

    private function getValidSeatHold(int $showtimeId, int $userId, ?int $seatHoldId): SeatHold
    {
        $holdQuery = SeatHold::query()
            ->where('showtime_id', $showtimeId)
            ->where('user_id', $userId)
            ->where('held_until', '>', now());

        if ($seatHoldId) {
            $holdQuery->whereKey($seatHoldId);
        }

        $seatHold = $holdQuery->lockForUpdate()->first();

        if (!$seatHold) {
            throw new \RuntimeException('Phiên giữ ghế không hợp lệ hoặc đã hết hạn. Vui lòng chọn lại ghế.');
        }

        return $seatHold;
    }

    private function ensureSeatHoldMatches(array $seatIds, array $heldSeatIds): void
    {
        $seatIds = array_values(array_map('intval', $seatIds));
        $heldSeatIds = array_values(array_map('intval', $heldSeatIds));

        sort($seatIds);
        sort($heldSeatIds);

        if ($seatIds !== $heldSeatIds) {
            throw new \RuntimeException('Danh sách ghế đặt không khớp với phiên giữ ghế hiện tại.');
        }
    }

    private function getBookedSeatIds(int $showtimeId, array $seatIds): array
    {
        return OrderItem::query()
            ->where('item_type', Seat::class)
            ->whereIn('item_id', $seatIds)
            ->whereHas('order', function ($query) use ($showtimeId) {
                $query->where('showtime_id', $showtimeId)
                    ->whereIn('status', $this->orderExpirationService->getActiveBookingStatuses());
            })
            ->pluck('item_id')
            ->all();
    }

    private function createPendingOrder(Showtime $showtime, int $userId, array $seatIds, int $seatHoldId): Order
    {
        // PHASE 1 FIX: Use Order::createPending() factory method
        return Order::createPending([
            'code' => 'ORD-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6)),
            'gateway_order_code' => (int) (now()->format('ymdHis') . random_int(100, 999)),
            'payment_provider' => 'web',
            'user_id' => $userId,
            'showtime_id' => $showtime->id,
            'total_amount' => 0,
            'payload' => [
                'seat_ids' => $seatIds,
                'seat_hold_id' => $seatHoldId,
                'source' => 'web',
            ],
            'expired_at' => now()->addMinutes(15),
        ]);
    }

    private function createProductOrderItems(Order $order, array $products): float
    {
        if (empty($products)) {
            return 0;
        }

        $productIds = collect($products)
            ->map(fn (array $product): int => (int) $product['id']);

        if ($productIds->duplicates()->isNotEmpty()) {
            throw new \RuntimeException('Không được phép gửi trùng sản phẩm trong đơn hàng.');
        }

        $requestedProducts = collect($products)
            ->mapWithKeys(fn (array $product) => [(int) $product['id'] => (int) $product['quantity']])
            ->filter(fn (int $quantity) => $quantity > 0);

        if ($requestedProducts->isEmpty()) {
            return 0;
        }

        $productIds = $requestedProducts->keys()
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        $productModels = Product::query()
            ->whereIn('id', $productIds)
            ->where('status', 1)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($productModels->count() !== $requestedProducts->count()) {
            throw new \RuntimeException('Một hoặc nhiều combo/sản phẩm không hợp lệ.');
        }

        $totalAmount = 0;

        foreach ($productModels as $product) {
            $quantity = (int) $requestedProducts->get($product->id);

            if ((int) $product->stock < $quantity) {
                throw new \RuntimeException("Sản phẩm {$product->name} không đủ số lượng tồn kho.");
            }

            $unitPrice = (float) $product->price;
            $lineTotal = $unitPrice * $quantity;
            $totalAmount += $lineTotal;

            // PHASE 5 FIX: Use OrderItem factory method instead of mass assignment
            $item = OrderItem::createFromProduct($order, $product, $quantity, (string) $unitPrice, [
                'product_name' => $product->name,
                'product_type' => $product->type,
                'image_url' => $product->image_url,
            ]);
            $item->save();

            $product->decrement('stock', $quantity);
        }

        return $totalAmount;
    }

    private function applyPromotion(?string $promotionCode, float $subtotal): array
    {
        $promotionCode = trim((string) $promotionCode);

        if ($promotionCode === '') {
            return [0, null];
        }

        $promotion = Promotion::query()
            ->active()
            ->valid()
            ->byCode($promotionCode)
            ->lockForUpdate()
            ->first();

        if (!$promotion) {
            throw new \RuntimeException('Mã khuyến mãi không hợp lệ hoặc đã hết hạn.');
        }

        $minOrderValue = (float) ($promotion->min_order_value ?? 0);

        if ($subtotal < $minOrderValue) {
            throw new \RuntimeException('Đơn hàng chưa đạt giá trị tối thiểu để áp dụng mã khuyến mãi.');
        }

        $discountAmount = $this->calculatePromotionDiscount($promotion, $subtotal);

        $promotion->increment('usage_count');

        return [
            $discountAmount,
            [
                'id' => $promotion->id,
                'code' => $promotion->code,
                'type' => $promotion->type,
                'value' => (float) $promotion->value,
                'discount_amount' => $discountAmount,
            ],
        ];
    }

    private function calculatePromotionDiscount(Promotion $promotion, float $subtotal): float
    {
        if ($promotion->type === 'percentage') {
            $discount = $subtotal * ((float) $promotion->value / 100);
            $maxDiscount = (float) ($promotion->max_discount ?? 0);

            if ($maxDiscount > 0) {
                $discount = min($discount, $maxDiscount);
            }

            return round(min($discount, $subtotal), 0);
        }

        if (in_array($promotion->type, ['fixed', 'amount'], true)) {
            return round(min((float) $promotion->value, $subtotal), 0);
        }

        return 0;
    }

    private function createSeatOrderItems(Order $order, $seats, Showtime $showtime): float
    {
        $totalAmount = 0;

        // Lấy thông tin format và phụ thu phim
        $formatName = $showtime->format?->name ?? '2D';
        $movieSurcharge = (int) ($showtime->movie?->surcharge ?? 0);
        $scheduledAt = $showtime->scheduled_at;

        foreach ($seats as $seat) {
            // Kiểm tra ghế đôi
            $isDoubleSeat = $this->isDoubleSeat(
                $seat->seatType?->name ?? '',
                $seat->seatType?->slug ?? ''
            );

            // Tính giá vé bằng TicketPricingService (mặc định adult cho đặt vé online)
            $pricingResult = $this->ticketPricingService->calculate(
                format: $formatName,
                scheduledAt: $scheduledAt,
                customerType: 'adult', // Online booking luôn tính giá adult
                isDoubleSeat: $isDoubleSeat,
                movieSurcharge: $movieSurcharge,
                extraHolidays: [],
                formatSurcharge: (int) ($showtime->format?->surcharge ?? 0),
                seatSurcharge: (int) ($seat->seatType?->surcharge ?? 0),
                theaterPricing: $showtime->screen?->theater?->pricing_profile
            );

            $unitPrice = $pricingResult['total_price'];
            $totalAmount += $unitPrice;

            // PHASE 5 WORKAROUND: Use forceCreate to bypass guarded protection
            // TODO Phase 6: Refactor seat reservation architecture to use Ticket references from the start
            // Current issue: OrderService stores seat references (Seat::class), but OrderItem expects Ticket references
            OrderItem::forceCreate([
                'order_id' => $order->id,
                'item_type' => Seat::class,
                'item_id' => $seat->id,
                'quantity' => 1,
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice,
                'metadata' => [
                    'seat_label' => $seat->label ?: ($seat->row . $seat->number),
                    'row' => $seat->row,
                    'number' => $seat->number,
                    'seat_type' => $seat->seatType?->name,
                    'pricing_details' => [
                        'base_price' => $pricingResult['base_price'],
                        'surcharges' => $pricingResult['surcharges'],
                        'day_type' => $pricingResult['day_type'],
                        'time_slot' => $pricingResult['time_slot'],
                        'customer_type' => $pricingResult['customer_type'],
                        'format' => $pricingResult['format'],
                    ],
                ],
            ]);
        }

        return $totalAmount;
    }

    /**
     * Kiểm tra xem ghế có phải ghế đôi không
     */
    private function isDoubleSeat(string $name, string $slug): bool
    {
        $nameLower = mb_strtolower($name);
        $slugLower = mb_strtolower($slug);

        $keywords = ['double', 'couple', 'đôi', 'sweetbox', 'sweet-box'];

        foreach ($keywords as $keyword) {
            if (str_contains($nameLower, $keyword) || str_contains($slugLower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function ensureUserCanAccess(Order $order, $user): void
    {
        if ((int) $order->user_id !== (int) $user->id && !$this->isStaffUser($user)) {
            throw new \RuntimeException('Unauthorized', 403);
        }
    }

    private function isStaffUser($user): bool
    {
        return method_exists($user, 'role')
            && $user->role
            && in_array($user->role->slug, ['admin', 'manager', 'staff']);
    }

}
