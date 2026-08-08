<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ScreenResource;
use App\Models\Screen;
use App\Services\ScreenService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ScreenController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ScreenService $screenService
    ) {
    }

    /**
     * Display a listing of screens with filter, search, pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Screen::class);

        $filters = $request->validate([
            'theater_id' => ['nullable', 'integer', 'exists:theaters,id'],
            'format_id' => ['nullable', 'integer', 'exists:formats,id'],
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', Rule::in(['name', 'capacity', 'created_at'])],
            'sort_dir' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $screens = $this->screenService->getAll($filters);
        
        return $this->paginatedResponse(
            $screens,
            'Screens retrieved successfully',
            200,
            fn ($screen) => new ScreenResource($screen)
        );
    }

    /**
     * Store a newly created screen.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Screen::class);

        $validated = $request->validate([
            'theater_id' => 'required|exists:theaters,id',
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:20|unique:screens,code',
            'capacity' => 'required|integer|min:1|max:1000',
            'seat_layout_template_id' => 'nullable|exists:seat_layout_templates,id',
            'format_id' => 'nullable|exists:formats,id',
            'sound_id' => 'nullable|exists:sounds,id',
            'status' => 'required|in:active,inactive',
        ]);

        // Transform status string to boolean for model
        if (isset($validated['status'])) {
            $validated['status'] = $validated['status'] === 'active' ? 1 : 0;
        }

        try {
            $screen = $this->screenService->create($validated);
            return $this->successResponse(
                new ScreenResource($screen),
                'Screen created successfully',
                201
            );
        } catch (\Exception $e) {
            Log::error('Screen creation failed', [
                'error' => $e->getMessage(),
                'data' => $validated
            ]);
            return $this->errorResponse('Failed to create screen', 500);
        }
    }

    /**
     * Display the specified screen.
     */
    public function show($id): JsonResponse
    {
        try {
            $screen = $this->screenService->getById((int) $id);
            $this->authorize('view', $screen);

            return $this->successResponse(
                new ScreenResource($screen),
                'Screen retrieved successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Screen not found', 404);
        }
    }

    /**
     * Update the specified screen.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $screen = Screen::findOrFail((int) $id);
            $this->authorize('update', $screen);

            // Check for active/future showtimes if layout or capacity changes
            if ($request->hasAny(['capacity', 'seat_layout_template_id'])) {
                $hasFutureShowtimes = $screen->showtimes()
                    ->where('scheduled_at', '>', now())
                    ->exists();

                if ($hasFutureShowtimes) {
                    return $this->errorResponse(
                        'Cannot modify screen layout or capacity: screen has scheduled future showtimes',
                        422
                    );
                }
            }

            $validated = $request->validate([
                'theater_id' => 'sometimes|exists:theaters,id',
                'name' => 'sometimes|string|max:100',
                'code' => 'nullable|string|max:20|unique:screens,code,' . $id,
                'capacity' => 'sometimes|integer|min:1|max:1000',
                'seat_layout_template_id' => 'nullable|exists:seat_layout_templates,id',
                'format_id' => 'nullable|exists:formats,id',
                'sound_id' => 'nullable|exists:sounds,id',
                'status' => 'sometimes|in:active,inactive',
            ]);

            // Transform status string to boolean for model
            if (isset($validated['status'])) {
                $validated['status'] = $validated['status'] === 'active' ? 1 : 0;
            }

            $screen = $this->screenService->update((int) $id, $validated);
            
            return $this->successResponse(
                new ScreenResource($screen),
                'Screen updated successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Screen not found', 404);
        } catch (\Exception $e) {
            Log::error('Screen update failed', [
                'screen_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to update screen', 500);
        }
    }

    /**
     * Remove the specified screen.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $screen = Screen::findOrFail((int) $id);
            $this->authorize('delete', $screen);

            // Prevent deletion if screen has any showtimes (past or future)
            if ($screen->showtimes()->exists()) {
                return $this->errorResponse(
                    'Cannot delete screen: screen has associated showtimes',
                    422
                );
            }

            $this->screenService->delete((int) $id);
            
            return $this->successResponse(null, 'Screen deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Screen not found', 404);
        } catch (\Exception $e) {
            Log::error('Screen deletion failed', [
                'screen_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to delete screen', 500);
        }
    }
}
