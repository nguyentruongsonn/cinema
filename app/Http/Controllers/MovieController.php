<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMovieRequest;
use App\Http\Requests\UpdateMovieRequest;
use App\Services\MovieService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
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
    public function store(Request $request)
    {
        try {
            $data = $request->except(['poster_file', 'banner_file']);

            // Xử lý upload poster
            if ($request->hasFile('poster_file') && $request->file('poster_file')->isValid()) {
                $data['poster_path'] = $request->file('poster_file')->store('movies/posters', 'public');
                unset($data['poster_url']); // xóa url cũ nếu có
            }

            // Xử lý upload banner
            if ($request->hasFile('banner_file') && $request->file('banner_file')->isValid()) {
                $data['banner_path'] = $request->file('banner_file')->store('movies/banners', 'public');
            }

            $movie = $this->movieService->createMovie($data);

            return $this->successResponse(
                $movie->append(['poster_display_url', 'banner_display_url']),
                'Movie created successfully',
                201
            );
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
    public function update(Request $request, $id)
    {
        try {
            $movie = \App\Models\Movie::findOrFail($id);
            $data = $request->except(['poster_file', 'banner_file']);

            // Xử lý upload poster mới
            if ($request->hasFile('poster_file') && $request->file('poster_file')->isValid()) {
                // Xóa file cũ nếu có
                if ($movie->poster_path) {
                    Storage::disk('public')->delete($movie->poster_path);
                }
                $data['poster_path'] = $request->file('poster_file')->store('movies/posters', 'public');
            }

            // Xử lý upload banner mới
            if ($request->hasFile('banner_file') && $request->file('banner_file')->isValid()) {
                if ($movie->banner_path) {
                    Storage::disk('public')->delete($movie->banner_path);
                }
                $data['banner_path'] = $request->file('banner_file')->store('movies/banners', 'public');
            }

            $movie = $this->movieService->updateMovie($id, $data);

            return $this->successResponse(
                $movie->append(['poster_display_url', 'banner_display_url']),
                'Movie updated successfully'
            );
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

    /**
     * Toggle active status.
     */
    public function toggleActive($id)
    {
        try {
            $movie = \App\Models\Movie::findOrFail($id);
            $movie->update(['status' => !$movie->status]);
            return $this->successResponse($movie, 'Movie status toggled successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to toggle movie status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Toggle hot status.
     */
    public function toggleHot($id)
    {
        try {
            $movie = \App\Models\Movie::findOrFail($id);
            $movie->update(['is_hot' => !$movie->is_hot]);
            return $this->successResponse($movie, 'Movie hot status toggled successfully');
        } catch (\Throwable $e) {
            return $this->errorResponse('Failed to toggle movie hot status: ' . $e->getMessage(), 500);
        }
    }
}
