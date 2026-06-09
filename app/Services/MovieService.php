<?php

namespace App\Services;

use App\Models\Movie;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * MovieService - Business Logic Layer for Movie Management
 *
 * Handles all movie-related business logic following Clean Architecture principles.
 * Separates business logic from controllers for better maintainability and testability.
 */
class MovieService
{
    /**
     * Get paginated list of movies with filters and sorting
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getMovies(array $filters): LengthAwarePaginator
    {
        try {
            $query = Movie::query()
                ->with(['categories'])
                ->when(($filters['status'] ?? 'active') !== 'all', function ($query) use ($filters) {
                    return match ($filters['status'] ?? 'active') {
                        'now_showing' => $query->nowShowing(),
                        'upcoming' => $query->upcoming(),
                        'hidden' => $query->where('is_hidden', 1),
                        default => $query->active(),
                    };
                })
                ->when($filters['q'] ?? null, function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->where('title', 'like', "%{$keyword}%")
                            ->orWhere('original_title', 'like', "%{$keyword}%")
                            ->orWhere('director', 'like', "%{$keyword}%")
                            ->orWhere('cast', 'like', "%{$keyword}%")
                            ->orWhere('description', 'like', "%{$keyword}%");
                    });
                })
                ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->byCategory($categoryId))
                ->when(array_key_exists('is_hot', $filters), fn ($query) => $query->where('is_hot', $filters['is_hot']))
                ->when($filters['age_rating'] ?? null, fn ($query, $ageRating) => $query->where('age_rating', $ageRating))
                ->when($filters['release_from'] ?? null, fn ($query, $date) => $query->whereDate('release_date', '>=', $date))
                ->when($filters['release_to'] ?? null, fn ($query, $date) => $query->whereDate('release_date', '<=', $date));

            $sortBy = $filters['sort_by'] ?? 'release_date';
            $sortDir = $filters['sort_dir'] ?? 'desc';
            $perPage = $filters['per_page'] ?? 12;

            $movies = $query
                ->orderBy($sortBy, $sortDir)
                ->orderBy('id', 'desc')
                ->paginate($perPage)
                ->withQueryString();

            Log::info('Movies retrieved', [
                'count' => $movies->count(),
                'total' => $movies->total(),
                'filters' => $filters
            ]);

            return $movies;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve movies', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get movies currently showing
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getNowShowingMovies(array $filters): LengthAwarePaginator
    {
        $filters['status'] = 'now_showing';
        return $this->getMovies($filters);
    }

    /**
     * Get upcoming movies
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getUpcomingMovies(array $filters): LengthAwarePaginator
    {
        $filters['status'] = 'upcoming';
        return $this->getMovies($filters);
    }

    /**
     * Search movies by keyword
     *
     * @param string $keyword
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function searchMovies(string $keyword, array $filters = []): LengthAwarePaginator
    {
        $filters['q'] = $keyword;
        return $this->getMovies($filters);
    }

    /**
     * Create a new movie
     *
     * @param array $data
     * @return Movie
     */
    public function createMovie(array $data): Movie
    {
        try {
            $movie = DB::transaction(function () use ($data) {
                // Extract category IDs
                $categoryIds = $data['category_ids'] ?? [];
                unset($data['category_ids']);

                // Generate slug if not provided
                $data['slug'] = $data['slug'] ?? Str::slug($data['title']);

                // Create movie
                $movie = Movie::create($data);

                // Sync categories
                if (!empty($categoryIds)) {
                    $movie->categories()->sync($categoryIds);
                }

                return $movie->load('categories');
            });

            // Invalidate statistics cache
            Cache::forget('movies:statistics');

            Log::info('Movie created successfully', [
                'movie_id' => $movie->id,
                'title' => $movie->title
            ]);

            return $movie;
        } catch (\Exception $e) {
            Log::error('Failed to create movie', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get movie by ID or slug (cached for 30 minutes)
     *
     * @param string|int $idOrSlug
     * @return Movie
     */
    public function getMovie($idOrSlug): Movie
    {
        try {
            $cacheKey = is_numeric($idOrSlug)
                ? "movie:id:{$idOrSlug}"
                : "movie:slug:{$idOrSlug}";

            return Cache::remember($cacheKey, 1800, function () use ($idOrSlug) {
                $movie = Movie::with(['categories', 'showtimes.screen.theater'])
                    ->where(function ($query) use ($idOrSlug) {
                        $query->where('id', $idOrSlug)
                            ->orWhere('slug', $idOrSlug);
                    })
                    ->firstOrFail();

                Log::info('Movie retrieved (cached)', ['movie_id' => $movie->id]);

                return $movie;
            });
        } catch (\Exception $e) {
            Log::warning('Movie not found', ['identifier' => $idOrSlug]);
            throw $e;
        }
    }

    /**
     * Update movie
     *
     * @param int $id
     * @param array $data
     * @return Movie
     */
    public function updateMovie(int $id, array $data): Movie
    {
        try {
            $movie = DB::transaction(function () use ($id, $data) {
                $movie = Movie::findOrFail($id);
                $oldSlug = $movie->slug;

                // Extract category IDs
                $categoryIds = $data['category_ids'] ?? null;
                unset($data['category_ids']);

                // Auto-generate slug if title changed but slug not provided
                if (isset($data['title']) && empty($data['slug'])) {
                    $data['slug'] = Str::slug($data['title']);
                }

                // Update movie
                $movie->update($data);

                // Sync categories if provided
                if (is_array($categoryIds)) {
                    $movie->categories()->sync($categoryIds);
                }

                // Invalidate caches
                Cache::forget("movie:id:{$id}");
                Cache::forget("movie:slug:{$oldSlug}");
                if ($movie->slug !== $oldSlug) {
                    Cache::forget("movie:slug:{$movie->slug}");
                }
                Cache::forget('movies:statistics');

                return $movie->load('categories');
            });

            Log::info('Movie updated successfully', [
                'movie_id' => $movie->id,
                'title' => $movie->title
            ]);

            return $movie;
        } catch (\Exception $e) {
            Log::error('Failed to update movie', [
                'movie_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Delete movie
     *
     * @param int $id
     * @return bool
     */
    public function deleteMovie(int $id): bool
    {
        try {
            $movie = Movie::findOrFail($id);
            $title = $movie->title;
            $slug = $movie->slug;

            $movie->delete();

            // Invalidate caches
            Cache::forget("movie:id:{$id}");
            Cache::forget("movie:slug:{$slug}");
            Cache::forget('movies:statistics');

            Log::info('Movie deleted successfully', [
                'movie_id' => $id,
                'title' => $title
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete movie', [
                'movie_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get movie statistics (cached for 5 minutes)
     *
     * @return array
     */
    public function getMovieStatistics(): array
    {
        try {
            return Cache::remember('movies:statistics', 300, function () {
                $stats = [
                    'total' => Movie::count(),
                    'active' => Movie::active()->count(),
                    'now_showing' => Movie::nowShowing()->count(),
                    'upcoming' => Movie::upcoming()->count(),
                    'hot' => Movie::where('is_hot', 1)->count(),
                ];

                Log::info('Movie statistics retrieved (cached)', $stats);

                return $stats;
            });
        } catch (\Exception $e) {
            Log::error('Failed to retrieve movie statistics', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
