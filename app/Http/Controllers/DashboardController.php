<?php

namespace App\Http\Controllers;

use App\Enums\RegistrationStatus;
use App\Models\Person;
use App\Models\Registration;
use App\Models\Tour;
use App\Support\Money;
use App\Support\Tenancy\CurrentTenant;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(CurrentTenant $currentTenant): Response
    {
        $tenant = $currentTenant->get();

        // İptal edilmemiş kayıtların kalan bakiyesi, para birimine göre.
        $outstanding = Registration::query()
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->withPaidTotal()
            ->get()
            ->groupBy('currency')
            ->map(fn ($registrations) => $registrations->reduce(
                fn (string $carry, Registration $registration) => Money::add($carry, $registration->balance()),
                '0.00',
            ))
            ->reject(fn (string $total) => Money::isZero($total));

        $upcomingTours = Tour::query()
            ->active()
            ->withCount(['registrations' => fn ($query) => $query->where('status', '!=', RegistrationStatus::Cancelled)])
            ->orderBy('start_date')
            ->limit(5)
            ->get(['id', 'name', 'start_date', 'end_date', 'status', 'capacity']);

        return Inertia::render('Dashboard', [
            'stats' => [
                'persons' => Person::count(),
                'activeTours' => $tenant?->activeTourCount() ?? 0,
                'activeTourLimit' => $tenant?->plan->active_tour_limit,
                'outstanding' => $outstanding,
            ],
            'upcomingTours' => $upcomingTours,
        ]);
    }
}
