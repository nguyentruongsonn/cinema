<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Login route - redirect to home (auth handled by frontend modal)
Route::get('/login', function () {
    return redirect('/')->with('message', 'Vui lòng đăng nhập để tiếp tục');
})->name('login');

Route::view('/movies', 'users.movies.index')->name('movies.index');
Route::view('/movies/{idOrSlug}', 'users.movies.show')->name('movies.show');

Route::get('/booking/{encryptedShowtimeId}', [BookingController::class, 'show'])->name('booking.show');
Route::get('/payment/{order}', [PaymentController::class, 'index'])->name('payment.index');

// Profile routes - auth handled by SSR (@guest/@auth directives in views)
// AuthenticateFromCookie middleware auto-authenticates from JWT cookie
Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');


// PayOS Payment Gateway Callbacks
Route::get('/payment/payos/callback', [PaymentController::class, 'payosCallback'])->name('payment.payos.callback');
Route::get('/payment/payos/cancel', [PaymentController::class, 'payosCancel'])->name('payment.payos.cancel');
Route::post('/payment/payos/webhook', [PaymentController::class, 'payosWebhook'])->name('payment.payos.webhook');
// Admin Panel Routes
Route::prefix('admin')->middleware(['admin'])->group(function () {
    Route::view('/dashboard', 'admin.dashboard')->name('admin.dashboard');
    Route::view('/movies', 'admin.movies.index')->name('admin.movies.index');
});
