<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\Platform\TenantController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\TourController;
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
        Route::get('persons-lookup', [PersonController::class, 'lookup'])->name('persons.lookup');

        // Turlar, gruplar ve kayıtlar
        Route::resource('tours', TourController::class);
        Route::post('tours/{tour}/groups', [GroupController::class, 'store'])->name('tours.groups.store');
        Route::put('groups/{group}', [GroupController::class, 'update'])->name('groups.update');
        Route::delete('groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');
        Route::post('tours/{tour}/registrations', [RegistrationController::class, 'store'])->name('tours.registrations.store');
        Route::put('registrations/{registration}', [RegistrationController::class, 'update'])->name('registrations.update');
        Route::delete('registrations/{registration}', [RegistrationController::class, 'destroy'])->name('registrations.destroy');
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
