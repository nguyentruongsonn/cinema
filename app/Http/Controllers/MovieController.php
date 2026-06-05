<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMovieRequest;
use App\Http\Requests\UpdateMovieRequest;
use App\Services\MovieService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MovieController extends Controller
{
    use ApiResponse;

    protected MovieService $movieService;

    public function __construct(MovieService $movieService)
    {
        $this->movieService = $movieService;
    }

    /**
     * Display a filterable, sortable, paginated listing of public movies.
     */
    public function index(Request $request)
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
                'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            ]);

            $movies = $this->movieService->getMovies($filters);

            return $this->paginatedResponse($movies, 'Movies retrieved successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to retrieve movies: ' . $e->getMessage(), 500);
        }
    }

    /**
     * List movies currently showing.
     */
    public function nowShowing(Request $request)
    {
        $request->merge(['status' => 'now_showing']);

        return $this->index($request);
    }

    /**
     * List upcoming movies.
     */
    public function comingSoon(Request $request)
    {
        $request->merge(['status' => 'upcoming']);

        return $this->index($request);
    }

    /**
     * Search movies by keyword.
     */
    public function search(Request $request)
    {
        $request->validate([
            'q' => ['required', 'string', 'max:255'],
        ]);

        return $this->index($request);
    }

    /**
     * Store a newly created movie.
     */
    public function store(StoreMovieRequest $request)
    {
        try {
            $movie = $this->movieService->createMovie($request->validated());

            return $this->successResponse($movie, 'Movie created successfully', 201);
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to create movie: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified movie by id or slug.
     */
    public function show($idOrSlug)
    {
        try {
            $movie = $this->movieService->getMovie($idOrSlug);

            return $this->successResponse($movie, 'Movie retrieved successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Movie not found', 404);
        }
    }

    /**
     * Update the specified movie.
     */
    public function update(UpdateMovieRequest $request, $id)
    {
        try {
            $movie = $this->movieService->updateMovie($id, $request->validated());

            return $this->successResponse($movie, 'Movie updated successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to update movie: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified movie.
     */
    public function destroy($id)
    {
        try {
            $this->movieService->deleteMovie($id);

            return $this->successResponse(null, 'Movie deleted successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to delete movie: ' . $e->getMessage(), 500);
        }
    }
}
