<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::view('/movies', 'users.movies.index')->name('movies.index');
Route::view('/movies/{idOrSlug}', 'users.movies.show')->name('movies.show');

Route::get('/booking/{showtimeId}', [BookingController::class, 'show'])->whereNumber('showtimeId')->name('booking.show');
Route::get('/payment/{order}', [PaymentController::class, 'index'])->name('payment.index');
Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
Route::get('/my-tickets', [ProfileController::class, 'tickets'])->name('tickets.index');

// PayOS Payment Gateway Callbacks
Route::get('/payment/payos/callback', [PaymentController::class, 'payosCallback'])->name('payment.payos.callback');
Route::get('/payment/payos/cancel', [PaymentController::class, 'payosCancel'])->name('payment.payos.cancel');
Route::post('/payment/payos/webhook', [PaymentController::class, 'payosWebhook'])->name('payment.payos.webhook');
