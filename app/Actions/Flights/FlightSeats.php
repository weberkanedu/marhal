<?php

namespace App\Actions\Flights;

use App\Actions\Rooms\StayOccupancy;
use App\Models\AircraftType;
use App\Models\Flight;
use App\Models\FlightPassenger;
use App\Support\Flights\AircraftLayout;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Uçuş koltuk planı kuralları (havayoluna gönderilen koltuk tercih listesi):
 * uçak tipi seçme (düzen uçuşa kopyalanır), koltuk atama / yer değiştirme / kaldırma,
 * başka yolculara ait (gri) koltuklar ve uyarılar (acil çıkış yaşı, yanında akrabası olmayan karşı cins).
 * Uyarılar engellemez; engelleyenler: olmayan koltuk, gri koltuk, dolu koltuk.
 */
class FlightSeats
{
    public function __construct(private readonly StayOccupancy $occupancy) {}

    public function setAircraft(Flight $flight, AircraftType $type): Flight
    {
        $layout = $type->layout();
        $seated = $flight->passengers()->whereNotNull('seat_no')->pluck('seat_no');

        if ($missing = $seated->reject(fn (string $s) => $layout->has($s))->values()->all()) {
            $this->fail('aircraft_type_id', 'Bu uçakta olmayan koltuklarda yolcu var, önce taşıyın: '.implode(', ', $missing));
        }

        $flight->update([
            'aircraft_type_id' => $type->id,
            ...$layout->toAttributes(),
            'blocked_seats' => array_values(array_filter($flight->blocked(), fn (string $s) => $layout->has($s))),
        ]);

        return $flight;
    }

    public function assign(Flight $flight, FlightPassenger $passenger, string $seat, bool $swap = false): void
    {
        $seat = strtoupper(trim($seat));

        DB::transaction(function () use ($flight, $passenger, $seat, $swap): void {
            $flight = Flight::query()->lockForUpdate()->whereKey($flight->getKey())->firstOrFail();
            $layout = $this->layout($flight);

            if ($passenger->flight_id !== $flight->id) {
                $this->fail('seat', 'Yolcu bu uçuşta değil.');
            }

            if (! $layout->has($seat)) {
                $this->fail('seat', "{$seat} bu uçakta yok.");
            }

            if (in_array($seat, $flight->blocked(), true)) {
                $this->fail('seat', "{$seat} başka bir yolcuya ait.");
            }

            $taken = $flight->passengers()->where('seat_no', $seat)->whereKeyNot($passenger->getKey())->with('registration.person')->first();

            if ($taken !== null && ! ($swap && $passenger->seat_no !== null)) {
                $this->fail('seat', "{$seat} koltuğunda {$taken->registration->person->full_name} var.");
            }

            if ($taken !== null) {
                // Yer değiştirme: (uçuş, koltuk) benzersiz; önce karşıdaki boşaltılır.
                $old = $passenger->seat_no;
                $taken->update(['seat_no' => null]);
                $passenger->update(['seat_no' => $seat]);
                $taken->update(['seat_no' => $old]);

                return;
            }

            $passenger->update(['seat_no' => $seat]);
        });
    }

    public function unassign(FlightPassenger $passenger): void
    {
        $passenger->update(['seat_no' => null]);
    }

    public function clear(Flight $flight): int
    {
        return DB::transaction(fn (): int => $flight->passengers()->whereNotNull('seat_no')->get()
            ->each(fn (FlightPassenger $p) => $p->update(['seat_no' => null]))
            ->count());
    }

    /**
     * Gri koltuk (başka yolcuya ait) aç / kapat.
     */
    public function toggleBlocked(Flight $flight, string $seat): bool
    {
        $seat = strtoupper(trim($seat));
        $layout = $this->layout($flight);

        if (! $layout->has($seat)) {
            $this->fail('seat', "{$seat} bu uçakta yok.");
        }

        if ($flight->passengers()->where('seat_no', $seat)->exists()) {
            $this->fail('seat', "{$seat} koltuğunda kendi yolcunuz var.");
        }

        $blocked = collect($flight->blocked());
        $now = ! $blocked->contains($seat);
        $flight->update(['blocked_seats' => ($now ? $blocked->push($seat) : $blocked->reject(fn ($s) => $s === $seat))->values()->all()]);

        return $now;
    }

    /**
     * Koltuk → uyarılar. Yolcuların registration.person ilişkisi yüklü olmalı.
     *
     * @param  Collection<int, FlightPassenger>  $passengers
     * @return array<string, list<string>>
     */
    public function warnings(Flight $flight, Collection $passengers): array
    {
        $layout = $flight->layout();

        if ($layout === null) {
            return [];
        }

        $bySeat = $passengers->filter(fn (FlightPassenger $p) => $p->seat_no !== null)->keyBy('seat_no');
        $links = $this->occupancy->familyLinks($bySeat->map(fn (FlightPassenger $p) => $p->registration->person_id));
        $warnings = [];

        foreach ($bySeat as $seat => $passenger) {
            $person = $passenger->registration->person;
            $age = $person->birth_date?->diffInYears($flight->departure_at);

            if ($layout->isExitRow($layout->row((string) $seat)) && $age !== null
                && ($age < AircraftLayout::EXIT_MIN_AGE || $age >= AircraftLayout::EXIT_MAX_AGE)) {
                $warnings[$seat][] = 'Acil çıkış sırasında '.((int) $age).' yaşında yolcu (havayolu kabul etmez: '
                    .AircraftLayout::EXIT_MIN_AGE.' yaş altı, '.AircraftLayout::EXIT_MAX_AGE.' yaş ve üstü)';
            }

            foreach ($layout->neighbours((string) $seat) as $neighbourSeat) {
                $neighbour = $bySeat->get($neighbourSeat)?->registration->person;

                if ($neighbour !== null && $neighbour->gender !== $person->gender && ! in_array($neighbour->id, $links[$person->id] ?? [], true)) {
                    $warnings[$seat][] = "Yanında karşı cinsten, akrabası olmayan yolcu ({$neighbour->full_name})";
                }
            }
        }

        return $warnings;
    }

    private function layout(Flight $flight): AircraftLayout
    {
        return $flight->layout() ?? $this->fail('seat', 'Önce uçak tipini seçin.');
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
