<?php

namespace App\Http\Controllers;

use App\Models\Screen;
use App\Models\Theater;
use App\Services\ScreenService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ScreenController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ScreenService $screenService
    ) {
    }

    /**
     * Display a listing of screens with filter, search, pagination
     */
    public function index(Request $request)
    {
        try {
            $screens = $this->screenService->getAll($request);
            return $this->paginatedResponse($screens, 'Screens retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve screens: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created screen
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'theater_id' => 'required|exists:theaters,id',
            'name' => 'required|string|max:100',
            'code' => 'nullable|string|max:20|unique:screens,code',
            'capacity' => 'required|integer|min:1',
            'rows' => 'required|integer|min:1',
            'columns' => 'required|integer|min:1',
            'screen_type' => 'nullable|string|max:50',
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
            return $this->successResponse($screen, 'Screen created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create screen: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified screen
     */
    public function show($id)
    {
        try {
            $screen = $this->screenService->getById((int) $id);
            return $this->successResponse($screen, 'Screen retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Screen not found', 404);
        }
    }

    /**
     * Update the specified screen
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'theater_id' => 'sometimes|exists:theaters,id',
            'name' => 'sometimes|string|max:100',
            'code' => 'nullable|string|max:20|unique:screens,code,' . $id,
            'capacity' => 'sometimes|integer|min:1',
            'rows' => 'sometimes|integer|min:1',
            'columns' => 'sometimes|integer|min:1',
            'screen_type' => 'nullable|string|max:50',
            'format_id' => 'nullable|exists:formats,id',
            'sound_id' => 'nullable|exists:sounds,id',
            'status' => 'sometimes|in:active,inactive',
        ]);

        // Transform status string to boolean for model
        if (isset($validated['status'])) {
            $validated['status'] = $validated['status'] === 'active' ? 1 : 0;
        }

        try {
            $screen = $this->screenService->update((int) $id, $validated);
            return $this->successResponse($screen, 'Screen updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update screen: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified screen
     */
    public function destroy($id)
    {
        try {
            $this->screenService->delete((int) $id);
            return $this->successResponse(null, 'Screen deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete screen: ' . $e->getMessage(), 500);
        }
    }
}
