<?php

namespace App\Actions\Rooms;

use App\Enums\RoomKind;
use App\Models\Room;
use App\Models\TourHotel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Toplu oda ekler: "5. kat, 501'den başlayarak 10 oda, 4 kişilik, erkek" → 501…510.
 * Başlangıç numarası sayı değilse (ör. "A1") tek oda eklenir.
 */
class CreateRooms
{
    /**
     * @return list<Room>
     */
    public function handle(TourHotel $stay, string $startNo, int $count, int $capacity, RoomKind $kind, ?string $floor = null): array
    {
        $numbers = ctype_digit($startNo)
            ? array_map(fn (int $i) => (string) ((int) $startNo + $i), range(0, $count - 1))
            : [$startNo];

        $existing = $stay->rooms()->whereIn('room_no', $numbers)->pluck('room_no');

        if ($existing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'start_no' => 'Bu otelde zaten var olan oda numaraları: '.$existing->join(', '),
            ]);
        }

        return DB::transaction(fn () => array_map(
            fn (string $no) => $stay->rooms()->create([
                'room_no' => $no,
                'floor' => $floor,
                'capacity' => $capacity,
                'kind' => $kind,
            ]),
            $numbers,
        ));
    }
}
