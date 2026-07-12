<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\Showtime;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    private const STATUS_CANCELLED = 0;
    private const STATUS_PENDING = 1;
    private const STATUS_CONFIRMED = 2;

    public function __construct(
        private readonly OrderExpirationService $orderExpirationService,
        private readonly TicketPricingService $ticketPricingService
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

            $seatHold = $this->getValidSeatHold($showtime->id, $user->id, $data['seat_hold_id'] ?? null);

            $this->ensureSeatHoldMatches($seatIds, (array) $seatHold->seat_ids);

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

            $order->update([
                'total_amount' => $totalAmount,
                'payload' => array_merge((array) $order->payload, [
                    'subtotal' => $subtotal,
                    'seat_total' => $seatTotal,
                    'product_total' => $productTotal,
                    'discount_amount' => $discountAmount,
                    'promotion' => $promotionPayload,
                ]),
            ]);
            $seatHold->delete();

        return $order->load([
            'showtime.movie',
            'showtime.screen.theater',
            'orderItems',
            'orderItems.item',
        ]);
        });
    }

    public function findForUser(int $id, $user): Order
    {
        $order = Order::with([
            'user',
            'showtime.movie',
            'showtime.screen.theater',
            'orderItems',
            'payment',
        ])->findOrFail($id);

        $order = $this->orderExpirationService->expireOrder($order)->load([
            'user',
            'showtime.movie',
            'showtime.screen.theater',
            'orderItems',
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
        $order = Order::findOrFail($id);
        $order = $this->orderExpirationService->expireOrder($order);

        $this->ensureUserCanAccess($order, $user);

        if ((int) $order->status === self::STATUS_CONFIRMED) {
            throw new \RuntimeException('Không thể hủy đơn hàng đã thanh toán/xác nhận.', 422);
        }

        $order->update([
            'status' => self::STATUS_CANCELLED,
            'payment_status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return $order->fresh();
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
            'payload' => $order->payload,
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
        return Order::create([
            'code' => 'ORD-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6)),
            'gateway_order_code' => (int) (now()->format('ymdHis') . random_int(100, 999)),
            'user_id' => $userId,
            'showtime_id' => $showtime->id,
            'total_amount' => 0,
            'payload' => [
                'seat_ids' => $seatIds,
                'seat_hold_id' => $seatHoldId,
                'source' => 'web',
            ],
            'status' => self::STATUS_PENDING,
            'payment_status' => 'created',
            'expired_at' => now()->addMinutes(15),
        ]);
    }

    private function createProductOrderItems(Order $order, array $products): float
    {
        if (empty($products)) {
            return 0;
        }

        $requestedProducts = collect($products)
            ->mapWithKeys(fn (array $product) => [(int) $product['id'] => (int) $product['quantity']])
            ->filter(fn (int $quantity) => $quantity > 0);

        if ($requestedProducts->isEmpty()) {
            return 0;
        }

        $productModels = Product::query()
            ->whereIn('id', $requestedProducts->keys()->all())
            ->where('status', 1)
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

            OrderItem::create([
                'order_id' => $order->id,
                'item_type' => Product::class,
                'item_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $lineTotal,
                'metadata' => [
                    'product_name' => $product->name,
                    'product_type' => $product->type,
                    'image_url' => $product->image_url,
                ],
            ]);

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

        $promotion->increment('used_count');

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

            OrderItem::create([
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
        return method_exists($user, 'roles')
            && $user->roles()
                ->whereIn('name', ['admin', 'manager', 'staff'])
                ->exists();
    }
}
