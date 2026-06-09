<?php

namespace App\Services;

use App\Models\Screen;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ScreenService
{
    /**
     * Get paginated, filterable list of screens.
     */
    public function getAll(Request $request): LengthAwarePaginator
    {
        $query = Screen::with(['theater', 'format', 'sound']);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $perPage = (int) $request->query('per_page', 15);

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
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('theater_id')) {
            $query->where('theater_id', $request->input('theater_id'));
        }

        if ($request->filled('format_id')) {
            $query->where('format_id', $request->input('format_id'));
        }

        if ($request->filled('screen_type')) {
            $query->where('screen_type', $request->input('screen_type'));
        }

        if ($request->filled('q')) {
            $search = $request->input('q');

            $query->where(function (Builder $subQuery) use ($search) {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $status = $request->input('status', 'active');
        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'inactive') {
            $query->where('status', 0);
        }
    }

    /**
     * Apply safe sorting to screen query.
     */
    private function applySorting(Builder $query, Request $request): void
    {
        $sortBy = $request->input('sort_by', 'name');
        $sortDir = $request->input('sort_dir', 'asc');

        $allowedSorts = ['name', 'capacity', 'created_at'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'name';
        }

        $query->orderBy($sortBy, $sortDir === 'desc' ? 'desc' : 'asc');
    }
}
