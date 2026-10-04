<?php

namespace App\Actions\Rooms;

use App\Enums\RegistrationStatus;
use App\Enums\Relation;
use App\Models\PersonRelation;
use App\Models\Registration;
use App\Models\RoomAssignment;
use App\Models\TourHotel;
use Illuminate\Support\Collection;

/**
 * Bir konaklamada kimlerin kalması beklendiği ve aile bağları.
 */
class StayOccupancy
{
    /**
     * Bu otelde kalması beklenen aktif kayıtlar: grubu bu konaklamada olanlar; aynı tarihlerde
     * başka bir otelin odasına yerleştirilmiş olanlar (istisna) hariç.
     *
     * @return Collection<int, Registration>
     */
    public function expected(TourHotel $stay): Collection
    {
        $groupIds = $stay->groups()->pluck('groups.id');

        return Registration::query()
            ->where('tour_id', $stay->tour_id)
            ->where('status', '!=', RegistrationStatus::Cancelled)
            ->whereIn('group_id', $groupIds)
            ->whereDoesntHave('roomAssignments', fn ($q) => $q->whereIn('tour_hotel_id', $this->overlappingStayIds($stay)))
            ->with(['person', 'group:id,name'])
            ->get();
    }

    /**
     * Aynı turda, bu konaklamayla aynı gecelere denk gelen diğer konaklamalar.
     *
     * @return Collection<int, string>
     */
    public function overlappingStayIds(TourHotel $stay): Collection
    {
        return TourHotel::query()
            ->where('tour_id', $stay->tour_id)
            ->whereKeyNot($stay->getKey())
            ->whereDate('check_in', '<', $stay->check_out)
            ->whereDate('check_out', '>', $stay->check_in)
            ->pluck('id');
    }

    /**
     * Kayıt aynı tarihlerde başka bir otelin odasında mı?
     */
    public function clashingAssignment(Registration $registration, TourHotel $stay): ?RoomAssignment
    {
        return RoomAssignment::query()
            ->where('registration_id', $registration->getKey())
            ->whereIn('tour_hotel_id', $this->overlappingStayIds($stay))
            ->with('room.stay.hotel')
            ->first();
    }

    /**
     * Verilen kişiler arasındaki aile bağları (komşuluk listesi; "diğer" yakınlık sayılmaz).
     *
     * @param  iterable<string>  $personIds
     * @return array<string, list<string>>
     */
    public function familyLinks(iterable $personIds): array
    {
        $ids = collect($personIds)->unique()->values();

        if ($ids->count() < 2) {
            return [];
        }

        $links = [];

        PersonRelation::query()
            ->whereIn('person_id', $ids)
            ->whereIn('related_person_id', $ids)
            ->where('relation', '!=', Relation::Other)
            ->get(['person_id', 'related_person_id'])
            ->each(function (PersonRelation $r) use (&$links): void {
                $links[$r->person_id][] = $r->related_person_id;
            });

        return $links;
    }
}
