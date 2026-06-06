<?php

namespace App\Services;

use App\Models\Movie;
use App\Models\Showtime;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ShowtimeService
{
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
}
