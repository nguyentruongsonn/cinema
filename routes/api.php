<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\TheaterController;
use App\Http\Controllers\ScreenController;
use App\Http\Controllers\SeatController;
use App\Http\Controllers\ShowtimeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PromotionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public home page data route
Route::get('home', [HomeController::class, 'data']);

// Public routes
Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('google', [AuthController::class, 'googleLogin']);
    Route::post('refresh', [AuthController::class, 'refresh']); // Refresh token from HttpOnly cookie
});

// Public movie routes
Route::prefix('movies')->group(function () {
    Route::get('/', [MovieController::class, 'index'])->name('api.movies.index');
    Route::get('now-showing', [MovieController::class, 'nowShowing'])->name('api.movies.now-showing');
    Route::get('coming-soon', [MovieController::class, 'comingSoon'])->name('api.movies.coming-soon');
    Route::get('search', [MovieController::class, 'search'])->name('api.movies.search');
    Route::get('{slug}', [MovieController::class, 'show'])->name('api.movies.show');
    Route::get('{slug}/showtimes', [ShowtimeController::class, 'getMovieShowtimes'])->name('api.movies.showtimes');
});

// Public theater routes
Route::prefix('theaters')->group(function () {
    Route::get('/', [TheaterController::class, 'index']);
    Route::get('cities', [TheaterController::class, 'cities']);
    Route::get('{id}/screens', [TheaterController::class, 'screens']);
    Route::get('{id}', [TheaterController::class, 'show']);
});

// Public screen routes
Route::prefix('screens')->group(function () {
    Route::get('/', [ScreenController::class, 'index']);
    Route::get('{id}', [ScreenController::class, 'show']);
});

// Public showtime routes
Route::prefix('showtimes')->group(function () {
    Route::get('/', [ShowtimeController::class, 'index']);
    Route::get('{id}', [ShowtimeController::class, 'show']);
});

Route::prefix('seats')->group(function () {
    Route::get('showtime/{showtimeId}', [SeatController::class, 'getByShowtime']);
});

// Protected routes
Route::middleware('auth:api')->group(function () {
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('profile', [AuthController::class, 'profile']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::post('send-verification-email', [AuthController::class, 'sendVerificationEmail']);
    });

    // Seat routes
    Route::prefix('seats')->group(function () {
        Route::post('lock', [SeatController::class, 'lock']);
        Route::delete('unlock/{holdId}', [SeatController::class, 'unlock']);
    });

    // Order routes
    Route::prefix('orders')->group(function () {
        Route::post('/', [OrderController::class, 'store']);
        Route::get('user/me', [OrderController::class, 'userOrders']);
        Route::get('{id}', [OrderController::class, 'show'])->whereNumber('id');
        Route::put('{id}/cancel', [OrderController::class, 'cancel'])->whereNumber('id');
    });

    // Payment routes
    Route::prefix('payments')->group(function () {
        Route::post('/', [PaymentController::class, 'store']);
        Route::get('{id}', [PaymentController::class, 'show']);
        Route::post('{id}/verify', [PaymentController::class, 'verify']);
    });
});

// Public auth routes (forgot/reset password)
Route::prefix('auth')->group(function () {
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('verify-email', [AuthController::class, 'verifyEmail']);
});

// Admin routes
Route::middleware(['auth:api', 'role:admin,super-admin'])->group(function () {
    Route::get('admin/dashboard/stats', [DashboardController::class, 'stats']);

    // Movie management
    Route::prefix('admin/movies')->group(function () {
        Route::post('/', [MovieController::class, 'store']);
        Route::put('{id}', [MovieController::class, 'update']);
        Route::delete('{id}', [MovieController::class, 'destroy']);
    });

    // Theater management
    Route::prefix('admin/theaters')->group(function () {
        Route::post('/', [TheaterController::class, 'store']);
        Route::put('{id}', [TheaterController::class, 'update']);
        Route::delete('{id}', [TheaterController::class, 'destroy']);
    });

    // Screen management
    Route::prefix('admin/screens')->group(function () {
        Route::post('/', [ScreenController::class, 'store']);
        Route::put('{id}', [ScreenController::class, 'update']);
        Route::delete('{id}', [ScreenController::class, 'destroy']);
    });

    // Showtime management
    Route::prefix('admin/showtimes')->group(function () {
        Route::post('/', [ShowtimeController::class, 'store']);
        Route::put('{id}', [ShowtimeController::class, 'update']);
        Route::delete('{id}', [ShowtimeController::class, 'destroy']);
    });
});

// Public product routes
Route::get('products', [ProductController::class, 'index']);

// Public promotion routes
Route::prefix('promotions')->group(function () {
    Route::post('validate', [PromotionController::class, 'validate']);
});
