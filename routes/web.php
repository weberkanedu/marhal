<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\Platform\TenantController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Acente ekranları
Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Yolcular
    Route::middleware('feature:passengers')->group(function () {
        Route::resource('persons', PersonController::class);
        Route::post('persons/{person}/reveal', [PersonController::class, 'reveal'])
            ->middleware('throttle:30,1')
            ->name('persons.reveal');
        Route::get('persons/{person}/photo', [PersonController::class, 'photo'])->name('persons.photo');
    });
});

// Platform yönetimi (super_admin)
Route::middleware(['auth', 'verified', 'role:super_admin'])
    ->prefix('platform')
    ->name('platform.')
    ->group(function () {
        Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
    });

require __DIR__.'/settings.php';
