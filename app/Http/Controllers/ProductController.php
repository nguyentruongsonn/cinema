<?php

namespace App\Http\Controllers;

use App\Services\ProductService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $request->validate([
            'type' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $products = $this->productService->getBookingProducts($request);

            return $this->successResponse($products, 'Products retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve products: ' . $e->getMessage(), 500);
        }
    }
}
