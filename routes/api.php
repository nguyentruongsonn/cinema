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
use App\Http\Controllers\User\PaymentController as UserPaymentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RevenueController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\Api\V1\TicketController;

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

// PayOS Webhook - OUTSIDE versioning (external service, URL already configured)
Route::post('payos/webhook', [UserPaymentController::class, 'handleWebhook'])
    ->middleware(['verify.payos', 'throttle:webhook']);

// API v1 Routes
Route::prefix('v1')->group(function () {
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
        Route::get('showtime/{encryptedShowtimeId}', [SeatController::class, 'getByShowtime']);
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
        Route::prefix('seats')->middleware('throttle:seats')->group(function () {
            Route::post('lock', [SeatController::class, 'lock']);
            Route::delete('unlock/{holdId}', [SeatController::class, 'unlock']);
        });

        // Order routes
        Route::prefix('orders')->middleware('throttle:orders')->group(function () {
            Route::post('/', [OrderController::class, 'store']);
            Route::get('user/me', [OrderController::class, 'userOrders']);
            Route::get('{id}', [OrderController::class, 'show'])->whereNumber('id');
            Route::delete('{id}', [OrderController::class, 'cancel'])->whereNumber('id');
        });

        // Payment routes
        Route::prefix('payments')->middleware('throttle:payments')->group(function () {
            Route::post('/', [UserPaymentController::class, 'createPayment']);
            Route::get('orders/{orderCode}', [UserPaymentController::class, 'showOrderSummary']);
        });

        // Ticket routes
        Route::prefix('tickets')->middleware('throttle:tickets')->group(function () {
            Route::get('/', [TicketController::class, 'index']);
            Route::get('{ticketCode}', [TicketController::class, 'show']);
        });

        // User promotion/voucher routes
        Route::prefix('promotions')->group(function () {
            Route::get('registered', [PromotionController::class, 'registered']);
            Route::post('register', [PromotionController::class, 'register']);
            Route::get('{code}/validate', [PromotionController::class, 'validate']);
        });
    });

    // Public auth routes (forgot/reset password) - rate limited to prevent abuse
    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
        Route::post('verify-email', [AuthController::class, 'verifyEmail']);
    });

    // Admin routes
    Route::middleware(['auth:api', 'role:admin,super-admin'])->group(function () {
        Route::get('admin/dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('admin/revenue/stats', [RevenueController::class, 'stats']);
        Route::get('admin/tickets/stats', [\App\Http\Controllers\Admin\TicketStatController::class, 'stats']);
        Route::get('admin/combos/stats', [\App\Http\Controllers\Admin\ComboStatController::class, 'stats']);
        Route::get('admin/food/stats', [\App\Http\Controllers\Admin\FoodStatController::class, 'stats']);

        // Movie management
        Route::prefix('admin/movies')->group(function () {
            Route::post('/', [MovieController::class, 'store']);
            Route::put('{id}', [MovieController::class, 'update']);
            Route::post('{id}/update', [MovieController::class, 'update']); // FormData upload (POST + _method=PUT)
            Route::delete('{id}', [MovieController::class, 'destroy']);
            Route::post('{id}/toggle-active', [MovieController::class, 'toggleActive']);
            Route::post('{id}/toggle-hot', [MovieController::class, 'toggleHot']);
        });

        // Branch management
        Route::prefix('admin/branches')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\BranchController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Admin\BranchController::class, 'store']);
            Route::put('{branch}', [\App\Http\Controllers\Admin\BranchController::class, 'update']);
            Route::delete('{branch}', [\App\Http\Controllers\Admin\BranchController::class, 'destroy']);
            Route::post('{branch}/toggle-active', [\App\Http\Controllers\Admin\BranchController::class, 'toggleActive']);
        });

        // Theater management
        Route::prefix('admin/theaters')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\TheaterController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Admin\TheaterController::class, 'store']);
            Route::put('{theater}', [\App\Http\Controllers\Admin\TheaterController::class, 'update']);
            Route::delete('{theater}', [\App\Http\Controllers\Admin\TheaterController::class, 'destroy']);
            Route::post('{theater}/toggle-active', [\App\Http\Controllers\Admin\TheaterController::class, 'toggleActive']);
        });


        // Seat layout templates
        Route::prefix('admin/seat-layout-templates')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\SeatLayoutTemplateController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Admin\SeatLayoutTemplateController::class, 'store']);
            Route::put('{seatLayoutTemplate}', [\App\Http\Controllers\Admin\SeatLayoutTemplateController::class, 'update']);
            Route::delete('{seatLayoutTemplate}', [\App\Http\Controllers\Admin\SeatLayoutTemplateController::class, 'destroy']);
            Route::post('{seatLayoutTemplate}/toggle-active', [\App\Http\Controllers\Admin\SeatLayoutTemplateController::class, 'toggleActive']);
        });

        // Screen management
        Route::prefix('admin/screens')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\ScreenController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Admin\ScreenController::class, 'store']);
            Route::put('{screen}', [\App\Http\Controllers\Admin\ScreenController::class, 'update']);
            Route::delete('{screen}', [\App\Http\Controllers\Admin\ScreenController::class, 'destroy']);
            Route::post('{screen}/toggle-active', [\App\Http\Controllers\Admin\ScreenController::class, 'toggleActive']);
            Route::get('{screen}/seats', [\App\Http\Controllers\Admin\ScreenController::class, 'showSeats']);
            Route::post('{screen}/seats/update', [\App\Http\Controllers\Admin\ScreenController::class, 'updateSeats']);
        });

        // Formats (Loại phòng)
        Route::prefix('admin/formats')->group(function () {
            Route::post('/', [\App\Http\Controllers\Admin\ScreenController::class, 'storeFormat']);
            Route::put('{format}', [\App\Http\Controllers\Admin\ScreenController::class, 'updateFormat']);
            Route::delete('{format}', [\App\Http\Controllers\Admin\ScreenController::class, 'destroyFormat']);
        });

        // Sounds (Âm thanh)
        Route::prefix('admin/sounds')->group(function () {
            Route::post('/', [\App\Http\Controllers\Admin\ScreenController::class, 'storeSound']);
            Route::put('{sound}', [\App\Http\Controllers\Admin\ScreenController::class, 'updateSound']);
            Route::delete('{sound}', [\App\Http\Controllers\Admin\ScreenController::class, 'destroySound']);
        });

        // Showtime management
        Route::prefix('admin/showtimes')->group(function () {
            Route::get('/', [\App\Http\Controllers\ShowtimeController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\ShowtimeController::class, 'store']);
            Route::post('bulk', [\App\Http\Controllers\ShowtimeController::class, 'bulkCreate']);
            Route::post('bulk-single', [\App\Http\Controllers\ShowtimeController::class, 'bulkSingleDay']);
            Route::put('{id}', [\App\Http\Controllers\ShowtimeController::class, 'update']);
            Route::delete('{id}', [\App\Http\Controllers\ShowtimeController::class, 'destroy']);
        });

        // Product management
        Route::prefix('admin/products')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\ProductController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Admin\ProductController::class, 'store']);
            Route::put('{product}', [\App\Http\Controllers\Admin\ProductController::class, 'update']);
            Route::delete('{product}', [\App\Http\Controllers\Admin\ProductController::class, 'destroy']);
            Route::post('{product}/toggle-active', [\App\Http\Controllers\Admin\ProductController::class, 'toggleActive']);
        });
    });

    // Public product routes
    Route::get('products', [ProductController::class, 'index']);

    // Pricing routes (public — không cần đăng nhập)
    Route::prefix('pricing')->group(function () {
        Route::match(['get', 'post'], 'calculate',     [\App\Http\Controllers\PricingController::class, 'calculate']);
        Route::match(['get', 'post'], 'calculate-all', [\App\Http\Controllers\PricingController::class, 'calculateAll']);
        Route::get('weekly-table',                     [\App\Http\Controllers\PricingController::class, 'weeklyTable']);
        Route::get('showtime/{id}',                    [\App\Http\Controllers\PricingController::class, 'fromShowtime']);
    });


    // Broadcasting channel auth – uses JWT instead of web session
    Route::middleware('auth:api')->post('broadcasting/auth', function (\Illuminate\Http\Request $request) {
        return \Illuminate\Support\Facades\Broadcast::auth($request);
    });
});
