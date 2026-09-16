<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreMovieRequest;
use App\Http\Requests\UpdateMovieRequest;
use App\Http\Resources\MovieResource;
use App\Models\Movie;
use App\Services\AuditLogService;
use App\Services\MovieService;
use App\Services\PublicFileStorageService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class MovieController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected MovieService $movieService,
        private readonly PublicFileStorageService $publicFiles
    ) {}

    /**
     * Display a filterable, sortable, paginated listing of public movies.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->validate([
                'q' => ['nullable', 'string', 'max:255'],
                'category_id' => ['nullable', 'integer', 'exists:categories,id'],
                'status' => ['nullable', Rule::in(['active', 'now_showing', 'upcoming', 'hidden', 'all'])],
                'is_hot' => ['nullable', 'boolean'],
                'age_rating' => ['nullable', 'string', 'max:50'],
                'release_from' => ['nullable', 'date'],
                'release_to' => ['nullable', 'date', 'after_or_equal:release_from'],
                'sort_by' => ['nullable', Rule::in(['title', 'release_date', 'duration', 'created_at', 'is_hot'])],
                'sort_dir' => ['nullable', Rule::in(['asc', 'desc'])],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $movies = $this->movieService->getMovies($filters);

            return $this->paginatedResponse(
                $movies->setCollection(MovieResource::collection($movies->getCollection())->collection),
                'Movies retrieved successfully'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Invalid filter parameters', 422, $e->errors());
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve movies', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('Failed to retrieve movies', 500);
        }
    }

    /**
     * List movies currently showing.
     */
    public function nowShowing(Request $request): JsonResponse
    {
        $request->merge(['status' => 'now_showing']);

        return $this->index($request);
    }

    /**
     * List upcoming movies.
     */
    public function comingSoon(Request $request): JsonResponse
    {
        $request->merge(['status' => 'upcoming']);

        return $this->index($request);
    }

    /**
     * Search movies by keyword.
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'q' => ['required', 'string', 'max:255'],
            ]);

            return $this->index($request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Search query is required', 422, $e->errors());
        }
    }

    /**
     * Store a newly created movie.
     */
    public function store(StoreMovieRequest $request): JsonResponse
    {
        $uploadedPaths = [];

        try {
            $this->authorize('create', Movie::class);

            return DB::transaction(function () use ($request, &$uploadedPaths) {
                $data = $request->except(['poster_file', 'banner_file']);

                // Handle poster upload
                if ($request->hasFile('poster_file') && $request->file('poster_file')->isValid()) {
                    $data['poster_path'] = $this->publicFiles->store($request->file('poster_file'), 'movies/posters');
                    $uploadedPaths[] = $data['poster_path'];
                    unset($data['poster_url']);
                }

                // Handle banner upload
                if ($request->hasFile('banner_file') && $request->file('banner_file')->isValid()) {
                    $data['banner_path'] = $this->publicFiles->store($request->file('banner_file'), 'movies/banners');
                    $uploadedPaths[] = $data['banner_path'];
                }

                $movie = $this->movieService->createMovie($data);

                app(AuditLogService::class)->record(
                    Auth::user(),
                    'movie.created',
                    $movie,
                    [],
                    $this->auditMovieValues($movie)
                );

                return $this->successResponse(
                    $movie->append(['poster_display_url', 'banner_display_url']),
                    'Movie created successfully',
                    201
                );
            });
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to create movies', 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Invalid movie data', 422, $e->errors());
        } catch (\Throwable $e) {
            $this->publicFiles->deleteMany($uploadedPaths);

            Log::error('Failed to create movie', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to create movie', 500);
        }
    }

    /**
     * Display the specified movie by id or slug.
     */
    public function show($idOrSlug): JsonResponse
    {
        try {
            $movie = $this->movieService->getMovie($idOrSlug);

            return $this->successResponse(new MovieResource($movie), 'Movie retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve movie', [
                'identifier' => $idOrSlug,
                'error' => $e->getMessage(),
            ]);
            return $this->errorResponse('Movie not found', 404);
        }
    }

    /**
     * Update the specified movie.
     */
    public function update(UpdateMovieRequest $request, $id): JsonResponse
    {
        $newUploadedPaths = [];
        $oldPathsToDelete = [];

        try {
            $movie = Movie::findOrFail($id);
            $this->authorize('update', $movie);
            $oldValues = $this->auditMovieValues($movie);

            $response = DB::transaction(function () use ($request, $movie, $id, $oldValues, &$newUploadedPaths, &$oldPathsToDelete) {
                $data = $request->except(['poster_file', 'banner_file']);

                // Handle new poster upload
                if ($request->hasFile('poster_file') && $request->file('poster_file')->isValid()) {
                    if ($movie->poster_path) {
                        $oldPathsToDelete[] = $movie->poster_path;
                    }

                    $data['poster_path'] = $this->publicFiles->store($request->file('poster_file'), 'movies/posters');
                    $newUploadedPaths[] = $data['poster_path'];
                }

                // Handle new banner upload
                if ($request->hasFile('banner_file') && $request->file('banner_file')->isValid()) {
                    if ($movie->banner_path) {
                        $oldPathsToDelete[] = $movie->banner_path;
                    }

                    $data['banner_path'] = $this->publicFiles->store($request->file('banner_file'), 'movies/banners');
                    $newUploadedPaths[] = $data['banner_path'];
                }

                $updatedMovie = $this->movieService->updateMovie((int) $id, $data);

                app(AuditLogService::class)->record(
                    Auth::user(),
                    'movie.updated',
                    $updatedMovie,
                    $oldValues,
                    $this->auditMovieValues($updatedMovie)
                );

                return $this->successResponse(
                    $updatedMovie->append(['poster_display_url', 'banner_display_url']),
                    'Movie updated successfully'
                );
            });

            $this->publicFiles->deleteMany($oldPathsToDelete);

            return $response;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Movie not found', 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to update this movie', 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->publicFiles->deleteMany($newUploadedPaths);

            return $this->errorResponse('Invalid movie data', 422, $e->errors());
        } catch (\Throwable $e) {
            $this->publicFiles->deleteMany($newUploadedPaths);

            Log::error('Failed to update movie', [
                'movie_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to update movie', 500);
        }
    }

    /**
     * Remove the specified movie.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $movie = Movie::findOrFail($id);
            $this->authorize('delete', $movie);
            $oldValues = $this->auditMovieValues($movie);

            DB::transaction(function () use ($id, $movie, $oldValues): void {
                $this->movieService->deleteMovie((int) $id);

                app(AuditLogService::class)->record(
                    Auth::user(),
                    'movie.deleted',
                    $movie,
                    $oldValues,
                    []
                );
            });

            return $this->successResponse(null, 'Movie deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Movie not found', 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to delete this movie', 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Invalid movie data', 422, $e->errors());
        } catch (\Throwable $e) {
            Log::error('Failed to delete movie', [
                'movie_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to delete movie', 500);
        }
    }

    /**
     * Toggle active status between 'hidden' and 'active'.
     * 
     * FIXED: Status is a string enum, not boolean.
     * Valid values: 'active', 'now_showing', 'upcoming', 'hidden'
     */
    public function toggleActive($id): JsonResponse
    {
        try {
            $movie = Movie::findOrFail($id);
            $this->authorize('toggleStatus', $movie);
            $oldValues = $this->auditMovieValues($movie);
            $updatedMovie = null;

            DB::transaction(function () use ($movie, $oldValues, &$updatedMovie) {
                $newStatus = $movie->status ? 0 : 1;
                
                $movie->lockForUpdate()->update(['status' => $newStatus]);
                $updatedMovie = $movie->fresh();

                app(AuditLogService::class)->record(
                    Auth::user(),
                    'movie.status_toggled',
                    $updatedMovie,
                    $oldValues,
                    $this->auditMovieValues($updatedMovie)
                );
            });

            return $this->successResponse($updatedMovie, 'Movie status toggled successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Movie not found', 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to toggle movie status', 403);
        } catch (\Throwable $e) {
            Log::error('Failed to toggle movie status', [
                'movie_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to toggle movie status', 500);
        }
    }

    /**
     * Toggle hot status (is_hot is boolean, safe to toggle).
     */
    public function toggleHot($id): JsonResponse
    {
        try {
            $movie = Movie::findOrFail($id);
            $this->authorize('toggleHot', $movie);
            $oldValues = $this->auditMovieValues($movie);
            $updatedMovie = null;

            DB::transaction(function () use ($movie, $oldValues, &$updatedMovie) {
                $movie->lockForUpdate()->update(['is_hot' => !$movie->is_hot]);
                $updatedMovie = $movie->fresh();

                app(AuditLogService::class)->record(
                    Auth::user(),
                    'movie.hot_toggled',
                    $updatedMovie,
                    $oldValues,
                    $this->auditMovieValues($updatedMovie)
                );
            });

            return $this->successResponse($updatedMovie, 'Movie hot status toggled successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Movie not found', 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse('Unauthorized to toggle movie hot status', 403);
        } catch (\Throwable $e) {
            Log::error('Failed to toggle movie hot status', [
                'movie_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            return $this->errorResponse('Failed to toggle movie hot status', 500);
        }
    }

    private function auditMovieValues(Movie $movie): array
    {
        return [
            'title' => $movie->title,
            'slug' => $movie->slug,
            'duration' => $movie->duration,
            'release_date' => $movie->release_date?->toDateString(),
            'end_date' => $movie->end_date?->toDateString(),
            'status' => $movie->status,
            'is_hidden' => (bool) $movie->is_hidden,
            'is_hot' => (bool) $movie->is_hot,
            'poster_path' => $movie->poster_path ? '[image]' : null,
            'banner_path' => $movie->banner_path ? '[image]' : null,
        ];
    }
}
