<?php

namespace App\Actions\Rooms;

use App\Enums\RoomKind;
use App\Models\Registration;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\TourHotel;
use Illuminate\Support\Facades\DB;

/**
 * Başka bir oteldeki oda düzenini bu otele kopyalar (ör. Mekke → Medine): aynı odada kalanlar
 * burada da aynı odaya, boş bir odaya yerleşir. Önce plan (önizleme), onaylanınca uygulanır.
 *
 * - Sadece bu otelde kalması beklenen ve henüz odası olmayan yolcular taşınır.
 * - Hedef oda: boş; önce aynı tür ve aynı büyüklük, sonra aynı tür daha büyük, sonra başka türde boş oda
 *   (türü kaynak odanınki yapılır). Uygun oda yoksa o oda grubu yerleşmemiş listesine düşer.
 * - Her yerleşim AssignRoom kurallarından geçer.
 */
class CopyRoomPlan
{
    public function __construct(
        private readonly StayOccupancy $occupancy,
        private readonly AssignRoom $assign,
    ) {}

    /**
     * @return array{
     *     placements: list<array{registration: Registration, room: Room, from: string}>,
     *     kinds: array<string, RoomKind>,
     *     unplaced: list<array{registration: Registration, reason: string}>,
     * }
     */
    public function plan(TourHotel $source, TourHotel $target): array
    {
        $expected = $this->occupancy->expected($target)->keyBy('id');
        $alreadyPlaced = $target->roomAssignments()->pluck('registration_id')->flip();

        /** @var array<string, array{room: Room, kind: RoomKind, empty: bool}> $free */
        $free = [];
        foreach ($target->rooms()->withCount('assignments')->get()->sort(fn (Room $a, Room $b) => strnatcmp($a->room_no, $b->room_no)) as $room) {
            $free[$room->id] = ['room' => $room, 'kind' => $room->kind, 'empty' => $room->assignments_count === 0];
        }

        $plan = ['placements' => [], 'kinds' => [], 'unplaced' => []];

        $sourceRooms = $source->rooms()->with('assignments')->get()->sort(fn (Room $a, Room $b) => strnatcmp($a->room_no, $b->room_no));

        foreach ($sourceRooms as $sourceRoom) {
            $members = $sourceRoom->assignments
                ->sortBy(fn (RoomAssignment $a) => $a->created_at)
                ->map(fn (RoomAssignment $a) => $expected->get($a->registration_id))
                ->filter(fn (?Registration $r) => $r !== null && ! $alreadyPlaced->has($r->id))
                ->values();

            if ($members->isEmpty()) {
                continue;
            }

            $key = $this->pickRoom($free, $sourceRoom, $members->count());

            if ($key === null) {
                foreach ($members as $registration) {
                    $plan['unplaced'][] = ['registration' => $registration, 'reason' => "{$sourceRoom->room_no} numaralı odadakiler için yeterli boş oda yok"];
                }

                continue;
            }

            $target = $free[$key];
            $free[$key] = [...$target, 'empty' => false];
            if ($target['kind'] !== $sourceRoom->kind) {
                $plan['kinds'][$key] = $sourceRoom->kind;
            }

            foreach ($members as $registration) {
                $plan['placements'][] = ['registration' => $registration, 'room' => $target['room'], 'from' => $sourceRoom->room_no];
            }
        }

        return $plan;
    }

    /**
     * @return array{placed: int, unplaced: int}
     */
    public function apply(TourHotel $source, TourHotel $target): array
    {
        $plan = $this->plan($source, $target);

        DB::transaction(function () use ($plan): void {
            foreach ($plan['kinds'] as $roomId => $kind) {
                Room::query()->whereKey($roomId)->firstOrFail()->update(['kind' => $kind]);
            }

            foreach ($plan['placements'] as $placement) {
                $this->assign->handle($placement['room']->refresh(), $placement['registration']);
            }
        });

        return ['placed' => count($plan['placements']), 'unplaced' => count($plan['unplaced'])];
    }

    /**
     * @param  array<string, array{room: Room, kind: RoomKind, empty: bool}>  $free
     */
    private function pickRoom(array $free, Room $sourceRoom, int $size): ?string
    {
        $best = null;
        $bestScore = null;
        $index = 0;

        foreach ($free as $key => $candidate) {
            $index++;
            $capacity = $candidate['room']->capacity;

            if (! $candidate['empty'] || $capacity < $size) {
                continue;
            }

            $sameKind = $candidate['kind'] === $sourceRoom->kind;
            $score = [
                $sameKind ? 0 : 1,
                $capacity === $sourceRoom->capacity ? 0 : 1,
                $capacity - $size,
                $index,
            ];

            if ($bestScore === null || $score < $bestScore) {
                $best = $key;
                $bestScore = $score;
            }
        }

        return $best;
    }
}
