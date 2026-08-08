<?php

use App\Http\Controllers\Api\V1\OperationalHealthController;
use App\Http\Controllers\Api\V1\PriceController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\ScreenController;
use App\Http\Controllers\SeatController;
use App\Http\Controllers\ShowtimeController;
use App\Http\Controllers\TheaterController;
use Illuminate\Support\Facades\Route;

Route::get('home', [HomeController::class, 'data']);
Route::get('banners', [ContentController::class, 'banners']);
Route::get('posts', [ContentController::class, 'posts']);
Route::get('posts/{post:slug}', [ContentController::class, 'post']);
Route::get('health/live', [OperationalHealthController::class, 'live']);
Route::get('health/ready', [OperationalHealthController::class, 'ready']);
Route::get('docs/openapi.json', [OperationalHealthController::class, 'openApi'])->name('api.docs.openapi');
Route::get('internal/metrics', [OperationalHealthController::class, 'metrics'])->middleware('internal.metrics');

Route::prefix('movies')->group(function () {
    Route::get('/', [MovieController::class, 'index'])->name('api.movies.index');
    Route::get('now-showing', [MovieController::class, 'nowShowing'])->name('api.movies.now-showing');
    Route::get('coming-soon', [MovieController::class, 'comingSoon'])->name('api.movies.coming-soon');
    Route::get('search', [MovieController::class, 'search'])->name('api.movies.search');
    Route::get('{slug}', [MovieController::class, 'show'])->name('api.movies.show');
    Route::get('{slug}/showtimes', [ShowtimeController::class, 'getMovieShowtimes'])->name('api.movies.showtimes');
});

Route::get('prices', [PriceController::class, 'index']);
Route::get('products', [ProductController::class, 'index']);
Route::get('promotions/{code}/validate', [PromotionController::class, 'validate']);

Route::prefix('theaters')->group(function () {
    Route::get('/', [TheaterController::class, 'index']);
    Route::get('cities', [TheaterController::class, 'cities']);
    Route::get('{id}/screens', [TheaterController::class, 'screens']);
    Route::get('{id}', [TheaterController::class, 'show']);
});

Route::prefix('screens')->group(function () {
    Route::get('/', [ScreenController::class, 'index']);
    Route::get('{id}', [ScreenController::class, 'show']);
});

Route::prefix('showtimes')->group(function () {
    Route::get('/', [ShowtimeController::class, 'index']);
    Route::get('{id}', [ShowtimeController::class, 'show']);
});

Route::get('seats/showtime/{encryptedShowtimeId}', [SeatController::class, 'getByShowtime']);

Route::prefix('pricing')->group(function () {
    Route::match(['get', 'post'], 'calculate', [PricingController::class, 'calculate']);
    Route::match(['get', 'post'], 'calculate-all', [PricingController::class, 'calculateAll']);
    Route::get('weekly-table', [PricingController::class, 'weeklyTable']);
    Route::get('showtime/{id}', [PricingController::class, 'fromShowtime']);
});
