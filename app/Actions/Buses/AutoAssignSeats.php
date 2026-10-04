<?php

namespace App\Actions\Buses;

use App\Actions\Rooms\StayOccupancy;
use App\Models\Bus;
use App\Models\Registration;
use App\Models\SeatAssignment;
use App\Support\FamilyUnits;
use App\Support\TurkishText;
use Illuminate\Support\Facades\DB;

/**
 * "Otomatik dağıt" (otobüs): bu otobüsün gruplarında olup henüz koltuğu olmayan yolcuları oturtur.
 * Önce plan (önizleme), onaylanınca aynı plan uygulanır. Elle yapılmış yerleşimlere dokunmaz.
 *
 * Öncelikler:
 *  1. 65 yaş ve üstü yolcular (ve aileleri) ön sıralara.
 *  2. Aileler ikişer ikişer aynı sıranın aynı tarafında yan yana, kalabalık aileler art arda sıralarda.
 *  3. Tek yolcular yanında karşı cinsten, akrabası olmayan biri olmayacak şekilde.
 * Rehbere ayrılmış koltuklar kullanılmaz.
 */
class AutoAssignSeats
{
    private const ELDERLY_AGE = 65;

    /** @var array<int, array{gender: string, person: string}> koltuk no → oturan */
    private array $taken = [];

    /** @var list<list<int>> */
    private array $blocks = [];

    /** @var array<string, list<string>> */
    private array $links = [];

    public function __construct(
        private readonly BusPassengers $passengers,
        private readonly StayOccupancy $occupancy,
        private readonly AssignSeat $assign,
    ) {}

    /**
     * @return array{placements: list<array{registration: Registration, seat: int}>, unplaced: list<array{registration: Registration, reason: string}>}
     */
    public function plan(Bus $bus): array
    {
        $seated = $bus->seats()->with('registration.person')->get();
        $pending = $this->passengers->expected($bus)
            ->reject(fn (Registration $r) => $seated->contains('registration_id', $r->id))
            ->sort(fn (Registration $a, Registration $b) => ($a->group->name ?? '') <=> ($b->group->name ?? '')
                ?: TurkishText::compare($a->person->last_name.' '.$a->person->first_name, $b->person->last_name.' '.$b->person->first_name))
            ->values();

        $layout = $bus->layout();
        $reserved = $bus->reserved();
        $this->blocks = array_map(
            fn (array $block) => array_values(array_diff($block, $reserved)),
            $layout->blocks(),
        );
        $this->taken = [];
        foreach ($seated as $seat) {
            $this->taken[$seat->seat_no] = ['gender' => $seat->registration->person->gender->value, 'person' => $seat->registration->person_id];
        }

        $this->links = $this->occupancy->familyLinks(
            $pending->toBase()->map(fn (Registration $r) => $r->person_id)
                ->merge($seated->map(fn (SeatAssignment $s) => $s->registration->person_id)),
        );

        $units = FamilyUnits::build(array_values($pending->all()), $this->links);
        $elderly = fn (array $unit) => collect($unit)->contains(fn (Registration $r) => ($r->person->birth_date->age ?? 0) >= self::ELDERLY_AGE);
        usort($units, fn (array $a, array $b) => $elderly($b) <=> $elderly($a) ?: count($b) <=> count($a));

        $plan = ['placements' => [], 'unplaced' => []];

        foreach ($units as $unit) {
            // Kalabalık aileler ikişer ikişer, birbirine yakın sıralara (arka sıraya yığılmasınlar).
            $pieces = array_chunk($unit, 2);
            $nearBlock = null;

            foreach ($pieces as $piece) {
                $window = count($piece) > 1 ? $this->window(count($piece), $nearBlock) : null;

                if ($window !== null) {
                    foreach ($piece as $i => $registration) {
                        $this->place($plan, $registration, $window['seats'][$i]);
                    }
                    $nearBlock = $window['block'];

                    continue;
                }

                foreach ($piece as $registration) {
                    $seat = $this->singleSeat($registration, $nearBlock);

                    if ($seat === null) {
                        $plan['unplaced'][] = ['registration' => $registration, 'reason' => 'Boş koltuk yok'];
                    } else {
                        $this->place($plan, $registration, $seat);
                    }
                }
            }
        }

        return $plan;
    }

    /**
     * @return array{placed: int, unplaced: int}
     */
    public function apply(Bus $bus): array
    {
        $plan = $this->plan($bus);

        DB::transaction(function () use ($bus, $plan): void {
            foreach ($plan['placements'] as $placement) {
                $this->assign->handle($bus, $placement['registration'], $placement['seat']);
            }
        });

        return ['placed' => count($plan['placements']), 'unplaced' => count($plan['unplaced'])];
    }

    /**
     * Aynı blokta (sıranın aynı tarafı) yan yana $size boş koltuk. Öndekiler önce; $near verilirse
     * o bloğa (ailenin önceki kısmına) en yakın bloklar önce.
     *
     * @return array{seats: list<int>, block: int}|null
     */
    private function window(int $size, ?int $near = null): ?array
    {
        foreach ($this->blockOrder($near) as $index) {
            $block = $this->blocks[$index];

            for ($i = 0; $i + $size <= count($block); $i++) {
                $seats = array_slice($block, $i, $size);

                if (array_filter($seats, fn (int $s) => isset($this->taken[$s])) === []) {
                    return ['seats' => $seats, 'block' => $index];
                }
            }
        }

        return null;
    }

    /**
     * Blok sırası: önden arkaya; $near verilirse ona uzaklığa göre (yakın olan önce).
     *
     * @return list<int>
     */
    private function blockOrder(?int $near): array
    {
        $order = array_keys($this->blocks);

        if ($near !== null) {
            usort($order, fn (int $a, int $b) => abs($a - $near) <=> abs($b - $near) ?: $a <=> $b);
        }

        return $order;
    }

    /**
     * Tek yolcu için ilk uygun koltuk: yanındakiler aynı cinsiyette veya akrabası olmalı;
     * yoksa (otobüs doluysa) ilk boş koltuk — ekranda uyarı görünür.
     */
    private function singleSeat(Registration $registration, ?int $near = null): ?int
    {
        $fallback = null;

        foreach ($this->blockOrder($near) as $blockIndex) {
            $block = $this->blocks[$blockIndex];

            foreach ($block as $index => $seat) {
                if (isset($this->taken[$seat])) {
                    continue;
                }

                $fallback ??= $seat;
                $ok = true;

                foreach ([$block[$index - 1] ?? null, $block[$index + 1] ?? null] as $neighbour) {
                    $other = $neighbour !== null ? ($this->taken[$neighbour] ?? null) : null;

                    if ($other !== null && $other['gender'] !== $registration->person->gender->value
                        && ! in_array($other['person'], $this->links[$registration->person_id] ?? [], true)) {
                        $ok = false;
                    }
                }

                if ($ok) {
                    return $seat;
                }
            }
        }

        return $fallback;
    }

    /**
     * @param  array{placements: list<array{registration: Registration, seat: int}>, unplaced: list<array{registration: Registration, reason: string}>}  $plan
     */
    private function place(array &$plan, Registration $registration, int $seat): void
    {
        $plan['placements'][] = ['registration' => $registration, 'seat' => $seat];
        $this->taken[$seat] = ['gender' => $registration->person->gender->value, 'person' => $registration->person_id];
    }
}
