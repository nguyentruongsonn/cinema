<?php

declare(strict_types=1);

namespace App\Services;

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
        Showtime $showtime,
        array $seatRequests,
        array $productRequests,
        ?string $voucherCode,
        int $pointsUsed
    ): array {
        // Load relations
        $showtime->load(['format', 'movie', 'screen.theater']);

        // Seat pricing with dynamic ticket pricing
        $seatIds = array_map(fn($item) => (int) ($item['id'] ?? $item), $seatRequests);
        $seats = Seat::with('seatType')->whereIn('id', $seatIds)->get();

        $seatItems = [];
        $seatTotal = 0;

        // Get format name and movie surcharge
        $formatName = $showtime->format?->name ?? '2D';
        $movieSurcharge = (int) ($showtime->movie?->surcharge ?? 0);
        $scheduledAt = $showtime->scheduled_at;

        foreach ($seats as $seat) {
            // Check if this is a double/couple seat
            $seatTypeName = $seat->seatType?->name ?? '';
            $seatTypeSlug = $seat->seatType?->slug ?? '';
            $isDoubleSeat = $this->isDoubleSeat($seatTypeName, $seatTypeSlug);

            // Calculate dynamic price using TicketPricingService
            // Default customer type is 'adult' (can be extended later to accept user preferences)
            $pricingResult = $this->ticketPricingService->calculate(
                format: $formatName,
                scheduledAt: $scheduledAt,
                customerType: 'adult',
                isDoubleSeat: $isDoubleSeat,
                movieSurcharge: $movieSurcharge,
                extraHolidays: [],
                formatSurcharge: (int) ($showtime->format?->surcharge ?? 0),
                seatSurcharge: (int) ($seat->seatType?->surcharge ?? 0),
                theaterPricing: $showtime->screen?->theater?->pricing_profile
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
                'type' => $seat->seatType?->name,
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
        if ($pointsUsed > 0 && $user && $user->loyalty_points >= $pointsUsed) {
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
