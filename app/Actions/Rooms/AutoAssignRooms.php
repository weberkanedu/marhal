<?php

namespace App\Actions\Rooms;

use App\Enums\NeedEffect;
use App\Enums\RoomKind;
use App\Models\Registration;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\TourHotel;
use App\Support\FamilyUnits;
use App\Support\Needs\NeedProfiles;
use App\Support\TurkishText;
use Illuminate\Support\Facades\DB;

/**
 * "Otomatik dağıt": bu otelde kalması beklenen, henüz odası olmayan yolcuları mevcut odalara yerleştirir.
 * Önce plan (önizleme) üretilir; kullanıcı onaylayınca aynı plan uygulanır. Elle yapılmış yerleşimlere dokunmaz.
 *
 * Öncelikler:
 *  1. Aileler (yakınlık bağıyla bağlı yolcular) birlikte: karma aileler aile odasına, tek cinsiyetli aileler aynı odaya.
 *  2. Asansöre yakın odalar işaretlenmişse hareket güçlüğü olanlar (ve aileleri) önce ve oraya; diğerleri
 *     o odalara ancak başka yer kalmazsa (yarı dolu oda tamamlamaktan da önce gelir).
 *  3. Ailesinden biri zaten bir odadaysa, yer varsa onun yanına.
 *  4. Ödenen oda tipiyle aynı büyüklükte odalar; önce yarı dolu odalar tamamlanır.
 *  5. Boş odanın türü (erkek / kadın / aile) yerleşen kişilere göre ayarlanır; önceden aile olarak
 *     ayrılmış boş odalar en son kullanılır.
 */
class AutoAssignRooms
{
    /** @var array<string, array{room: Room, kind: RoomKind, free: int, persons: array<int, string>, index: int}> */
    private array $rooms = [];

    /** @var array<string, list<string>> */
    private array $links = [];

    /** @var array<string, true> hareket güçlüğü olan person_id'ler */
    private array $mobility = [];

    private bool $elevatorKnown = false;

    public function __construct(
        private readonly StayOccupancy $occupancy,
        private readonly AssignRoom $assign,
        private readonly NeedProfiles $needs,
    ) {}

    /**
     * @return array{
     *     placements: list<array{registration: Registration, room: Room}>,
     *     kinds: array<string, RoomKind>,
     *     unplaced: list<array{registration: Registration, reason: string}>,
     * }
     */
    public function plan(TourHotel $stay): array
    {
        $assigned = $stay->roomAssignments()->pluck('registration_id')->flip();
        $pending = $this->occupancy->expected($stay)
            ->reject(fn (Registration $r) => $assigned->has($r->id))
            ->sort(fn (Registration $a, Registration $b) => ($a->group->name ?? '') <=> ($b->group->name ?? '')
                ?: TurkishText::compare($a->person->last_name.' '.$a->person->first_name, $b->person->last_name.' '.$b->person->first_name))
            ->values();

        $this->loadRooms($stay);
        $occupantIds = collect($this->rooms)->flatMap(fn (array $r) => $r['persons']);
        // toBase: boş Eloquent koleksiyonuna metin eklenirken hata vermesin (herkes yerleşmişken).
        $this->links = $this->occupancy->familyLinks($pending->toBase()->map(fn (Registration $r) => $r->person_id)->merge($occupantIds));
        $this->mobility = array_map(fn () => true, array_filter(
            $this->needs->forPersons($pending->pluck('person_id')),
            fn (array $items) => NeedProfiles::has($items, NeedEffect::Mobility),
        ));
        $this->elevatorKnown = collect($this->rooms)->contains(fn (array $r) => $r['room']->near_elevator);

        $plan = ['placements' => [], 'kinds' => [], 'unplaced' => []];

        foreach ($this->units(array_values($pending->all())) as $unit) {
            $mixed = FamilyUnits::isMixedGender($unit);
            $kind = $mixed ? RoomKind::Family : RoomKind::forGender($unit[0]->person->gender);

            $key = $this->pick($unit, $kind);

            if ($key !== null) {
                $this->place($plan, $unit, $key, $kind);

                continue;
            }

            if ($mixed) {
                foreach ($unit as $registration) {
                    $plan['unplaced'][] = ['registration' => $registration, 'reason' => 'Ailesiyle birlikte kalabileceği boş aile odası yok'];
                }

                continue;
            }

            // Tek cinsiyetli aile bir odaya sığmadıysa tek tek yerleştirilir.
            foreach ($unit as $registration) {
                $single = $this->pick([$registration], $kind);

                if ($single === null) {
                    $plan['unplaced'][] = ['registration' => $registration, 'reason' => 'Uygun boş yatak yok'];
                } else {
                    $this->place($plan, [$registration], $single, $kind);
                }
            }
        }

        return $plan;
    }

    /**
     * Planı uygular. Her yerleşim AssignRoom kurallarından geçer; biri bile uymazsa hiçbiri kaydedilmez.
     *
     * @return array{placed: int, unplaced: int}
     */
    public function apply(TourHotel $stay): array
    {
        $plan = $this->plan($stay);

        DB::transaction(function () use ($plan): void {
            foreach ($plan['kinds'] as $roomId => $kind) {
                Room::query()->whereKey($roomId)->firstOrFail()->update(['kind' => $kind]);
            }

            foreach ($plan['placements'] as $placement) {
                $this->assign->handle($placement['room'], $placement['registration']);
            }
        });

        return ['placed' => count($plan['placements']), 'unplaced' => count($plan['unplaced'])];
    }

    private function loadRooms(TourHotel $stay): void
    {
        $this->rooms = [];

        $rooms = $stay->rooms()->with('assignments.registration.person')->get()
            ->sort(fn (Room $a, Room $b) => strnatcmp($a->room_no, $b->room_no))
            ->values();

        foreach ($rooms as $index => $room) {
            $this->rooms[$room->id] = [
                'room' => $room,
                'kind' => $room->kind,
                'free' => $room->capacity - $room->assignments->count(),
                'persons' => $room->assignments->map(fn (RoomAssignment $a) => $a->registration->person_id)->values()->all(),
                'index' => $index,
            ];
        }
    }

    /**
     * Aile bağıyla birbirine bağlı yolcu kümeleri. Küme içi sıra: her kişi kendinden önceki
     * birine bağlıdır (aile odası kuralı sırayla yerleştirirken de sağlansın diye).
     * Karma aileler ve kalabalık kümeler önce gelir.
     *
     * @param  list<Registration>  $pending
     * @return list<list<Registration>>
     */
    private function units(array $pending): array
    {
        $units = FamilyUnits::build($pending, $this->links);

        // Hareket güçlüğü olan aileler önce (asansöre yakın odalar dolmadan), sonra karma ve kalabalık aileler.
        usort($units, fn (array $a, array $b) => $this->needsElevator($b) <=> $this->needsElevator($a)
            ?: FamilyUnits::isMixedGender($b) <=> FamilyUnits::isMixedGender($a)
            ?: count($b) <=> count($a));

        return $units;
    }

    /**
     * Kümeye en uygun odayı seçer (puanı en düşük olan).
     *
     * @param  list<Registration>  $unit
     */
    private function pick(array $unit, RoomKind $kind): ?string
    {
        $size = count($unit);
        $personIds = array_map(fn (Registration $r) => $r->person_id, $unit);
        $desired = max($size, ...array_map(fn (Registration $r) => $r->room_type?->capacity() ?? $size, $unit));

        $best = null;
        $bestScore = null;

        foreach ($this->rooms as $key => $room) {
            if ($room['free'] < $size) {
                continue;
            }

            $empty = $room['persons'] === [];
            $capacity = $room['room']->capacity;
            $linked = ! $empty && $this->linkedToAny($personIds, $room['persons']);
            $tier = $this->tier($room['kind'], $kind, $empty, $linked, $capacity === $desired);

            if ($tier === null) {
                continue;
            }

            // Asansöre yakın odalar hareket güçlüğü olanlara; diğerleri için en sona bırakılır.
            // Ailesinin yanına giden yolcu için asansör tercihi aranmaz.
            $elevator = $this->elevatorKnown && ! $linked ? ($room['room']->near_elevator === $this->needsElevator($unit) ? 0 : 1) : 0;
            $score = [$elevator, $tier, abs($capacity - $desired), $room['index']];

            if ($bestScore === null || $score < $bestScore) {
                $best = $key;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * @param  list<Registration>  $unit
     */
    private function needsElevator(array $unit): bool
    {
        return collect($unit)->contains(fn (Registration $r) => isset($this->mobility[$r->person_id]));
    }

    /**
     * Daha küçük = daha iyi. null = bu oda kullanılamaz.
     */
    private function tier(RoomKind $roomKind, RoomKind $kind, bool $empty, bool $linked, bool $sizeMatches): ?int
    {
        if ($empty) {
            return match (true) {
                $roomKind === $kind && $sizeMatches => 3,
                $roomKind !== RoomKind::Family && $sizeMatches => 4,
                $roomKind === RoomKind::Family && $sizeMatches => 5,
                $roomKind === $kind => 7,
                $roomKind !== RoomKind::Family => 8,
                default => 9,
            };
        }

        // Dolu odalar: aile odası sadece akrabası oradaysa; karma aile başkasının odasına girmez.
        if ($roomKind === RoomKind::Family) {
            return $linked ? 1 : null;
        }

        if ($kind === RoomKind::Family || $roomKind !== $kind) {
            return null;
        }

        return match (true) {
            $linked => 1,
            $sizeMatches => 2,
            default => 6,
        };
    }

    /**
     * @param  list<string>  $personIds
     * @param  array<int, string>  $occupants
     */
    private function linkedToAny(array $personIds, array $occupants): bool
    {
        foreach ($personIds as $id) {
            if (array_intersect($this->links[$id] ?? [], $occupants) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{placements: list<array{registration: Registration, room: Room}>, kinds: array<string, RoomKind>, unplaced: list<array{registration: Registration, reason: string}>}  $plan
     * @param  list<Registration>  $unit
     */
    private function place(array &$plan, array $unit, string $key, RoomKind $kind): void
    {
        $room = &$this->rooms[$key];

        // Boş odanın türü yerleşenlere göre ayarlanır (aile odası boşken de ayrılmışsa korunur).
        if ($room['persons'] === [] && $room['kind'] !== $kind && ! ($room['kind'] === RoomKind::Family && $kind !== RoomKind::Family && count($unit) > 1)) {
            $room['kind'] = $kind;
            $plan['kinds'][$key] = $kind;
        }

        foreach ($unit as $registration) {
            $plan['placements'][] = ['registration' => $registration, 'room' => $room['room']];
            $room['persons'][] = $registration->person_id;
            $room['free']--;
        }
    }
}
