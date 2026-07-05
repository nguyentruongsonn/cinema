<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromotionController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Promotion::query();

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('code', 'like', '%' . $request->search . '%')
                  ->orWhere('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status === 'active');
        }

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        $promotions = $query->orderBy('created_at', 'desc')->paginate(10);

        return $this->paginatedResponse($promotions, 'Promotions retrieved successfully');
    }

    public function getCategories()
    {
        $categories = Promotion::whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values();

        return $this->successResponse($categories, 'Categories retrieved successfully');
    }

    public function show(Promotion $promotion)
    {
        return $this->successResponse($promotion, 'Promotion retrieved successfully');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:promotions,code',
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'description' => 'nullable|string',
            'discount_type' => 'required|in:percent,fixed',
            'discount_value' => 'required|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'usage_limit' => 'nullable|integer|min:1',
            'daily_usage_limit' => 'nullable|integer|min:1',
            'status' => 'boolean',
        ]);

        $validated['usage_count'] = 0;
        $validated['code'] = strtoupper($validated['code']);

        $promotion = Promotion::create($validated);

        return $this->successResponse($promotion, 'Promotion created successfully', 201);
    }

    public function update(Request $request, Promotion $promotion)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:promotions,code,' . $promotion->id,
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:50',
            'description' => 'nullable|string',
            'discount_type' => 'required|in:percent,fixed',
            'discount_value' => 'required|numeric|min:0',
            'min_order_value' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'usage_limit' => 'nullable|integer|min:1',
            'daily_usage_limit' => 'nullable|integer|min:1',
            'status' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);

        $promotion->update($validated);

        return $this->successResponse($promotion, 'Promotion updated successfully');
    }

    public function destroy(Promotion $promotion)
    {
        // Kiểm tra xem mã có đang được sử dụng không
        if ($promotion->usage_count > 0) {
            return $this->errorResponse('Cannot delete promotion that has been used', 422);
        }

        $promotion->delete();
        return $this->successResponse(null, 'Promotion deleted successfully');
    }

    public function toggleActive(Promotion $promotion)
    {
        $promotion->update(['status' => !$promotion->status]);
        return $this->successResponse($promotion, 'Promotion status updated successfully');
    }

    public function resetUsageCount(Promotion $promotion)
    {
        $promotion->update(['usage_count' => 0]);
        return $this->successResponse($promotion, 'Usage count reset successfully');
    }
}