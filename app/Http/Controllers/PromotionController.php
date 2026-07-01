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

    public function registered(Request $request): JsonResponse
    {
        try {
            $promotions = $this->promotionService
                ->getUserRegisteredPromotions($request->user())
                ->map(fn ($promotion) => $this->formatPromotion($promotion))
                ->values();

            return $this->successResponse($promotions, 'Danh sách voucher đã đăng ký.');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to load registered promotions: ' . $e->getMessage(), 500);
        }
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:50'],
        ]);

        try {
            $result = $this->promotionService->registerPromotionForUser(
                $request->user(),
                (string) $request->input('code')
            );

            if (!$result['success']) {
                return $this->errorResponse($result['message'], 404);
            }

            return $this->successResponse(
                $this->formatPromotion($result['promotion']),
                $result['message']
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to register promotion: ' . $e->getMessage(), 500);
        }
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
            $result = $this->promotionService->validatePromotion($code, $orderTotal, $request->user());

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

            return $this->successResponse(array_merge(
                $this->formatPromotion($promotion),
                [
                    'valid' => true,
                    'discount_amount' => $result['discount_amount'],
                ]
            ), 'Mã khuyến mãi hợp lệ!');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to validate promotion: ' . $e->getMessage(), 500);
        }
    }

    private function formatPromotion($promotion): array
    {
        return [
            'id' => $promotion->id,
            'code' => $promotion->code,
            'name' => $promotion->name ?? null,
            'description' => $promotion->description ?? null,
            'discount_type' => $promotion->discount_type,
            'discount_value' => (float) $promotion->discount_value,
            'discount_amount' => 0,
            'max_discount_amount' => (float) ($promotion->max_discount_amount ?? 0),
            'min_order_value' => (float) ($promotion->min_order_value ?? 0),
            'start_date' => $promotion->start_date ?? null,
            'end_date' => $promotion->end_date ?? null,
            'registered_at' => $promotion->pivot->created_at ?? null,
            'usage_count' => (int) ($promotion->pivot->usage_count ?? 0),
        ];
    }
}
