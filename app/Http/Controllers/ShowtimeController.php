<?php

namespace App\Http\Controllers;

use App\Models\Showtime;
use App\Services\ShowtimeService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ShowtimeController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ShowtimeService $showtimeService
    ) {
    }

    /**
     * Display a listing of showtimes with filters
     */
    public function index(Request $request)
    {
        try {
            $showtimes = $this->showtimeService->getAll($request);
            return $this->paginatedResponse($showtimes, 'Showtimes retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve showtimes: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created showtime
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'movie_id' => 'required|exists:movies,id',
            'screen_id' => 'required|exists:screens,id',
            'format_id' => 'nullable|exists:formats,id',
            'sound_id' => 'nullable|exists:sounds,id',
            'subtitle_id' => 'nullable|exists:subtitles,id',
            'scheduled_at' => 'required|date_format:Y-m-d H:i:s|after:now',
            'price' => 'required|numeric|min:0',
            'status' => 'required|in:0,1',
        ]);

        try {
            $showtime = $this->showtimeService->create($validated);
            return $this->successResponse($showtime, 'Showtime created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create showtime: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified showtime
     */
    public function show($id)
    {
        try {
            $showtime = $this->showtimeService->getById((int) $id);
            return $this->successResponse($showtime, 'Showtime retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Showtime not found', 404);
        }
    }

    /**
     * Update the specified showtime
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'movie_id' => 'sometimes|exists:movies,id',
            'screen_id' => 'sometimes|exists:screens,id',
            'format_id' => 'nullable|exists:formats,id',
            'sound_id' => 'nullable|exists:sounds,id',
            'subtitle_id' => 'nullable|exists:subtitles,id',
            'scheduled_at' => 'sometimes|date_format:Y-m-d H:i:s',
            'price' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:0,1',
        ]);

        try {
            $showtime = $this->showtimeService->update((int) $id, $validated);
            return $this->successResponse($showtime, 'Showtime updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update showtime: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified showtime
     */
    public function destroy($id)
    {
        try {
            $this->showtimeService->delete((int) $id);
            return $this->successResponse(null, 'Showtime deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete showtime: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get showtimes for a movie by slug or ID
     */
    public function getMovieShowtimes($slugOrId)
    {
        try {
            $data = $this->showtimeService->getMovieShowtimes($slugOrId);
            return $this->successResponse($data, 'Showtimes retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Movie not found', 404);
        } catch (\Throwable $e) {
            return $this->errorResponse('Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 500);
        }
    }
}
