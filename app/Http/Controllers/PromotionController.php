<?php

namespace App\Http\Controllers;

use App\Http\Resources\PromotionResource;
use App\Services\PromotionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PromotionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PromotionService $promotionService
    ) {
    }

    public function registered(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        try {
            $promotions = $this->promotionService->getUserRegisteredPromotions($user);

            return $this->successResponse(
                PromotionResource::collection($promotions),
                'Danh sách voucher đã đăng ký.'
            );
        } catch (\Throwable $e) {
            Log::error('Failed to load registered promotions', [
                'exception' => $e,
                'user_id' => $user->id,
            ]);

            return $this->errorResponse('Không thể tải danh sách voucher', 500);
        }
    }

    public function register(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        $code = mb_strtoupper(trim($validated['code']));

        try {
            $result = $this->promotionService->registerPromotionForUser($user, $code);

            if (!$result['success']) {
                return $this->errorResponse(
                    $result['message'],
                    $this->mapRegistrationFailureStatus($result)
                );
            }

            return $this->successResponse(
                new PromotionResource($result['promotion']),
                $result['message']
            );
        } catch (\Throwable $e) {
            Log::error('Failed to register promotion', [
                'exception' => $e,
                'code' => $code,
                'user_id' => $user->id,
            ]);

            return $this->errorResponse('Không thể đăng ký voucher', 500);
        }
    }

    /**
     * Validate a promotion/voucher code for a given order total.
     *
     * IMPORTANT: This is a preview/estimate endpoint only. It accepts a client-provided
     * order_total to show the user an estimated discount. Final checkout/payment flows
     * must recalculate totals and promotion eligibility server-side and must not trust
     * this endpoint's response as proof of the final payable amount.
     *
     * RESTful endpoint: GET /api/v1/promotions/{code}/validate?order_total=xxx
     */
    public function validate(Request $request, string $code): JsonResponse
    {
        $validated = $request->validate([
            'order_total' => ['required', 'numeric', 'gt:0'],
        ]);

        $code = mb_strtoupper(trim($code));

        if (!preg_match('/^[A-Z0-9_-]{1,50}$/', $code)) {
            return $this->errorResponse('Mã khuyến mãi không hợp lệ.', 422);
        }

        // Client-supplied total is used for preview only; final checkout must recalculate server-side.
        $orderTotal = (float) $validated['order_total'];

        try {
            $result = $this->promotionService->validatePromotion($code, $orderTotal, $request->user());

            if (!$result['valid']) {
                $statusCode = $result['promotion'] ? 422 : 404;
                $data = [];

                if (isset($result['min_order_value'])) {
                    $data = [
                        'min_order_value' => (string) $result['min_order_value'],
                        'current_total' => (string) $orderTotal,
                    ];
                }

                return $this->errorResponse(
                    $result['error'],
                    $statusCode,
                    $data
                );
            }

            return $this->successResponse(
                array_merge(
                    (new PromotionResource($result['promotion']))->toArray($request),
                    [
                        'valid' => true,
                        'discount_amount' => (string) $result['discount_amount'],
                    ]
                ),
                'Mã khuyến mãi hợp lệ!'
            );
        } catch (\Throwable $e) {
            Log::error('Failed to validate promotion', [
                'exception' => $e,
                'code' => $code,
                'order_total' => $orderTotal,
                'user_id' => $request->user()?->id,
            ]);

            return $this->errorResponse('Không thể xác thực voucher', 500);
        }
    }

    /**
     * Map registration failure reasons to appropriate HTTP status codes.
     *
     * PromotionService currently returns only a localized message. Keep the legacy 404 fallback
     * until the service is updated to expose machine-readable reason codes.
     */
    private function mapRegistrationFailureStatus(array $result): int
    {
        return match ($result['reason'] ?? null) {
            'expired', 'inactive', 'not_eligible', 'usage_limit_reached', 'already_used' => 422,
            'already_registered' => 409,
            'not_found' => 404,
            default => 404,
        };
    }
}
