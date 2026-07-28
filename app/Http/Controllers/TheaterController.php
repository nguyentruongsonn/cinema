<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreTheaterRequest;
use App\Http\Requests\UpdateTheaterRequest;
use App\Http\Resources\TheaterResource;
use App\Http\Resources\ScreenResource;
use App\Models\Theater;
use App\Services\TheaterService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TheaterController extends Controller
{
    use ApiResponse;

    /**
     * Constructor with dependency injection and authorization middleware.
     */
    public function __construct(
        private readonly TheaterService $theaterService
    ) {}

    /**
     * Display a listing of theaters with filter, search, pagination.
     *
     * Public endpoint - no authentication required.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->validate([
                'q' => ['nullable', 'string', 'max:255'],
                'city' => ['nullable', 'string', 'max:100'],
                'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
                'status' => ['nullable', 'in:active,inactive,all'],
                'sort_by' => ['nullable', 'in:name,city,created_at,branch_id'],
                'sort_dir' => ['nullable', 'in:asc,desc'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            ]);

            $this->authorize('viewAny', Theater::class);

            $theaters = $this->theaterService->getTheaters($filters);

            return $this->paginatedResponse(
                TheaterResource::collection($theaters),
                'Theaters retrieved successfully'
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Unauthorized theater list access attempt', [
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
            ]);
            return $this->errorResponse('Unauthorized', 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to retrieve theaters', [
                'filters' => $filters ?? [],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('Failed to retrieve theaters', 500);
        }
    }

    /**
     * Get all unique cities from active theaters.
     *
     * Public endpoint - returns only active theaters' cities.
     */
    public function cities(): JsonResponse
    {
        try {
            $cities = $this->theaterService->getCities();

            return $this->successResponse($cities, 'Cities retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to retrieve cities', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('Failed to retrieve cities', 500);
        }
    }

    /**
     * Store a newly created theater.
     *
     * Requires admin authentication and theater_manage permission.
     */
    public function store(StoreTheaterRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Theater::class);

            $theater = $this->theaterService->createTheater($request->validated());

            Log::info('Theater created via API', [
                'theater_id' => $theater->id,
                'theater_name' => $theater->name,
                'user_id' => auth()->id(),
            ]);

            return $this->successResponse(
                new TheaterResource($theater),
                'Theater created successfully',
                201
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Unauthorized theater creation attempt', [
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
            ]);
            return $this->errorResponse('Unauthorized', 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to create theater', [
                'data' => $request->validated(),
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('Failed to create theater', 500);
        }
    }

    /**
     * Display the specified theater with screens.
     *
     * Public endpoint - no authentication required.
     */
    public function show($id): JsonResponse
    {
        try {
            $this->authorize('view', Theater::class);

            $theater = $this->theaterService->getTheater((int) $id);

            return $this->successResponse(
                new TheaterResource($theater),
                'Theater retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            Log::info('Theater not found', [
                'theater_id' => $id,
                'ip' => request()->ip(),
            ]);
            return $this->errorResponse('Theater not found', 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Unauthorized theater view attempt', [
                'theater_id' => $id,
                'user_id' => auth()->id(),
                'ip' => request()->ip(),
            ]);
            return $this->errorResponse('Unauthorized', 403);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve theater', [
                'theater_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('Failed to retrieve theater', 500);
        }
    }

    /**
     * Get screens belonging to a specific theater.
     *
     * Public endpoint - no authentication required.
     */
    public function screens($theaterId, Request $request): JsonResponse
    {
        try {
            $filters = $request->validate([
                'status' => ['nullable', 'in:active,inactive,all'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            ]);

            $screens = $this->theaterService->getTheaterScreens((int) $theaterId, $filters);

            return $this->paginatedResponse(
                ScreenResource::collection($screens),
                'Screens retrieved successfully'
            );
        } catch (ModelNotFoundException $e) {
            Log::info('Theater not found for screens query', [
                'theater_id' => $theaterId,
                'ip' => $request->ip(),
            ]);
            return $this->errorResponse('Theater not found', 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to retrieve theater screens', [
                'theater_id' => $theaterId,
                'filters' => $filters ?? [],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('Failed to retrieve screens', 500);
        }
    }

    /**
     * Update the specified theater.
     *
     * Requires admin authentication and theater_manage permission.
     */
    public function update(UpdateTheaterRequest $request, $id): JsonResponse
    {
        try {
            $theater = Theater::findOrFail((int) $id);
            $this->authorize('update', $theater);

            $theater = $this->theaterService->updateTheater((int) $id, $request->validated());

            Log::info('Theater updated via API', [
                'theater_id' => $id,
                'theater_name' => $theater->name,
                'user_id' => auth()->id(),
            ]);

            return $this->successResponse(
                new TheaterResource($theater),
                'Theater updated successfully'
            );
        } catch (ModelNotFoundException $e) {
            Log::warning('Theater not found for update', [
                'theater_id' => $id,
                'user_id' => auth()->id(),
            ]);
            return $this->errorResponse('Theater not found', 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Unauthorized theater update attempt', [
                'theater_id' => $id,
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
            ]);
            return $this->errorResponse('Unauthorized', 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to update theater', [
                'theater_id' => $id,
                'data' => $request->validated(),
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('Failed to update theater', 500);
        }
    }

    /**
     * Remove the specified theater.
     *
     * Requires admin authentication and theater_manage permission.
     * Service layer enforces business rules (e.g., cannot delete theaters with active screens/showtimes).
     */
    public function destroy($id): JsonResponse
    {
        try {
            $theater = Theater::findOrFail((int) $id);
            $this->authorize('delete', $theater);

            $this->theaterService->deleteTheater((int) $id);

            Log::warning('Theater deleted via API', [
                'theater_id' => $id,
                'theater_name' => $theater->name,
                'user_id' => auth()->id(),
            ]);

            return $this->successResponse(null, 'Theater deleted successfully', 200);
        } catch (ModelNotFoundException $e) {
            Log::warning('Theater not found for deletion', [
                'theater_id' => $id,
                'user_id' => auth()->id(),
            ]);
            return $this->errorResponse('Theater not found', 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::warning('Unauthorized theater deletion attempt', [
                'theater_id' => $id,
                'user_id' => auth()->id(),
                'ip' => request()->ip(),
            ]);
            return $this->errorResponse('Unauthorized', 403);
        } catch (\Exception $e) {
            Log::error('Failed to delete theater', [
                'theater_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->errorResponse('Failed to delete theater', 500);
        }
    }
}
