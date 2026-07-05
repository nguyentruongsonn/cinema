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
    Route::view('/revenue', 'admin.revenue.index')->name('admin.revenue.index');
    Route::view('/tickets', 'admin.tickets.index')->name('admin.tickets.index');
    Route::view('/products', 'admin.products.index', [
        'productType' => 'all',
        'productTitle' => 'Quản lý sản phẩm',
        'productHeading' => 'Danh sách Đồ ăn & Nước uống',
        'productSubtitle' => 'Quản lý chung các sản phẩm đồ ăn và nước uống bán tại rạp.',
        'productCreateLabel' => 'Thêm Đồ ăn/Nước uống',
        'productNameLabel' => 'Tên đồ ăn/nước uống',
        'productModalCreateTitle' => 'Thêm đồ ăn/nước uống mới',
        'productModalEditTitle' => 'Cập nhật đồ ăn/nước uống',
        'productSubmitLabel' => 'Lưu đồ ăn/nước uống',
        'showTypeFilter' => true,
        'allowedProductTypes' => ['food', 'drink'],
    ])->name('admin.products.index');
    Route::view('/products/foods', 'admin.products.index', [
        'productType' => 'food',
        'productTitle' => 'Quản lý đồ ăn',
        'productHeading' => 'Danh sách Đồ ăn',
        'productSubtitle' => 'Quản lý các sản phẩm đồ ăn bán tại rạp.',
        'productCreateLabel' => 'Thêm Đồ ăn',
        'productNameLabel' => 'Tên đồ ăn',
        'productModalCreateTitle' => 'Thêm đồ ăn mới',
        'productModalEditTitle' => 'Cập nhật đồ ăn',
        'productSubmitLabel' => 'Lưu đồ ăn',
        'showTypeFilter' => false,
    ])->name('admin.products.foods');
    Route::view('/products/drinks', 'admin.products.index', [
        'productType' => 'drink',
        'productTitle' => 'Quản lý nước uống',
        'productHeading' => 'Danh sách Nước uống',
        'productSubtitle' => 'Quản lý các sản phẩm nước uống bán tại rạp.',
        'productCreateLabel' => 'Thêm Nước uống',
        'productNameLabel' => 'Tên nước uống',
        'productModalCreateTitle' => 'Thêm nước uống mới',
        'productModalEditTitle' => 'Cập nhật nước uống',
        'productSubmitLabel' => 'Lưu nước uống',
        'showTypeFilter' => false,
    ])->name('admin.products.drinks');
    Route::view('/combos', 'admin.combos.index')->name('admin.combos.index');
    Route::view('/combos/stats', 'admin.combos.stats')->name('admin.combos.stats');
    Route::view('/showtimes', 'admin.showtimes.index')->name('admin.showtimes.index');
    Route::view('/orders', 'admin.orders.index')->name('admin.orders.index');
    
    // Branches
    Route::view('branches', 'admin.branches.index')->name('admin.branches.index');

    // Theaters
    Route::view('theaters', 'admin.theaters.index')->name('admin.theaters.index');

    // Seat layout templates
    Route::view('seat-layout-templates', 'admin.seat-layout-templates.index')->name('admin.seat-layout-templates.index');

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
});
