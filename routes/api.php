<?php

use App\Http\Controllers\User\PaymentController as UserPaymentController;
use Illuminate\Support\Facades\Route;

Route::post('payos/webhook', [UserPaymentController::class, 'handleWebhook'])
    ->middleware('throttle:webhook');

Route::prefix('v1')->group(function () {
    require __DIR__ . '/api/public.php';
    require __DIR__ . '/api/auth.php';
    require __DIR__ . '/api/customer.php';
    require __DIR__ . '/api/admin.php';
});
