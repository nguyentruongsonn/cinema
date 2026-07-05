<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    /**
     * Display banners management page
     */
    public function index()
    {
        return view('admin.banners.index');
    }

    /**
     * Get banners list (API)
     */
    public function list(Request $request)
    {
        $query = Banner::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by position
        if ($request->filled('position') && $request->position !== 'all') {
            $query->where('position', $request->position);
        }

        // Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $isActive = $request->status === '1';
            $query->where('is_active', $isActive);
        }

        // Paginate
        $banners = $query->orderBy('display_order', 'asc')
                         ->orderBy('created_at', 'desc')
                         ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $banners->items(),
            'pagination' => [
                'current_page' => $banners->currentPage(),
                'last_page' => $banners->lastPage(),
                'per_page' => $banners->perPage(),
                'total' => $banners->total(),
                'from' => $banners->firstItem(),
                'to' => $banners->lastItem(),
            ]
        ]);
    }

    /**
     * Store new banner
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image_path' => 'required|image|max:5120',
            'link_url' => 'nullable|url',
            'position' => 'required|in:home_slider,sidebar,popup,top_bar,footer',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        // Handle image upload
        if ($request->hasFile('image_path')) {
            $path = $request->file('image_path')->store('banners', 'public');
            $validated['image_path'] = $path;
        }

        $validated['display_order'] = $validated['display_order'] ?? 0;

        $banner = Banner::create($validated);

        return response()->json([
            'message' => 'Tạo banner thành công',
            'data' => $banner
        ], 201);
    }

    /**
     * Update banner
     */
    public function update(Request $request, Banner $banner)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image_path' => 'nullable|image|max:5120',
            'link_url' => 'nullable|url',
            'position' => 'required|in:home_slider,sidebar,popup,top_bar,footer',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        // Handle image upload
        if ($request->hasFile('image_path')) {
            // Delete old image
            if ($banner->image_path) {
                Storage::disk('public')->delete($banner->image_path);
            }
            $path = $request->file('image_path')->store('banners', 'public');
            $validated['image_path'] = $path;
        }

        $banner->update($validated);

        return response()->json([
            'message' => 'Cập nhật banner thành công',
            'data' => $banner
        ]);
    }

    /**
     * Delete banner
     */
    public function destroy(Banner $banner)
    {
        // Delete image
        if ($banner->image_path) {
            Storage::disk('public')->delete($banner->image_path);
        }

        $banner->delete();

        return response()->json([
            'message' => 'Xóa banner thành công'
        ]);
    }

    /**
     * Toggle active status
     */
    public function toggleActive(Banner $banner)
    {
        $banner->is_active = !$banner->is_active;
        $banner->save();

        return response()->json([
            'message' => 'Cập nhật trạng thái thành công',
            'data' => $banner
        ]);
    }

    /**
     * Get positions for filter
     */
    public function positions()
    {
        return response()->json([
            'data' => ['home_slider', 'sidebar', 'popup', 'top_bar', 'footer']
        ]);
    }
}