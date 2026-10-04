<?php

namespace App\Actions\Rooms;

use App\Enums\RegistrationStatus;
use App\Enums\RoomKind;
use App\Models\Registration;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Support\TurkishText;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Yolcuyu odaya yerleştirir. Engelleyen kurallar:
 *  - kapasite, iptal edilmiş kayıt, başka turun yolcusu
 *  - erkek / kadın odasına karşı cinsten kişi
 *  - aile odasında oda arkadaşlarından en az biriyle aile bağı (yakınlık) şartı
 *  - aynı gecelerde başka bir otelin odasında olmak
 * Ödenen oda tipiyle oda büyüklüğünün farklı olması engellemez, sadece uyarıdır (müşteri kararı).
 * Yolcu bu otelde başka bir odadaysa yeni odaya taşınır.
 */
class AssignRoom
{
    public function __construct(private readonly StayOccupancy $occupancy) {}

    public function handle(Room $room, Registration $registration): RoomAssignment
    {
        return DB::transaction(function () use ($room, $registration): RoomAssignment {
            // Aynı odaya aynı anda iki yerleştirme yapılırsa kapasite aşılmasın.
            $room = Room::query()->lockForUpdate()->whereKey($room->getKey())->firstOrFail();
            $stay = $room->stay;
            $person = $registration->person;
            $name = $person->full_name;

            if ($registration->tour_id !== $stay->tour_id || $registration->status === RegistrationStatus::Cancelled) {
                $this->fail("{$name} bu turun aktif yolcusu değil.");
            }

            $occupants = $room->assignments()
                ->where('registration_id', '!=', $registration->getKey())
                ->with('registration.person')
                ->get()
                ->map(fn (RoomAssignment $a) => $a->registration->person);

            if ($occupants->count() >= $room->capacity) {
                $this->fail("{$room->room_no} numaralı oda dolu ({$room->capacity} kişilik).");
            }

            if ($room->kind !== RoomKind::Family && RoomKind::forGender($person->gender) !== $room->kind) {
                $this->fail("{$name} ".TurkishText::lower($room->kind->label()).' odasına yerleşemez. Karma kalacaklarsa odayı aile odası yapın.');
            }

            if ($room->kind === RoomKind::Family && $occupants->isNotEmpty()) {
                $links = $this->occupancy->familyLinks([$person->id, ...$occupants->pluck('id')]);

                if (($links[$person->id] ?? []) === []) {
                    $this->fail("{$name} odadakilerden hiçbiriyle aile bağıyla bağlı değil. Önce yolcu sayfasından yakınlığını ekleyin.");
                }
            }

            if ($clash = $this->occupancy->clashingAssignment($registration, $stay)) {
                $this->fail("{$name} aynı tarihlerde {$clash->room->stay->hotel->name} otelinde ({$clash->room->room_no}) kalıyor.");
            }

            return RoomAssignment::query()->updateOrCreate(
                ['tour_hotel_id' => $stay->getKey(), 'registration_id' => $registration->getKey()],
                ['room_id' => $room->getKey()],
            );
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['room' => $message]);
    }
}
