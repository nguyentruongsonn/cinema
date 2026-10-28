<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Controllers\HomeController;
use App\Models\Movie;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class MovieService
{
    private const ALLOWED_SORT_COLUMNS = [
        'release_date',
        'title',
        'duration',
        'created_at',
        'is_hot',
    ];

    private const ALLOWED_SORT_DIRECTIONS = ['asc', 'desc'];

    private const MAX_PER_PAGE = 100;

    private const DEFAULT_PER_PAGE = 12;

    private const MAX_SEARCH_LENGTH = 100;

    private const CREATE_FIELDS = [
        'title',
        'original_title',
        'slug',
        'description',
        'director',
        'cast',
        'duration',
        'release_date',
        'end_date',
        'poster_url',
        'poster_path',
        'banner_path',
        'trailer_url',
        'age_rating',
        'surcharge',
        'backdrops',
        'status',
        'is_hot',
        'is_hidden',
        'manual_override_status',
    ];

    private const UPDATE_FIELDS = [
        'title',
        'original_title',
        'slug',
        'description',
        'director',
        'cast',
        'duration',
        'release_date',
        'end_date',
        'poster_url',
        'poster_path',
        'banner_path',
        'trailer_url',
        'age_rating',
        'surcharge',
        'backdrops',
        'status',
        'is_hot',
        'is_hidden',
        'manual_override_status',
    ];

    public function getMovies(array $filters): LengthAwarePaginator
    {
        $query = Movie::query()
            ->with(['categories'])
            ->when(($filters['status'] ?? 'active') !== 'all', function ($query) use ($filters) {
                return match ($filters['status'] ?? 'active') {
                    'now_showing' => $query->nowShowing(),
                    'upcoming' => $query->upcoming(),
                    'hidden' => $query->where('is_hidden', 1),
                    'published' => $query->where('status', 1),
                    'draft' => $query->where('status', 0),
                    default => $query->active(),
                };
            })
            ->when($filters['q'] ?? null, function ($query, string $keyword): void {
                $keyword = trim(mb_substr($keyword, 0, self::MAX_SEARCH_LENGTH));
                $escaped = addcslashes($keyword, '\\%_');

                $query->where(function ($q) use ($escaped): void {
                    $q->where('title', 'like', "%{$escaped}%")
                        ->orWhere('original_title', 'like', "%{$escaped}%")
                        ->orWhere('director', 'like', "%{$escaped}%")
                        ->orWhere('cast', 'like', "%{$escaped}%");
                });
            })
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->byCategory($categoryId))
            ->when(array_key_exists('is_hot', $filters), fn ($query) => $query->where('is_hot', $filters['is_hot']))
            ->when($filters['age_rating'] ?? null, fn ($query, $ageRating) => $query->where('age_rating', $ageRating))
            ->when($filters['release_from'] ?? null, fn ($query, $date) => $query->whereDate('release_date', '>=', $date))
            ->when($filters['release_to'] ?? null, fn ($query, $date) => $query->whereDate('release_date', '<=', $date));

        $sortBy = in_array($filters['sort_by'] ?? null, self::ALLOWED_SORT_COLUMNS, true)
            ? $filters['sort_by']
            : 'release_date';

        $sortDir = in_array(strtolower($filters['sort_dir'] ?? ''), self::ALLOWED_SORT_DIRECTIONS, true)
            ? strtolower($filters['sort_dir'])
            : 'desc';

        $perPage = min(
            max((int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE), 1),
            self::MAX_PER_PAGE
        );

        $movies = $query
            ->orderBy($sortBy, $sortDir)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        Log::debug('Movies retrieved', [
            'count' => $movies->count(),
            'total' => $movies->total(),
        ]);

        return $movies;
    }

    public function getNowShowingMovies(array $filters): LengthAwarePaginator
    {
        $filters['status'] = 'now_showing';
        return $this->getMovies($filters);
    }

    public function getUpcomingMovies(array $filters): LengthAwarePaginator
    {
        $filters['status'] = 'upcoming';
        return $this->getMovies($filters);
    }

    public function searchMovies(string $keyword, array $filters = []): LengthAwarePaginator
    {
        $filters['q'] = $keyword;
        return $this->getMovies($filters);
    }

    public function createMovie(array $data): Movie
    {
        $movie = DB::transaction(function () use ($data): Movie {
            $categoryIds = $data['category_ids'] ?? [];

            $payload = Arr::only($data, self::CREATE_FIELDS);

            $movie = Movie::create($payload);

            if (!empty($categoryIds)) {
                $movie->categories()->sync($categoryIds);
            }

            return $movie->load('categories');
        });

        $this->flushMovieCaches();

        Log::info('Movie created successfully', [
            'movie_id' => $movie->id,
            'title' => $movie->title,
        ]);

        return $movie;
    }

    public function getMovie(string|int $idOrSlug): Movie
    {
        $cacheKey = is_numeric($idOrSlug)
            ? "movie:id:{$idOrSlug}"
            : "movie:slug:{$idOrSlug}";

        return Cache::remember($cacheKey, 1800, function () use ($idOrSlug): Movie {
            $query = Movie::with(['categories', 'showtimes.screen.theater']);

            $movie = is_numeric($idOrSlug)
                ? $query->whereKey((int) $idOrSlug)->firstOrFail()
                : $query->where('slug', $idOrSlug)->firstOrFail();

            Log::debug('Movie retrieved from database for cache', [
                'movie_id' => $movie->id,
            ]);

            return $movie;
        });
    }

    public function updateMovie(int $id, array $data): Movie
    {
        $oldSlug = null;

        $movie = DB::transaction(function () use ($id, $data, &$oldSlug): Movie {
            $movie = Movie::findOrFail($id);
            $oldSlug = $movie->slug;

            $categoryIds = $data['category_ids'] ?? null;

            $payload = Arr::only($data, self::UPDATE_FIELDS);

            $movie->update($payload);

            if (is_array($categoryIds)) {
                $movie->categories()->sync($categoryIds);
            }

            return $movie->load('categories');
        });

        Cache::forget("movie:id:{$id}");
        Cache::forget("movie:slug:{$movie->slug}");
        if (isset($oldSlug) && $movie->slug !== $oldSlug) {
            Cache::forget("movie:slug:{$oldSlug}");
        }
        $this->flushMovieCaches();

        Log::info('Movie updated successfully', [
            'movie_id' => $movie->id,
            'title' => $movie->title,
        ]);

        return $movie;
    }

    public function deleteMovie(int $id): bool
    {
        $movie = Movie::findOrFail($id);

        if ($movie->showtimes()->exists()) {
            throw ValidationException::withMessages([
                'movie' => 'Cannot delete a movie that has scheduled showtimes. Consider hiding or archiving it instead.',
            ]);
        }

        $slug = $movie->slug;

        DB::transaction(function () use ($movie): void {
            $movie->categories()->detach();
            $movie->delete();
        });

        Cache::forget("movie:id:{$id}");
        Cache::forget("movie:slug:{$slug}");
        $this->flushMovieCaches();

        Log::info('Movie deleted successfully', [
            'movie_id' => $id,
            'title' => $movie->title,
        ]);

        return true;
    }

    public function getMovieStatistics(): array
    {
        return Cache::remember('movies:statistics', 300, function (): array {
            $stats = [
                'total' => Movie::query()->count(),
                'active' => Movie::query()->active()->count(),
                'now_showing' => Movie::query()->nowShowing()->count(),
                'upcoming' => Movie::query()->upcoming()->count(),
                'hot' => Movie::query()->where('is_hot', 1)->count(),
            ];

            Log::debug('Movie statistics retrieved from database', $stats);

            return $stats;
        });
    }

    private function flushMovieCaches(): void
    {
        Cache::forget('movies:statistics');
        HomeController::flushCachedData();
    }
}
