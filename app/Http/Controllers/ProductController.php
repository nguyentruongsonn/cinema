<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * Get all active products (combos, food, drinks) for booking
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $products = Product::query()
            ->active()
            ->where('stock', '>', 0)
            ->orderBy('type')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'type',
                'price',
                'stock',
                'image_url',
                'description',
            ]);

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }
}
