<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ProductService
{
    /**
     * Get active, in-stock products for booking flow.
     */
    public function getBookingProducts(Request $request): Collection
    {
        $query = Product::query()
            ->active()
            ->inStock();

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('q')) {
            $search = $request->input('q');

            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query
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
    }
}
