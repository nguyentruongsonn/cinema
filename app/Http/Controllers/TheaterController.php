<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTheaterRequest;
use App\Http\Requests\UpdateTheaterRequest;
use App\Services\TheaterService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class TheaterController extends Controller
{
    use ApiResponse;

    protected TheaterService $theaterService;

    public function __construct(TheaterService $theaterService)
    {
        $this->theaterService = $theaterService;
    }

    /**
     * Display a listing of theaters with filter, search, pagination
     */
    public function index(Request $request)
    {
        try {
            $filters = $request->validate([
                'q' => ['nullable', 'string', 'max:255'],
                'city' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', 'in:active,inactive,all'],
                'sort_by' => ['nullable', 'in:name,city,created_at'],
                'sort_dir' => ['nullable', 'in:asc,desc'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            ]);

            $theaters = $this->theaterService->getTheaters($filters);

            return $this->paginatedResponse($theaters, 'Theaters retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve theaters: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all unique cities from theaters
     */
    public function cities()
    {
        try {
            $cities = $this->theaterService->getCities();

            return $this->successResponse($cities, 'Cities retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve cities: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created theater
     */
    public function store(StoreTheaterRequest $request)
    {
        try {
            $theater = $this->theaterService->createTheater($request->validated());

            return $this->successResponse($theater, 'Theater created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create theater: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified theater with screens
     */
    public function show($id)
    {
        try {
            $theater = $this->theaterService->getTheater($id);

            return $this->successResponse($theater, 'Theater retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Theater not found', 404);
        }
    }

    /**
     * Get screens belonging to a specific theater
     */
    public function screens($theaterId, Request $request)
    {
        try {
            $filters = $request->validate([
                'status' => ['nullable', 'in:active,inactive,all'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            ]);

            $screens = $this->theaterService->getTheaterScreens($theaterId, $filters);

            return $this->paginatedResponse($screens, 'Screens retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve screens: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified theater
     */
    public function update(UpdateTheaterRequest $request, $id)
    {
        try {
            $theater = $this->theaterService->updateTheater($id, $request->validated());

            return $this->successResponse($theater, 'Theater updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update theater: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified theater
     */
    public function destroy($id)
    {
        try {
            $this->theaterService->deleteTheater($id);

            return $this->successResponse(null, 'Theater deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete theater: ' . $e->getMessage(), 500);
        }
    }
}
