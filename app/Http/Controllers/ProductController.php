<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductSummaryResource;
use App\Services\ProductService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ProductService $productService
    ) {
    }

    /**
     * Get all active, in-stock products for booking.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        try {
            $products = $this->productService->getBookingProducts($filters);

            return $this->successResponse(
                ProductSummaryResource::collection($products)->response()->getData(true),
                'Products retrieved successfully'
            );
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve booking products.', [
                'exception' => $e,
            ]);

            return $this->errorResponse('Failed to retrieve products.', 500);
        }
    }
}
