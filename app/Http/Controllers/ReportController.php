<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Bus;
use App\Models\Group;
use App\Models\SeatAssignment;
use App\Models\Tour;
use App\Models\TourHotel;
use App\Reports\Definitions\BusPassengerList;
use App\Reports\Definitions\CollectionReport;
use App\Reports\Definitions\StayRoomingList;
use App\Reports\Definitions\StayRoomOccupancy;
use App\Reports\Definitions\TourPassengerList;
use App\Reports\Definitions\TourPaymentStatus;
use App\Reports\ReportResponder;
use App\Support\Collections\CollectionFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Excel / PDF indirmeleri. ?format=xlsx|pdf (varsayılan xlsx).
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportResponder $responder) {}

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

        // Tam kimlik / pasaport no sadece maskesiz görme yetkisi olan kullanıcıya.
        $revealIds = $request->user()?->role->canRevealSensitiveData() ?? false;

        return $this->responder->download($definition->build($tour, $group, $revealIds), $this->format($request));
    }

    public function tourPayments(Request $request, Tour $tour, TourPaymentStatus $definition): Response
    {
        Gate::authorize('viewFinance', $tour);

        return $this->responder->download($definition->build($tour), $this->format($request));
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

        $bus->load('tour');
        $layout = $bus->layout();

        return $this->responder->pdfView('bus_seat_chart', Str::slug("{$bus->name} koltuk plani", '-', 'tr').'.pdf', 'reports.bus-seats', [
            'title' => "{$bus->tour->name} — {$bus->name} Koltuk Planı",
            'subtitle' => array_values(array_filter([
                $layout->label().($bus->plate ? ' · Plaka '.$bus->plate : ''),
                $bus->driver_name ? 'Şoför: '.$bus->driver_name.' '.($bus->driver_phone ?? '') : null,
            ])),
            'grid' => $layout->grid(),
            'reserved' => $bus->reserved(),
            'names' => $bus->seats()->with('registration.person')->get()
                ->mapWithKeys(fn (SeatAssignment $s) => [$s->seat_no => $s->registration->person->full_name])
                ->all(),
        ], landscape: $layout->rows > 9);
    }

    private function format(Request $request): string
    {
        return $request->query('format') === 'pdf' ? 'pdf' : 'xlsx';
    }
}
