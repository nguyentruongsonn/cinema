<?php

namespace App\Http\Controllers;

use App\Models\Screen;
use App\Models\Theater;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ScreenController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of screens with filter, search, pagination
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->query('per_page', 15);
            $query = Screen::with(['theater', 'format', 'sound']);

            // Filter by theater
            if ($request->filled('theater_id')) {
                $query->where('theater_id', $request->input('theater_id'));
            }

            // Filter by format
            if ($request->filled('format_id')) {
                $query->where('format_id', $request->input('format_id'));
            }

            // Filter by screen type
            if ($request->filled('screen_type')) {
                $query->where('screen_type', $request->input('screen_type'));
            }

            // Search by name or code
            if ($request->filled('q')) {
                $q = $request->input('q');
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%");
                });
            }

            // Status filter
            $status = $request->input('status', 'active');
            if ($status === 'active') {
                $query->active();
            } elseif ($status === 'inactive') {
                $query->where('status', 0);
            }

            // Sort
            $sortBy = $request->input('sort_by', 'name');
            $sortDir = $request->input('sort_dir', 'asc');
            $allowedSorts = ['name', 'capacity', 'created_at'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc');
            }

            $screens = $query->paginate($perPage);

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

        try {
            $screen = Screen::create($validated);
            $screen->load(['theater', 'format', 'sound']);
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
            $screen = Screen::with(['theater', 'format', 'sound', 'seats'])->findOrFail($id);
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

        try {
            $screen = Screen::findOrFail($id);
            $screen->update($validated);
            $screen->load(['theater', 'format', 'sound']);
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
            $screen = Screen::findOrFail($id);
            $screen->delete();
            return $this->successResponse(null, 'Screen deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete screen: ' . $e->getMessage(), 500);
        }
    }
}
