<?php

use App\Http\Controllers\AgencySettingsController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\Platform\TenantController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TourController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Ana adres: giriş yapmışsa panele, değilse giriş ekranına.
Route::get('/', fn () => auth()->check() ? to_route('dashboard') : to_route('login'))->name('home');

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

    // Ödemeler ve tahsilat
    Route::middleware('feature:payments')->group(function () {
        Route::get('registrations/{registration}', [RegistrationController::class, 'show'])->name('registrations.show');
        Route::post('registrations/{registration}/payments', [PaymentController::class, 'store'])->name('registrations.payments.store');
        Route::put('registrations/{registration}/installments', [PaymentController::class, 'updateInstallments'])->name('registrations.installments.update');
        Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
        Route::get('collections', CollectionController::class)->name('collections.index');
    });

    // Excel / PDF raporları
    Route::middleware(['feature:basic_reports', 'throttle:30,1'])->prefix('reports')->name('reports.')->group(function () {
        Route::get('tours/{tour}/passengers', [ReportController::class, 'tourPassengers'])->name('tours.passengers');
        Route::get('tours/{tour}/payments', [ReportController::class, 'tourPayments'])->name('tours.payments');
        Route::get('collections', [ReportController::class, 'collections'])->name('collections');
    });

    // Acente yönetimi (sadece acente yöneticisi)
    Route::middleware('role:admin')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])
            ->middleware('throttle:10,1')
            ->name('users.reset-password');

        Route::get('agency', [AgencySettingsController::class, 'edit'])->name('agency.edit');
        Route::post('agency', [AgencySettingsController::class, 'update'])->name('agency.update');
    });
    Route::get('agency/logo', [AgencySettingsController::class, 'logo'])->name('agency.logo');
});

// Platform yönetimi (super_admin)
Route::middleware(['auth', 'verified', 'role:super_admin'])
    ->prefix('platform')
    ->name('platform.')
    ->group(function () {
        Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
        Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
        Route::put('tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
        Route::put('tenants/{tenant}/features', [TenantController::class, 'updateFeature'])->name('tenants.features.update');
        Route::post('tenants/{tenant}/users/{user}/reset-password', [TenantController::class, 'resetUserPassword'])
            ->middleware('throttle:10,1')
            ->name('tenants.users.reset-password');
    });

require __DIR__.'/settings.php';
