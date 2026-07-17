<?php

namespace App\Services;

use App\Models\Screen;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ScreenService
{
    /**
     * Get paginated, filterable list of screens.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Screen::with(['theater', 'format', 'sound']);

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query->paginate($perPage);
    }

    /**
     * Find screen by ID with full relationships.
     */
    public function getById(int $id): Screen
    {
        return Screen::with(['theater', 'format', 'sound', 'seats'])->findOrFail($id);
    }

    /**
     * Create a screen.
     */
    public function create(array $data): Screen
    {
        $screen = Screen::create($data);
        $screen->load(['theater', 'format', 'sound']);

        return $screen;
    }

    /**
     * Update a screen.
     */
    public function update(int $id, array $data): Screen
    {
        $screen = Screen::findOrFail($id);
        $screen->update($data);
        $screen->load(['theater', 'format', 'sound']);

        return $screen;
    }

    /**
     * Delete a screen.
     */
    public function delete(int $id): bool
    {
        $screen = Screen::findOrFail($id);

        return (bool) $screen->delete();
    }

    /**
     * Apply request filters to screen query.
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['theater_id'])) {
            $query->where('theater_id', $filters['theater_id']);
        }

        if (! empty($filters['format_id'])) {
            $query->where('format_id', $filters['format_id']);
        }

        if (! empty($filters['q'])) {
            $search = $filters['q'];

            $query->where(function (Builder $subQuery) use ($search) {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $status = $filters['status'] ?? 'active';
        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'inactive') {
            $query->where('status', 0);
        }
    }

    /**
     * Apply safe sorting to screen query.
     */
    private function applySorting(Builder $query, array $filters): void
    {
        $sortBy = $filters['sort_by'] ?? 'name';
        $sortDir = $filters['sort_dir'] ?? 'asc';

        $allowedSorts = ['name', 'capacity', 'created_at'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'name';
        }

        $query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc');
    }
}
