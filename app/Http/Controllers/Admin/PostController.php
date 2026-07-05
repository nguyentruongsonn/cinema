<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    /**
     * Display posts management page
     */
    public function index()
    {
        return view('admin.posts.index');
    }

    /**
     * Get posts list (API)
     */
    public function list(Request $request)
    {
        $query = Post::with('author:id,name');

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        // Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $isPublished = $request->status === '1';
            $query->where('is_published', $isPublished);
        }

        // Paginate
        $posts = $query->orderBy('created_at', 'desc')
                       ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $posts->items(),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
                'from' => $posts->firstItem(),
                'to' => $posts->lastItem(),
            ]
        ]);
    }

    /**
     * Store new post
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:posts,slug',
            'content' => 'required|string',
            'excerpt' => 'nullable|string',
            'category' => 'required|in:news,blog,announcement,event,promotion',
            'featured_image' => 'nullable|image|max:5120',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        // Auto-generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        // Handle image upload
        if ($request->hasFile('featured_image')) {
            $path = $request->file('featured_image')->store('posts', 'public');
            $validated['featured_image'] = $path;
        }

        // Set author
        $validated['author_id'] = auth()->id();

        // Set published_at if is_published is true
        if ($validated['is_published'] && empty($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        $post = Post::create($validated);

        return response()->json([
            'message' => 'Tạo bài viết thành công',
            'data' => $post->load('author:id,name')
        ], 201);
    }

    /**
     * Update post
     */
    public function update(Request $request, Post $post)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:posts,slug,' . $post->id,
            'content' => 'required|string',
            'excerpt' => 'nullable|string',
            'category' => 'required|in:news,blog,announcement,event,promotion',
            'featured_image' => 'nullable|image|max:5120',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        // Handle image upload
        if ($request->hasFile('featured_image')) {
            // Delete old image
            if ($post->featured_image) {
                Storage::disk('public')->delete($post->featured_image);
            }
            $path = $request->file('featured_image')->store('posts', 'public');
            $validated['featured_image'] = $path;
        }

        // Set published_at if is_published is true and not set
        if ($validated['is_published'] && empty($validated['published_at']) && !$post->is_published) {
            $validated['published_at'] = now();
        }

        $post->update($validated);

        return response()->json([
            'message' => 'Cập nhật bài viết thành công',
            'data' => $post->load('author:id,name')
        ]);
    }

    /**
     * Delete post
     */
    public function destroy(Post $post)
    {
        // Delete featured image
        if ($post->featured_image) {
            Storage::disk('public')->delete($post->featured_image);
        }

        $post->delete();

        return response()->json([
            'message' => 'Xóa bài viết thành công'
        ]);
    }

    /**
     * Toggle publish status
     */
    public function togglePublish(Post $post)
    {
        $post->is_published = !$post->is_published;
        
        if ($post->is_published && !$post->published_at) {
            $post->published_at = now();
        }
        
        $post->save();

        return response()->json([
            'message' => 'Cập nhật trạng thái thành công',
            'data' => $post
        ]);
    }

    /**
     * Get categories for filter
     */
    public function categories()
    {
        return response()->json([
            'data' => ['news', 'blog', 'announcement', 'event', 'promotion']
        ]);
    }
}