<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\BulkCreateShowtimeRequest;
use App\Http\Requests\BulkSingleDayShowtimeRequest;
use App\Http\Requests\StoreShowtimeRequest;
use App\Http\Requests\UpdateShowtimeRequest;
use App\Models\Showtime;
use App\Services\ShowtimeService;
use App\Traits\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class ShowtimeController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ShowtimeService $showtimeService
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'movie_id' => ['nullable', 'integer', 'exists:movies,id'],
            'screen_id' => ['nullable', 'integer', 'exists:screens,id'],
            'theater_id' => ['nullable', 'integer', 'exists:theaters,id'],
            'format_id' => ['nullable', 'integer', 'exists:formats,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'time_scope' => ['nullable', Rule::in(['upcoming', 'past', 'all'])],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', Rule::in(['scheduled_at', 'created_at'])],
            'sort_dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $filters['status'] = $filters['status'] ?? 'all';
        $filters['time_scope'] = $filters['time_scope'] ?? 'upcoming';

        try {
            $showtimes = $this->showtimeService->getAll($filters);

            return $this->paginatedResponse($showtimes, 'Showtimes retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve showtimes', ['exception' => $e]);

            return $this->errorResponse('Failed to retrieve showtimes', 500);
        }
    }

    public function store(StoreShowtimeRequest $request)
    {
        $validated = $request->validated();

        try {
            $showtime = $this->showtimeService->create($validated);
            return $this->successResponse($showtime, 'Showtime created successfully', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Invalid showtime data', 422, $e->errors());
        } catch (\Throwable $e) {
            Log::error('Failed to create showtime', ['exception' => $e]);

            return $this->errorResponse('Failed to create showtime', 500);
        }
    }

    public function show($id)
    {
        try {
            $showtime = $this->showtimeService->getById((int) $id);

            return $this->successResponse($showtime, 'Showtime retrieved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Showtime not found', 404);
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve showtime', [
                'showtime_id' => (int) $id,
                'exception' => $e,
            ]);

            return $this->errorResponse('Failed to retrieve showtime', 500);
        }
    }

    public function update(UpdateShowtimeRequest $request, $id)
    {
        $validated = $request->validated();

        try {
            $showtime = $this->showtimeService->update((int) $id, $validated);
            return $this->successResponse($showtime, 'Showtime updated successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Showtime not found', 404);
        } catch (ValidationException $e) {
            return $this->errorResponse('Invalid showtime data', 422, $e->errors());
        } catch (\Throwable $e) {
            Log::error('Failed to update showtime', [
                'showtime_id' => (int) $id,
                'exception' => $e,
            ]);

            return $this->errorResponse('Failed to update showtime', 500);
        }
    }

    public function bulkCreate(BulkCreateShowtimeRequest $request)
    {
        $validated = $request->validated();

        try {
            $result = $this->showtimeService->bulkCreateDateRange($validated);

            if ($result['created'] === 0 && $result['skipped'] > 0) {
                return $this->errorResponse("Tạo thất bại: Tất cả các khung giờ ({$result['skipped']}) đều bị trùng lịch với suất chiếu khác.", 409);
            }

            return $this->successResponse(
                $result,
                "Tạo thành công {$result['created']} suất chiếu"
                    . ($result['skipped'] ? ", bỏ qua {$result['skipped']} trùng lịch" : ''),
                201
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Invalid showtime data', 422, $e->errors());
        } catch (\Throwable $e) {
            Log::error('Bulk showtime creation failed', ['exception' => $e]);

            return $this->errorResponse('Bulk showtime creation failed', 500);
        }
    }

    public function bulkSingleDay(BulkSingleDayShowtimeRequest $request)
    {
        $validated = $request->validated();

        try {
            $result = $this->showtimeService->bulkCreateSingleDay($validated);

            if ($result['created'] === 0 && $result['skipped'] > 0) {
                return $this->errorResponse("Tạo thất bại: Tất cả các khung giờ ({$result['skipped']}) đều bị trùng lịch với suất chiếu khác.", 409);
            }

            return $this->successResponse(
                $result,
                "Tạo thành công {$result['created']} suất chiếu"
                    . ($result['skipped'] ? ", bỏ qua {$result['skipped']} trùng lịch" : ''),
                201
            );
        } catch (ValidationException $e) {
            return $this->errorResponse('Invalid showtime data', 422, $e->errors());
        } catch (\Throwable $e) {
            Log::error('Single-day bulk showtime creation failed', ['exception' => $e]);

            return $this->errorResponse('Bulk showtime creation failed', 500);
        }
    }

    public function destroy($id)
    {
        try {
            $showtime = Showtime::findOrFail((int) $id);
            $this->authorize('delete', $showtime);

            $this->showtimeService->delete((int) $id);
            return $this->successResponse(null, 'Showtime deleted successfully');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Showtime not found', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Forbidden', 403);
        } catch (ValidationException $e) {
            return $this->errorResponse('Invalid showtime data', 422, $e->errors());
        } catch (\Throwable $e) {
            Log::error('Failed to delete showtime', [
                'showtime_id' => (int) $id,
                'exception' => $e,
            ]);

            return $this->errorResponse('Failed to delete showtime', 500);
        }
    }

    public function getMovieShowtimes($slugOrId)
    {
        try {
            $data = $this->showtimeService->getMovieShowtimes($slugOrId);
            return $this->successResponse($data, 'Showtimes retrieved successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Movie not found', 404);
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve movie showtimes', [
                'movie' => $slugOrId,
                'exception' => $e,
            ]);

            return $this->errorResponse('Failed to retrieve showtimes', 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', 'boolean'],
        ]);

        try {
            $showtime = Showtime::findOrFail((int) $id);
            $this->authorize('update', $showtime);

            $showtime->update(['status' => (bool) $validated['status']]);

            return $this->successResponse($showtime, 'Cập nhật trạng thái suất chiếu thành công');
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse('Showtime not found', 404);
        } catch (AuthorizationException $e) {
            return $this->errorResponse('Forbidden', 403);
        } catch (\Throwable $e) {
            Log::error('Failed to update showtime status', [
                'showtime_id' => (int) $id,
                'exception' => $e,
            ]);
            return $this->errorResponse('Failed to update showtime status', 500);
        }
    }
}
