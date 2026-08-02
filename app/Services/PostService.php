<?php

namespace App\Services;

use App\Models\Movie;
use App\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Collection;

class PostService
{
    private const TRAILERS_CACHE_KEY = 'posts:sidebar:trailers:v1';
    private const TRAILERS_CACHE_TTL = 600;

    /**
     * Get the hero featured post if on first page without search/category filters.
     */
    public function getFeaturedPost(?string $category, string $search, int $page): ?Post
    {
        if (! empty($category) || $search !== '' || $page > 1) {
            return null;
        }

        return Post::query()
            ->with('author:id,name')
            ->published()
            ->latest('published_at')
            ->first();
    }

    /**
     * Get paginated list of published posts with category and search filtering.
     */
    public function getFilteredPosts(?Post $featuredPost, ?string $category, string $search, int $perPage = 10): LengthAwarePaginator
    {
        $query = Post::query()
            ->with('author:id,name')
            ->published();

        if ($featuredPost) {
            $query->where('id', '!=', $featuredPost->id);
        }

        if (in_array($category, ['news', 'blog', 'announcement', 'event', 'promotion'], true)) {
            $query->category($category);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        return $query->latest('published_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get latest movies for sidebar trailer widget.
     */
    public function getSidebarTrailers(int $limit = 3): Collection
    {
        return Cache::remember(self::TRAILERS_CACHE_KEY, self::TRAILERS_CACHE_TTL, fn () =>
            Movie::query()
                ->select(['id', 'title', 'slug', 'poster_url', 'trailer_url', 'duration', 'age_rating', 'release_date'])
                ->active()
                ->latest('release_date')
                ->take($limit)
                ->get()
        );
    }

    /**
     * Get popular topic tags for sidebar cloud widget matching mockup UI.
     */
    public function getPopularTags(): array
    {
        return [
            'Oscar 2025',
            'Marvel Phase 6',
            'Phim Độc Lập',
            'Review Phim',
            'Phỏng Vấn',
        ];
    }

    /**
     * Get related posts in the same category.
     */
    public function getRelatedPosts(Post $post, int $limit = 3): Collection
    {
        $related = Post::query()
            ->with('author:id,name')
            ->published()
            ->where('id', '!=', $post->id)
            ->where('category', $post->category)
            ->latest('published_at')
            ->take($limit)
            ->get();

        if ($related->count() < $limit) {
            $extra = Post::query()
                ->with('author:id,name')
                ->published()
                ->where('id', '!=', $post->id)
                ->whereNotIn('id', $related->pluck('id'))
                ->latest('published_at')
                ->take($limit - $related->count())
                ->get();

            $related = $related->merge($extra);
        }

        return $related;
    }

    /**
     * Safely increment view count for a post.
     */
    public function recordView(Post $post): void
    {
        $post->incrementViews();
    }
}
