<?php

namespace App\Support;

use App\Models\Registration;
use App\Models\RoomAssignment;

/**
 * Bir kaydın oda ve koltuk bilgisi ("Mekke 501", "1. Otobüs 12"). Tur ekranı, rehber görünümü
 * ve yolcu listesi raporu aynı bilgiyi kullanır.
 *
 * Önce RELATIONS yüklenmeli; yüklenmemiş ilişki atlanır (modül kapalıysa ek sorgu yapılmaz).
 */
final class Placements
{
    /** @var list<string> */
    public const ROOM_RELATIONS = ['roomAssignments.room.stay.hotel'];

    /** @var list<string> */
    public const SEAT_RELATIONS = ['seatAssignments.bus'];

    /**
     * Konaklama sırasıyla odalar: [{label: "Mekke", value: "501"}, …]
     *
     * @return array<int, array{label: string, value: string}>
     */
    public static function rooms(Registration $registration): array
    {
        if (! $registration->relationLoaded('roomAssignments')) {
            return [];
        }

        return $registration->roomAssignments
            ->sortBy(fn (RoomAssignment $a) => $a->room->stay->check_in)
            ->map(fn (RoomAssignment $a) => ['label' => $a->room->stay->hotel->city->label(), 'value' => $a->room->room_no])
            ->values()
            ->all();
    }

    /**
     * Otobüs koltuğu: {label: "1. Otobüs", value: "12"} veya null.
     *
     * @return array{label: string, value: string}|null
     */
    public static function seat(Registration $registration): ?array
    {
        if (! $registration->relationLoaded('seatAssignments') || ($seat = $registration->seatAssignments->first()) === null) {
            return null;
        }

        return ['label' => $seat->bus->name, 'value' => (string) $seat->seat_no];
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public static function all(Registration $registration): array
    {
        return array_values(array_filter([...self::rooms($registration), self::seat($registration)]));
    }

    /**
     * Excel / PDF hücresi için: "Mekke 501 · Medine 302".
     *
     * @param  array<int, array{label: string, value: string}>  $items
     */
    public static function text(array $items): ?string
    {
        return $items === [] ? null : implode(' · ', array_map(fn (array $i) => "{$i['label']} {$i['value']}", $items));
    }
}
