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

/**
 * Ana paneldeki "tur ne kadar hazır?" özeti: oda, koltuk, uçuş, pasaport, ön kayıt, grupsuz yolcu.
 * Her kontrol, ilgili modül açıksa ve turda o kısım başlatılmışsa (ör. en az bir otel) gösterilir.
 */
class TourReadiness
{
    public function __construct(
        private readonly StayOccupancy $occupancy,
        private readonly FlightPassengers $flights,
    ) {}

    /**
     * @return array{
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

        return [
            'registered' => $active->count(),
            'pending' => $active->where('status', RegistrationStatus::Pending)->count(),
            'ungrouped' => $active->whereNull('group_id')->count(),
            'passport_issues' => $active->filter(fn (Registration $r) => $this->flights->warnings($r->person, $tour) !== [])->count(),
            'checks' => $checks,
        ];
    }
}
