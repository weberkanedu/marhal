<?php

namespace App\Support\Dashboard;

use App\Actions\Flights\FlightPassengers;
use App\Actions\Rooms\StayOccupancy;
use App\Enums\Feature;
use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourHotel;
use App\Support\Money;
use App\Support\Readiness\ReadinessBoard;

/**
 * Ana paneldeki "tur ne kadar hazır?" özeti: oda, koltuk, uçuş, pasaport, ön kayıt, grupsuz yolcu.
 * Her kontrol, ilgili modül açıksa ve turda o kısım başlatılmışsa (ör. en az bir otel) gösterilir.
 */
class TourReadiness
{
    public function __construct(
        private readonly StayOccupancy $occupancy,
        private readonly FlightPassengers $flights,
        private readonly ReadinessBoard $board,
    ) {}

    /**
     * @return array{
     *     collection: array{currency: string, paid: string, total: string}|null,
     *     registered: int,
     *     pending: int,
     *     ungrouped: int,
     *     passport_issues: int,
     *     checks: list<array{key: string, label: string, done: int, total: int, tab: string}>,
     * }
     */
    public function for(Tour $tour, ?Tenant $tenant): array
    {
        $active = $tour->registrations()
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->with('person')
            ->withCount(['seatAssignments', 'flightPassengers'])
            ->withPaidTotal()
            ->get();

        $checks = [];

        if ($tenant?->hasFeature(Feature::RoomPlanning) && $tour->stays()->exists()) {
            [$done, $total] = $tour->stays()->get()->reduce(function (array $carry, TourHotel $stay): array {
                $expected = $this->occupancy->expected($stay)->pluck('id');
                $placed = $stay->roomAssignments()->whereIn('registration_id', $expected)->count();

                return [$carry[0] + $placed, $carry[1] + $expected->count()];
            }, [0, 0]);
            $checks[] = ['key' => 'rooms', 'label' => 'Oda yerleşimi', 'done' => $done, 'total' => $total, 'tab' => 'konaklama'];
        }

        if ($tenant?->hasFeature(Feature::BusPlanning) && $tour->buses()->exists()) {
            $checks[] = [
                'key' => 'seats', 'label' => 'Otobüs koltuğu', 'tab' => 'ulasim',
                'done' => $active->where('seat_assignments_count', '>', 0)->count(), 'total' => $active->count(),
            ];
        }

        if ($tenant?->hasFeature(Feature::FlightLists) && $tour->flights()->exists()) {
            $checks[] = [
                'key' => 'flights', 'label' => 'Uçuş kaydı', 'tab' => 'ulasim',
                'done' => $active->where('flight_passengers_count', '>', 0)->count(), 'total' => $active->count(),
            ];
        }

        // Hazırlık: bütün maddeleri tamam olan yolcular (ReadinessBoard; Ravza bekleyenler ana panel uyarısında).
        $ravza = null;
        if ($tenant?->hasFeature(Feature::Readiness) && $active->isNotEmpty()) {
            $summary = $this->board->summary($tour);
            $checks[] = ['key' => 'readiness', 'label' => 'Hazırlık', 'done' => $summary['ready'], 'total' => $summary['total'], 'tab' => 'hazirlik'];
            $ravza = $summary['ravza_waiting'];
        }

        // Tahsilat oranı: turun para birimindeki kayıtlar (farklı para birimleri toplanmaz).
        $collection = null;
        if ($tenant?->hasFeature(Feature::Payments)) {
            $inTourCurrency = $active->where('currency', $tour->currency);
            $collection = [
                'currency' => $tour->currency,
                'paid' => $inTourCurrency->reduce(fn (string $c, Registration $r) => Money::add($c, $r->paidTotal()), '0.00'),
                'total' => $inTourCurrency->reduce(fn (string $c, Registration $r) => Money::add($c, $r->netPrice()), '0.00'),
            ];
        }

        return [
            'collection' => $collection,
            'registered' => $active->count(),
            'pending' => $active->where('status', RegistrationStatus::Pending)->count(),
            'ungrouped' => $active->whereNull('group_id')->count(),
            'passport_issues' => $active->filter(fn (Registration $r) => $this->flights->warnings($r->person, $tour) !== [])->count(),
            'checks' => $checks,
            'ravza_waiting' => $ravza,
        ];
    }
}
