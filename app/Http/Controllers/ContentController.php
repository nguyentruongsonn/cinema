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

    public function posts(Request $request, \App\Services\PostService $service): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string', 'in:news,blog,announcement,event,promotion'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $category = $validated['category'] ?? null;
        $search = trim((string) ($validated['search'] ?? ''));
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);

        $featuredPost = $service->getFeaturedPost();
        $posts = $service->getFilteredPosts($category, $search, $perPage);

        return response()->json([
            'data' => PostResource::collection($posts->getCollection())->resolve(),
            'featured_post' => $featuredPost ? (new PostResource($featuredPost))->resolve() : null,
            'sidebar_trailers' => $service->getSidebarTrailers(3),
            'popular_tags' => $service->getPopularTags(),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    public function post(Post $post, \App\Services\PostService $service): JsonResponse
    {
        abort_unless($post->isPubliclyVisible(), 404);
        $post->incrementViews();

        $relatedPosts = $service->getRelatedPosts($post, 3);

        return response()->json([
            'data' => (new PostResource($post->fresh('author:id,name')))->resolve(),
            'related_posts' => PostResource::collection($relatedPosts)->resolve(),
        ]);
    }

    public function postsPage(): View
    {
        return view('users.posts.index');
    }

    public function postPage(Post $post, \App\Services\PostService $service): View
    {
        abort_unless($post->isPubliclyVisible(), 404);

        $service->recordView($post);
        $relatedPosts = $service->getRelatedPosts($post, 3);
        $safeContent = $this->htmlSanitizer->sanitize((string) $post->content);

        return view('users.posts.show', compact('post', 'relatedPosts', 'safeContent'));
    }
}
