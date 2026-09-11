<?php

use Illuminate\Support\Facades\Route;
use Kitloom\NovaMenuWeights\Http\Controllers\MenuOrderController;

Route::prefix('nova-vendor/nova-menu-weights')->group(function () {
    Route::get('/entries', [MenuOrderController::class, 'index'])->name('nova-menu-weights.index');
    Route::post('/entries', [MenuOrderController::class, 'store'])->name('nova-menu-weights.store');
    Route::delete('/entries', [MenuOrderController::class, 'destroy'])->name('nova-menu-weights.destroy');

    Route::post('/custom', [MenuOrderController::class, 'storeCustom'])->name('nova-menu-weights.custom.store');
    Route::delete('/custom/{id}', [MenuOrderController::class, 'destroyCustom'])->name('nova-menu-weights.custom.destroy');
});
