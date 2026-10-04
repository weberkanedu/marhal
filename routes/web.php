<?php

use App\Http\Controllers\AgencySettingsController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BusController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FlightController;
use App\Http\Controllers\FlightPassengerController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PersonRelationController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\TenantController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoomAssignmentController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomPlanController;
use App\Http\Controllers\SeatAssignmentController;
use App\Http\Controllers\SeatPlanController;
use App\Http\Controllers\TourController;
use App\Http\Controllers\TourHotelController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleTypeController;
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
        Route::post('persons/{person}/relations', [PersonRelationController::class, 'store'])->name('persons.relations.store');
        Route::delete('person-relations/{relation}', [PersonRelationController::class, 'destroy'])->name('person-relations.destroy');

        // Turlar, gruplar ve kayıtlar
        Route::resource('tours', TourController::class);
        Route::post('tours/{tour}/groups', [GroupController::class, 'store'])->name('tours.groups.store');
        Route::put('groups/{group}', [GroupController::class, 'update'])->name('groups.update');
        Route::delete('groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');
        Route::post('tours/{tour}/registrations', [RegistrationController::class, 'store'])->name('tours.registrations.store');
        Route::put('registrations/{registration}', [RegistrationController::class, 'update'])->name('registrations.update');
        Route::delete('registrations/{registration}', [RegistrationController::class, 'destroy'])->name('registrations.destroy');
    });

    // Oteller ve tur konaklamaları (oda planı modülü)
    Route::middleware(['feature:passengers', 'feature:room_planning'])->group(function () {
        Route::get('hotels', [HotelController::class, 'index'])->name('hotels.index');
        Route::post('hotels', [HotelController::class, 'store'])->name('hotels.store');
        Route::put('hotels/{hotel}', [HotelController::class, 'update'])->name('hotels.update');
        Route::delete('hotels/{hotel}', [HotelController::class, 'destroy'])->name('hotels.destroy');
        Route::post('tours/{tour}/stays', [TourHotelController::class, 'store'])->name('tours.stays.store');
        Route::put('stays/{stay}', [TourHotelController::class, 'update'])->name('stays.update');
        Route::delete('stays/{stay}', [TourHotelController::class, 'destroy'])->name('stays.destroy');

        // Oda planı
        Route::get('stays/{stay}/rooms', [RoomPlanController::class, 'show'])->name('stays.room-plan');
        Route::get('stays/{stay}/auto-assign', [RoomPlanController::class, 'preview'])->name('stays.auto-assign-preview');
        Route::post('stays/{stay}/auto-assign', [RoomPlanController::class, 'apply'])->name('stays.auto-assign');
        Route::get('stays/{stay}/copy-preview', [RoomPlanController::class, 'copyPreview'])->name('stays.copy-preview');
        Route::post('stays/{stay}/copy', [RoomPlanController::class, 'copy'])->name('stays.copy');
        Route::post('stays/{stay}/rooms', [RoomController::class, 'store'])->name('stays.rooms.store');
        Route::put('rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
        Route::delete('rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');
        Route::post('rooms/{room}/assignments', [RoomAssignmentController::class, 'store'])->name('rooms.assignments.store');
        Route::delete('room-assignments/{assignment}', [RoomAssignmentController::class, 'destroy'])->name('room-assignments.destroy');
    });

    // Araç tipleri, tur otobüsleri ve koltuk planı (otobüs planı modülü)
    Route::middleware(['feature:passengers', 'feature:bus_planning'])->group(function () {
        Route::get('vehicle-types', [VehicleTypeController::class, 'index'])->name('vehicle-types.index');
        Route::post('vehicle-types', [VehicleTypeController::class, 'store'])->name('vehicle-types.store');
        Route::put('vehicle-types/{vehicleType}', [VehicleTypeController::class, 'update'])->name('vehicle-types.update');
        Route::delete('vehicle-types/{vehicleType}', [VehicleTypeController::class, 'destroy'])->name('vehicle-types.destroy');
        Route::post('tours/{tour}/buses', [BusController::class, 'store'])->name('tours.buses.store');
        Route::put('buses/{bus}', [BusController::class, 'update'])->name('buses.update');
        Route::delete('buses/{bus}', [BusController::class, 'destroy'])->name('buses.destroy');

        // Koltuk planı
        Route::get('buses/{bus}/seats', [SeatPlanController::class, 'show'])->name('buses.seat-plan');
        Route::get('buses/{bus}/auto-assign', [SeatPlanController::class, 'preview'])->name('buses.auto-assign-preview');
        Route::post('buses/{bus}/auto-assign', [SeatPlanController::class, 'apply'])->name('buses.auto-assign');
        Route::post('buses/{bus}/seats', [SeatAssignmentController::class, 'store'])->name('buses.seats.store');
        Route::delete('seat-assignments/{seat}', [SeatAssignmentController::class, 'destroy'])->name('seat-assignments.destroy');
    });

    // Uçuşlar ve havayolu listeleri (uçuş listesi modülü)
    Route::middleware(['feature:passengers', 'feature:flight_lists'])->group(function () {
        Route::post('tours/{tour}/flights', [FlightController::class, 'store'])->name('tours.flights.store');
        Route::get('flights/{flight}', [FlightController::class, 'show'])->name('flights.show');
        Route::put('flights/{flight}', [FlightController::class, 'update'])->name('flights.update');
        Route::delete('flights/{flight}', [FlightController::class, 'destroy'])->name('flights.destroy');
        Route::post('flights/{flight}/passengers', [FlightPassengerController::class, 'store'])->name('flights.passengers.store');
        Route::put('flight-passengers/{passenger}', [FlightPassengerController::class, 'update'])->name('flight-passengers.update');
        Route::delete('flight-passengers/{passenger}', [FlightPassengerController::class, 'destroy'])->name('flight-passengers.destroy');
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
        Route::middleware('feature:room_planning')->group(function () {
            Route::get('stays/{stay}/rooming-list', [ReportController::class, 'roomingList'])->name('stays.rooming-list');
            Route::get('stays/{stay}/room-occupancy', [ReportController::class, 'roomOccupancy'])->name('stays.room-occupancy');
        });
        Route::middleware('feature:bus_planning')->group(function () {
            Route::get('buses/{bus}/passengers', [ReportController::class, 'busPassengers'])->name('buses.passengers');
            Route::get('buses/{bus}/seat-chart', [ReportController::class, 'busSeatChart'])->name('buses.seat-chart');
        });
        Route::get('flights/{flight}/manifest', [ReportController::class, 'flightManifest'])
            ->middleware('feature:flight_lists')
            ->name('flights.manifest');
    });

    // Acente yönetimi (sadece acente yöneticisi)
    Route::middleware('role:admin')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])
            ->middleware('throttle:10,1')
            ->name('users.reset-password');

        Route::get('audit-logs', AuditLogController::class)->name('audit.index');

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
        Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
        Route::put('plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
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
