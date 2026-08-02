<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ContentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Login route - redirect to home (auth handled by frontend modal)
Route::get('/login', function () {
    return redirect('/')->with('message', 'Vui lòng đăng nhập để tiếp tục');
})->name('login');

Route::view('/movies', 'users.movies.index')->name('movies.index');
Route::view('/movies/{idOrSlug}', 'users.movies.show')->name('movies.show');

Route::view('/theaters', 'users.theaters.index')->name('theaters.index');
Route::get('/prices', [\App\Http\Controllers\PricePageController::class, 'index'])->name('prices.index');

Route::get('/booking/{encryptedShowtimeId}', [BookingController::class, 'show'])
    ->middleware('throttle:booking')
    ->name('booking.show');
Route::get('/payment/{order}', [PaymentController::class, 'index'])->name('payment.index');

// Profile routes - auth handled by SSR (@guest/@auth directives in views)
// AuthenticateFromCookie middleware auto-authenticates from JWT cookie
Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
Route::get('/posts', [ContentController::class, 'postsPage'])->name('posts.index');
Route::get('/posts/{post:slug}', [ContentController::class, 'postPage'])->name('posts.show');


// PayOS Payment Gateway Callbacks
Route::get('/payment/payos/callback', [PaymentController::class, 'payosCallback'])
    ->middleware('throttle:payments')
    ->name('payment.payos.callback');
Route::get('/payment/payos/cancel', [PaymentController::class, 'payosCancel'])
    ->middleware('throttle:payments')
    ->name('payment.payos.cancel');
Route::post('/payment/payos/webhook', [PaymentController::class, 'payosWebhook'])
    ->middleware('throttle:webhook')
    ->name('payment.payos.webhook');
// Admin Panel Routes
Route::prefix('admin')->middleware(['admin'])->group(function () {
    Route::view('/dashboard', 'admin.dashboard')->name('admin.dashboard');
    Route::view('/movies', 'admin.movies.index')->name('admin.movies.index');
    Route::view('/revenue', 'admin.revenue.index')->name('admin.revenue.index');
    Route::view('/tickets', 'admin.tickets.index')->name('admin.tickets.index');
    Route::view('/products', 'admin.products.index')->name('admin.products.index');
    Route::view('/combos', 'admin.combos.index')->name('admin.combos.index');
    Route::view('/combos/stats', 'admin.combos.stats')->name('admin.combos.stats');
    Route::view('/showtimes', 'admin.showtimes.index')->name('admin.showtimes.index');
    Route::view('/orders', 'admin.orders.index')->name('admin.orders.index');

    // Branches
    Route::view('branches', 'admin.branches.index')->name('admin.branches.index');

    // Theaters
    Route::view('theaters', 'admin.theaters.index')->name('admin.theaters.index');

    // Pricing Rules
    Route::view('pricing-rules', 'admin.pricing-rules.index')->name('admin.pricing-rules.index');

    // Seat layout templates
    Route::view('seat-layout-templates', 'admin.seat-layout-templates.index')->name('admin.seat-layout-templates.index');
    Route::get('seat-layout-templates/{template}/seats', function($template) {
        return view('admin.seat-layout-templates.seats', ['templateId' => $template]);
    })->name('admin.seat-layout-templates.seats');

    // Screens (Phòng chiếu)
    Route::view('screens', 'admin.screens.index')->name('admin.screens.index');
    Route::get('screens/{screen}/seats', function($screen) {
        return view('admin.screens.seats', ['screenId' => $screen]);
    })->name('admin.screens.seats');

    // Promotions (Mã giảm giá)
    Route::view('promotions', 'admin.promotions.index')->name('admin.promotions.index');

    // Posts (Bài viết)
    Route::view('posts', 'admin.posts.index')->name('admin.posts.index');

    // Banners (Quảng cáo)
    Route::view('banners', 'admin.banners.index')->name('admin.banners.index');

    // Users (Quản lý tài khoản)
    Route::view('users', 'admin.users.index')->name('admin.users.index');
});
