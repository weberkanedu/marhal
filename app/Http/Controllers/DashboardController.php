<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\Installment;
use App\Models\Person;
use App\Models\Tour;
use App\Support\Collections\CollectionSummary;
use App\Support\Dashboard\ActivityFeed;
use App\Support\Dashboard\CollectionTrend;
use App\Support\Dashboard\TourReadiness;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ana panel: "bugün neyle ilgilenmeliyim?" — vadesi geçmiş / yaklaşan taksitler ve yaklaşan turların
 * hazırlık durumu (oda, koltuk, uçuş, pasaport, ön kayıt). Rehber doğrudan kendi turlarına gider.
 */
class DashboardController extends Controller
{
    /** "Turların durumu" tablosunda en fazla bu kadar aktif tur (başlangıç tarihine göre). */
    private const UPCOMING_LIMIT = 10;

    public function __invoke(
        Request $request,
        CurrentTenant $currentTenant,
        TourReadiness $readiness,
        ActivityFeed $activity,
        CollectionTrend $trend,
        CollectionSummary $money,
    ): Response|RedirectResponse {
        if ($request->user()?->hasRole(UserRole::Guide)) {
            return to_route('tours.index');
        }

        $tenant = $currentTenant->get();
        $payments = $tenant?->hasFeature(Feature::Payments) ?? false;

        $overdue = $payments ? $money->overdue() : null;

        $upcoming = Tour::query()
            ->active()
            ->orderBy('start_date')
            ->limit(self::UPCOMING_LIMIT)
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => [
                'persons' => Person::count(),
                'activeTours' => $tenant?->activeTourCount() ?? 0,
                'activeTourLimit' => $tenant?->plan->active_tour_limit,
                'outstanding' => $payments ? $money->outstanding() : [],
            ],
            'payments' => $overdue ? [
                'overdue_count' => $overdue['count'],
                'overdue' => $overdue['amounts'],
                // Önümüzdeki 7 günde vadesi gelen taksitler (bugün hariç; bugünkü "vadesi geçmiş"e dahil).
                'due_soon_count' => Installment::query()
                    ->whereDate('due_date', '>', today())
                    ->whereDate('due_date', '<=', today()->addDays(7))
                    ->whereHas('registration', fn ($q) => $q->where('status', '!=', RegistrationStatus::Cancelled))
                    ->distinct('registration_id')
                    ->count('registration_id'),
            ] : null,
            'activity' => $tenant ? $activity->latest($tenant->id) : [],
            'trend' => $payments ? $trend->lastMonths() : null,
            'tours' => $upcoming->map(fn (Tour $tour) => [
                'id' => $tour->id,
                'name' => $tour->name,
                'status_label' => $tour->status->label(),
                'start_date' => $tour->start_date->toDateString(),
                'end_date' => $tour->end_date->toDateString(),
                'days_left' => (int) today()->diffInDays($tour->start_date, false),
                'capacity' => $tour->capacity,
                ...$readiness->for($tour, $tenant),
            ]),
        ]);
    }
}
