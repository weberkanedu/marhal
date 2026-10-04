<?php

namespace App\Actions\Rooms;

use App\Enums\RoomKind;
use App\Models\Person;
use App\Models\Room;
use App\Models\RoomAssignment;
use Illuminate\Validation\ValidationException;

/**
 * Oda bilgilerini değiştirir; odadakiler yeni ayara uymuyorsa engeller
 * (kapasite küçültme, erkek odasında kadın, aile bağı olmayanları aile odası yapma).
 */
class UpdateRoom
{
    public function __construct(private readonly StayOccupancy $occupancy) {}

    /**
     * @param  array{room_no: string, floor?: string|null, capacity: int, kind: string, notes?: string|null}  $data
     */
    public function handle(Room $room, array $data): Room
    {
        $kind = RoomKind::from($data['kind']);
        $occupants = $room->assignments()->with('registration.person')->get()
            ->map(fn (RoomAssignment $a) => $a->registration->person);

        if ($occupants->count() > $data['capacity']) {
            $this->fail('capacity', "Odada {$occupants->count()} kişi var; kapasite bundan küçük olamaz.");
        }

        if ($kind !== RoomKind::Family && $occupants->contains(fn (Person $p) => RoomKind::forGender($p->gender) !== $kind)) {
            $this->fail('kind', 'Odada karşı cinsten yolcu var; önce onu başka odaya taşıyın.');
        }

        if ($kind === RoomKind::Family && $occupants->count() > 1) {
            $links = $this->occupancy->familyLinks($occupants->pluck('id'));
            $alone = $occupants->first(fn (Person $p) => ($links[$p->id] ?? []) === []);

            if ($alone !== null) {
                $this->fail('kind', "{$alone->full_name} odadakilerle aile bağıyla bağlı değil; oda aile odası yapılamaz.");
            }
        }

        $room->update([...$data, 'kind' => $kind]);

        return $room;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
