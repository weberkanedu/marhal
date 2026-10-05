<?php

namespace App\Reports\Definitions;

use App\Enums\NeedEffect;
use App\Models\Flight;
use App\Models\FlightPassenger;
use App\Reports\Column;
use App\Reports\Report;
use App\Support\Needs\NeedProfiles;
use App\Support\TurkishText;

/**
 * Havayoluna özel yardım / yemek bildirimi: tekerlekli sandalye (WCHR / WCHS), işitme / görme engeli,
 * diyet yemeği. Yalnız havayolu kodu ya da yemek etkisi olan ihtiyaçlar listelenir (diğer sağlık
 * bilgileri havayoluna gitmez).
 */
class FlightSpecialAssistance
{
    public function __construct(private readonly NeedProfiles $needs) {}

    public function build(Flight $flight): Report
    {
        $passengers = $flight->passengers()->with('registration.person')->get();
        $profiles = $this->needs->forPersons($passengers->map(fn (FlightPassenger $p) => $p->registration->person_id));

        $rows = $passengers
            ->map(function (FlightPassenger $p) use ($profiles, $flight): ?array {
                $items = array_values(array_filter(
                    $profiles[$p->registration->person_id] ?? [],
                    fn (array $i) => $i['airline_code'] !== null || $i['effect'] === NeedEffect::Diet->value,
                ));

                if ($items === []) {
                    return null;
                }

                $person = $p->registration->person;

                return [
                    'seat' => $p->seat_no,
                    'name' => TurkishText::upper($person->last_name).' / '.TurkishText::upper($person->first_name),
                    'pnr' => $p->pnr ?? $flight->pnr,
                    'codes' => implode(', ', array_unique(array_filter(array_column($items, 'airline_code')))) ?: null,
                    'needs' => implode(', ', array_column($items, 'name')),
                    'note' => implode('; ', array_filter(array_column($items, 'note'))) ?: null,
                ];
            })
            ->filter()
            ->sortBy('name')
            ->values()
            ->all();

        return new Report(
            key: 'flight_assistance',
            title: "{$flight->title()} Özel Yardım Listesi",
            columns: [
                Column::text('seat', 'Koltuk', 5),
                Column::text('name', 'Yolcu (SOYAD / AD)', 20),
                Column::text('pnr', 'PNR', 7),
                Column::text('codes', 'Kod', 8),
                Column::text('needs', 'İhtiyaç', 18),
                Column::text('note', 'Not', 22),
            ],
            rows: $rows,
            subtitle: [
                $flight->airline.' · '.$flight->departure_at->format('d.m.Y H:i'),
                count($rows).' yolcu',
                'Kodlar: WCHR yürüme güçlüğü, WCHS tekerlekli sandalye, DEAF işitme, BLND görme, SPML özel yemek',
            ],
            landscape: true,
        );
    }
}
