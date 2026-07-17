<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\PublicFileStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PostController extends Controller
{
    public function __construct(
        private readonly PublicFileStorageService $publicFiles
    ) {
    }

    /**
     * Display posts management page
     */
    public function index(): View
    {
        $this->authorize('viewAny', Post::class);

        return view('admin.posts.index');
    }

    /**
     * Get posts list (API)
     */
    public function list(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Post::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'in:all,news,blog,announcement,event,promotion'],
            'status' => ['nullable', 'string', 'in:all,published,draft'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 15;
        $search = isset($validated['search']) ? trim($validated['search']) : null;

        $query = Post::with('author:id,name');

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $category = $validated['category'] ?? null;
        if ($category !== null && $category !== 'all') {
            $query->where('category', $category);
        }

        $status = $validated['status'] ?? null;
        if ($status !== null && $status !== 'all') {
            $query->where('is_published', $status === 'published');
        }

        $posts = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $posts->items(),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
                'from' => $posts->firstItem(),
                'to' => $posts->lastItem(),
            ],
        ]);
    }

    /**
     * Store new post.
     *
     * SECURITY: Rich content should be sanitized on output with an allowlist sanitizer.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Post::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:posts,slug',
            'content' => 'required|string|max:65535',
            'excerpt' => 'nullable|string|max:1000',
            'category' => 'required|in:news,blog,announcement,event,promotion',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'is_published' => 'nullable|boolean',
            'published_at' => 'nullable|date',
        ]);

        $imagePath = null;

        try {
            $post = DB::transaction(function () use (&$validated, $request, &$imagePath) {
                $validated['slug'] = $this->uniqueSlug($validated['slug'] ?? $validated['title']);

                if ($request->hasFile('featured_image')) {
                    $imagePath = $this->publicFiles->store($request->file('featured_image'), 'posts');
                    $validated['featured_image'] = $imagePath;
                }

                $validated['author_id'] = Auth::id();

                if (($validated['is_published'] ?? false) && empty($validated['published_at'])) {
                    $validated['published_at'] = now();
                }

                return Post::create($validated);
            });

            return response()->json([
                'message' => 'Tạo bài viết thành công',
                'data' => $post->load('author:id,name'),
            ], 201);
        } catch (\Throwable $e) {
            if ($imagePath) {
                $this->publicFiles->deleteMany([$imagePath]);
            }

            Log::error('Failed to create post', [
                'user_id' => Auth::id(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Không thể tạo bài viết. Vui lòng thử lại.',
            ], 500);
        }
    }

    /**
     * Update post.
     *
     * SECURITY: Rich content should be sanitized on output with an allowlist sanitizer.
     */
    public function update(Request $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:posts,slug,' . $post->id,
            'content' => 'required|string|max:65535',
            'excerpt' => 'nullable|string|max:1000',
            'category' => 'required|in:news,blog,announcement,event,promotion',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'is_published' => 'nullable|boolean',
            'published_at' => 'nullable|date',
        ]);

        $newImagePath = null;
        $oldImagePath = $post->featured_image;

        try {
            DB::transaction(function () use (&$validated, $request, $post, &$newImagePath) {
                if (empty($validated['slug'])) {
                    $validated['slug'] = $this->uniqueSlug($validated['title'], $post->id);
                }

                if ($request->hasFile('featured_image')) {
                    $newImagePath = $this->publicFiles->store($request->file('featured_image'), 'posts');
                    $validated['featured_image'] = $newImagePath;
                }

                if (
                    ($validated['is_published'] ?? false)
                    && empty($validated['published_at'])
                    && ! $post->is_published
                ) {
                    $validated['published_at'] = now();
                }

                $post->update($validated);
            });

            if ($newImagePath && $oldImagePath && $oldImagePath !== $newImagePath) {
                $this->publicFiles->deleteMany([$oldImagePath]);
            }

            return response()->json([
                'message' => 'Cập nhật bài viết thành công',
                'data' => $post->fresh(['author:id,name']),
            ]);
        } catch (\Throwable $e) {
            if ($newImagePath) {
                $this->publicFiles->deleteMany([$newImagePath]);
            }

            Log::error('Failed to update post', [
                'post_id' => $post->id,
                'user_id' => Auth::id(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Không thể cập nhật bài viết. Vui lòng thử lại.',
            ], 500);
        }
    }

    /**
     * Delete post.
     */
    public function destroy(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $imagePath = $post->featured_image;

        try {
            DB::transaction(function () use ($post) {
                Post::whereKey($post->getKey())->delete();
            });

            if ($imagePath) {
                $this->publicFiles->deleteMany([$imagePath]);
            }

            return response()->json([
                'message' => 'Xóa bài viết thành công',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to delete post', [
                'post_id' => $post->id,
                'user_id' => Auth::id(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Không thể xóa bài viết. Vui lòng thử lại.',
            ], 500);
        }
    }

    /**
     * Toggle publish status.
     */
    public function togglePublish(Post $post): JsonResponse
    {
        $this->authorize('publish', $post);

        try {
            DB::transaction(function () use ($post) {
                $post->is_published = ! $post->is_published;

                if ($post->is_published && ! $post->published_at) {
                    $post->published_at = now();
                }

                $post->save();
            });

            return response()->json([
                'message' => 'Cập nhật trạng thái thành công',
                'data' => $post->fresh(['author:id,name']),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to toggle post publish status', [
                'post_id' => $post->id,
                'user_id' => Auth::id(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Không thể cập nhật trạng thái bài viết. Vui lòng thử lại.',
            ], 500);
        }
    }

    /**
     * Get categories for filter.
     */
    public function categories(): JsonResponse
    {
        $this->authorize('viewAny', Post::class);

        return response()->json([
            'data' => ['news', 'blog', 'announcement', 'event', 'promotion'],
        ]);
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($value);

        if ($baseSlug === '') {
            $baseSlug = 'post';
        }

        $slug = $baseSlug;
        $counter = 1;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;

            if ($counter > 100) {
                throw new \RuntimeException('Unable to generate unique post slug.');
            }
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $query = Post::query()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->where('id', '<>', $ignoreId);
        }

        return $query->exists();
    }
}
