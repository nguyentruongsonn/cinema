<?php

use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\ComboController;
use App\Http\Controllers\Admin\ComboStatController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FoodStatController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\RevenueController;
use App\Http\Controllers\Admin\ScreenController;
use App\Http\Controllers\Admin\SeatLayoutTemplateController;
use App\Http\Controllers\Admin\TheaterController;
use App\Http\Controllers\Admin\PricingRuleController;
use App\Http\Controllers\Admin\TicketStatController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ShowtimeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(['auth:api', 'role:admin,super-admin'])->group(function () {
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('revenue/stats', [RevenueController::class, 'stats']);
    Route::get('tickets/stats', [TicketStatController::class, 'stats']);
    Route::get('combos/stats', [ComboStatController::class, 'stats']);
    Route::get('food/stats', [FoodStatController::class, 'stats']);
    Route::get('orders', [OrderController::class, 'adminOrders']);
    Route::get('orders/{id}', [OrderController::class, 'adminOrder'])->whereNumber('id');

    Route::prefix('movies')->group(function () {
        Route::post('/', [MovieController::class, 'store']);
        Route::put('{id}', [MovieController::class, 'update']);
        Route::post('{id}/update', [MovieController::class, 'update']);
        Route::delete('{id}', [MovieController::class, 'destroy']);
        Route::post('{id}/toggle-active', [MovieController::class, 'toggleActive']);
        Route::post('{id}/toggle-hot', [MovieController::class, 'toggleHot']);
    });

    Route::prefix('branches')->group(function () {
        Route::get('/', [BranchController::class, 'index']);
        Route::post('/', [BranchController::class, 'store']);
        Route::put('{branch}', [BranchController::class, 'update']);
        Route::delete('{branch}', [BranchController::class, 'destroy']);
        Route::post('{branch}/toggle-active', [BranchController::class, 'toggleActive']);
    });

    Route::prefix('theaters')->group(function () {
        Route::get('/', [TheaterController::class, 'index']);
        Route::post('/', [TheaterController::class, 'store']);
        Route::put('{theater}', [TheaterController::class, 'update']);
        Route::delete('{theater}', [TheaterController::class, 'destroy']);
        Route::post('{theater}/toggle-active', [TheaterController::class, 'toggleActive']);
    });

    Route::prefix('pricing-rules')->group(function () {
        Route::get('holidays', [PricingRuleController::class, 'getHolidays']);
        Route::post('holidays', [PricingRuleController::class, 'storeHoliday']);
        Route::put('holidays/{holiday}', [PricingRuleController::class, 'updateHoliday']);
        Route::delete('holidays/{holiday}', [PricingRuleController::class, 'destroyHoliday']);
        Route::post('holidays/{holiday}/toggle-active', [PricingRuleController::class, 'toggleHolidayActive']);

        Route::get('day-rules', [PricingRuleController::class, 'getDayRules']);
        Route::put('day-rules', [PricingRuleController::class, 'updateDayRules']);

        Route::get('time-slots', [PricingRuleController::class, 'getTimeSlots']);
        Route::post('time-slots', [PricingRuleController::class, 'storeTimeSlot']);
        Route::put('time-slots/{timeSlot}', [PricingRuleController::class, 'updateTimeSlot']);
        Route::delete('time-slots/{timeSlot}', [PricingRuleController::class, 'destroyTimeSlot']);
        Route::post('time-slots/{timeSlot}/toggle-active', [PricingRuleController::class, 'toggleTimeSlotActive']);
    });

    Route::prefix('seat-layout-templates')->group(function () {
        Route::get('/', [SeatLayoutTemplateController::class, 'index']);
        Route::post('/', [SeatLayoutTemplateController::class, 'store']);
        Route::put('{seatLayoutTemplate}', [SeatLayoutTemplateController::class, 'update']);
        Route::delete('{seatLayoutTemplate}', [SeatLayoutTemplateController::class, 'destroy']);
        Route::post('{seatLayoutTemplate}/toggle-active', [SeatLayoutTemplateController::class, 'toggleActive']);
        Route::get('{seatLayoutTemplate}/seats', [SeatLayoutTemplateController::class, 'getSeats']);
        Route::post('{seatLayoutTemplate}/seats/update', [SeatLayoutTemplateController::class, 'updateSeats']);
    });

    Route::prefix('screens')->group(function () {
        Route::get('/', [ScreenController::class, 'index']);
        Route::post('/', [ScreenController::class, 'store']);
        Route::put('{screen}', [ScreenController::class, 'update']);
        Route::delete('{screen}', [ScreenController::class, 'destroy']);
        Route::post('{screen}/toggle-active', [ScreenController::class, 'toggleActive']);
        Route::get('{screen}/seats', [ScreenController::class, 'showSeats']);
        Route::post('{screen}/seats/update', [ScreenController::class, 'updateSeats']);
    });

    Route::prefix('formats')->group(function () {
        Route::post('/', [ScreenController::class, 'storeFormat']);
        Route::put('{format}', [ScreenController::class, 'updateFormat']);
        Route::delete('{format}', [ScreenController::class, 'destroyFormat']);
    });

    Route::prefix('sounds')->group(function () {
        Route::post('/', [ScreenController::class, 'storeSound']);
        Route::put('{sound}', [ScreenController::class, 'updateSound']);
        Route::delete('{sound}', [ScreenController::class, 'destroySound']);
    });

    Route::prefix('showtimes')->group(function () {
        Route::get('/', [ShowtimeController::class, 'index']);
        Route::post('/', [ShowtimeController::class, 'store']);
        Route::post('bulk', [ShowtimeController::class, 'bulkCreate']);
        Route::post('bulk-single', [ShowtimeController::class, 'bulkSingleDay']);
        Route::put('{id}', [ShowtimeController::class, 'update']);
        Route::delete('{id}', [ShowtimeController::class, 'destroy']);
        Route::put('{id}/status', [ShowtimeController::class, 'updateStatus']);
    });

    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::post('/', [ProductController::class, 'store']);
        Route::put('{product}', [ProductController::class, 'update']);
        Route::post('{product}/update', [ProductController::class, 'update']);
        Route::delete('{product}', [ProductController::class, 'destroy']);
        Route::post('{product}/toggle-active', [ProductController::class, 'toggleActive']);
    });

    Route::prefix('combos')->group(function () {
        Route::get('/', [ComboController::class, 'index']);
        Route::get('available-products', [ComboController::class, 'getAvailableProducts']);
        Route::get('{combo}', [ComboController::class, 'show']);
        Route::post('/', [ComboController::class, 'store']);
        Route::put('{combo}', [ComboController::class, 'update']);
        Route::post('{combo}/update', [ComboController::class, 'update']);
        Route::delete('{combo}', [ComboController::class, 'destroy']);
        Route::post('{combo}/toggle-active', [ComboController::class, 'toggleActive']);
    });

    Route::prefix('promotions')->group(function () {
        Route::get('/', [PromotionController::class, 'index']);
        Route::get('categories', [PromotionController::class, 'getCategories']);
        Route::get('{promotion}', [PromotionController::class, 'show']);
        Route::post('/', [PromotionController::class, 'store']);
        Route::put('{promotion}', [PromotionController::class, 'update']);
        Route::delete('{promotion}', [PromotionController::class, 'destroy']);
        Route::post('{promotion}/toggle-active', [PromotionController::class, 'toggleActive']);
        Route::post('{promotion}/reset-usage', [PromotionController::class, 'resetUsageCount']);
    });

    Route::post('tickets/verify', [TicketController::class, 'verify']);

    Route::prefix('posts')->group(function () {
        Route::get('/', [PostController::class, 'list']);
        Route::get('categories', [PostController::class, 'categories']);
        Route::post('/', [PostController::class, 'store']);
        Route::put('{post}', [PostController::class, 'update']);
        Route::post('{post}/update', [PostController::class, 'update']);
        Route::delete('{post}', [PostController::class, 'destroy']);
        Route::post('{post}/toggle-publish', [PostController::class, 'togglePublish']);
    });

    Route::prefix('banners')->group(function () {
        Route::get('/', [BannerController::class, 'list']);
        Route::post('/', [BannerController::class, 'store']);
        Route::put('{banner}', [BannerController::class, 'update']);
        Route::post('{banner}/update', [BannerController::class, 'update']);
        Route::delete('{banner}', [BannerController::class, 'destroy']);
        Route::post('{banner}/toggle-active', [BannerController::class, 'toggleActive']);
    });

    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'list']);
        Route::get('stats', [UserController::class, 'stats']);
        Route::get('roles', [UserController::class, 'getRoles']);
        Route::get('{user}', [UserController::class, 'show']);
        Route::post('/', [UserController::class, 'store']);
        Route::put('{user}', [UserController::class, 'update']);
        Route::delete('{user}', [UserController::class, 'destroy']);
        Route::post('{user}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::post('{user}/reset-password', [UserController::class, 'resetPassword']);
    });
});
