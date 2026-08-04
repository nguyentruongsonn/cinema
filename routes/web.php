<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ContentController;
use Illuminate\Support\Facades\Auth;
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
    Route::get('/', function () {
        $routeName = Auth::user()?->adminLandingRouteName() ?? 'home';

        return redirect()->route($routeName);
    })->name('admin.entry');

    Route::view('/dashboard', 'admin.dashboard')->middleware('permission:dashboard.view')->name('admin.dashboard');
    Route::view('/movies', 'admin.movies.index')->middleware('permission:movies.view')->name('admin.movies.index');
    Route::view('/revenue', 'admin.revenue.index')->middleware('permission:reports.view,analytics.view')->name('admin.revenue.index');
    Route::view('/tickets', 'admin.tickets.index')->middleware('permission:analytics.view')->name('admin.tickets.index');
    Route::view('/products', 'admin.products.index')->middleware('permission:products.view')->name('admin.products.index');
    Route::view('/combos', 'admin.combos.index')->middleware('permission:combos.view')->name('admin.combos.index');
    Route::view('/combos/stats', 'admin.combos.stats')->middleware('permission:analytics.view')->name('admin.combos.stats');
    Route::view('/showtimes', 'admin.showtimes.index')->middleware('permission:showtimes.view')->name('admin.showtimes.index');
    Route::view('/orders', 'admin.orders.index')->middleware('permission:orders.view_all,orders.view_theater')->name('admin.orders.index');

    // Branches
    Route::view('branches', 'admin.branches.index')->middleware('permission:branches.view')->name('admin.branches.index');

    // Theaters
    Route::view('theaters', 'admin.theaters.index')->middleware('permission:theaters.view')->name('admin.theaters.index');

    // Pricing Rules
    Route::view('pricing-rules', 'admin.pricing-rules.index')->middleware('permission:pricing.view,pricing.update')->name('admin.pricing-rules.index');

    // Seat layout templates
    Route::view('seat-layout-templates', 'admin.seat-layout-templates.index')->middleware('permission:seat_layouts.view,screens.manage_seats')->name('admin.seat-layout-templates.index');
    Route::get('seat-layout-templates/{template}/seats', function($template) {
        return view('admin.seat-layout-templates.seats', ['templateId' => $template]);
    })->middleware('permission:seat_layouts.view,screens.manage_seats')->name('admin.seat-layout-templates.seats');

    // Screens (Phòng chiếu)
    Route::view('screens', 'admin.screens.index')->middleware('permission:screens.view')->name('admin.screens.index');
    Route::get('screens/{screen}/seats', function($screen) {
        return view('admin.screens.seats', ['screenId' => $screen]);
    })->middleware('permission:screens.manage_seats')->name('admin.screens.seats');

    // Promotions (Mã giảm giá)
    Route::view('promotions', 'admin.promotions.index')->middleware('permission:promotions.view')->name('admin.promotions.index');

    // Posts (Bài viết)
    Route::view('posts', 'admin.posts.index')->middleware('permission:posts.view')->name('admin.posts.index');

    // Banners (Quảng cáo)
    Route::view('banners', 'admin.banners.index')->middleware('permission:banners.view')->name('admin.banners.index');

    // Users (Quản lý tài khoản)
    Route::view('users', 'admin.users.index')->middleware('permission:users.view')->name('admin.users.index');
    Route::view('roles-permissions', 'admin.roles-permissions.index')->middleware('permission:roles.view,permissions.assign')->name('admin.roles-permissions.index');
    Route::view('audit-logs', 'admin.audit-logs.index')->middleware('permission:audit_logs.view')->name('admin.audit-logs.index');
});
