<?php

namespace App\Reports\Definitions;

use App\Actions\Flights\FlightSeats;
use App\Models\Flight;
use App\Models\FlightPassenger;
use App\Reports\Column;
use App\Reports\Report;
use App\Support\TurkishText;

/**
 * Havayoluna gönderilen koltuk tercih listesi: koltuk sırasıyla yolcu, PNR, bilet no; koltuğu
 * olmayanlar sonda. Uyarılar (acil çıkış yaşı vb.) "Not" sütununda.
 */
class FlightSeatPreferences
{
    public function __construct(private readonly FlightSeats $seats) {}

    public function build(Flight $flight): Report
    {
        $passengers = $flight->passengers()->with(['registration.person', 'registration.group:id,name'])->get();
        $warnings = $this->seats->warnings($flight, $passengers);
        $layout = $flight->layout();
        $order = $layout ? array_flip($layout->seats()) : [];

        $rows = $passengers
            ->sort(fn (FlightPassenger $a, FlightPassenger $b) => ($order[$a->seat_no ?? ''] ?? PHP_INT_MAX) <=> ($order[$b->seat_no ?? ''] ?? PHP_INT_MAX)
                ?: TurkishText::compare($a->registration->person->last_name, $b->registration->person->last_name))
            ->values()
            ->map(fn (FlightPassenger $p) => [
                'seat' => $p->seat_no,
                'name' => TurkishText::upper($p->registration->person->last_name).' / '.TurkishText::upper($p->registration->person->first_name),
                'gender' => $p->registration->person->gender->label(),
                'group' => $p->registration->group?->name,
                'pnr' => $p->pnr ?? $flight->pnr,
                'ticket' => $p->ticket_no,
                'note' => $p->seat_no ? (implode('; ', $warnings[$p->seat_no] ?? []) ?: null) : 'Koltuk seçilmedi',
            ])
            ->all();

        return new Report(
            key: 'flight_seats',
            title: "{$flight->title()} Koltuk Tercih Listesi",
            columns: [
                Column::text('seat', 'Koltuk', 5),
                Column::text('name', 'Yolcu (SOYAD / AD)', 20),
                Column::text('gender', 'Cinsiyet', 6),
                Column::text('group', 'Grup', 7),
                Column::text('pnr', 'PNR', 7),
                Column::text('ticket', 'Bilet no', 10),
                Column::text('note', 'Not', 22),
            ],
            rows: $rows,
            subtitle: [
                $flight->airline.' · '.$flight->departure_at->format('d.m.Y H:i'),
                $layout ? trim(($flight->aircraftType->name ?? '').' '.$layout->label()) : 'Uçak tipi seçilmedi',
                count($rows).' yolcu',
            ],
            landscape: true,
        );
    }
}
