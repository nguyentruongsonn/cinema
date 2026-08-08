<?php

use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\StaffCatalogController;
use App\Http\Controllers\Api\V1\ConcessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('staff')->middleware([
    'auth:api',
    'role:ticket_checker,admin,super-admin,theater_manager',
    'theater.scope',
    'throttle:pos',
])->group(function (): void {
    Route::post('tickets/verify', [TicketController::class, 'verify'])
        ->middleware('permission:tickets.verify');
});

Route::prefix('staff/concessions')->middleware([
    'auth:api',
    'role:concession_staff,admin,super-admin,theater_manager',
    'theater.scope',
    'throttle:pos',
])->group(function (): void {
    Route::get('catalog', [StaffCatalogController::class, 'concessionCatalog'])
        ->middleware('permission:products.view,combos.view');
    Route::get('orders/pending', [ConcessionController::class, 'pending'])
        ->middleware('permission:concessions.fulfill');
    Route::post('items/{orderItem}/fulfill', [ConcessionController::class, 'fulfill'])
        ->whereNumber('orderItem')
        ->middleware('permission:concessions.fulfill');
});
