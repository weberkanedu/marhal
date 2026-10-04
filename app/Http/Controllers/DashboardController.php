<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\Installment;
use App\Models\Person;
use App\Models\Registration;
use App\Models\Tour;
use App\Support\Dashboard\TourReadiness;
use App\Support\Money;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ana panel: "bugün neyle ilgilenmeliyim?" — vadesi geçmiş / yaklaşan taksitler ve yaklaşan turların
 * hazırlık durumu (oda, koltuk, uçuş, pasaport, ön kayıt). Rehber doğrudan kendi turlarına gider.
 */
class DashboardController extends Controller
{
    /** Hazırlık kartında gösterilecek en fazla yaklaşan tur. */
    private const UPCOMING_LIMIT = 4;

    public function __invoke(Request $request, CurrentTenant $currentTenant, TourReadiness $readiness): Response|RedirectResponse
    {
        if ($request->user()?->hasRole(UserRole::Guide)) {
            return to_route('tours.index');
        }

        $tenant = $currentTenant->get();
        $payments = $tenant?->hasFeature(Feature::Payments) ?? false;

        $active = Registration::query()
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->withPaidTotal()
            ->withDueTotal()
            ->get();

        $outstanding = $this->sumByCurrency($active, fn (Registration $r) => $r->balance());
        $overdueRegistrations = $active->filter(fn (Registration $r) => ! Money::isZero($r->overdue()));

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
                'outstanding' => $payments ? $outstanding : [],
            ],
            'payments' => $payments ? [
                'overdue_count' => $overdueRegistrations->count(),
                'overdue' => $this->sumByCurrency($overdueRegistrations, fn (Registration $r) => $r->overdue()),
                // Önümüzdeki 7 günde vadesi gelen taksitler (bugün hariç; bugünkü "vadesi geçmiş"e dahil).
                'due_soon_count' => Installment::query()
                    ->whereDate('due_date', '>', today())
                    ->whereDate('due_date', '<=', today()->addDays(7))
                    ->whereHas('registration', fn ($q) => $q->where('status', '!=', RegistrationStatus::Cancelled))
                    ->distinct('registration_id')
                    ->count('registration_id'),
            ] : null,
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

    /**
     * @param  Collection<int, Registration>  $registrations
     * @param  callable(Registration): string  $amount
     * @return array<string, string>
     */
    private function sumByCurrency(Collection $registrations, callable $amount): array
    {
        return $registrations
            ->groupBy('currency')
            ->map(fn (Collection $items) => $items->reduce(fn (string $carry, Registration $r) => Money::add($carry, $amount($r)), '0.00'))
            ->reject(fn (string $total) => Money::isZero($total))
            ->all();
    }
}
