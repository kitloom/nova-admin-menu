<?php

use Illuminate\Support\Facades\Route;
use Kitloom\NovaAdminMenu\Http\Controllers\MenuOrderController;

Route::prefix('nova-vendor/nova-admin-menu')->group(function () {
    Route::get('/entries', [MenuOrderController::class, 'index'])->name('nova-admin-menu.index');
    Route::post('/entries', [MenuOrderController::class, 'store'])->name('nova-admin-menu.store');
    Route::delete('/entries', [MenuOrderController::class, 'destroy'])->name('nova-admin-menu.destroy');

    Route::post('/custom', [MenuOrderController::class, 'storeCustom'])->name('nova-admin-menu.custom.store');
    Route::delete('/custom/{id}', [MenuOrderController::class, 'destroyCustom'])->name('nova-admin-menu.custom.destroy');
});
