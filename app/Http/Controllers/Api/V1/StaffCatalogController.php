<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ComboResource;
use App\Http\Resources\ProductResource;
use App\Models\Combo;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class StaffCatalogController extends Controller
{
    use ApiResponse;

    public function concessionCatalog(): JsonResponse
    {
        $products = Product::query()
            ->where('status', 1)
            ->where('stock', '>', 0)
            ->orderBy('name')
            ->limit(100)
            ->get();

        $combos = Combo::query()
            ->where('status', 1)
            ->with('comboItems.product')
            ->orderBy('name')
            ->limit(100)
            ->get();

        return $this->successResponse([
            'products' => ProductResource::collection($products)->resolve(),
            'combos' => ComboResource::collection($combos)->resolve(),
        ], 'Concession catalog retrieved');
    }
}
