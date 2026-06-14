<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use App\Models\Showtime;
use App\Models\Theater;
use App\Traits\ApiResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class HomeController extends Controller
{
    use ApiResponse;

    public function index(): View
    {
        return view('users.home');
    }

    public function data(): JsonResponse
    {
        $featuredMovie = Movie::query()
            ->with('categories:id,name')
            ->active()
            ->where(function ($query) {
                $query->where('is_hot', true)
                    ->orWhereHas('showtimes', function ($showtimeQuery) {
                        $showtimeQuery->available()->upcoming();
                    });
            })
            ->orderByDesc('is_hot')
            ->latest('release_date')
            ->first();

        $nowShowingMovies = Movie::query()
            ->with('categories:id,name')
            ->nowShowing()
            ->latest('release_date')
            ->limit(8)
            ->get();

        $upcomingMovies = Movie::query()
            ->with('categories:id,name')
            ->upcoming()
            ->orderBy('release_date')
            ->limit(8)
            ->get();

        $movieOptions = Movie::query()
            ->active()
            ->whereHas('showtimes', function ($query) {
                $query->available()->upcoming();
            })
            ->orderBy('title')
            ->get(['id', 'title']);

        $cinemaOptions = Theater::query()
            ->active()
            ->with('branch:id,name')
            ->get(['id', 'name', 'branch_id'])
            ->sortBy(function($theater) {
                return ($theater->branch?->name ?? '') . '_' . $theater->name;
            })
            ->values()
            ->map(function($theater) {
                return [
                    'id' => $theater->id,
                    'name' => $theater->name,
                    'city' => $theater->branch?->name ?? '',
                ];
            });

        $availableDates = Showtime::query()
            ->available()
            ->upcoming()
            ->selectRaw('DATE(scheduled_at) as show_date')
            ->distinct()
            ->orderByRaw('show_date asc')
            ->limit(7)
            ->pluck('show_date')
            ->map(function ($date) {
                $parsedDate = Carbon::parse($date)->locale('vi');

                return [
                    'value' => $parsedDate->format('Y-m-d'),
                    'label' => $parsedDate->isoFormat('dddd, DD/MM'),
                ];
            })
            ->values();

        if (! $featuredMovie) {
            $featuredMovie = $nowShowingMovies->first() ?? $upcomingMovies->first();
        }

        return $this->successResponse([
            'featured_movie' => $this->transformMovie($featuredMovie),
            'now_showing_movies' => $nowShowingMovies->map(fn (Movie $movie) => $this->transformMovie($movie))->values(),
            'upcoming_movies' => $upcomingMovies->map(fn (Movie $movie) => $this->transformMovie($movie))->values(),
            'movie_options' => $movieOptions,
            'cinema_options' => $cinemaOptions,
            'available_dates' => $availableDates,
        ], 'Home data loaded successfully');
    }

    private function transformMovie(?Movie $movie): ?array
    {
        if (! $movie) {
            return null;
        }

        // Parse backdrops JSON if it exists
        $backdrops = [];
        if ($movie->backdrops) {
            $decoded = is_string($movie->backdrops) ? json_decode($movie->backdrops, true) : $movie->backdrops;
            $backdrops = is_array($decoded) ? $decoded : [];
        }

        return [
            'id' => $movie->id,
            'title' => $movie->title,
            'slug' => $movie->slug,
            'description' => $movie->description,
            'poster_url' => $movie->poster_url,
            'backdrop_url' => !empty($backdrops) ? $backdrops[0] : $movie->poster_url,
            'backdrops' => $backdrops,
            'trailer_url' => $movie->trailer_url,
            'age_rating' => $movie->age_rating,
            'duration' => $movie->duration,
            'release_date' => optional($movie->release_date)->format('Y-m-d'),
            'categories' => $movie->categories
                ->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])
                ->values(),
        ];
    }
}
