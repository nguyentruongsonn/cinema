<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Combo;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;

class PricingService
{
    public function __construct(
        private readonly TicketPricingService $ticketPricingService
    ) {
    }
    public function buildSnapshot(
        User $user,
        ?Showtime $showtime,
        array $seatRequests,
        array $productRequests,
        ?string $voucherCode,
        int $pointsUsed
    ): array {
        // Load relations if showtime exists
        $showtime?->load(['format', 'movie', 'screen.theater']);

        // Seat pricing with dynamic ticket pricing
        $seatIds = array_map(fn($item) => (int) ($item['id'] ?? $item), $seatRequests);
        $seatRequestById = collect($seatRequests)->keyBy(fn ($item) => (int) ($item['id'] ?? $item));
        $seats = !empty($seatIds) ? Seat::with('seatType')->whereIn('id', $seatIds)->get() : collect([]);

        $seatItems = [];
        $seatTotal = 0;

        // Get format name and movie surcharge if showtime exists
        $formatName = (string) data_get($showtime, 'format.name', '2D');
        $movieSurcharge = (int) data_get($showtime, 'movie.surcharge', 0);
        $scheduledAt = $showtime === null ? now() : $showtime->scheduled_at;

        foreach ($seats as $seat) {
            // Check if this is a double/couple seat
            $seatTypeName = (string) data_get($seat, 'seatType.name', '');
            $seatTypeSlug = (string) data_get($seat, 'seatType.slug', '');
            $isDoubleSeat = $this->isDoubleSeat($seatTypeName, $seatTypeSlug);

            $seatRequest = $seatRequestById->get((int) $seat->id, []);
            $audienceType = $seatRequest['audience_type'] ?? 'adult';

            // Calculate each ticket independently; the client never supplies the final price.
            $pricingResult = $this->ticketPricingService->calculate(
                format: $formatName,
                scheduledAt: $scheduledAt,
                customerType: $audienceType,
                isDoubleSeat: $isDoubleSeat,
                movieSurcharge: $movieSurcharge,
                extraHolidays: [],
                formatSurcharge: (int) data_get($showtime, 'format.surcharge', 0),
                seatSurcharge: (int) data_get($seat, 'seatType.surcharge', 0),
                theaterPricing: data_get($showtime, 'screen.theater.pricing_profile')

            );

            $unitPrice = $pricingResult['total_price'];
            $seatTotal += $unitPrice;

            $seatItems[] = [
                'id' => $seat->id,
                'name' => $seat->label ?: ($seat->row . $seat->number),
                'quantity' => 1,
                'price' => $unitPrice,
                'row' => $seat->row,
                'number' => $seat->number,
                'type' => data_get($seat, 'seatType.name'),
                'audience_type' => $audienceType,
                'student_card_verified' => (bool) ($seatRequest['student_card_verified'] ?? false),
                'pricing_details' => [
                    'base_price' => $pricingResult['base_price'],
                    'surcharges' => $pricingResult['surcharges'],
                    'day_type' => $pricingResult['day_type'],
                    'time_slot' => $pricingResult['time_slot'],
                ],
            ];
        }

        // Product pricing
        $productItems = [];
        $productTotal = 0;

        if (!empty($productRequests)) {
            $requestedProducts = collect($productRequests)
                ->filter(fn (array $product): bool => ($product['type'] ?? 'product') === 'product')
                ->mapWithKeys(fn (array $product) => [(int) $product['id'] => (int) $product['quantity']])
                ->filter(fn (int $quantity) => $quantity > 0);

            if ($requestedProducts->isNotEmpty()) {
                $products = Product::whereIn('id', $requestedProducts->keys()->all())
                    ->where('status', 1)
                    ->get();

                foreach ($products as $product) {
                    $quantity = (int) $requestedProducts->get($product->id);
                    $unitPrice = (float) $product->price;
                    $lineTotal = $unitPrice * $quantity;
                    $productTotal += $lineTotal;

                    $productItems[] = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'quantity' => $quantity,
                        'price' => $unitPrice,
                        'type' => $product->type,
                        'image_url' => $product->image_url,
                        'item_type' => 'product',
                    ];
                }
            }

            $requestedCombos = collect($productRequests)
                ->filter(fn (array $product): bool => ($product['type'] ?? null) === 'combo')
                ->mapWithKeys(fn (array $product) => [(int) $product['id'] => (int) $product['quantity']])
                ->filter(fn (int $quantity) => $quantity > 0);

            if ($requestedCombos->isNotEmpty()) {
                $combos = Combo::query()
                    ->active()
                    ->whereIn('id', $requestedCombos->keys()->all())
                    ->with('comboItems.product')
                    ->get();

                foreach ($combos as $combo) {
                    $quantity = (int) $requestedCombos->get($combo->id);
                    if ($combo->available_stock < $quantity) {
                        throw new \RuntimeException("Combo {$combo->name} không còn đủ số lượng.", 409);
                    }

                    $unitPrice = (float) $combo->price;
                    $lineTotal = $unitPrice * $quantity;
                    $productTotal += $lineTotal;

                    $productItems[] = [
                        'id' => $combo->id,
                        'name' => $combo->name,
                        'quantity' => $quantity,
                        'price' => $unitPrice,
                        'type' => 'combo',
                        'image_url' => $combo->image_url,
                        'item_type' => 'combo',
                        'items' => $combo->comboItems->map(fn ($item): array => [
                            'product_id' => $item->product_id,
                            'product_name' => $item->product?->name,
                            'quantity' => $item->quantity,
                        ])->values()->all(),
                    ];
                }
            }
        }

        $subtotal = $seatTotal + $productTotal;

        // Promotion: chỉ tính giảm giá khi frontend gửi voucher_code đã được người dùng bấm "Áp dụng".
        // Voucher cũng phải tồn tại trong Kho Voucher của chính user để tránh nhập mã trực tiếp khi chưa đăng ký.
        [$voucherDiscount, $voucherPayload] = $this->applyPromotion($voucherCode, $subtotal, $user);

        // Points (1 point = 1000 VND)
        $pointDiscount = 0;
        if ($pointsUsed > 0 && $user->loyalty_points >= $pointsUsed) {
            $pointDiscount = $pointsUsed * 1000;
        } else {
            $pointsUsed = 0;
        }

        $totalDiscount = $voucherDiscount + $pointDiscount;
        $finalAmount = max(0, $subtotal - $totalDiscount);

        return [
            'subtotal' => $subtotal,
            'seat_total' => $seatTotal,
            'product_total' => $productTotal,
            'discount_amount' => $totalDiscount,
            'voucher_discount' => $voucherDiscount,
            'point_discount' => $pointDiscount,
            'points_used' => $pointsUsed,
            'voucher' => $voucherPayload,
            'seats' => $seatItems,
            'products' => $productItems,
            'final_amount' => $finalAmount,
        ];
    }

    private function applyPromotion(?string $promotionCode, float $subtotal, User $user): array
    {
        $promotionCode = trim((string) $promotionCode);

        if ($promotionCode === '') {
            return [0, null];
        }

        $promotion = Promotion::query()
            ->active()
            ->valid()
            ->byCode($promotionCode)
            ->whereHas('users', function ($query) use ($user) {
                $query->where('users.id', $user->id)
                    ->where('user_promotion.status', 1)
                    ->whereNull('user_promotion.used_at')
                    ->where(function ($pivotQuery) {
                        $pivotQuery->whereNull('user_promotion.usage_count')
                            ->orWhere('user_promotion.usage_count', 0);
                    });
            })
            ->first();

        if (!$promotion) {
            throw new \RuntimeException('Mã khuyến mãi chưa được đăng ký trong Kho Voucher, không hợp lệ hoặc đã hết hạn.');
        }

        $minOrderValue = (float) ($promotion->min_order_value ?? 0);

        if ($subtotal < $minOrderValue) {
            throw new \RuntimeException('Đơn hàng chưa đạt giá trị tối thiểu để áp dụng mã khuyến mãi.');
        }

        $discountAmount = $this->calculatePromotionDiscount($promotion, $subtotal);

        return [
            $discountAmount,
            [
                'id' => $promotion->id,
                'code' => $promotion->code,
                'type' => $promotion->discount_type,
                'value' => (float) $promotion->discount_value,
                'discount_amount' => $discountAmount,
            ],
        ];
    }

    private function calculatePromotionDiscount(Promotion $promotion, float $subtotal): float
    {
        if ($promotion->discount_type === 'percentage') {
            $discount = $subtotal * ((float) $promotion->discount_value / 100);
            $maxDiscount = (float) ($promotion->max_discount_amount ?? 0);

            if ($maxDiscount > 0) {
                $discount = min($discount, $maxDiscount);
            }

            return round(min($discount, $subtotal), 0);
        }

        if (in_array($promotion->discount_type, ['fixed_amount', 'fixed', 'amount'], true)) {
            return round(min((float) $promotion->discount_value, $subtotal), 0);
        }

        return 0;
    }

    /**
     * Check if a seat is a double/couple seat based on name or slug
     */
    private function isDoubleSeat(string $name, string $slug): bool
    {
        $nameLower = mb_strtolower($name);
        $slugLower = mb_strtolower($slug);

        $doubleKeywords = ['double', 'couple', 'đôi', 'sweetbox', 'sweet-box'];

        foreach ($doubleKeywords as $keyword) {
            if (str_contains($nameLower, $keyword) || str_contains($slugLower, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
