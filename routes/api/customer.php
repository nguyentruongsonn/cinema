<?php

use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\SeatController;
use App\Http\Controllers\User\PaymentController as UserPaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    Route::prefix('seats')->middleware('throttle:seats')->group(function () {
        Route::post('lock', [SeatController::class, 'lock']);
        Route::delete('unlock/{holdId}', [SeatController::class, 'unlock']);
        Route::post('holds/{holdId}/release', [SeatController::class, 'unlock']);
        Route::delete('holds/{holdId}/seats/{seatId}', [SeatController::class, 'releaseSeat']);
    });

    Route::prefix('orders')->middleware('throttle:orders')->group(function () {
        Route::post('/', [OrderController::class, 'store']);
        Route::get('user/me', [OrderController::class, 'userOrders']);
        Route::get('{id}', [OrderController::class, 'show'])->whereNumber('id');
        Route::delete('{id}', [OrderController::class, 'cancel'])->whereNumber('id');
    });

    Route::prefix('payments')->middleware('throttle:payments')->group(function () {
        Route::post('/', [UserPaymentController::class, 'createPayment']);
        Route::get('orders/{orderCode}', [UserPaymentController::class, 'showOrderSummary']);
    });

    Route::prefix('tickets')->middleware('throttle:tickets')->group(function () {
        Route::get('/', [TicketController::class, 'index']);
        Route::get('{ticketCode}', [TicketController::class, 'show']);
    });

    Route::prefix('promotions')->group(function () {
        Route::get('registered', [PromotionController::class, 'registered']);
        Route::post('register', [PromotionController::class, 'register']);
    });

    Route::post('broadcasting/auth', function (Request $request) {
        return Broadcast::auth($request);
    });
});
