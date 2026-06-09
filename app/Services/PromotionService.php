<?php

namespace App\Services;

use App\Models\Promotion;

class PromotionService
{
    /**
     * Validate a promotion code and calculate discount for a given order total.
     *
     * @param string $code
     * @param float $orderTotal
     * @return array{valid: bool, promotion: ?Promotion, discount_amount: float, error?: string, min_order_value?: float}
     */
    public function validatePromotion(string $code, float $orderTotal): array
    {
        $promotion = Promotion::active()
            ->valid()
            ->byCode($code)
            ->first();

        if (!$promotion) {
            return [
                'valid' => false,
                'promotion' => null,
                'discount_amount' => 0,
                'error' => 'Promotion not found',
            ];
        }

        // Check minimum order value requirement
        $minOrderValue = (float) ($promotion->min_order_value ?? 0);

        if ($orderTotal < $minOrderValue) {
            return [
                'valid' => false,
                'promotion' => $promotion,
                'discount_amount' => 0,
                'error' => 'Đơn hàng tối thiểu ' . number_format($minOrderValue, 0, ',', '.') . 'đ để áp dụng mã này.',
                'min_order_value' => $minOrderValue,
            ];
        }

        // Calculate discount
        $discountAmount = $this->calculateDiscount($promotion, $orderTotal);

        return [
            'valid' => true,
            'promotion' => $promotion,
            'discount_amount' => $discountAmount,
        ];
    }

    /**
     * Calculate discount amount based on promotion type.
     *
     * @param Promotion $promotion
     * @param float $orderTotal
     * @return float
     */
    public function calculateDiscount(Promotion $promotion, float $orderTotal): float
    {
        $discount = 0;

        if ($promotion->discount_type === 'percentage') {
            $discount = $orderTotal * ((float) $promotion->discount_value / 100);

            // Apply max_discount_amount cap if set
            $maxDiscount = (float) ($promotion->max_discount_amount ?? 0);
            if ($maxDiscount > 0 && $discount > $maxDiscount) {
                $discount = $maxDiscount;
            }
        } elseif (in_array($promotion->discount_type, ['fixed_amount', 'amount'])) {
            $discount = (float) $promotion->discount_value;

            // Discount shouldn't exceed order total
            if ($discount > $orderTotal) {
                $discount = $orderTotal;
            }
        }

        return round($discount, 0);
    }
}
