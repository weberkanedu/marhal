<?php

namespace App\Reports\Definitions;

use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\TourHotel;
use App\Reports\Column;
use App\Reports\Report;
use App\Support\Needs\NeedProfiles;
use App\Support\TurkishText;
use Illuminate\Support\Collection;

/**
 * Otel ekranının iki çıktısı:
 * - "Kat planı": kat kat odalar, kişi sayısı, asansöre yakınlık, kalanlar (resepsiyon ve rehber için),
 * - "İhtiyaç listesi": otelde özel ihtiyacı olan yolcular, odaları ve notları (sağlık verisi: yalnız personel).
 */
class StayFloorReports
{
    public function __construct(private readonly NeedProfiles $needs) {}

    public function floorPlan(TourHotel $stay): Report
    {
        $rows = $this->rooms($stay)->map(fn (Room $room) => [
            'floor' => $room->floor,
            'room_no' => $room->room_no,
            'capacity' => "{$room->assignments->count()} / {$room->capacity}",
            'kind' => $room->kind->label(),
            'elevator' => $room->near_elevator ? 'Yakın' : null,
            'occupants' => $room->assignments->sortBy(fn (RoomAssignment $a) => $a->created_at)
                ->map(fn (RoomAssignment $a) => $a->registration->person->full_name)->join(', ') ?: null,
        ])->values()->all();

        return new Report(
            key: 'stay_floor_plan',
            title: "{$stay->hotel->name} Kat Planı",
            columns: [
                Column::text('floor', 'Kat', 4),
                Column::text('room_no', 'Oda', 5),
                Column::text('capacity', 'Dolu', 5),
                Column::text('kind', 'Tür', 6),
                Column::text('elevator', 'Asansör', 6),
                Column::text('occupants', 'Kalanlar', 40),
            ],
            rows: $rows,
            subtitle: $this->subtitle($stay),
            landscape: true,
        );
    }

    public function needsList(TourHotel $stay): Report
    {
        $rooms = $this->rooms($stay);
        $assignments = $rooms->flatMap(fn (Room $room) => $room->assignments->map(fn (RoomAssignment $a) => [$room, $a]));
        $profiles = $this->needs->forPersons($assignments->map(fn (array $pair) => $pair[1]->registration->person_id));

        $rows = $assignments
            ->filter(fn (array $pair) => isset($profiles[$pair[1]->registration->person_id]))
            ->map(function (array $pair) use ($profiles): array {
                [$room, $assignment] = $pair;
                $person = $assignment->registration->person;
                $items = $profiles[$person->id];

                return [
                    'room_no' => $room->room_no,
                    'floor' => $room->floor,
                    'elevator' => $room->near_elevator ? 'Yakın' : null,
                    'name' => TurkishText::upper($person->last_name).' '.$person->first_name,
                    'needs' => implode(', ', array_column($items, 'name')),
                    'note' => implode('; ', array_filter(array_column($items, 'note'))) ?: null,
                ];
            })
            ->values()
            ->all();

        return new Report(
            key: 'stay_needs',
            title: "{$stay->hotel->name} İhtiyaç Listesi",
            columns: [
                Column::text('room_no', 'Oda', 5),
                Column::text('floor', 'Kat', 4),
                Column::text('elevator', 'Asansör', 6),
                Column::text('name', 'Yolcu', 18),
                Column::text('needs', 'İhtiyaç', 18),
                Column::text('note', 'Not', 26),
            ],
            rows: $rows,
            subtitle: [...$this->subtitle($stay), count($rows).' yolcu', 'Sağlık bilgisi içerir — yalnız ilgili personele verin'],
            landscape: true,
        );
    }

    /**
     * @return Collection<int, Room>
     */
    private function rooms(TourHotel $stay): Collection
    {
        $stay->loadMissing(['hotel', 'tour']);

        return $stay->rooms()
            ->with('assignments.registration.person')
            ->get()
            ->sort(fn (Room $a, Room $b) => strnatcmp((string) $a->floor, (string) $b->floor) ?: strnatcmp($a->room_no, $b->room_no))
            ->values();
    }

    /**
     * @return list<string>
     */
    private function subtitle(TourHotel $stay): array
    {
        return [
            $stay->tour->name,
            $stay->hotel->city->label().' · '.$stay->check_in->format('d.m.Y').' – '.$stay->check_out->format('d.m.Y'),
        ];
    }
}
