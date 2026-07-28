<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Theater;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * TheaterService - Business Logic Layer for Theater Management
 *
 * Handles all theater-related business logic following Clean Architecture principles.
 * Separates business logic from controllers for better maintainability and testability.
 */
class TheaterService
{
    /**
     * Get paginated list of theaters with filters and sorting
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getTheaters(array $filters): LengthAwarePaginator
    {
        try {
            $perPage = $filters['per_page'] ?? 12;

            $query = Theater::query()->with(['screens', 'branch']);

            // Search by name, address or branch name
            if (!empty($filters['q'])) {
                $keyword = $filters['q'];
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('name', 'like', "%{$keyword}%")
                        ->orWhere('address', 'like', "%{$keyword}%")
                        ->orWhereHas('branch', function ($q) use ($keyword) {
                            $q->where('name', 'like', "%{$keyword}%");
                        });
                });
            }

            // Filter by branch_id or city (legacy)
            if (!empty($filters['branch_id'])) {
                $query->where('branch_id', $filters['branch_id']);
            } elseif (!empty($filters['city'])) {
                $query->whereHas('branch', function ($q) use ($filters) {
                    $q->where('name', $filters['city']);
                });
            }

            // Status filter
            $status = $filters['status'] ?? 'active';
            if ($status === 'active') {
                $query->active();
            } elseif ($status === 'inactive') {
                $query->where('status', 0);
            }

            // Sort
            $sortBy = $filters['sort_by'] ?? 'name';
            $sortDir = $filters['sort_dir'] ?? 'asc';
            $allowedSorts = ['name', 'branch_id', 'created_at'];

            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc');
            }

            $theaters = $query->paginate($perPage)->withQueryString();

            Log::info('Theaters retrieved', [
                'count' => $theaters->count(),
                'total' => $theaters->total(),
                'filters' => $filters
            ]);

            return $theaters;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve theaters', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get all unique cities (branches) from active theaters
     *
     * @return \Illuminate\Support\Collection
     */
    public function getCities()
    {
        try {
            $cities = Theater::active()
                ->whereHas('branch')
                ->with('branch:id,name')
                ->get()
                ->pluck('branch.name')
                ->unique()
                ->values();

            Log::info('Cities retrieved', ['count' => $cities->count()]);

            return $cities;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve cities', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create a new theater
     *
     * @param array $data
     * @return Theater
     */
    public function createTheater(array $data): Theater
    {
        try {
            $theater = Theater::create($data);

            Log::info('Theater created successfully', [
                'theater_id' => $theater->id,
                'name' => $theater->name
            ]);

            return $theater;
        } catch (\Exception $e) {
            Log::error('Failed to create theater', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get theater by ID with screens
     *
     * @param int $id
     * @return Theater
     */
    public function getTheater(int $id): Theater
    {
        try {
            $theater = Theater::with(['screens' => function ($q) {
                $q->active()->with(['format', 'sound']);
            }])->findOrFail($id);

            Log::info('Theater retrieved', ['theater_id' => $theater->id]);

            return $theater;
        } catch (\Exception $e) {
            Log::warning('Theater not found', ['theater_id' => $id]);
            throw $e;
        }
    }

    /**
     * Get screens belonging to a specific theater
     *
     * @param int $theaterId
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getTheaterScreens(int $theaterId, array $filters): LengthAwarePaginator
    {
        try {
            $theater = Theater::findOrFail($theaterId);
            $perPage = $filters['per_page'] ?? 15;

            $screens = $theater->screens()
                ->with(['format', 'sound'])
                ->when(!empty($filters['status']), function ($q) use ($filters) {
                    if ($filters['status'] === 'active') {
                        $q->active();
                    }
                })
                ->paginate($perPage);

            Log::info('Theater screens retrieved', [
                'theater_id' => $theaterId,
                'count' => $screens->count()
            ]);

            return $screens;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve theater screens', [
                'theater_id' => $theaterId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update theater
     *
     * @param int $id
     * @param array $data
     * @return Theater
     */
    public function updateTheater(int $id, array $data): Theater
    {
        try {
            $theater = Theater::findOrFail($id);
            $theater->update($data);

            // Reload with screens
            $theater->load(['screens' => fn($q) => $q->active()->with(['format', 'sound'])]);

            Log::info('Theater updated successfully', [
                'theater_id' => $theater->id,
                'name' => $theater->name
            ]);

            return $theater;
        } catch (\Exception $e) {
            Log::error('Failed to update theater', [
                'theater_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Delete theater
     *
     * @param int $id
     * @return bool
     */
    public function deleteTheater(int $id): bool
    {
        try {
            $theater = Theater::findOrFail($id);
            $name = $theater->name;

            $theater->delete();

            Log::info('Theater deleted successfully', [
                'theater_id' => $id,
                'name' => $name
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete theater', [
                'theater_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
