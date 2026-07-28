<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\BannerResource;
use App\Http\Resources\PostResource;
use App\Models\Banner;
use App\Models\Post;
use App\Services\HtmlContentSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function __construct(
        private readonly HtmlContentSanitizer $htmlSanitizer
    ) {
    }

    public function banners(Request $request): JsonResponse
    {
        $banners = Banner::query()
            ->with('images')
            ->active()
            ->ordered()
            ->limit(5)
            ->get();

        return response()->json([
            'data' => BannerResource::collection($banners)->resolve(),
        ]);
    }

    public function posts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string', 'in:news,blog,announcement,event,promotion'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $posts = Post::query()
            ->with('author:id,name')
            ->published()
            ->when($validated['category'] ?? null, fn ($query, $category) => $query->category($category))
            ->latest('published_at')
            ->paginate($validated['per_page'] ?? 12);

        return response()->json([
            'data' => PostResource::collection($posts->getCollection())->resolve(),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    public function post(Post $post): JsonResponse
    {
        abort_unless($post->isPubliclyVisible(), 404);
        $post->incrementViews();

        return response()->json([
            'data' => (new PostResource($post->fresh('author:id,name')))->resolve(),
        ]);
    }

    public function postsPage(Request $request): View
    {
        $posts = Post::query()
            ->with('author:id,name')
            ->published()
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        return view('users.posts.index', compact('posts'));
    }

    public function postPage(Post $post): View
    {
        abort_unless($post->isPubliclyVisible(), 404);
        $post->incrementViews();

        return view('users.posts.show', [
            'post' => $post->fresh('author:id,name'),
            'safeContent' => $this->htmlSanitizer->sanitize((string) $post->content),
        ]);
    }
}
