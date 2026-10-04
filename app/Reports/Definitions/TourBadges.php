<?php

namespace App\Reports\Definitions;

use App\Enums\RegistrationStatus;
use App\Models\Group;
use App\Models\Registration;
use App\Models\RoomAssignment;
use App\Models\Tour;
use App\Models\TourHotel;
use App\Support\Media\PersonPhotoStore;
use App\Support\TurkishText;
use Illuminate\Support\Collection;

/**
 * Yaka kartı verisi (tur, grup veya tek yolcu). Kart başına: ad soyad, fotoğraf, grup, rehber,
 * kaldığı oteller (oda no varsa yanında), otobüs ve koltuk. Kimlik / pasaport bilgisi yazılmaz.
 * Görünüm: resources/views/reports/badges.blade.php (A4'e 8 kart, kesme çizgili).
 */
class TourBadges
{
    public function __construct(private readonly PersonPhotoStore $photos) {}

    /**
     * @return array<int, array{name: string, group: string|null, guide: string|null, photo: string|null, initials: string, lines: array<int, array{label: string, value: string}>}>
     */
    public function build(Tour $tour, ?Group $group = null, ?Registration $only = null): array
    {
        $registrations = $tour->registrations()
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->when($group, fn ($q) => $q->where('group_id', $group?->getKey()))
            ->when($only, fn ($q) => $q->whereKey($only?->getKey()))
            ->with(['person', 'group', 'roomAssignments.room.stay.hotel', 'seatAssignments.bus'])
            ->get()
            ->sort(fn (Registration $a, Registration $b) => ($a->group->name ?? "\u{FFFF}") <=> ($b->group->name ?? "\u{FFFF}")
                ?: TurkishText::compare($a->person->last_name.' '.$a->person->first_name, $b->person->last_name.' '.$b->person->first_name))
            ->values();

        // Grupların kaldığı oteller (odası henüz belli olmayan yolcuya da otel adı yazılsın).
        $groupStays = $tour->stays()->with(['hotel', 'groups:id'])->orderBy('check_in')->get();

        return $registrations->map(function (Registration $r) use ($groupStays): array {
            $person = $r->person;
            $group = $r->group;

            return [
                'name' => TurkishText::upper($person->first_name.' '.$person->last_name),
                'group' => $group?->name,
                'guide' => $group && ($group->guide_name || $group->guide_phone)
                    ? trim(($group->guide_name ?? '').' '.($group->guide_phone ?? ''))
                    : null,
                'photo' => $this->photos->dataUri($person),
                'initials' => TurkishText::upper(mb_substr($person->first_name, 0, 1).mb_substr($person->last_name, 0, 1)),
                'lines' => [...$this->hotelLines($r, $groupStays), ...$this->busLine($r)],
            ];
        })->all();
    }

    /**
     * "Mekke: Swissôtel Al Maqam · Oda 501". Yolcunun kendi odası (istisna otel dahil) öncelikli,
     * yoksa grubunun oteli (oda no olmadan).
     *
     * @param  Collection<int, TourHotel>  $groupStays
     * @return array<int, array{label: string, value: string}>
     */
    private function hotelLines(Registration $r, Collection $groupStays): array
    {
        $assigned = $r->roomAssignments->keyBy('tour_hotel_id');

        $stays = $groupStays
            ->filter(fn (TourHotel $s) => $s->groups->contains('id', $r->group_id))
            // Aynı tarihlerde başka otelde odası varsa grubun oteli yerine o yazılır.
            ->reject(fn (TourHotel $s) => ! $assigned->has($s->id) && $r->roomAssignments->contains(
                fn (RoomAssignment $a) => $a->room->stay->check_in < $s->check_out && $a->room->stay->check_out > $s->check_in,
            ))
            ->merge($r->roomAssignments->map(fn (RoomAssignment $a) => $a->room->stay))
            ->unique('id')
            ->sortBy('check_in');

        return $stays->map(fn (TourHotel $s) => [
            'label' => $s->hotel->city->label(),
            'value' => $s->hotel->name.($assigned->has($s->id) ? ' · Oda '.$assigned[$s->id]->room->room_no : ''),
        ])->values()->all();
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function busLine(Registration $r): array
    {
        $seat = $r->seatAssignments->first();

        return $seat ? [['label' => 'Otobüs', 'value' => "{$seat->bus->name} · Koltuk {$seat->seat_no}"]] : [];
    }
}
