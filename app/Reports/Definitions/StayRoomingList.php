<?php

namespace App\Reports\Definitions;

use App\Models\Group;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\TourHotel;
use App\Reports\Column;
use App\Reports\Report;
use App\Support\TurkishText;

/**
 * Otele verilen oda listesi (rooming list): oda oda kalan kişiler, pasaport bilgileriyle.
 * Pasaport no yalnızca yetkili kullanıcıya (yönetici) tam yazılır, diğerlerine maskeli.
 * Müşteri örnek format paylaşmadı (2026-10-04); otellerin genelde istediği alanlarla hazırlandı.
 */
class StayRoomingList
{
    public function build(TourHotel $stay, bool $revealIds): Report
    {
        $stay->load(['hotel', 'tour', 'groups']);

        $rooms = $stay->rooms()
            ->with(['assignments.registration.person', 'assignments.registration.group:id,name'])
            ->get()
            ->sort(fn (Room $a, Room $b) => strnatcmp((string) $a->floor, (string) $b->floor) ?: strnatcmp($a->room_no, $b->room_no));

        $rows = [];

        foreach ($rooms as $room) {
            $assignments = $room->assignments->sortBy(fn (RoomAssignment $a) => $a->created_at)->values();

            foreach ($assignments as $index => $assignment) {
                $registration = $assignment->registration;
                $person = $registration->person;

                $rows[] = [
                    'room_no' => $room->room_no,
                    'floor' => $room->floor,
                    'room_type' => "{$room->capacity} kişilik",
                    'kind' => $room->kind->label(),
                    'bed' => $index + 1,
                    'last_name' => TurkishText::upper($person->last_name),
                    'first_name' => TurkishText::upper($person->first_name),
                    'gender' => $person->gender->label(),
                    'birth_date' => $person->birth_date?->toDateString(),
                    'nationality' => $person->nationality,
                    'passport_no' => $revealIds ? $person->passport_no : $person->masked_passport_no,
                    'passport_expiry' => $person->passport_expiry_date?->toDateString(),
                    'group' => $registration->group?->name,
                    'notes' => $room->notes,
                ];
            }
        }

        $guides = $stay->groups
            ->map(fn (Group $g) => trim($g->name.': '.($g->guide_name ?? '—').' '.($g->guide_phone ?? '')))
            ->join(' · ');

        return new Report(
            key: 'stay_rooming_list',
            title: "{$stay->hotel->name} Oda Listesi",
            columns: [
                Column::text('room_no', 'Oda No', 5),
                Column::text('floor', 'Kat', 3),
                Column::text('room_type', 'Oda tipi', 6),
                Column::text('kind', 'Tür', 4),
                Column::number('bed', 'Sıra'),
                Column::text('last_name', 'Soyad', 10),
                Column::text('first_name', 'Ad', 10),
                Column::text('gender', 'Cinsiyet', 5),
                Column::date('birth_date', 'Doğum tarihi'),
                Column::text('nationality', 'Uyruk', 4),
                Column::text('passport_no', 'Pasaport No', 8),
                Column::date('passport_expiry', 'Pasaport bitiş'),
                Column::text('group', 'Grup', 6),
                Column::text('notes', 'Not', 10),
            ],
            rows: $rows,
            subtitle: array_values(array_filter([
                $stay->tour->name.' — '.$stay->hotel->city->label(),
                'Giriş '.$stay->check_in->format('d.m.Y').' · Çıkış '.$stay->check_out->format('d.m.Y').' · '.$stay->nights().' gece',
                $rooms->count().' oda · '.count($rows).' kişi',
                $guides !== '' ? 'Rehber: '.$guides : null,
                $revealIds ? null : 'Pasaport numaraları maskelidir',
            ])),
            landscape: true,
        );
    }
}
