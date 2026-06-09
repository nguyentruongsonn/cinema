<?php

namespace App\Services;

use App\Models\Movie;
use App\Models\Showtime;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ShowtimeService
{
    /**
     * Get paginated, filterable list of showtimes for admin/catalog.
     */
    public function getAll(Request $request): LengthAwarePaginator
    {
        $query = Showtime::with([
            'movie:id,title,slug,duration,age_rating,poster_url',
            'screen:id,name,code,screen_type,theater_id,capacity',
            'screen.theater:id,name,address,city',
            'format:id,name,code',
            'subtitle:id,name',
        ]);

        $query = $this->applyFilters($query, $request);
        $query = $this->applySorting($query, $request);

        $perPage = (int) $request->query('per_page', 15);
        $showtimes = $query->paginate($perPage);

        $showtimes->getCollection()->transform(fn ($s) => $this->enrichShowtime($s));

        return $showtimes;
    }

    /**
     * Find a single showtime by ID with full relations.
     */
    public function getById(int $id): Showtime
    {
        $showtime = Showtime::with([
            'movie',
            'screen',
            'screen.theater',
            'format',
            'subtitle',
            'seatLayoutSnapshot',
        ])->findOrFail($id);

        return $this->enrichShowtime($showtime);
    }

    /**
     * Create a new showtime.
     */
    public function create(array $data): Showtime
    {
        $showtime = Showtime::create($data);
        $showtime->load(['movie', 'screen', 'screen.theater', 'format']);
        return $showtime;
    }

    /**
     * Update an existing showtime.
     */
    public function update(int $id, array $data): Showtime
    {
        $showtime = Showtime::findOrFail($id);
        $showtime->update($data);
        $showtime->load(['movie', 'screen', 'screen.theater', 'format']);
        return $showtime;
    }

    /**
     * Delete a showtime.
     */
    public function delete(int $id): bool
    {
        $showtime = Showtime::findOrFail($id);
        return (bool) $showtime->delete();
    }

    /**
     * Get showtimes for a movie by slug or ID, grouped by theater then format.
     * Only shows next 5 days, excludes showtimes that started more than 20min ago.
     */
    public function getMovieShowtimes(string|int $slugOrId): array
    {
        $movieQuery = Movie::query()->where('status', 1);

        if (is_numeric($slugOrId)) {
            $movieQuery->where('id', (int) $slugOrId);
        } else {
            $movieQuery->where('slug', $slugOrId);
        }

        $movie = $movieQuery->firstOrFail();

        $showtimes = $this->getFilteredShowtimes($movie->id);
        $grouped = $this->groupShowtimes($showtimes);

        return [
            'movie' => $this->transformMovie($movie),
            'showtimes_grouped' => $grouped,
        ];
    }

    /**
     * Get filtered showtimes (5 days, exclude past 20min)
     */
    private function getFilteredShowtimes(int $movieId): Collection
    {
        $now = Carbon::now();
        $cutoffTime = $now->copy()->subMinutes(20);
        $endDate = $now->copy()->addDays(5)->endOfDay();

        return Showtime::with([
                'screen.theater:id,name,address,city',
                'screen:id,name,code,theater_id',
                'format:id,name,surcharge',
                'sound:id,name',
                'subtitle:id,name',
            ])
            ->where('movie_id', $movieId)
            ->where('status', 1)
            ->where('scheduled_at', '>', $cutoffTime)
            ->where('scheduled_at', '<=', $endDate)
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * Group showtimes by theater -> format
     */
    private function groupShowtimes(Collection $showtimes): array
    {
        $grouped = [];

        foreach ($showtimes as $showtime) {
            $theaterId = $showtime->screen->theater_id;
            $formatId = $showtime->format_id ?? 0;

            if (!isset($grouped[$theaterId])) {
                $grouped[$theaterId] = [
                    'theater' => [
                        'id' => $showtime->screen->theater->id,
                        'name' => $showtime->screen->theater->name,
                        'address' => $showtime->screen->theater->address,
                        'city' => $showtime->screen->theater->city,
                    ],
                    'formats' => [],
                ];
            }

            if (!isset($grouped[$theaterId]['formats'][$formatId])) {
                $grouped[$theaterId]['formats'][$formatId] = [
                    'format' => [
                        'id' => $showtime->format?->id,
                        'name' => $showtime->format?->name ?? 'Standard',
                        'slug' => $showtime->format?->name ? \Illuminate\Support\Str::slug($showtime->format->name) : 'standard',
                        'description' => null,
                        'surcharge' => $showtime->format?->surcharge ?? 0,
                    ],
                    'showtimes' => [],
                ];
            }

            $grouped[$theaterId]['formats'][$formatId]['showtimes'][] = [
                'id' => $showtime->id,
                'encrypted_id' => $showtime->encrypted_id,
                'time' => $showtime->scheduled_at->format('H:i'),
                'screen' => [
                    'id' => $showtime->screen->id,
                    'name' => $showtime->screen->name,
                ],
                'subtitle' => [
                    'id' => $showtime->subtitle?->id,
                    'name' => $showtime->subtitle?->name ?? 'N/A',
                ],
                'sound' => [
                    'id' => $showtime->sound?->id,
                    'name' => $showtime->sound?->name ?? 'N/A',
                ],
                'scheduled_date' => $showtime->scheduled_at->format('Y-m-d'),
            ];
        }

        return array_values(array_map(function ($theaterGroup) {
            $theaterGroup['formats'] = array_values($theaterGroup['formats']);
            return $theaterGroup;
        }, $grouped));
    }

    /**
     * Transform movie data
     */
    private function transformMovie(Movie $movie): array
    {
        return [
            'id' => $movie->id,
            'title' => $movie->title,
            'slug' => $movie->slug,
            'duration' => $movie->duration,
            'age_rating' => $movie->age_rating,
            'poster_url' => $movie->poster_url,
        ];
    }

    /**
     * Apply filters to showtime query based on request parameters.
     */
    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('movie_id')) {
            $query->where('movie_id', $request->movie_id);
        }

        if ($request->filled('screen_id')) {
            $query->where('screen_id', $request->screen_id);
        }

        if ($request->filled('theater_id')) {
            $query->whereHas('screen', fn ($q) => $q->where('theater_id', $request->theater_id));
        }

        if ($request->filled('format_id')) {
            $query->where('format_id', $request->format_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('scheduled_at', $request->date);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('scheduled_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('scheduled_at', '<=', $request->date_to);
        }

        $status = $request->query('status', 'active');
        if ($status === 'active') {
            $query->where('status', 1);
        } elseif ($status === 'inactive') {
            $query->where('status', 0);
        }

        if ($request->boolean('upcoming', true)) {
            $query->where('scheduled_at', '>=', now());
        }

        if ($request->filled('q')) {
            $search = $request->q;
            $query->whereHas('movie', fn ($q) => $q->where('title', 'like', "%{$search}%"));
        }

        return $query;
    }

    /**
     * Apply sorting to showtime query.
     */
    private function applySorting(Builder $query, Request $request): Builder
    {
        $sortField = $request->query('sort_by', 'scheduled_at');
        $sortDir = $request->query('sort_dir', 'asc');

        $allowedSortFields = ['scheduled_at', 'price', 'created_at'];
        if (!in_array($sortField, $allowedSortFields, true)) {
            $sortField = 'scheduled_at';
        }

        $sortDir = $sortDir === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortField, $sortDir);
    }

    /**
     * Enrich showtime with computed fields.
     */
    private function enrichShowtime(Showtime $showtime): Showtime
    {
        $showtime->start_time = $showtime->scheduled_at
            ? $showtime->scheduled_at->format('Y-m-d H:i:s')
            : null;

        $showtime->end_time_estimated = $showtime->scheduled_at && $showtime->movie
            ? $showtime->scheduled_at->copy()->addMinutes($showtime->movie->duration)->format('Y-m-d H:i:s')
            : null;

        return $showtime;
    }
}
