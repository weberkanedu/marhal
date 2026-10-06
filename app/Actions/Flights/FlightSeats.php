<?php

namespace App\Actions\Flights;

use App\Actions\Rooms\StayOccupancy;
use App\Enums\NeedEffect;
use App\Models\AircraftType;
use App\Models\Flight;
use App\Models\FlightPassenger;
use App\Support\FamilyUnits;
use App\Support\Flights\AircraftLayout;
use App\Support\Needs\NeedProfiles;
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
    public function __construct(
        private readonly StayOccupancy $occupancy,
        private readonly NeedProfiles $needs,
    ) {}

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

    /**
     * "Otomatik yerleştir": koltuğu olmayan yolcuları önden arkaya yerleştirir. Aileler aynı sırada
     * yan yana (aynı koridor bölümünde, sığmazsa aynı sırada); 15 yaş altı, 65 yaş ve üstü ve hareket
     * güçlüğü olanlar acil çıkış sırasına konmaz; gri (başka yolcuya ait) koltuklar atlanır.
     *
     * @return array{placed: int, unplaced: int}
     */
    public function autoAssign(Flight $flight): array
    {
        $layout = $this->layout($flight);

        return DB::transaction(function () use ($flight, $layout): array {
            $passengers = $flight->passengers()->with('registration.person')->get();
            $taken = array_flip([...$flight->blocked(), ...$passengers->pluck('seat_no')->filter()->all()]);
            $pending = $passengers->filter(fn (FlightPassenger $p) => $p->seat_no === null)->values();
            $profiles = $this->needs->forPersons($pending->map(fn (FlightPassenger $p) => $p->registration->person_id));
            $links = $this->occupancy->familyLinks($pending->map(fn (FlightPassenger $p) => $p->registration->person_id));
            $byRegistration = $pending->keyBy('registration_id');

            $exitBanned = function (FlightPassenger $p) use ($flight, $profiles): bool {
                $person = $p->registration->person;
                $age = $person->birth_date?->diffInYears($flight->departure_at);

                return ($age !== null && ($age < AircraftLayout::EXIT_MIN_AGE || $age >= AircraftLayout::EXIT_MAX_AGE))
                    || NeedProfiles::has($profiles[$person->id] ?? [], NeedEffect::Mobility);
            };

            // Bloklar: her sıranın her koridor bölümü (yan yana oturulan koltuklar).
            $blocks = [];
            foreach ($layout->rows() as $row) {
                foreach ($layout->groups() as $group) {
                    $blocks[] = ['row' => $row, 'seats' => array_map(fn (string $l) => $row.$l, $group)];
                }
            }

            $units = FamilyUnits::build(array_values($pending->map(fn (FlightPassenger $p) => $p->registration)->all()), $links);
            usort($units, fn (array $a, array $b) => count($b) <=> count($a));
            $placed = 0;
            $unplaced = 0;

            foreach ($units as $unit) {
                /** @var list<FlightPassenger> $members */
                $members = array_values(array_filter(array_map(fn ($r) => $byRegistration->get($r->id), $unit)));
                $banned = array_filter($members, $exitBanned) !== [];
                $free = fn (array $block) => array_values(array_filter($block['seats'], fn (string $s) => ! isset($taken[$s])));
                $usable = fn (array $block) => ! ($banned && $layout->isExitRow($block['row']));

                // Önce kümenin tamamına yetecek tek blok, yoksa aynı sıradaki bloklar, yoksa tek tek.
                $target = collect($blocks)->first(fn (array $b) => $usable($b) && count($free($b)) >= count($members));
                $seats = $target ? array_slice($free($target), 0, count($members)) : null;

                if ($seats === null) {
                    foreach ($layout->rows() as $row) {
                        $rowSeats = collect($blocks)->where('row', $row)->filter($usable)->flatMap($free)->values()->all();
                        if (count($rowSeats) >= count($members)) {
                            $seats = array_slice($rowSeats, 0, count($members));
                            break;
                        }
                    }
                }

                foreach ($members as $i => $member) {
                    $seat = $seats[$i] ?? collect($blocks)
                        ->filter(fn (array $b) => ! ($exitBanned($member) && $layout->isExitRow($b['row'])))
                        ->flatMap($free)
                        ->first();

                    if ($seat === null) {
                        $unplaced++;

                        continue;
                    }

                    $member->update(['seat_no' => $seat]);
                    $taken[$seat] = true;
                    $placed++;
                }
            }

            return ['placed' => $placed, 'unplaced' => $unplaced];
        });
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
        $profiles = $this->needs->forPersons($bySeat->map(fn (FlightPassenger $p) => $p->registration->person_id));

        foreach ($bySeat as $seat => $passenger) {
            $person = $passenger->registration->person;
            $age = $person->birth_date?->diffInYears($flight->departure_at);

            if ($layout->isExitRow($layout->row((string) $seat)) && $age !== null
                && ($age < AircraftLayout::EXIT_MIN_AGE || $age >= AircraftLayout::EXIT_MAX_AGE)) {
                $warnings[$seat][] = 'Acil çıkış sırasında '.((int) $age).' yaşında yolcu (havayolu kabul etmez: '
                    .AircraftLayout::EXIT_MIN_AGE.' yaş altı, '.AircraftLayout::EXIT_MAX_AGE.' yaş ve üstü)';
            }

            if ($layout->isExitRow($layout->row((string) $seat)) && NeedProfiles::has($profiles[$person->id] ?? [], NeedEffect::Mobility)) {
                $warnings[$seat][] = 'Hareket güçlüğü olan yolcu acil çıkış sırasında oturamaz';
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
