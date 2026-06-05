<?php

namespace App\Http\Controllers;

use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    /**
     * Validate a promotion/voucher code for a given order total
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:50',
            'order_total' => 'required|numeric|min:0',
        ]);

        $promotion = Promotion::active()
            ->valid()
            ->byCode($request->input('code'))
            ->first();

        if (!$promotion) {
            return response()->json([
                'success' => false,
                'message' => 'Mã khuyến mãi không hợp lệ hoặc đã hết hạn.',
            ], 422);
        }

        // Check min_order_value
        $minOrder = (float) ($promotion->min_order_value ?? 0);
        $orderTotal = (float) $request->input('order_total');

        if ($orderTotal < $minOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Đơn hàng tối thiểu ' . number_format($minOrder, 0, ',', '.') . 'đ để áp dụng mã này.',
                'data' => [
                    'min_order_value' => $minOrder,
                    'current_total' => $orderTotal,
                ],
            ], 422);
        }

        // Calculate discount
        $discountAmount = $this->calculateDiscount($promotion, $orderTotal);

        return response()->json([
            'success' => true,
            'message' => 'Mã khuyến mãi hợp lệ!',
            'data' => [
                'code' => $promotion->code,
                'type' => $promotion->type,
                'value' => (float) $promotion->value,
                'discount_amount' => $discountAmount,
                'max_discount' => (float) ($promotion->max_discount ?? 0),
                'min_order_value' => $minOrder,
            ],
        ]);
    }

    /**
     * Calculate discount amount based on promotion type
     *
     * @param Promotion $promotion
     * @param float $orderTotal
     * @return float
     */
    private function calculateDiscount(Promotion $promotion, float $orderTotal): float
    {
        $discount = 0;

        if ($promotion->type === 'percentage') {
            $discount = $orderTotal * ((float) $promotion->value / 100);
            // Apply max_discount cap if set
            $maxDiscount = (float) ($promotion->max_discount ?? 0);
            if ($maxDiscount > 0 && $discount > $maxDiscount) {
                $discount = $maxDiscount;
            }
        } elseif (in_array($promotion->type, ['fixed', 'amount'])) {
            $discount = (float) $promotion->value;
            // Discount shouldn't exceed order total
            if ($discount > $orderTotal) {
                $discount = $orderTotal;
            }
        }

        return round($discount, 0);
    }
}
