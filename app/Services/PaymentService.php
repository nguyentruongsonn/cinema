<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PaymentGatewayException;
use App\Models\Combo;
use App\Models\IdempotencyKey;
use App\Models\LoyaltyHistory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\SeatHoldItem;
use App\Models\Showtime;
use App\Models\Theater;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Orchestrator: điều phối toàn bộ luồng tạo và xử lý thanh toán.
 */
class PaymentService
{
    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(
        private readonly PayOSGateway            $gateway,
        private readonly PricingService          $pricing,
        private readonly OrderFulfillmentService $fulfillment,
        private readonly AuditSnapshotService    $auditSnapshots,
    ) {}

    /**
     * Tạo đơn hàng và link thanh toán.
     * Phase 1.2: Refactored with robust IdempotencyKey::executeIdempotent() wrapper
     */
    public function initiate(
        User      $user,
        ?Showtime $showtime,
        array     $validated,
        string    $baseUrl,
        ?string   $idempotencyKey = null,
        ?User     $actor = null,
        ?User     $seatHolder = null
    ): array {
        $actor ??= $user;
        $seatHolder ??= $user;

        // Auto-generate idempotency key if not provided (for backward compatibility)
        if ($idempotencyKey === null) {
            $idempotencyKey = $this->generateIdempotencyKey($user, $showtime, $validated);
        }

        // Use robust IdempotencyKey::executeIdempotent() wrapper
        // This provides: unique constraint protection, payload verification,
        // retry logic, concurrent request blocking, and response caching
        $idempotentResult = IdempotencyKey::executeIdempotent(
            $idempotencyKey,
            function ($record) use ($user, $actor, $seatHolder, $showtime, $validated, $baseUrl) {
                // Core payment initiation logic (executeIdempotent handles transaction)
                $items = collect($validated['items'] ?? []);

                $seatRequests    = $items->where('type', 'seat')->all();
                $productRequests = $items->whereIn('type', ['product', 'combo'])->all();

                // PHASE 1: Validate seat hold BEFORE creating order
                $seatIds = collect($seatRequests)
                    ->map(fn($seat) => (int)($seat['id'] ?? $seat))
                    ->filter()
                    ->values()
                    ->all();

                $seatHold = !empty($seatIds)
                    ? $this->validateSeatHold($seatHolder, $showtime, $seatIds)
                    : null;
                $checkoutFingerprint = $seatHold
                    ? $this->checkoutFingerprint($user, $showtime, $validated, $seatHold)
                    : null;
                $isPosCashPayment = ($validated['source'] ?? 'web') === 'pos'
                    && ($validated['payment_method'] ?? null) === 'cash';

                if ($checkoutFingerprint) {
                    $existingOrder = Order::query()
                        ->where('checkout_fingerprint', $checkoutFingerprint)
                        ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED])
                        ->with('payment')
                        ->first();

                    if ($existingOrder?->checkout_url || $existingOrder?->isPaid()) {
                        return [
                            'status' => 200,
                            'data' => $this->formatInitiateResponse($existingOrder),
                            'payment_id' => $existingOrder->payment?->id,
                        ];
                    }
                }

            [$order, $payment, $checkoutReplayed] = DB::transaction(function () use (
                $user,
                $showtime,
                $seatRequests,
                $productRequests,
                $validated,
                $checkoutFingerprint,
                $seatHold,
                $seatIds,
                $seatHolder,
                $isPosCashPayment
            ): array {
                $theaterId = $validated['theater_id'] ?? $showtime?->screen?->theater_id;
                $theaterId = is_numeric($theaterId) && Theater::query()->whereKey((int) $theaterId)->exists()
                    ? (int) $theaterId
                    : null;

                if ($seatHold) {
                    SeatHold::query()->whereKey($seatHold->id)->lockForUpdate()->firstOrFail();
                }

                if ($checkoutFingerprint) {
                    $existingOrder = Order::query()
                        ->where('checkout_fingerprint', $checkoutFingerprint)
                        ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED])
                        ->with('payment')
                        ->first();

                    if ($existingOrder?->checkout_url || $existingOrder?->isPaid()) {
                        return [$existingOrder, $existingOrder->payment, true];
                    }

                    if ($existingOrder) {
                        throw new \RuntimeException('Checkout is already being created. Please retry shortly.', 409);
                    }
                }

                $pricing = $this->pricing->buildSnapshot(
                    $user,
                    $showtime,
                    $seatRequests,
                    $productRequests,
                    $validated['voucher_code'] ?? null,
                    (int) ($validated['points_used'] ?? 0),
                );
                $isZeroAmountCheckout = (float) $pricing['final_amount'] <= 0.0;

                $orderContext = array_intersect_key(
                    (array) ($validated['order_context'] ?? []),
                    array_flip(['customer_id', 'customer_name', 'customer_phone', 'customer_mode', 'staff_id', 'staff_name'])
                );

                $order = Order::createPending([
                    'code' => (string) ($validated['order_code'] ?? $this->generateOrderNumber()),
                    'gateway_order_code' => $this->generateOrderCode(),
                    'payment_provider' => ($isZeroAmountCheckout || $isPosCashPayment) ? 'internal' : 'payos',
                    'user_id' => $user->id,
                    'showtime_id' => $showtime?->id,
                    'theater_id' => $theaterId,
                    'served_by_user_id' => $validated['served_by_user_id'] ?? null,
                    'source' => $validated['source'] ?? 'web',
                    'payment_method' => $validated['payment_method'] ?? ($isZeroAmountCheckout ? 'zero_amount' : 'payos'),
                    'checkout_fingerprint' => $checkoutFingerprint,
                    'total_amount' => $pricing['final_amount'],
                    'payload' => array_merge($orderContext, [
                        'seat_hold_id' => $seatHold?->id,
                        'seat_hold_user_id' => $seatHold ? $seatHolder->id : null,
                        'source' => $validated['source'] ?? 'web',
                        'theater_id' => $theaterId,
                        'served_by_user_id' => $validated['served_by_user_id'] ?? null,
                        'payment_method' => $validated['payment_method'] ?? ($isZeroAmountCheckout ? 'zero_amount' : 'payos'),
                        'subtotal' => $pricing['subtotal'],
                        'seat_total' => $pricing['seat_total'] ?? 0,
                        'product_total' => $pricing['product_total'] ?? 0,
                        'discount_amount' => $pricing['discount_amount'],
                        'voucher_discount' => $pricing['voucher_discount'],
                        'point_discount' => $pricing['point_discount'],
                        'points_used' => $pricing['points_used'],
                        'voucher' => $pricing['voucher'],
                        'seats' => $pricing['seats'],
                        'products' => $pricing['products'],
                        'product_stock_reserved' => ! empty($pricing['products']),
                        'points_reserved' => $pricing['points_used'] > 0,
                        'voucher_reserved' => $pricing['voucher'] !== null,
                    ]),
                    'expired_at' => now()->addMinutes(15),
                ]);

                if ((int) $pricing['points_used'] > 0) {
                    $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                    $pointsUsed = (int) $pricing['points_used'];

                    if ((int) $lockedUser->loyalty_points < $pointsUsed) {
                        throw new \RuntimeException('Số điểm khả dụng đã thay đổi. Vui lòng thử lại.', 409);
                    }

                    $lockedUser->decrement('loyalty_points', $pointsUsed);
                    LoyaltyHistory::create([
                        'user_id' => $lockedUser->id,
                        'order_id' => $order->id,
                        'type' => 'redeem',
                        'points' => $pointsUsed,
                        'description' => "Trừ điểm dùng cho đơn hàng #{$order->code}",
                    ]);
                }

                if ($pricing['voucher'] !== null) {
                    $promotion = Promotion::query()
                        ->whereKey($pricing['voucher']['id'])
                        ->lockForUpdate()
                        ->firstOrFail();
                    $pivot = DB::table('user_promotion')
                        ->where('user_id', $user->id)
                        ->where('promotion_id', $promotion->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $pivot || (int) $pivot->status !== 1 || $pivot->used_at !== null) {
                        throw new \RuntimeException('Voucher đã được dùng bởi một giao dịch khác.', 409);
                    }

                    if ($promotion->usage_limit !== null && (int) $promotion->usage_count >= (int) $promotion->usage_limit) {
                        throw new \RuntimeException('Voucher đã hết lượt sử dụng.', 409);
                    }

                    $promotion->increment('usage_count');
                    DB::table('user_promotion')->where('id', $pivot->id)->update([
                        'status' => 2,
                        'order_id' => $order->id,
                        'updated_at' => now(),
                    ]);
                }

                foreach ($pricing['seats'] as $seatData) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'item_type' => Seat::class,
                        'item_id' => (int) $seatData['id'],
                        'quantity' => 1,
                        'unit_price' => (string) $seatData['price'],
                        'total_price' => (string) $seatData['price'],
                        'metadata' => [
                            'seat_label' => $seatData['name'] ?? null,
                            'row' => $seatData['row'] ?? null,
                            'number' => $seatData['number'] ?? null,
                            'seat_type' => $seatData['type'] ?? null,
                            'audience_type' => $seatData['audience_type'] ?? 'adult',
                            'student_card_verified' => (bool) ($seatData['student_card_verified'] ?? false),
                            'pricing' => $seatData['pricing'] ?? null,
                        ],
                    ]);
                }

                foreach ($pricing['products'] as $productData) {
                    $quantity = (int) $productData['quantity'];
                    $itemType = $productData['item_type'] ?? $productData['type'] ?? 'product';

                    if ($itemType === 'combo') {
                        $combo = Combo::query()
                            ->whereKey($productData['id'])
                            ->where('status', 1)
                            ->with('comboItems.product')
                            ->lockForUpdate()
                            ->firstOrFail();

                        foreach ($combo->comboItems as $comboItem) {
                            $requiredStock = (int) $comboItem->quantity * $quantity;
                            $product = Product::query()
                                ->whereKey($comboItem->product_id)
                                ->where('status', 1)
                                ->lockForUpdate()
                                ->firstOrFail();

                            if ((int) $product->stock < $requiredStock) {
                                throw new \RuntimeException("Combo {$combo->name} không còn đủ số lượng.", 409);
                            }

                            $product->decrement('stock', $requiredStock);
                        }

                        OrderItem::createFromCombo(
                            $order,
                            $combo,
                            $quantity,
                            (string) $productData['price'],
                            [
                                'combo_name' => $productData['name'],
                                'image_url' => $productData['image_url'] ?? null,
                                'stock_reserved' => true,
                            ]
                        )->save();

                        continue;
                    }

                    $product = Product::query()->whereKey($productData['id'])->lockForUpdate()->firstOrFail();

                    if ((int) $product->stock < $quantity) {
                        throw new \RuntimeException("Sản phẩm {$product->name} không còn đủ tồn kho.", 409);
                    }

                    $product->decrement('stock', $quantity);
                    OrderItem::createFromProduct(
                        $order,
                        $product,
                        $quantity,
                        (string) $productData['price'],
                        [
                            'product_name' => $productData['name'],
                            'product_type' => $productData['type'] ?? null,
                            'image_url' => $productData['image_url'] ?? null,
                            'stock_reserved' => true,
                        ]
                    )->save();
                }

                $paymentMethod = $isPosCashPayment
                    ? 'cash'
                    : ($isZeroAmountCheckout ? 'zero_amount' : 'payos');

                $payment = Payment::createPending([
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'method' => $paymentMethod,
                    'gateway_order_code' => $order->gateway_order_code,
                    'amount' => $order->total_amount,
                    'payload' => [
                        'showtime_id' => $showtime?->id,
                        'seat_ids' => $seatIds,
                        'created_at' => now()->toISOString(),
                    ],
                ]);

                return [$order, $payment, false];
            }, self::TRANSACTION_ATTEMPTS);

            if ($checkoutReplayed) {
                return [
                    'status' => 200,
                    'data' => $this->formatInitiateResponse($order),
                    'payment_id' => $payment?->id,
                ];
            }

            if ((float) $order->total_amount <= 0.0) {
                app(AuditLogService::class)->record(
                    $actor,
                    'order.created',
                    $order,
                    [],
                    $this->auditSnapshots->order($order)
                );

                app(AuditLogService::class)->record(
                    $actor,
                    'payment.created',
                    $payment,
                    [],
                    $this->auditSnapshots->payment($payment)
                );

                $this->fulfillment->finalize((int) $order->gateway_order_code);

                return [
                    'status' => 200,
                    'data' => $this->formatInitiateResponse($order->fresh()),
                    'payment_id' => $payment->id,
                ];
            }

            if ($isPosCashPayment) {
                app(AuditLogService::class)->record(
                    $actor,
                    'order.created',
                    $order,
                    [],
                    $this->auditSnapshots->order($order)
                );

                app(AuditLogService::class)->record(
                    $actor,
                    'payment.created',
                    $payment,
                    [],
                    $this->auditSnapshots->payment($payment)
                );

                return [
                    'status' => 200,
                    'data' => $this->formatInitiateResponse($order),
                    'payment_id' => $payment->id,
                ];
            }

            try {
                $response = $this->gateway->createPaymentLink([
                'orderCode'   => $order->gateway_order_code,
                'amount'      => (int) round((float) $order->total_amount),
                'description' => substr('DH ' . $order->code, 0, 25),
                'cancelUrl'   => $baseUrl . '/payment/payos/cancel?orderCode=' . $order->gateway_order_code,
                'returnUrl'   => $baseUrl . '/payment/payos/callback?orderCode=' . $order->gateway_order_code,
                'items'       => [[
                    'name'     => 'Don hang ' . $order->code,
                    'quantity' => 1,
                    'price'    => (int) round((float) $order->total_amount),
                ]],
            ]);
            } catch (PaymentGatewayException $e) {
                // Hủy đơn nếu tạo link thất bại - PHASE 1 FIX: Use state transition method
                $this->releaseFailedCheckoutReservations($order);
                throw $e;
            }

            $checkoutUrl = $response['checkoutUrl'] ?? null;
            // PHASE 1 FIX: Use controlled checkout URL setter
            $order->setCheckoutUrl($checkoutUrl);

            app(AuditLogService::class)->record(
                $actor,
                'order.created',
                $order,
                [],
                $this->auditSnapshots->order($order)
            );

            app(AuditLogService::class)->record(
                $actor,
                'payment.created',
                $payment,
                [],
                $this->auditSnapshots->payment($payment)
            );

            $result = $this->formatInitiateResponse($order);

                // Return result in format expected by executeIdempotent()
                return [
                    'status' => 200,
                    'data' => $result,
                    'payment_id' => $payment->id,
                ];
            },
            [
                'path' => request()->path(),
                'method' => 'POST',
                'data' => $validated,
            ]
        );

        // Extract data from idempotent result
        return $idempotentResult['data'];
    }

    private function formatInitiateResponse(Order $order): array
    {
        return [
            'checkout_url' => $order->checkout_url,
            'gateway_order_code' => $order->gateway_order_code,
            'order_number' => $order->code,
            'payment_status' => $order->payment_status,
            'requires_payment' => ! $order->isPaid() && (float) $order->total_amount > 0.0,
            'total_amount' => (float) $order->total_amount,
        ];
    }

    /**
     * PHASE 1: Validate user has valid seat hold for payment
     * Prevents bypass of seat locking mechanism
     */
    private function validateSeatHold(User $user, Showtime $showtime, array $seatIds): SeatHold
    {
        // 1. Normalize and validate seat IDs
        $seatIds = array_values(array_unique(array_map('intval', $seatIds)));
        sort($seatIds);

        if (empty($seatIds)) {
            throw new \RuntimeException('Danh sách ghế trống.');
        }

        // 2. Validate seats belong to showtime's screen with lock
        $seats = Seat::query()
            ->whereIn('id', $seatIds)
            ->where('screen_id', $showtime->screen_id)
            ->lockForUpdate()
            ->get();

        if ($seats->count() !== count($seatIds)) {
            throw new \RuntimeException('Một hoặc nhiều ghế không thuộc phòng chiếu này.');
        }

        // 3. Validate user has valid hold for these seats
        $hold = SeatHold::query()
            ->with(['items' => fn ($query) => $query
                ->where('status', SeatHoldItem::STATUS_ACTIVE)
                ->where('expires_at', '>', now())
                ->lockForUpdate()])
            ->valid()
            ->where('user_id', $user->id)
            ->where('showtime_id', $showtime->id)
            ->lockForUpdate()
            ->first();

        if (!$hold) {
            throw new \RuntimeException('Phiên giữ ghế đã hết hạn. Vui lòng chọn lại ghế.');
        }

        // Use normalizedSeatIds() for backward compatibility with legacy JSON seat_ids
        $heldSeatIds = $hold->normalizedSeatIds();
        sort($heldSeatIds);

        if ($seatIds !== $heldSeatIds) {
            throw new \RuntimeException('Danh sách ghế không khớp với phiên giữ ghế.');
        }

        // 4. Check seats not already booked (double-booking protection)
        $bookedSeatIds = OrderItem::query()
            ->where('item_type', Seat::class)
            ->whereIn('item_id', $seatIds)
            ->whereHas('order', function ($query) use ($showtime) {
                $query->where('showtime_id', $showtime->id)
                    ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED]);
            })
            ->pluck('item_id')
            ->all();

        if (!empty($bookedSeatIds)) {
            throw new \RuntimeException('Một số ghế đã được đặt bởi người dùng khác.');
        }

        return $hold;
    }

    private function checkoutFingerprint(User $user, Showtime $showtime, array $validated, SeatHold $hold): string
    {
        $payload = [
            'user_id' => $user->id,
            'showtime_id' => $showtime->id,
            'seat_hold_id' => $hold->id,
            'items' => collect($validated['items'] ?? [])
                ->map(fn ($item) => [
                    'type' => (string) ($item['type'] ?? ''),
                    'id' => (int) ($item['id'] ?? $item),
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'audience_type' => (string) ($item['audience_type'] ?? 'adult'),
                    'student_card_verified' => (bool) ($item['student_card_verified'] ?? false),
                ])
                ->sortBy([['type', 'asc'], ['id', 'asc']])
                ->values()
                ->all(),
            'voucher_code' => $validated['voucher_code'] ?? null,
            'points_used' => (int) ($validated['points_used'] ?? 0),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function releaseFailedCheckoutReservations(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $productItems = OrderItem::query()
                ->where('order_id', $lockedOrder->id)
                ->where('item_type', Product::class)
                ->lockForUpdate()
                ->get();

            foreach ($productItems as $item) {
                Product::query()->whereKey($item->item_id)->lockForUpdate()->first()?->increment('stock', $item->quantity);
                $item->delete();
            }

            $comboItems = OrderItem::query()
                ->where('order_id', $lockedOrder->id)
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

            $payload = (array) $lockedOrder->payload;
            $seatHoldId = data_get($payload, 'seat_hold_id');
            if (is_numeric($seatHoldId)) {
                SeatHold::query()
                    ->whereKey((int) $seatHoldId)
                    ->where('user_id', (int) data_get($payload, 'seat_hold_user_id', $lockedOrder->user_id))
                    ->where('showtime_id', $lockedOrder->showtime_id)
                    ->delete();
            }
            $pointsUsed = (int) ($payload['points_used'] ?? 0);
            if (($payload['points_reserved'] ?? false) && $pointsUsed > 0) {
                User::query()->whereKey($lockedOrder->user_id)->lockForUpdate()->first()?->increment('loyalty_points', $pointsUsed);
                LoyaltyHistory::query()
                    ->where('order_id', $lockedOrder->id)
                    ->where('user_id', $lockedOrder->user_id)
                    ->where('type', 'redeem')
                    ->delete();
            }

            $voucher = $payload['voucher'] ?? null;
            if (($payload['voucher_reserved'] ?? false) && isset($voucher['id'])) {
                $promotion = Promotion::query()->whereKey($voucher['id'])->lockForUpdate()->first();
                if ($promotion && (int) $promotion->usage_count > 0) {
                    $promotion->decrement('usage_count');
                }

                DB::table('user_promotion')
                    ->where('user_id', $lockedOrder->user_id)
                    ->where('promotion_id', $voucher['id'])
                    ->where('order_id', $lockedOrder->id)
                    ->where('status', 2)
                    ->update([
                        'status' => 1,
                        'order_id' => null,
                        'updated_at' => now(),
                    ]);
            }

            $lockedOrder->forceFill(['checkout_fingerprint' => null])->save();
            $lockedOrder->markFailed();
            $lockedOrder->payment?->markFailed();
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Xử lý webhook PayOS.
     */
    public function handleWebhook(array $rawData): array
    {
        try {
            $webhookData = $this->gateway->verifyWebhook($rawData);
        } catch (PaymentGatewayException) {
            return ['already_processed' => false, 'skipped' => true];
        }

        $orderCode = $webhookData['orderCode']
            ?? $webhookData['order_code']
            ?? Arr::get($rawData, 'data.orderCode')
            ?? Arr::get($rawData, 'data.order_code');

        $status = $this->normalizeGatewayStatus(
            $webhookData['status']
                ?? $webhookData['code']
                ?? Arr::get($rawData, 'data.status')
                ?? Arr::get($rawData, 'code')
                ?? null
        );

        if (! $orderCode) {
            throw new \InvalidArgumentException('Thiếu orderCode trong webhook.');
        }

        if ($this->isSuccessfulGatewayStatus($status)) {
            return $this->fulfillment->finalize((int) $orderCode);
        }

        $this->markAsUnsuccessful((int) $orderCode, $status ?: 'failed');

        return ['already_processed' => false, 'skipped' => true];
    }

    /**
     * Đồng bộ trạng thái đơn hàng với PayOS (polling từ frontend).
     */
    public function syncFromGateway(Order $order): void
    {
        try {
            $info   = $this->gateway->getPaymentInfo($order->gateway_order_code);
            $status = $this->extractGatewayStatus($info);

            if ($this->isSuccessfulGatewayStatus($status)) {
                $this->fulfillment->finalize((int) $order->gateway_order_code);
            } elseif ($this->isUnsuccessfulGatewayStatus($status)) {
                $this->markAsUnsuccessful((int) $order->gateway_order_code, $status);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * SECURITY NOTE:
     * Paid order finalization must never be triggered from browser return URLs.
     * Return URLs are user-controlled and can be manipulated. Payment success is
     * accepted only through verified webhook processing or gateway status sync.
     */
    public function markCancelledFromReturn(Order $order): void
    {
        $this->markAsUnsuccessful((int) $order->gateway_order_code, 'CANCELLED');
    }

    private function extractGatewayStatus(array $info): string
    {
        return $this->normalizeGatewayStatus(
            $info['status']
                ?? Arr::get($info, 'data.status')
                ?? Arr::get($info, 'paymentLinkInfo.status')
                ?? Arr::get($info, 'result.status')
                ?? null
        );
    }

    private function normalizeGatewayStatus(mixed $status): string
    {
        return strtoupper(trim((string) $status));
    }

    private function isSuccessfulGatewayStatus(string $status): bool
    {
        return in_array($status, ['PAID', 'COMPLETED', 'SUCCESS', 'SUCCEEDED', '00'], true);
    }

    private function isUnsuccessfulGatewayStatus(string $status): bool
    {
        return in_array($status, ['CANCELLED', 'CANCELED', 'EXPIRED', 'FAILED', 'FAILURE', 'ERROR'], true);
    }

    private function markAsUnsuccessful(int $gatewayOrderCode, string $gatewayStatus): void
    {
        DB::transaction(function () use ($gatewayOrderCode, $gatewayStatus) {
            $order = Order::query()
                ->where('gateway_order_code', $gatewayOrderCode)
                ->lockForUpdate()
                ->first();

            if (!$order || (int) $order->status === Order::STATUS_CONFIRMED || $order->payment_status === 'paid') {
                return;
            }

            $oldOrderValues = $this->auditSnapshots->order($order);

            // PHASE 1 FIX: Use state transition methods instead of direct forceFill
            $normalizedStatus = $this->normalizeGatewayStatus($gatewayStatus);

            match ($normalizedStatus) {
                'CANCELLED', 'CANCELED' => $order->markCancelled(),
                'EXPIRED' => $order->markExpired(),
                default => $order->markFailed(),
            };

            $payment = Payment::query()
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->first();

            $oldPaymentValues = $payment ? $this->auditSnapshots->payment($payment) : null;

            if ($payment) {
                $failedAt = now();
                // PHASE 1 FIX: Use payment state transitions matching order status
                match ($normalizedStatus) {
                    'CANCELLED', 'CANCELED' => $payment->markCancelled($failedAt),
                    'EXPIRED', 'FAILED', 'FAILURE', 'ERROR' => $payment->markFailed($failedAt),
                    default => $payment->markFailed($failedAt),
                };
            }

            SeatHold::query()
                ->where('user_id', (int) data_get($order->payload, 'seat_hold_user_id', $order->user_id))
                ->where('showtime_id', $order->showtime_id)
                ->delete();

            $orderAction = in_array($normalizedStatus, ['CANCELLED', 'CANCELED'], true)
                ? 'order.cancelled'
                : 'order.payment_failed';

            app(AuditLogService::class)->recordSystemChange(
                $orderAction,
                $order->fresh(),
                $oldOrderValues,
                $this->auditSnapshots->order($order->fresh())
            );

            if ($payment && $oldPaymentValues) {
                $paymentAction = in_array($normalizedStatus, ['CANCELLED', 'CANCELED'], true)
                    ? 'payment.cancelled'
                    : 'payment.failed';

                app(AuditLogService::class)->recordSystemChange(
                    $paymentAction,
                    $payment->fresh(),
                    $oldPaymentValues,
                    $this->auditSnapshots->payment($payment->fresh())
                );
            }
        }, self::TRANSACTION_ATTEMPTS);
    }

    private function generateIdempotencyKey(User $user, ?Showtime $showtime, array $validated): string
    {
        // Normalize payload for consistent UUID generation
        $payload = [
            'user_id' => $user->id,
            'showtime_id' => $showtime?->id,
            'items' => collect($validated['items'] ?? [])
                ->map(fn ($item) => [
                    'type' => (string) ($item['type'] ?? ''),
                    'id' => (int) ($item['id'] ?? $item),
                    'quantity' => (int) ($item['quantity'] ?? 1),
                ])
                ->sortBy([
                    ['type', 'asc'],
                    ['id', 'asc'],
                ])
                ->values()
                ->all(),
            'voucher_code' => $validated['voucher_code'] ?? null,
            'points_used' => (int) ($validated['points_used'] ?? 0),
        ];

        $hash = hash('sha256', 'payment:' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return sprintf(
            '%s-%s-4%s-%s%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            dechex((hexdec(substr($hash, 16, 1)) & 0x3) | 0x8),
            substr($hash, 17, 3),
            substr($hash, 20, 12)
        );
    }

    private function generateOrderNumber(): string
    {
        return 'DH' . strtoupper(Str::random(10));
    }

    private function generateOrderCode(): int
    {
        do {
            $value = (int) (now()->format('ymdHis') . random_int(10, 99));
        } while (Order::query()->where('gateway_order_code', $value)->exists());

        return $value;
    }

}
