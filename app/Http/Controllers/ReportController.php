<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\BadgeSetting;
use App\Models\Bus;
use App\Models\Flight;
use App\Models\Group;
use App\Models\Person;
use App\Models\SeatAssignment;
use App\Models\Tour;
use App\Models\TourHotel;
use App\Reports\Definitions\BusDriverList;
use App\Reports\Definitions\BusPassengerList;
use App\Reports\Definitions\CollectionReport;
use App\Reports\Definitions\FlightManifest;
use App\Reports\Definitions\FlightSeatPreferences;
use App\Reports\Definitions\FlightSpecialAssistance;
use App\Reports\Definitions\PersonList;
use App\Reports\Definitions\StayFloorReports;
use App\Reports\Definitions\StayRoomingList;
use App\Reports\Definitions\StayRoomOccupancy;
use App\Reports\Definitions\TourBadges;
use App\Reports\Definitions\TourPassengerList;
use App\Reports\Definitions\TourPaymentStatus;
use App\Reports\Definitions\TourProgram;
use App\Reports\ReportResponder;
use App\Support\Collections\CollectionFilters;
use App\Support\GroupColors;
use App\Support\Persons\PersonListFilter;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Excel / PDF indirmeleri. ?format=xlsx|pdf (varsayılan xlsx).
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportResponder $responder,
        private readonly CurrentTenant $currentTenant,
    ) {}

    public function tourPassengers(Request $request, Tour $tour, TourPassengerList $definition): Response
    {
        Gate::authorize('view', $tour);

        $user = $request->user();
        $groups = Group::query()
            ->where('tour_id', $tour->getKey())
            // Rehber sadece kendi grubunun listesini alabilir.
            ->when($user?->hasRole(UserRole::Guide), fn ($q) => $q->where('guide_user_id', $user?->getKey()));

        $groupId = $request->query('group');
        $group = match (true) {
            is_string($groupId) && $groupId !== '' => $groups->whereKey($groupId)->firstOrFail(),
            $user?->hasRole(UserRole::Guide) ?? false => $groups->firstOrFail(),
            default => null,
        };

        return $this->responder->download($definition->build($tour, $group, $this->revealIds($request)), $this->format($request));
    }

    public function tourPayments(Request $request, Tour $tour, TourPaymentStatus $definition): Response
    {
        Gate::authorize('viewFinance', $tour);

        return $this->responder->download($definition->build($tour), $this->format($request));
    }

    /**
     * Turun şoför listesi (araç, plaka, şoför telefonu, gruplar).
     */
    public function busDrivers(Request $request, Tour $tour, BusDriverList $definition): Response
    {
        Gate::authorize('update', $tour);

        return $this->responder->download($definition->build($tour), $this->format($request));
    }

    /**
     * Tur programı (tarih, şehir, otel, uçuş); rehber de alabilir.
     */
    public function tourProgram(Request $request, Tour $tour, TourProgram $definition): Response
    {
        Gate::authorize('view', $tour);

        return $this->responder->download($definition->build($tour, $this->currentTenant->get()), $this->format($request));
    }

    /**
     * Yolcular ekranı: tüm yolcular (ekrandaki süzgeçle).
     */
    public function persons(Request $request, PersonList $definition): Response
    {
        Gate::authorize('viewAny', Person::class);

        return $this->responder->download(
            $definition->build($this->personFilter($request), $this->revealIds($request)),
            $this->format($request),
        );
    }

    /**
     * Pasaport kontrol listesi: pasaport no / bitiş tarihi / 6 ay kuralı, sorunlular üstte.
     */
    public function passports(Request $request, PersonList $definition): Response
    {
        Gate::authorize('viewAny', Person::class);

        return $this->responder->download(
            $definition->passports($this->personFilter($request), $this->revealIds($request)),
            $this->format($request),
        );
    }

    public function collections(Request $request, CollectionReport $definition): Response
    {
        Gate::authorize('viewAnyFinance', Tour::class);

        return $this->responder->download(
            $definition->build(CollectionFilters::fromRequest($request)),
            $this->format($request),
        );
    }

    /**
     * Otele verilecek oda listesi (rooming list).
     */
    public function floorPlan(Request $request, TourHotel $stay, StayFloorReports $definition): Response
    {
        Gate::authorize('update', $stay->tour);

        return $this->responder->download($definition->floorPlan($stay), $this->format($request));
    }

    /**
     * Otelde özel ihtiyacı olan yolcular (sağlık verisi: yalnız personel).
     */
    public function stayNeeds(Request $request, TourHotel $stay, StayFloorReports $definition): Response
    {
        Gate::authorize('update', $stay->tour);

        return $this->responder->download($definition->needsList($stay), $this->format($request));
    }

    public function roomingList(Request $request, TourHotel $stay, StayRoomingList $definition): Response
    {
        Gate::authorize('update', $stay->tour);

        $revealIds = $request->user()?->role->canRevealSensitiveData() ?? false;

        return $this->responder->download($definition->build($stay, $revealIds), $this->format($request));
    }

    public function roomOccupancy(Request $request, TourHotel $stay, StayRoomOccupancy $definition): Response
    {
        Gate::authorize('update', $stay->tour);

        return $this->responder->download($definition->build($stay), $this->format($request));
    }

    /**
     * Otobüs yolcu listesi (koltuk sırasıyla).
     */
    public function busPassengers(Request $request, Bus $bus, BusPassengerList $definition): Response
    {
        Gate::authorize('update', $bus->tour);

        $revealIds = $request->user()?->role->canRevealSensitiveData() ?? false;

        return $this->responder->download($definition->build($bus, $revealIds), $this->format($request));
    }

    /**
     * Otobüse asılabilecek koltuk planı çizimi (sadece PDF).
     */
    public function busSeatChart(Bus $bus): Response
    {
        Gate::authorize('update', $bus->tour);

        $bus->load(['tour', 'groups']);
        $layout = $bus->layout();
        // Tabela bandı: aracın (ilk) grubunun rengi — yaka kartı bandıyla aynı.
        $colors = GroupColors::forGroups($bus->tour->groups()->orderBy('name')->get());
        $group = $bus->groups->sortBy('name')->first();

        return $this->responder->pdfView('bus_seat_chart', Str::slug("{$bus->name} koltuk plani", '-', 'tr').'.pdf', 'reports.bus-seats', [
            'title' => "{$bus->tour->name} — {$bus->name} Koltuk Planı",
            'subtitle' => array_values(array_filter([
                $layout->label().($bus->plate ? ' · Plaka '.$bus->plate : ''),
                $bus->driver_name ? 'Şoför: '.$bus->driver_name.' '.($bus->driver_phone ?? '') : null,
            ])),
            'grid' => $layout->grid(),
            'reserved' => $bus->reserved(),
            'color' => $group ? $colors[$group->id] : '#111111',
            'groupNames' => $bus->groups->pluck('name')->join(', ') ?: null,
            'names' => $bus->seats()->with('registration.person')->get()
                ->mapWithKeys(fn (SeatAssignment $s) => [$s->seat_no => $s->registration->person->full_name])
                ->all(),
        ], landscape: $layout->rows > 9);
    }

    /**
     * Havayolu yolcu listesi (manifest).
     */
    public function flightManifest(Request $request, Flight $flight, FlightManifest $definition): Response
    {
        Gate::authorize('update', $flight->tour);

        $revealIds = $request->user()?->role->canRevealSensitiveData() ?? false;

        return $this->responder->download($definition->build($flight, $revealIds), $this->format($request));
    }

    /**
     * Yaka kartları (PDF). ?group=<id> bir grup, ?registration=<id> tek yolcu; ikisi de yoksa bütün tur.
     */
    public function tourBadges(Request $request, Tour $tour, TourBadges $definition): Response
    {
        Gate::authorize('update', $tour);

        $filters = $request->validate([
            'group' => ['nullable', 'uuid'],
            'registration' => ['nullable', 'uuid'],
        ]);

        // Başka turun / acentenin grubu veya kaydı → 404.
        $group = isset($filters['group']) ? $tour->groups()->whereKey($filters['group'])->firstOrFail() : null;
        $registration = isset($filters['registration']) ? $tour->registrations()->whereKey($filters['registration'])->firstOrFail() : null;

        $settings = BadgeSetting::current();
        $badges = $definition->build($tour, $group, $registration, $settings);
        $suffix = $registration ? Str::slug($registration->person->full_name, '-', 'tr') : ($group ? Str::slug($group->name, '-', 'tr') : 'tum-tur');

        return $this->responder->pdfView('tour_badges', "yaka-karti-{$suffix}.pdf", 'reports.badges', [
            'title' => "{$tour->name} Yaka Kartları",
            'badges' => $badges,
            'tourName' => $tour->name,
            'tourDates' => $tour->start_date->format('d.m.Y').' – '.$tour->end_date->format('d.m.Y'),
            'emergencyPhone' => $this->currentTenant->get()?->phone,
            'settings' => $settings,
            'size' => $settings->size,
        ]);
    }

    /**
     * Havayoluna özel yardım / yemek bildirimi (sağlık verisi içerir: yalnız personel).
     */
    public function flightAssistance(Request $request, Flight $flight, FlightSpecialAssistance $definition): Response
    {
        Gate::authorize('update', $flight->tour);

        return $this->responder->download($definition->build($flight), $this->format($request));
    }

    /**
     * Havayoluna gönderilecek koltuk tercih listesi.
     */
    public function flightSeats(Request $request, Flight $flight, FlightSeatPreferences $definition): Response
    {
        Gate::authorize('update', $flight->tour);

        return $this->responder->download($definition->build($flight), $this->format($request));
    }

    /**
     * Tam kimlik / pasaport no sadece maskesiz görme yetkisi olan kullanıcıya.
     */
    private function revealIds(Request $request): bool
    {
        return $request->user()?->role->canRevealSensitiveData() ?? false;
    }

    private function personFilter(Request $request): ?PersonListFilter
    {
        return PersonListFilter::tryFrom((string) $request->query('filtre', ''));
    }

    private function format(Request $request): string
    {
        return $request->query('format') === 'pdf' ? 'pdf' : 'xlsx';
    }
}
