<?php

namespace App\Reports\Definitions;

use App\Actions\Flights\FlightPassengers;
use App\Models\Flight;
use App\Models\FlightPassenger;
use App\Reports\Column;
use App\Reports\Report;
use App\Support\TurkishText;

/**
 * Havayoluna verilen yolcu listesi (manifest): unvan, ad soyad pasaportta yazdığı gibi BÜYÜK harf,
 * pasaport bilgileri, PNR ve bilet no. Pasaport no yalnızca yetkili kullanıcıya tam yazılır.
 */
class FlightManifest
{
    public function __construct(private readonly FlightPassengers $rules) {}

    public function build(Flight $flight, bool $revealIds): Report
    {
        $flight->load('tour');

        $rows = $flight->passengers()
            ->with(['registration.person', 'registration.group:id,name'])
            ->get()
            ->sort(fn (FlightPassenger $a, FlightPassenger $b) => TurkishText::compare(
                $a->registration->person->last_name.' '.$a->registration->person->first_name,
                $b->registration->person->last_name.' '.$b->registration->person->first_name,
            ))
            ->values()
            ->map(function (FlightPassenger $p) use ($flight, $revealIds): array {
                $person = $p->registration->person;

                return [
                    'title' => $this->rules->title($person, $flight->departure_at),
                    'last_name' => TurkishText::upper($person->last_name),
                    'first_name' => TurkishText::upper($person->first_name),
                    'gender' => $person->gender->label(),
                    'birth_date' => $person->birth_date?->toDateString(),
                    'nationality' => $person->nationality,
                    'passport_no' => $revealIds ? $person->passport_no : $person->masked_passport_no,
                    'passport_expiry' => $person->passport_expiry_date?->toDateString(),
                    'pnr' => $p->pnr ?? $flight->pnr,
                    'ticket_no' => $p->ticket_no,
                    'group' => $p->registration->group?->name,
                ];
            })
            ->all();

        return new Report(
            key: 'flight_manifest',
            title: "{$flight->airline} {$flight->title()} Yolcu Listesi",
            columns: [
                Column::text('title', 'Unvan', 4),
                Column::text('last_name', 'Soyad', 12),
                Column::text('first_name', 'Ad', 12),
                Column::text('gender', 'Cinsiyet', 5),
                Column::date('birth_date', 'Doğum tarihi'),
                Column::text('nationality', 'Uyruk', 4),
                Column::text('passport_no', 'Pasaport No', 9),
                Column::date('passport_expiry', 'Pasaport bitiş'),
                Column::text('pnr', 'PNR', 7),
                Column::text('ticket_no', 'Bilet No', 10),
                Column::text('group', 'Grup', 7),
            ],
            rows: $rows,
            subtitle: array_values(array_filter([
                "{$flight->tour->name} — {$flight->direction->label()}",
                'Kalkış '.$flight->departure_at->format('d.m.Y H:i').' · Varış '.$flight->arrival_at->format('d.m.Y H:i'),
                $flight->pnr ? 'Grup PNR: '.$flight->pnr : null,
                $flight->baggage ? 'Bagaj: '.$flight->baggage : null,
                count($rows).' yolcu',
                $revealIds ? null : 'Pasaport numaraları maskelidir',
            ])),
            landscape: true,
        );
    }
}
