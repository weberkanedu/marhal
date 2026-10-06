<?php

use App\Http\Controllers\AgencySettingsController;
use App\Http\Controllers\AircraftTypeController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BadgeSettingController;
use App\Http\Controllers\BusController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\FlightController;
use App\Http\Controllers\FlightPassengerController;
use App\Http\Controllers\FlightSeatPlanController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\NeedTypeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PersonImportController;
use App\Http\Controllers\PersonNeedController;
use App\Http\Controllers\PersonRelationController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\TenantController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoomAssignmentController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomPlanController;
use App\Http\Controllers\SeatAssignmentController;
use App\Http\Controllers\ReadinessItemController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SeatPlanController;
use App\Http\Controllers\TourBadgeController;
use App\Http\Controllers\TourReadinessController;
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

    // "Görüşünü paylaş" (her kullanıcı)
    Route::post('feedback', [FeedbackController::class, 'store'])->middleware('throttle:10,1')->name('feedback.store');

    // Yan menüdeki "Ara" (Ctrl K): yolcu ve tur
    Route::get('search', SearchController::class)->middleware('throttle:60,1')->name('search');

    // Yolcular
    Route::middleware('feature:passengers')->group(function () {
        // Excel'den toplu yolcu aktarma (resource'tan önce: "persons/import" bir kişi sanılmasın)
        Route::get('persons/import', [PersonImportController::class, 'create'])->name('person-import.show');
        Route::get('persons/import/template', [PersonImportController::class, 'template'])->name('person-import.template');
        Route::post('persons/import', [PersonImportController::class, 'upload'])
            ->middleware('throttle:20,1')
            ->name('person-import.upload');
        Route::post('persons/import/confirm', [PersonImportController::class, 'store'])->name('person-import.store');
        Route::resource('persons', PersonController::class);
        Route::post('persons/{person}/reveal', [PersonController::class, 'reveal'])
            ->middleware('throttle:30,1')
            ->name('persons.reveal');
        Route::get('persons/{person}/photo', [PersonController::class, 'photo'])->name('persons.photo');
        Route::get('persons-lookup', [PersonController::class, 'lookup'])->name('persons.lookup');
        Route::post('persons/{person}/relations', [PersonRelationController::class, 'store'])->name('persons.relations.store');
        Route::put('persons/{person}/needs', [PersonNeedController::class, 'update'])->name('persons.needs.update');
        Route::put('persons/{person}/health-consent', [PersonNeedController::class, 'consent'])->name('persons.health-consent');
        Route::get('need-types', [NeedTypeController::class, 'index'])->name('need-types.index');
        Route::post('need-types', [NeedTypeController::class, 'store'])->name('need-types.store');
        Route::put('need-types/{needType}', [NeedTypeController::class, 'update'])->name('need-types.update');
        Route::middleware('feature:readiness')->group(function () {
            Route::get('readiness-items', [ReadinessItemController::class, 'index'])->name('readiness-items.index');
            Route::post('readiness-items', [ReadinessItemController::class, 'store'])->name('readiness-items.store');
            Route::put('readiness-items/{readinessItem}', [ReadinessItemController::class, 'update'])->name('readiness-items.update');
            Route::put('tours/{tour}/readiness/items', [TourReadinessController::class, 'items'])->name('tours.readiness.items');
            Route::post('tours/{tour}/readiness', [TourReadinessController::class, 'mark'])->name('tours.readiness.mark');
            Route::post('tours/{tour}/readiness/column', [TourReadinessController::class, 'column'])->name('tours.readiness.column');
            Route::put('tours/{tour}/ravza', [TourReadinessController::class, 'ravza'])->name('tours.ravza');
        });
        Route::middleware('feature:badge_generation')->group(function () {
            Route::put('badge-settings', [BadgeSettingController::class, 'update'])->name('badge-settings.update');
            Route::get('tours/{tour}/badge-cards', [TourBadgeController::class, 'show'])->name('tours.badge-cards');
            Route::put('groups/{group}/color', [TourBadgeController::class, 'color'])->name('groups.color');
        });
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
        Route::put('stays/{stay}/floors', [RoomPlanController::class, 'floors'])->name('stays.floors');
        Route::get('stays/{stay}/auto-assign', [RoomPlanController::class, 'preview'])->name('stays.auto-assign-preview');
        Route::post('stays/{stay}/auto-assign', [RoomPlanController::class, 'apply'])->name('stays.auto-assign');
        Route::delete('stays/{stay}/assignments', [RoomPlanController::class, 'clear'])->name('stays.assignments.clear');
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
        Route::delete('buses/{bus}/seats', [SeatAssignmentController::class, 'clear'])->name('buses.seats.clear');
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

        // Uçak koltuk planı (havayoluna koltuk tercihi) ve uçak tipleri
        Route::get('flights/{flight}/seats', [FlightSeatPlanController::class, 'show'])->name('flights.seat-plan');
        Route::put('flights/{flight}/aircraft', [FlightSeatPlanController::class, 'aircraft'])->name('flights.aircraft');
        Route::post('flights/{flight}/seats', [FlightSeatPlanController::class, 'assign'])->name('flights.seats.store');
        Route::delete('flights/{flight}/seats', [FlightSeatPlanController::class, 'clear'])->name('flights.seats.clear');
        Route::post('flights/{flight}/auto-seats', [FlightSeatPlanController::class, 'auto'])->name('flights.seats.auto');
        Route::post('flights/{flight}/blocked-seats', [FlightSeatPlanController::class, 'block'])->name('flights.blocked-seats');
        Route::delete('flight-passengers/{passenger}/seat', [FlightSeatPlanController::class, 'unassign'])->name('flight-passengers.seat.destroy');
        Route::get('aircraft-types', [AircraftTypeController::class, 'index'])->name('aircraft-types.index');
        Route::post('aircraft-types', [AircraftTypeController::class, 'store'])->name('aircraft-types.store');
        Route::put('aircraft-types/{aircraftType}', [AircraftTypeController::class, 'update'])->name('aircraft-types.update');
        Route::delete('aircraft-types/{aircraftType}', [AircraftTypeController::class, 'destroy'])->name('aircraft-types.destroy');
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
        Route::get('tours/{tour}/program', [ReportController::class, 'tourProgram'])->name('tours.program');
        Route::get('tours/{tour}/readiness', [ReportController::class, 'tourReadiness'])->middleware('feature:readiness')->name('tours.readiness');
        Route::get('collections', [ReportController::class, 'collections'])->name('collections');
        Route::get('persons', [ReportController::class, 'persons'])->name('persons.list');
        Route::get('persons/passports', [ReportController::class, 'passports'])->name('persons.passports');
        Route::middleware('feature:room_planning')->group(function () {
            Route::get('stays/{stay}/rooming-list', [ReportController::class, 'roomingList'])->name('stays.rooming-list');
            Route::get('stays/{stay}/room-occupancy', [ReportController::class, 'roomOccupancy'])->name('stays.room-occupancy');
            Route::get('stays/{stay}/floor-plan', [ReportController::class, 'floorPlan'])->name('stays.floor-plan');
            Route::get('stays/{stay}/needs', [ReportController::class, 'stayNeeds'])->name('stays.needs');
        });
        Route::middleware('feature:bus_planning')->group(function () {
            Route::get('buses/{bus}/passengers', [ReportController::class, 'busPassengers'])->name('buses.passengers');
            Route::get('buses/{bus}/seat-chart', [ReportController::class, 'busSeatChart'])->name('buses.seat-chart');
            Route::get('tours/{tour}/drivers', [ReportController::class, 'busDrivers'])->name('tours.drivers');
        });
        Route::get('tours/{tour}/badges', [ReportController::class, 'tourBadges'])
            ->middleware('feature:badge_generation')
            ->name('tours.badges');
        Route::get('flights/{flight}/manifest', [ReportController::class, 'flightManifest'])
            ->middleware('feature:flight_lists')
            ->name('flights.manifest');
        Route::get('flights/{flight}/seats', [ReportController::class, 'flightSeats'])
            ->middleware('feature:flight_lists')
            ->name('flights.seats');
        Route::get('flights/{flight}/assistance', [ReportController::class, 'flightAssistance'])
            ->middleware('feature:flight_lists')
            ->name('flights.assistance');
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
        Route::get('feedback', [FeedbackController::class, 'index'])->name('feedback.index');
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
