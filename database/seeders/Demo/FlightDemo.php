<?php

namespace Database\Seeders\Demo;

use App\Actions\Flights\FlightPassengers;
use App\Enums\FlightDirection;
use App\Enums\RegistrationStatus;
use App\Models\FlightPassenger;
use App\Models\Tenant;
use App\Models\Tour;

/**
 * Faz 3 / uçuş örnek verisi: aktif turda gidiş (IST → JED) ve dönüş (MED → IST) uçuşları,
 * bütün aktif yolcular ve grup PNR'ı; dönüşte birkaç yolcunun bilet numarası girilmiş.
 */
class FlightDemo
{
    public function __construct(private readonly FlightPassengers $rules) {}

    public function run(Tenant $tenant): string
    {
        $tour = Tour::query()->active()->orderBy('start_date')->first();

        if ($tour === null) {
            return 'Aktif tur olmadığı için uçuş eklenmedi.';
        }

        $start = $tour->start_date->format('Y-m-d');
        $end = $tour->end_date->format('Y-m-d');

        $outbound = $tour->flights()->create([
            'direction' => FlightDirection::Outbound, 'airline' => 'Türk Hava Yolları', 'flight_no' => 'TK 92',
            'departure_airport' => 'IST', 'arrival_airport' => 'JED',
            'departure_at' => "{$start} 09:40", 'arrival_at' => "{$start} 13:35",
            'pnr' => 'MRH8K2', 'baggage' => '2 × 23 kg + 8 kg kabin',
        ]);
        $return = $tour->flights()->create([
            'direction' => FlightDirection::Return, 'airline' => 'Türk Hava Yolları', 'flight_no' => 'TK 109',
            'departure_airport' => 'MED', 'arrival_airport' => 'IST',
            'departure_at' => "{$end} 15:10", 'arrival_at' => "{$end} 19:05",
            'pnr' => 'MRH9Q4', 'baggage' => '2 × 23 kg + 8 kg kabin',
        ]);

        $registrations = $tour->registrations()->where('status', '!=', RegistrationStatus::Cancelled)->with('person')->get();
        $this->rules->add($outbound, $registrations);
        $this->rules->add($return, $registrations);

        $return->passengers()->limit(4)->get()->each(fn (FlightPassenger $p, int $i) => $p->update(['ticket_no' => '235-21'.str_pad((string) ($i + 1), 8, '0', STR_PAD_LEFT)]));

        return "{$tour->name} için gidiş ve dönüş uçuşu, {$registrations->count()} yolcuyla eklendi.";
    }
}
