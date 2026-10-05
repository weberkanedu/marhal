<?php

namespace Database\Seeders\Demo;

use App\Actions\Flights\FlightSeats;
use App\Enums\VehicleBody;
use App\Http\Controllers\AircraftTypeController;
use App\Models\Flight;
use App\Models\FlightPassenger;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\VehicleType;

/**
 * Tasarım yenileme 3 örnek verisi: şoför yanı koltuklu minibüs ve VIP van araç tipleri; turun uçuşlarına
 * uçak tipi (A321neo / A330), birkaç yolcunun koltuk tercihi ve başka yolculara ait (gri) koltuklar.
 */
class VehicleAircraftDemo
{
    public function __construct(private readonly FlightSeats $seats) {}

    public function run(Tenant $tenant): string
    {
        VehicleType::query()->firstOrCreate(['name' => 'Sprinter minibüs (2+1)'], [
            'body' => VehicleBody::Mini, 'left_seats' => 2, 'right_seats' => 1, 'rows' => 4, 'back_row_seats' => 4, 'front_seats' => 2,
        ]);
        VehicleType::query()->firstOrCreate(['name' => 'VIP van (1+1)'], [
            'body' => VehicleBody::Van, 'left_seats' => 1, 'right_seats' => 1, 'rows' => 2, 'back_row_seats' => 3, 'front_seats' => 1,
        ]);

        $tour = Tour::query()->active()->orderBy('start_date')->first();
        $flights = $tour?->flights()->orderBy('departure_at')->get() ?? collect();

        foreach ($flights as $i => $flight) {
            /** @var Flight $flight */
            $this->seats->setAircraft($flight, AircraftTypeController::fromPreset($i === 0 ? 'a321neo' : 'a330-300'));
            $flight->refresh();
            $layout = $flight->layout();

            if ($layout === null) {
                continue;
            }

            // İlk sıralardan birkaçı başka yolculara ait; ilk 6 yolcu art arda koltuklara.
            $flight->update(['blocked_seats' => array_slice($layout->seats(), 0, 4)]);
            $free = array_slice($layout->seats(), 12);
            $flight->passengers()->whereNull('seat_no')->limit(6)->get()
                ->each(function (FlightPassenger $p, int $n) use ($flight, $free): void {
                    $this->seats->assign($flight, $p, $free[$n]);
                });
        }

        return '2 araç tipi, '.$flights->count().' uçuşa uçak tipi ve koltuk örnekleri eklendi.';
    }
}
