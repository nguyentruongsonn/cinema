<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Movie;
use App\Models\Showtime;
use App\Models\Theater;
use App\Models\Banner;
use App\Traits\ApiResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    use ApiResponse;

    private const HOME_MOVIE_LIMIT = 8;
    private const MOVIE_OPTION_LIMIT = 100;
    private const AVAILABLE_DATE_LIMIT = 7;
    private const HOME_CACHE_KEY = 'home:data:v2';
    private const HOME_CACHE_TTL_SECONDS = 300;

    private const MOVIE_COLUMNS = [
        'id',
        'title',
        'slug',
        'description',
        'poster_url',
        'poster_path',
        'banner_path',
        'backdrops',
        'trailer_url',
        'age_rating',
        'duration',
        'release_date',
        'is_hot',
    ];

    public function index(): View
    {
        $latestPosts = \App\Models\Post::query()
            ->with('author:id,name')
            ->published()
            ->latest('published_at')
            ->take(3)
            ->get();

        return view('users.home', compact('latestPosts'));
    }

    public function data(): JsonResponse
    {
        $data = Cache::remember(
            self::HOME_CACHE_KEY,
            self::HOME_CACHE_TTL_SECONDS,
            fn (): array => $this->composeHomeData()
        );

        return $this->successResponse($data, 'Home data loaded successfully');
    }

    public static function flushCachedData(): void
    {
        Cache::forget(self::HOME_CACHE_KEY);
    }

    private function composeHomeData(): array
    {
        $featuredBanners = Banner::query()
            ->with('images')
            ->active()
            ->ordered()
            ->limit(5)
            ->get();

        $featuredSlides = $featuredBanners
            ->flatMap(fn (Banner $banner) => $banner->images->map(fn ($image) => [
                'id' => $banner->id . '-' . $image->id,
                'title' => strip_tags((string) $banner->title),
                'description' => strip_tags((string) $banner->description),
                'image_url' => asset('storage/' . $image->image_path),
                'link_url' => $banner->link_url,
            ])->all())
            ->take(5)
            ->values();

        $featuredBanner = $featuredSlides->first();

        $featuredMovie = Movie::query()
            ->select(self::MOVIE_COLUMNS)
            ->with('categories:id,name')
            ->active()
            ->where(function ($query) {
                    $query->where('is_hot', true)
                        ->orWhereHas('showtimes', function ($showtimeQuery) {
                        $showtimeQuery->where('status', true)
                            ->where('scheduled_at', '>=', now());
                    });
            })
            ->orderByDesc('is_hot')
            ->latest('release_date')
            ->first();

        $nowShowingMovies = Movie::query()
            ->select(self::MOVIE_COLUMNS)
            ->with('categories:id,name')
            ->nowShowing()
            ->latest('release_date')
            ->limit(self::HOME_MOVIE_LIMIT)
            ->get();

        $upcomingMovies = Movie::query()
            ->select(self::MOVIE_COLUMNS)
            ->with('categories:id,name')
            ->upcoming()
            ->orderBy('release_date')
            ->limit(self::HOME_MOVIE_LIMIT)
            ->get();

        $movieOptions = Movie::query()
            ->active()
            ->whereHas('showtimes', function ($query) {
                $query->where('status', true)
                    ->where('scheduled_at', '>=', now());
            })
            ->orderBy('title')
            ->limit(self::MOVIE_OPTION_LIMIT)
            ->get(['id', 'title'])
            ->map(fn (Movie $movie) => [
                'id' => $movie->id,
                'title' => $movie->title,
            ])
            ->values();

        $cinemaOptions = Theater::query()
            ->select('theaters.id', 'theaters.name', 'theaters.branch_id', 'branches.name as branch_name')
            ->leftJoin('branches', 'branches.id', '=', 'theaters.branch_id')
            ->active()
            ->orderBy('branches.name')
            ->orderBy('theaters.name')
            ->limit(100)
            ->get()
            ->map(fn (Theater $theater) => [
                'id' => $theater->id,
                'name' => $theater->name,
                'city' => (string) ($theater->branch_name ?? ''),
            ])
            ->values();

        $availableDates = Showtime::query()
            ->available()
            ->upcoming()
            ->selectRaw('DATE(scheduled_at) as show_date')
            ->distinct()
            ->orderByRaw('show_date asc')
            ->limit(self::AVAILABLE_DATE_LIMIT)
            ->pluck('show_date')
            ->map(function ($date) {
                $parsedDate = Carbon::parse($date);

                return [
                    'value' => $parsedDate->toDateString(),
                    'label' => $parsedDate->toDateString(),
                ];
            })
            ->values();

        if (! $featuredMovie) {
            $featuredMovie = $nowShowingMovies->first() ?? $upcomingMovies->first();
        }

        return [
            'featured_banners' => $featuredSlides,
            'featured_banner' => $featuredBanner,
            'featured_movie' => $this->transformMovie($featuredMovie),
            'now_showing_movies' => $nowShowingMovies
                ->map(fn (Movie $movie) => $this->transformMovie($movie))
                ->values(),
            'upcoming_movies' => $upcomingMovies
                ->map(fn (Movie $movie) => $this->transformMovie($movie))
                ->values(),
            'movie_options' => $movieOptions,
            'cinema_options' => $cinemaOptions,
            'available_dates' => $availableDates,
        ];
    }

    private function transformMovie(?Movie $movie): ?array
    {
        if (! $movie) {
            return null;
        }

        $backdrops = $this->safeBackdrops($movie);
        $posterUrl = $this->safeUrl($movie->poster_display_url);
        $backdrops = collect($backdrops)
            ->map(fn ($url): ?string => $this->safeUrl(is_string($url) ? $url : null))
            ->reject(fn (?string $url): bool => $url === null)
            ->values()
            ->all();

        return [
            'id' => $movie->id,
            'title' => strip_tags((string) $movie->title),
            'slug' => $movie->slug,
            'description' => strip_tags((string) $movie->description),
            'poster_url' => $posterUrl,
            'poster_display_url' => $posterUrl,
            'backdrop_url' => ! empty($backdrops) ? $backdrops[0] : $posterUrl,
            'backdrops' => $backdrops,
            'trailer_url' => $this->safeUrl($movie->trailer_url),
            'age_rating' => $movie->age_rating,
            'duration' => $movie->duration,
            'release_date' => optional($movie->release_date)->format('Y-m-d'),
            'categories' => $movie->categories
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => strip_tags((string) $category->name),
                ])
                ->values(),
        ];
    }

    private function safeBackdrops(Movie $movie): array
    {
        if (! $movie->backdrops) {
            return [];
        }

        return $movie->backdrops;
    }

    private function safeUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $url = trim($url);

        if (str_starts_with($url, '/')) {
            return $url;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }
}
