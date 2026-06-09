<?php

namespace App\Http\Controllers;

use App\Services\PromotionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PromotionService $promotionService
    ) {
    }

    /**
     * Validate a promotion/voucher code for a given order total
     *
     * RESTful endpoint: GET /api/v1/promotions/{code}/validate?order_total=xxx
     */
    public function validate(Request $request, string $code): JsonResponse
    {
        $request->validate([
            'order_total' => ['required', 'numeric', 'min:0'],
        ]);

        $orderTotal = (float) $request->input('order_total');

        try {
            $result = $this->promotionService->validatePromotion($code, $orderTotal);

            if (!$result['valid']) {
                $statusCode = $result['promotion'] ? 422 : 404;
                $data = [];

                if (isset($result['min_order_value'])) {
                    $data = [
                        'min_order_value' => $result['min_order_value'],
                        'current_total' => $orderTotal,
                    ];
                }

                return $this->errorResponse(
                    $result['error'],
                    $statusCode,
                    $data
                );
            }

            $promotion = $result['promotion'];

            return $this->successResponse([
                'valid' => true,
                'code' => $promotion->code,
                'discount_type' => $promotion->discount_type,
                'discount_value' => (float) $promotion->discount_value,
                'discount_amount' => $result['discount_amount'],
                'max_discount_amount' => (float) ($promotion->max_discount_amount ?? 0),
                'min_order_value' => (float) ($promotion->min_order_value ?? 0),
            ], 'Mã khuyến mãi hợp lệ!');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to validate promotion: ' . $e->getMessage(), 500);
        }
    }
}
