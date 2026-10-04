<?php

namespace App\Reports\Definitions;

use App\Models\Bus;
use App\Models\Group;
use App\Models\SeatAssignment;
use App\Reports\Column;
use App\Reports\Report;
use App\Support\TurkishText;

/**
 * Otobüs yolcu listesi (koltuk sırasıyla): transfer firması, rehber ve sınır / kontrol noktaları için.
 * Pasaport no yalnızca yetkili kullanıcıya tam yazılır. Rehbere ayrılan koltuklar da listede görünür.
 */
class BusPassengerList
{
    public function build(Bus $bus, bool $revealIds): Report
    {
        $bus->load(['tour', 'groups']);
        $seats = $bus->seats()->with(['registration.person', 'registration.group:id,name'])->get()->keyBy('seat_no');

        $rows = [];
        foreach ($bus->layout()->seatNumbers() as $seatNo) {
            /** @var SeatAssignment|null $seat */
            $seat = $seats->get($seatNo);

            if ($seat === null) {
                if (in_array($seatNo, $bus->reserved(), true)) {
                    $rows[] = ['seat' => $seatNo, 'last_name' => 'REHBER / GÖREVLİ'];
                }

                continue;
            }

            $person = $seat->registration->person;
            $rows[] = [
                'seat' => $seatNo,
                'last_name' => TurkishText::upper($person->last_name),
                'first_name' => TurkishText::upper($person->first_name),
                'gender' => $person->gender->label(),
                'birth_date' => $person->birth_date?->toDateString(),
                'nationality' => $person->nationality,
                'passport_no' => $revealIds ? $person->passport_no : $person->masked_passport_no,
                'phone' => $person->phone,
                'group' => $seat->registration->group?->name,
            ];
        }

        $guides = $bus->groups
            ->map(fn (Group $g) => trim($g->name.': '.($g->guide_name ?? '—').' '.($g->guide_phone ?? '')))
            ->join(' · ');

        return new Report(
            key: 'bus_passengers',
            title: "{$bus->tour->name} — {$bus->name} Yolcu Listesi",
            columns: [
                Column::number('seat', 'Koltuk'),
                Column::text('last_name', 'Soyad', 13),
                Column::text('first_name', 'Ad', 12),
                Column::text('gender', 'Cinsiyet', 6),
                Column::date('birth_date', 'Doğum tarihi'),
                Column::text('nationality', 'Uyruk', 5),
                Column::text('passport_no', 'Pasaport No', 10),
                Column::text('phone', 'Telefon', 10),
                Column::text('group', 'Grup', 8),
            ],
            rows: $rows,
            subtitle: array_values(array_filter([
                $bus->layout()->label().($bus->plate ? ' · Plaka '.$bus->plate : ''),
                $bus->driver_name ? 'Şoför: '.$bus->driver_name.' '.($bus->driver_phone ?? '') : null,
                $guides !== '' ? 'Rehber: '.$guides : null,
                $seats->count().' yolcu',
                $revealIds ? null : 'Pasaport numaraları maskelidir',
            ])),
            landscape: true,
        );
    }
}
