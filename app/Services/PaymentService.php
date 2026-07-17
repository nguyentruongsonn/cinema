<?php

namespace App\Services;

use App\Exceptions\PaymentGatewayException;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\SeatHoldItem;
use App\Models\Showtime;
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
        Showtime  $showtime,
        array     $validated,
        string    $baseUrl,
        ?string   $idempotencyKey = null
    ): array {
        // Auto-generate idempotency key if not provided (for backward compatibility)
        if ($idempotencyKey === null) {
            $idempotencyKey = $this->generateIdempotencyKey($user, $showtime, $validated);
        }

        // Use robust IdempotencyKey::executeIdempotent() wrapper
        // This provides: unique constraint protection, payload verification,
        // retry logic, concurrent request blocking, and response caching
        $idempotentResult = IdempotencyKey::executeIdempotent(
            $idempotencyKey,
            function ($record) use ($user, $showtime, $validated, $baseUrl) {
                // Core payment initiation logic (executeIdempotent handles transaction)
                $items = collect($validated['items'] ?? []);

                $seatRequests    = $items->where('type', 'seat')->all();
                $productRequests = $items->where('type', 'product')->all();

                // PHASE 1: Validate seat hold BEFORE creating order
                $seatIds = collect($seatRequests)
                    ->map(fn($seat) => (int)($seat['id'] ?? $seat))
                    ->filter()
                    ->values()
                    ->all();

                $seatHold = !empty($seatIds)
                    ? $this->validateSeatHold($user, $showtime, $seatIds)
                    : null;
                $checkoutFingerprint = $seatHold
                    ? $this->checkoutFingerprint($user, $showtime, $validated, $seatHold)
                    : null;

                if ($checkoutFingerprint) {
                    $existingOrder = Order::query()
                        ->where('checkout_fingerprint', $checkoutFingerprint)
                        ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED])
                        ->with('payment')
                        ->first();

                    if ($existingOrder?->checkout_url) {
                        return [
                            'status' => 200,
                            'data' => [
                                'checkout_url' => $existingOrder->checkout_url,
                                'gateway_order_code' => $existingOrder->gateway_order_code,
                                'order_number' => $existingOrder->code,
                            ],
                            'payment_id' => $existingOrder->payment?->id,
                        ];
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

            // PHASE 1 FIX: Use factory method to create order safely
            $order = Order::createPending([
                'code'               => $this->generateOrderNumber(),
                'gateway_order_code' => $this->generateOrderCode(),
                'payment_provider'   => 'payos',
                'user_id'            => $user->id,
                'showtime_id'        => $showtime->id,
                'checkout_fingerprint' => $checkoutFingerprint,
                'total_amount'       => $pricing['final_amount'],
                'payload'            => [
                    'subtotal'         => $pricing['subtotal'],
                    'discount_amount'  => $pricing['discount_amount'],
                    'voucher_discount' => $pricing['voucher_discount'],
                    'point_discount'   => $pricing['point_discount'],
                    'points_used'      => $pricing['points_used'],
                    'voucher'          => $pricing['voucher'],
                    'seats'            => $pricing['seats'],
                    'products'         => $pricing['products'],
                ],
                'expired_at'         => now()->addMinutes(15),
            ]);

            // PHASE 2: Create payment record for audit trail
            $payment = Payment::createPending([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'method' => 'payos',
                'gateway_order_code' => $order->gateway_order_code,
                'amount' => $order->total_amount,
                'payload' => [
                    'showtime_id' => $showtime->id,
                    'seat_ids' => $seatIds,
                    'created_at' => now()->toISOString(),
                ],
            ]);

            try {
                $response = $this->gateway->createPaymentLink([
                'orderCode'   => $order->gateway_order_code,
                'amount'      => (int) round($order->total_amount),
                'description' => substr('DH ' . $order->code, 0, 25),
                'cancelUrl'   => $baseUrl . '/payment/payos/cancel?orderCode=' . $order->gateway_order_code,
                'returnUrl'   => $baseUrl . '/payment/payos/callback?orderCode=' . $order->gateway_order_code,
                'items'       => [[
                    'name'     => 'Don hang ' . $order->code,
                    'quantity' => 1,
                    'price'    => (int) round($order->total_amount),
                ]],
            ]);
            } catch (PaymentGatewayException $e) {
                // Hủy đơn nếu tạo link thất bại - PHASE 1 FIX: Use state transition method
                $order->markFailed();
                throw $e;
            }

            $checkoutUrl = $response['checkoutUrl'] ?? null;
            // PHASE 1 FIX: Use controlled checkout URL setter
            $order->setCheckoutUrl($checkoutUrl);

            app(AuditLogService::class)->record(
                $user,
                'order.created',
                $order,
                [],
                $this->auditSnapshots->order($order)
            );

            app(AuditLogService::class)->record(
                $user,
                'payment.created',
                $payment,
                [],
                $this->auditSnapshots->payment($payment)
            );

            $result = [
                'checkout_url'       => $checkoutUrl,
                'gateway_order_code' => $order->gateway_order_code,
                'order_number'       => $order->code,
            ];

                // Return result in format expected by executeIdempotent()
                return [
                    'status' => 200,
                    'data' => $result,
                    'payment_id' => $payment->id,
                ];
            },
            [
                'path' => request()->path() ?? '/payment/initiate',
                'method' => 'POST',
                'data' => $validated,
            ]
        );

        // Extract data from idempotent result
        return $idempotentResult['data'];
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
                ])
                ->sortBy([['type', 'asc'], ['id', 'asc']])
                ->values()
                ->all(),
            'voucher_code' => $validated['voucher_code'] ?? null,
            'points_used' => (int) ($validated['points_used'] ?? 0),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
                ->where('user_id', $order->user_id)
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

    private function generateIdempotencyKey(User $user, Showtime $showtime, array $validated): string
    {
        // Normalize payload for consistent UUID generation
        $payload = [
            'user_id' => $user->id,
            'showtime_id' => $showtime->id,
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
