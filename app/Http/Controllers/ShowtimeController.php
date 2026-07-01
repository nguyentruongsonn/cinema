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
            // Admin: xem tất cả, không giới hạn upcoming/status mặc định
            $request->merge([
                'status' => $request->query('status', 'all'),
                'upcoming' => false,
            ]);
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
     * Bulk create showtimes across a date range × multiple times (Tab 1)
     * Payload: { movie_id, screen_id, date_from, date_to, times[], price, format_id?, sound_id?, subtitle_id? }
     */
    public function bulkCreate(Request $request)
    {
        $validated = $request->validate([
            'movie_id'    => 'required|exists:movies,id',
            'screen_id'   => 'required|exists:screens,id',
            'date_from'   => 'required|date|before_or_equal:date_to',
            'date_to'     => 'required|date',
            'times'       => 'required|array|min:1',
            'times.*'     => 'required|date_format:H:i',
            'price'       => 'required|numeric|min:0',
            'format_id'   => 'nullable|exists:formats,id',
            'sound_id'    => 'nullable|exists:sounds,id',
            'subtitle_id' => 'nullable|exists:subtitles,id',
        ]);

        try {
            $created = 0;
            $skipped = 0;
            $from = new \DateTime($validated['date_from']);
            $to   = new \DateTime($validated['date_to']);
            $to->modify('+1 day'); // inclusive

            $base = [
                'movie_id'    => $validated['movie_id'],
                'screen_id'   => $validated['screen_id'],
                'price'       => $validated['price'],
                'format_id'   => $validated['format_id'] ?? null,
                'sound_id'    => $validated['sound_id'] ?? null,
                'subtitle_id' => $validated['subtitle_id'] ?? null,
                'status'      => 1,
            ];

            for ($d = clone $from; $d < $to; $d->modify('+1 day')) {
                foreach ($validated['times'] as $time) {
                    $scheduledAt = $d->format('Y-m-d') . ' ' . $time . ':00';

                    // Skip if duplicate
                    $exists = \App\Models\Showtime::where('screen_id', $base['screen_id'])
                        ->where('scheduled_at', $scheduledAt)
                        ->exists();

                    if ($exists) { $skipped++; continue; }

                    \App\Models\Showtime::create(array_merge($base, ['scheduled_at' => $scheduledAt]));
                    $created++;
                }
            }

            return $this->successResponse(
                ['created' => $created, 'skipped' => $skipped],
                "Tạo thành công {$created} suất chiếu" . ($skipped ? ", bỏ qua {$skipped} trùng lịch" : ''),
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Bulk create failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk create showtimes for a single day, multiple (time × screen) slots (Tab 2)
     * Payload: { movie_id, date, slots[{time, screen_id}], price, format_id?, sound_id?, subtitle_id?, status }
     */
    public function bulkSingleDay(Request $request)
    {
        $validated = $request->validate([
            'movie_id'       => 'required|exists:movies,id',
            'date'           => 'required|date',
            'price'          => 'required|numeric|min:0',
            'format_id'      => 'nullable|exists:formats,id',
            'sound_id'       => 'nullable|exists:sounds,id',
            'subtitle_id'    => 'nullable|exists:subtitles,id',
            'status'         => 'nullable|in:0,1',
            'slots'          => 'required|array|min:1',
            'slots.*.time'   => 'required|date_format:H:i',
            'slots.*.screen_id' => 'required|exists:screens,id',
        ]);

        try {
            $created = 0;
            $skipped = 0;

            foreach ($validated['slots'] as $slot) {
                $scheduledAt = $validated['date'] . ' ' . $slot['time'] . ':00';

                $exists = \App\Models\Showtime::where('screen_id', $slot['screen_id'])
                    ->where('scheduled_at', $scheduledAt)
                    ->exists();

                if ($exists) { $skipped++; continue; }

                \App\Models\Showtime::create([
                    'movie_id'    => $validated['movie_id'],
                    'screen_id'   => $slot['screen_id'],
                    'scheduled_at'=> $scheduledAt,
                    'price'       => $validated['price'],
                    'format_id'   => $validated['format_id'] ?? null,
                    'sound_id'    => $validated['sound_id'] ?? null,
                    'subtitle_id' => $validated['subtitle_id'] ?? null,
                    'status'      => $validated['status'] ?? 1,
                ]);
                $created++;
            }

            return $this->successResponse(
                ['created' => $created, 'skipped' => $skipped],
                "Tạo thành công {$created} suất chiếu" . ($skipped ? ", bỏ qua {$skipped} trùng lịch" : ''),
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Bulk single-day create failed: ' . $e->getMessage(), 500);
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
