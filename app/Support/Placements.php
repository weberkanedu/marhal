<?php

namespace App\Support;

use App\Models\Registration;
use App\Models\RoomAssignment;
use App\Models\TourHotel;
use Illuminate\Support\Collection;

/**
 * Bir kaydın otel, oda ve koltuk bilgisi ("Mekke 501", "1. Otobüs 12"). Tek kaynak: tur ekranı,
 * rehber görünümü, yolcu listesi raporu, yaka kartı ve (ileride) aile ekranı bunu kullanır.
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
     * Kaldığı oteller (konaklama sırasıyla), odası belliyse oda no ile: yaka kartı ve aile ekranı.
     * Yolcunun kendi odası (istisna otel dahil) önceliklidir; odası yoksa grubunun oteli yazılır.
     * Aynı tarihlerde başka otelde odası varsa grubun oteli yerine o yazılır.
     *
     * @param  Collection<int, TourHotel>  $groupStays  turun konaklamaları (hotel ve groups:id yüklü)
     * @return list<array{city: string, hotel: string, room: string|null, address: string|null, check_in: string, check_out: string}>
     */
    public static function hotels(Registration $registration, Collection $groupStays): array
    {
        $assignments = $registration->relationLoaded('roomAssignments') ? $registration->roomAssignments : collect();
        $assigned = $assignments->keyBy('tour_hotel_id');

        return array_values($groupStays
            ->filter(fn (TourHotel $s) => $s->groups->contains('id', $registration->group_id))
            ->reject(fn (TourHotel $s) => ! $assigned->has($s->id) && $assignments->contains(
                fn (RoomAssignment $a) => $a->room->stay->check_in < $s->check_out && $a->room->stay->check_out > $s->check_in,
            ))
            ->merge($assignments->map(fn (RoomAssignment $a) => $a->room->stay))
            ->unique('id')
            ->sortBy('check_in')
            ->map(fn (TourHotel $s) => [
                'city' => $s->hotel->city->label(),
                'hotel' => $s->hotel->name,
                'room' => $assigned->has($s->id) ? (string) $assigned[$s->id]->room->room_no : null,
                'address' => $s->hotel->address,
                'check_in' => $s->check_in->toDateString(),
                'check_out' => $s->check_out->toDateString(),
            ])
            ->all());
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
