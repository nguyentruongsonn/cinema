<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Movie;
use App\Models\Showtime;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ShowtimeService
{
    private const TRANSACTION_ATTEMPTS = 3;

    /**
     * Get paginated, filterable list of showtimes for admin/catalog.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Showtime::with([
            'movie:id,title,slug,duration,age_rating,poster_url,poster_path',
            'screen:id,name,code,format_id,theater_id,capacity',
            'screen.theater:id,name,address,branch_id',
            'screen.theater.branch:id,name',
            'screen.format:id,name',
            'format:id,name,surcharge',
            'versionType:id,name,slug',
        ]);

        $query = $this->applyFilters($query, $filters);
        $query = $this->applySorting($query, $filters);

        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));
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
            'versionType',
            'seatLayoutSnapshot',
        ])->findOrFail($id);

        return $this->enrichShowtime($showtime);
    }

    /**
     * Create a new showtime.
     */
    public function create(array $data): Showtime
    {
        return DB::transaction(function () use ($data): Showtime {
            $payload = $this->showtimePayload($data);

            $this->assertNoScheduleConflict($payload);

            $showtime = Showtime::create($payload);
            $showtime->load(['movie', 'screen', 'screen.theater', 'format']);

            return $showtime;
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Update an existing showtime.
     */
    public function update(int $id, array $data): Showtime
    {
        return DB::transaction(function () use ($id, $data): Showtime {
            $showtime = Showtime::query()
                ->lockForUpdate()
                ->findOrFail($id);

            $payload = $this->showtimePayload($data);
            $merged = array_merge($showtime->only([
                'movie_id',
                'screen_id',
                'format_id',
                'version_type_id',
                'price_rule_id',
                'scheduled_at',
                'pricing_snapshot',
                'status',
            ]), $payload);

            $this->assertNoScheduleConflict($merged, $showtime->id);

            $showtime->update($payload);
            $showtime->load(['movie', 'screen', 'screen.theater', 'format']);

            return $showtime;
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Delete a showtime.
     */
    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            $showtime = Showtime::query()
                ->withCount(['orders', 'seatHolds'])
                ->lockForUpdate()
                ->findOrFail($id);

            if ($showtime->orders_count > 0 || $showtime->seat_holds_count > 0) {
                throw ValidationException::withMessages([
                    'showtime' => 'Cannot delete a showtime with existing orders or seat holds.',
                ]);
            }

            return (bool) $showtime->delete();
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Bulk create showtimes across a date range with multiple times per day.
     *
     * @param array $data Contains: movie_id, screen_id, date_from, date_to, times[], format_id?, version_type_id?
     * @return array ['created' => int, 'skipped' => int]
     */
    public function bulkCreateDateRange(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $created = 0;
            $skipped = 0;
            $from = new \DateTime($data['date_from']);
            $to = new \DateTime($data['date_to']);
            $to->modify('+1 day'); // inclusive

            $base = $this->showtimePayload([
                'movie_id' => $data['movie_id'],
                'screen_id' => $data['screen_id'],
                'format_id' => $data['format_id'] ?? null,
                'version_type_id' => $data['version_type_id'] ?? null,
                'status' => 1,
            ]);

            for ($d = clone $from; $d < $to; $d->modify('+1 day')) {
                foreach ($data['times'] as $time) {
                    $payload = array_merge($base, [
                        'scheduled_at' => $d->format('Y-m-d') . ' ' . $time . ':00',
                    ]);

                    if ($this->hasScheduleConflict($payload)) {
                        $skipped++;
                        continue;
                    }

                    Showtime::create($payload);
                    $created++;
                }
            }

            return ['created' => $created, 'skipped' => $skipped];
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Bulk create showtimes for a single day with multiple time/screen slots.
     *
     * @param array $data Contains: movie_id, date, slots[{time, screen_id}], format_id?, version_type_id?, status?
     * @return array ['created' => int, 'skipped' => int]
     */
    public function bulkCreateSingleDay(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $created = 0;
            $skipped = 0;

            foreach ($data['slots'] as $slot) {
                $payload = $this->showtimePayload([
                    'movie_id' => $data['movie_id'],
                    'screen_id' => $slot['screen_id'],
                    'scheduled_at' => $data['date'] . ' ' . $slot['time'] . ':00',
                    'format_id' => $data['format_id'] ?? null,
                    'version_type_id' => $data['version_type_id'] ?? null,
                    'status' => $data['status'] ?? 1,
                ]);

                if ($this->hasScheduleConflict($payload)) {
                    $skipped++;
                    continue;
                }

                Showtime::create($payload);
                $created++;
            }

            return ['created' => $created, 'skipped' => $skipped];
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Get a showtime that is allowed to enter the booking flow.
     */
    public function getBookableShowtimeForBookingPage(int $showtimeId): Showtime
    {
        $showtime = Showtime::with([
            'movie',
            'screen.theater',
            'format',
            'versionType',
        ])->findOrFail($showtimeId);

        if ((int) $showtime->status !== 1) {
            throw new HttpException(403, 'Suất chiếu này không khả dụng.');
        }

        if (!$showtime->screen || (int) $showtime->screen->status !== 1) {
            throw new HttpException(403, 'Phòng chiếu của suất chiếu này tạm thời ngưng hoạt động.');
        }

        if (!$showtime->screen->theater || (int) $showtime->screen->theater->status !== 1) {
            throw new HttpException(403, 'Rạp chiếu của suất chiếu này tạm thời ngưng hoạt động.');
        }

        if (!$showtime->screen->theater->branch || !$showtime->screen->theater->branch->is_active) {
            throw new HttpException(403, 'Chi nhánh của suất chiếu này tạm thời ngưng hoạt động.');
        }

        if ($showtime->scheduled_at === null || $showtime->scheduled_at->isPast()) {
            throw new HttpException(403, 'Suất chiếu này đã bắt đầu hoặc kết thúc. Không thể đặt vé.');
        }

        return $showtime;
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
     * Get filtered showtimes (5 days, exclude past showtimes)
     */
    private function getFilteredShowtimes(int $movieId): Collection
    {
        $now = Carbon::now();
        $endDate = $now->copy()->addDays(5)->endOfDay();

        return Showtime::where('movie_id', $movieId)
            ->where('status', 1)
            ->where('scheduled_at', '>', $now)
            ->where('scheduled_at', '<=', $endDate)
            ->whereHas('screen', function ($q) {
                $q->where('status', 1);
            })
            ->whereHas('screen.theater', function ($q) {
                $q->where('status', 1);
            })
            ->whereHas('screen.theater.branch', function ($q) {
                $q->where('is_active', true);
            })
            ->with([
                'screen.theater:id,name,address,branch_id',
                'screen.theater.branch:id,name',
                'screen:id,name,code,theater_id',
                'format:id,name,surcharge',
                'versionType:id,name,slug',
            ])
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
                        'city' => $showtime->screen->theater->branch?->name ?? '',
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
                'version_type' => [
                    'id' => $showtime->versionType?->id,
                    'name' => $showtime->versionType?->name ?? 'N/A',
                    'slug' => $showtime->versionType?->slug ?? 'standard',
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
            'poster_url' => $movie->poster_display_url,
            'poster_display_url' => $movie->poster_display_url,
        ];
    }

    /**
     * Return only fields that are safe for showtime creation/update.
     */
    private function showtimePayload(array $data): array
    {
        return Arr::only($data, [
            'movie_id',
            'screen_id',
            'format_id',
            'version_type_id',
            'price_rule_id',
            'scheduled_at',
            'pricing_snapshot',
            'status',
        ]);
    }

    /**
     * Assert that a showtime does not overlap another showtime in the same screen.
     */
    private function assertNoScheduleConflict(array $payload, ?int $ignoreShowtimeId = null): void
    {
        if ($this->hasScheduleConflict($payload, $ignoreShowtimeId)) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'The selected screen already has an overlapping showtime.',
            ]);
        }
    }

    /**
     * Check schedule overlap using the movie duration and same-screen locking.
     */
    private function hasScheduleConflict(array $payload, ?int $ignoreShowtimeId = null): bool
    {
        if (empty($payload['movie_id']) || empty($payload['screen_id']) || empty($payload['scheduled_at'])) {
            return false;
        }

        $movie = Movie::query()
            ->select(['id', 'duration'])
            ->lockForUpdate()
            ->findOrFail((int) $payload['movie_id']);

        $duration = max((int) $movie->duration, 1);
        $start = Carbon::parse($payload['scheduled_at']);
        $end = $start->copy()->addMinutes($duration);

        return Showtime::query()
            ->select(['id', 'movie_id', 'scheduled_at'])
            ->with('movie:id,duration')
            ->where('screen_id', (int) $payload['screen_id'])
            ->where('scheduled_at', '<', $end)
            ->when($ignoreShowtimeId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreShowtimeId))
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->contains(function (Showtime $showtime) use ($start): bool {
                $existingDuration = max((int) $showtime->movie?->duration, 1);
                $existingEnd = $showtime->scheduled_at->copy()->addMinutes($existingDuration);

                return $existingEnd->greaterThan($start);
            });
    }

    /**
     * Escape SQL LIKE wildcards.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Apply filters to showtime query based on request parameters.
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['movie_id'])) {
            $query->where('movie_id', $filters['movie_id']);
        }

        if (! empty($filters['screen_id'])) {
            $query->where('screen_id', $filters['screen_id']);
        }

        if (! empty($filters['theater_id'])) {
            $query->whereHas('screen', fn ($q) => $q->where('theater_id', $filters['theater_id']));
        }

        if (! empty($filters['format_id'])) {
            $query->where('format_id', $filters['format_id']);
        }

        if (! empty($filters['date'])) {
            $query->whereBetween('scheduled_at', [
                Carbon::parse($filters['date'])->startOfDay(),
                Carbon::parse($filters['date'])->endOfDay(),
            ]);
        }

        if (! empty($filters['date_from'])) {
            $query->where('scheduled_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (! empty($filters['date_to'])) {
            $query->where('scheduled_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        $status = $filters['status'] ?? 'active';
        if ($status === 'active') {
            $query->where('status', 1);
        } elseif ($status === 'inactive') {
            $query->where('status', 0);
        }

        if (($filters['upcoming'] ?? true) === true) {
            $query->where('scheduled_at', '>=', now());
        }

        if (! empty($filters['q'])) {
            $search = $this->escapeLike((string) $filters['q']);
            $query->whereHas('movie', fn ($q) => $q->where('title', 'like', "%{$search}%"));
        }

        return $query;
    }

    /**
     * Apply sorting to showtime query.
     */
    private function applySorting(Builder $query, array $filters): Builder
    {
        $sortField = $filters['sort_by'] ?? 'scheduled_at';
        $sortDir = $filters['sort_dir'] ?? 'asc';

        $allowedSortFields = ['scheduled_at', 'created_at'];
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
