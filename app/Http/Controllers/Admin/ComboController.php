<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Combo;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ComboController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Combo::with('comboItems.product');

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status === 'active');
        }

        $combos = $query->orderBy('created_at', 'desc')->paginate(10);

        // Thêm available_stock cho mỗi combo
        $combos->getCollection()->transform(function ($combo) {
            $combo->available_stock = $combo->available_stock;
            return $combo;
        });

        return $this->paginatedResponse($combos, 'Combos retrieved successfully');
    }

    public function show(Combo $combo)
    {
        $combo->load('comboItems.product');
        $combo->available_stock = $combo->available_stock;
        return $this->successResponse($combo, 'Combo retrieved successfully');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:combos,name',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:5120',
            'status' => 'boolean',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            // Upload image nếu có
            if ($request->hasFile('image_file')) {
                $file = $request->file('image_file');
                $filename = time() . '_' . Str::slug($validated['name']) . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('images/products'), $filename);
                $validated['image_url'] = '/images/products/' . $filename;
            }

            // Tạo combo
            $combo = Combo::create([
                'name' => $validated['name'],
                'price' => $validated['price'],
                'description' => $validated['description'] ?? null,
                'image_url' => $validated['image_url'] ?? null,
                'status' => $validated['status'] ?? true,
            ]);

            // Tạo combo items
            foreach ($validated['items'] as $item) {
                $combo->comboItems()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            // Tính và cập nhật original_price
            $this->updateOriginalPrice($combo);

            DB::commit();

            $combo->load('comboItems.product');
            $combo->available_stock = $combo->available_stock;

            return $this->successResponse($combo, 'Combo created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to create combo: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, Combo $combo)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:combos,name,' . $combo->id,
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:5120',
            'status' => 'boolean',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            // Upload image nếu có
            if ($request->hasFile('image_file')) {
                $file = $request->file('image_file');
                $filename = time() . '_' . Str::slug($validated['name']) . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('images/products'), $filename);
                $validated['image_url'] = '/images/products/' . $filename;
            }

            // Cập nhật combo
            $combo->update([
                'name' => $validated['name'],
                'price' => $validated['price'],
                'description' => $validated['description'] ?? null,
                'image_url' => $validated['image_url'] ?? $combo->image_url,
                'status' => $validated['status'] ?? $combo->status,
            ]);

            // Xóa combo items cũ
            $combo->comboItems()->delete();

            // Tạo combo items mới
            foreach ($validated['items'] as $item) {
                $combo->comboItems()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            // Tính và cập nhật original_price
            $this->updateOriginalPrice($combo);

            DB::commit();

            $combo->load('comboItems.product');
            $combo->available_stock = $combo->available_stock;

            return $this->successResponse($combo, 'Combo updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to update combo: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(Combo $combo)
    {
        $combo->delete(); // Cascade xóa combo_items
        return $this->successResponse(null, 'Combo deleted successfully');
    }

    public function toggleActive(Combo $combo)
    {
        $combo->update(['status' => !$combo->status]);
        $combo->load('comboItems.product');
        return $this->successResponse($combo, 'Combo status updated successfully');
    }

    // Get available products for combo (food + drink only)
    public function getAvailableProducts()
    {
        $products = Product::where('status', 1)
            ->whereIn('type', ['food', 'drink'])
            ->where('stock', '>', 0)
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'price', 'stock']);

        return $this->successResponse($products, 'Available products retrieved successfully');
    }

    /**
     * Tính và cập nhật giá gốc của combo dựa trên tổng giá sản phẩm
     */
    private function updateOriginalPrice(Combo $combo)
    {
        $originalPrice = DB::table('combo_items')
            ->join('products', 'combo_items.product_id', '=', 'products.id')
            ->where('combo_items.combo_id', $combo->id)
            ->selectRaw('SUM(products.price * combo_items.quantity) as total')
            ->value('total');

        $combo->update(['original_price' => $originalPrice ?? 0]);
    }
}
