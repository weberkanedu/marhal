<?php

namespace App\Reports\Definitions;

use App\Actions\Rooms\StayOccupancy;
use App\Models\Registration;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\TourHotel;
use App\Reports\Column;
use App\Reports\Report;

/**
 * Acente içi oda doluluk özeti: oda başına dolu / boş yatak, kalanlar, uyarılar
 * ve henüz yerleşmemiş yolcular (listenin sonunda).
 */
class StayRoomOccupancy
{
    public function __construct(private readonly StayOccupancy $occupancy) {}

    public function build(TourHotel $stay): Report
    {
        $stay->load(['hotel', 'tour']);

        $rooms = $stay->rooms()
            ->with(['assignments.registration.person'])
            ->get()
            ->sort(fn (Room $a, Room $b) => strnatcmp((string) $a->floor, (string) $b->floor) ?: strnatcmp($a->room_no, $b->room_no));

        $rows = $rooms->map(function (Room $room): array {
            $registrations = $room->assignments->sortBy(fn (RoomAssignment $a) => $a->created_at)->map(fn (RoomAssignment $a) => $a->registration);
            $mismatch = $registrations->filter(fn (Registration $r) => $r->room_type !== null && $r->room_type->capacity() !== $room->capacity);

            return [
                'room_no' => $room->room_no,
                'floor' => $room->floor,
                'kind' => $room->kind->label(),
                'capacity' => $room->capacity,
                'occupied' => $registrations->count(),
                'free' => $room->capacity - $registrations->count(),
                'names' => $registrations->map(fn (Registration $r) => $r->person->full_name)->join(', '),
                'warning' => $mismatch->isEmpty() ? null : 'Oda tipi farklı: '.$mismatch->map(fn (Registration $r) => $r->person->full_name.' ('.$r->room_type?->label().')')->join(', '),
            ];
        })->values();

        $assigned = $rooms->flatMap(fn (Room $room) => $room->assignments->pluck('registration_id'))->flip();
        $unplaced = $this->occupancy->expected($stay)->reject(fn (Registration $r) => $assigned->has($r->id));

        $unplacedRows = $unplaced->map(fn (Registration $r) => [
            'room_no' => '—',
            'kind' => $r->person->gender->label(),
            'names' => $r->person->full_name,
            'warning' => 'Yerleşmedi'.($r->room_type ? ' ('.$r->room_type->label().' ödedi)' : ''),
        ])->values();

        return new Report(
            key: 'stay_room_occupancy',
            title: "{$stay->hotel->name} Oda Doluluk Özeti",
            columns: [
                Column::text('room_no', 'Oda No', 5),
                Column::text('floor', 'Kat', 3),
                Column::text('kind', 'Tür', 5),
                Column::number('capacity', 'Yatak'),
                Column::number('occupied', 'Dolu'),
                Column::number('free', 'Boş'),
                Column::text('names', 'Kalanlar', 30),
                Column::text('warning', 'Uyarı', 20),
            ],
            rows: [...$rows->all(), ...$unplacedRows->all()],
            subtitle: [
                $stay->tour->name.' — '.$stay->hotel->city->label(),
                $stay->check_in->format('d.m.Y').' – '.$stay->check_out->format('d.m.Y').' · '.$stay->nights().' gece',
                $rooms->count().' oda · '.$rows->sum('capacity').' yatak · '.$rows->sum('occupied').' dolu · '.$rows->sum('free').' boş · '.$unplaced->count().' yolcu yerleşmedi',
            ],
            totals: [[
                'room_no' => 'TOPLAM',
                'capacity' => $rows->sum('capacity'),
                'occupied' => $rows->sum('occupied'),
                'free' => $rows->sum('free'),
            ]],
            landscape: true,
        );
    }
}
