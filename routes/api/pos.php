<?php

use App\Http\Controllers\Pos\PosController;
use Illuminate\Support\Facades\Route;

Route::prefix('pos')->middleware([
    'auth:api',
    'admin',
    'theater.scope',
    'throttle:pos',
])->group(function () {
    Route::get('catalog', [PosController::class, 'catalog'])
        ->middleware('permission:products.view,combos.view');
    Route::get('theaters', [PosController::class, 'theaters']);
    Route::get('showtimes', [PosController::class, 'getShowtimes'])
        ->middleware('permission:showtimes.view');
    Route::get('showtimes/{id}/seats', [PosController::class, 'getSeats'])
        ->whereNumber('id')
        ->middleware('permission:seats.view_status');
    Route::put('seat-holds/{holdId}', [PosController::class, 'syncSeatHold'])
        ->whereNumber('holdId')
        ->middleware('permission:booking.hold_seats,booking.release_seats');
    Route::post('customers/lookup', [PosController::class, 'lookupCustomer'])
        ->middleware('permission:customers.lookup');
    Route::post('orders', [PosController::class, 'createOrder'])
        ->middleware('permission:orders.create,booking.create_order');
    Route::post('orders/{order}/confirm-cash', [PosController::class, 'confirmCash'])
        ->whereNumber('order')
        ->middleware('permission:payments.process_cash,payments.process');
    Route::post('orders/{order}/cancel', [PosController::class, 'cancelOrder'])
        ->whereNumber('order')
        ->middleware('permission:orders.cancel');
    Route::get('orders/{order}', [PosController::class, 'getOrder'])
        ->whereNumber('order')
        ->middleware('permission:orders.view_theater');
    Route::get('orders/{order}/payment-status', [PosController::class, 'checkPaymentStatus'])
        ->whereNumber('order')
        ->middleware('permission:orders.view_theater');
});
