<?php

namespace App\Http\Controllers;

use App\Models\Showtime;
use App\Services\ShowtimeService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ShowtimeController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of showtimes with filters
     */
    public function index(Request $request)
    {
        try {
            $query = Showtime::with([
                'movie:id,title,slug,duration,age_rating,poster_url',
                'screen:id,name,code,screen_type,theater_id,capacity',
                'screen.theater:id,name,address,city',
                'format:id,name,code',
                'subtitle:id,name'
            ]);

            // Filter by movie
            if ($request->filled('movie_id')) {
                $query->where('movie_id', $request->movie_id);
            }

            // Filter by screen
            if ($request->filled('screen_id')) {
                $query->where('screen_id', $request->screen_id);
            }

            // Filter by theater (through screen)
            if ($request->filled('theater_id')) {
                $query->whereHas('screen', function ($q) use ($request) {
                    $q->where('theater_id', $request->theater_id);
                });
            }

            // Filter by format
            if ($request->filled('format_id')) {
                $query->where('format_id', $request->format_id);
            }

            // Filter by date
            if ($request->filled('date')) {
                $query->whereDate('scheduled_at', $request->date);
            }

            // Date range
            if ($request->filled('date_from')) {
                $query->whereDate('scheduled_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('scheduled_at', '<=', $request->date_to);
            }

            // Filter by status
            $status = $request->query('status', 'active');
            if ($status === 'active') {
                $query->where('status', 1);
            } elseif ($status === 'inactive') {
                $query->where('status', 0);
            }

            // Only upcoming showtimes by default
            if ($request->boolean('upcoming', true)) {
                $query->where('scheduled_at', '>=', now());
            }

            // Search by movie title
            if ($request->filled('q')) {
                $search = $request->q;
                $query->whereHas('movie', function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%");
                });
            }

            // Sort
            $sortField = $request->query('sort_by', 'scheduled_at');
            $sortDir = $request->query('sort_dir', 'asc');
            $allowedSortFields = ['scheduled_at', 'price', 'created_at'];
            if (!in_array($sortField, $allowedSortFields)) {
                $sortField = 'scheduled_at';
            }
            $sortDir = $sortDir === 'desc' ? 'desc' : 'asc';
            $query->orderBy($sortField, $sortDir);

            $perPage = $request->query('per_page', 15);
            $showtimes = $query->paginate($perPage);

            // Transform to include human-readable fields
            $showtimes->getCollection()->transform(function ($showtime) {
                $showtime->start_time = $showtime->scheduled_at ? $showtime->scheduled_at->format('Y-m-d H:i:s') : null;
                $showtime->end_time_estimated = $showtime->scheduled_at && $showtime->movie
                    ? $showtime->scheduled_at->copy()->addMinutes($showtime->movie->duration)->format('Y-m-d H:i:s')
                    : null;
                return $showtime;
            });

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
            $showtime = Showtime::create($validated);
            $showtime->load(['movie', 'screen', 'screen.theater', 'format']);
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
            $showtime = Showtime::with([
                'movie',
                'screen',
                'screen.theater',
                'format',
                'subtitle',
                'seatLayoutSnapshot'
            ])->findOrFail($id);

            $showtime->start_time = $showtime->scheduled_at ? $showtime->scheduled_at->format('Y-m-d H:i:s') : null;
            $showtime->end_time_estimated = $showtime->scheduled_at && $showtime->movie
                ? $showtime->scheduled_at->copy()->addMinutes($showtime->movie->duration)->format('Y-m-d H:i:s')
                : null;

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
            $showtime = Showtime::findOrFail($id);
            $showtime->update($validated);
            $showtime->load(['movie', 'screen', 'screen.theater', 'format']);
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
            $showtime = Showtime::findOrFail($id);
            $showtime->delete();
            return $this->successResponse(null, 'Showtime deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete showtime: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get showtimes for a movie by slug or ID
     */
    public function getMovieShowtimes($slugOrId, ShowtimeService $service)
    {
        try {
            $data = $service->getMovieShowtimes($slugOrId);
            return $this->successResponse($data, 'Showtimes retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Movie not found', 404);
        } catch (\Throwable $e) {
            return $this->errorResponse('Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 500);
        }
    }
}
