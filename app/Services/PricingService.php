<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Promotion;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\User;

class PricingService
{
    public function buildSnapshot(
        User $user,
        Showtime $showtime,
        array $seatRequests,
        array $productRequests,
        ?string $voucherCode,
        int $pointsUsed
    ): array {
        // Seat pricing
        $seatIds = array_map(fn($item) => (int) ($item['id'] ?? $item), $seatRequests); // handle if item is array or scalar
        $seats = Seat::with('seatType')->whereIn('id', $seatIds)->get();
        
        $seatItems = [];
        $seatTotal = 0;
        $basePrice = (float) $showtime->price;

        foreach ($seats as $seat) {
            $unitPrice = $basePrice + (float) ($seat->seatType->surcharge ?? 0);
            $seatTotal += $unitPrice;
            $seatItems[] = [
                'id' => $seat->id,
                'name' => $seat->label ?: ($seat->row . $seat->number),
                'quantity' => 1,
                'price' => $unitPrice,
                'row' => $seat->row,
                'number' => $seat->number,
                'type' => $seat->seatType?->name,
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

        // Promotion
        [$voucherDiscount, $voucherPayload] = $this->applyPromotion($voucherCode, $subtotal);

        // Points (Assuming 1 point = 1 VND if supported, else ignore)
        $pointDiscount = 0;
        if ($pointsUsed > 0 && method_exists($user, 'points') && $user->points >= $pointsUsed) {
            $pointDiscount = $pointsUsed;
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
            ->first();

        if (!$promotion) {
            throw new \RuntimeException('Mã khuyến mãi không hợp lệ hoặc đã hết hạn.');
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
}
