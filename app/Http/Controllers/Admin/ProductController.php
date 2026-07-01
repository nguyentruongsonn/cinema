<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status === 'active');
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(10);

        return $this->paginatedResponse($products, 'Products retrieved successfully');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:food,drink,combo',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'image_url' => 'nullable|url',
            'status' => 'boolean',
        ]);

        $product = Product::create($validated);

        return $this->successResponse($product, 'Product created successfully', 201);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:food,drink,combo',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'image_url' => 'nullable|url',
            'status' => 'boolean',
        ]);

        $product->update($validated);

        return $this->successResponse($product, 'Product updated successfully');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return $this->successResponse(null, 'Product deleted successfully');
    }

    public function toggleActive(Product $product)
    {
        $product->update(['status' => !$product->status]);
        return $this->successResponse($product, 'Product status updated successfully');
    }
}
