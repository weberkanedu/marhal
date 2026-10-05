<?php

namespace App\Reports\Definitions;

use App\Models\Tenant;
use App\Models\Tour;
use App\Reports\Column;
use App\Reports\Report;
use App\Support\Tours\TourJourney;

/**
 * Tur programı: yolculuk çizelgesinin (TourJourney) yazdırılabilir hâli — tarih, adım, otel / uçuş.
 * Hazırlık adımı yolcuya dağıtılan programda yer almaz.
 */
class TourProgram
{
    public function __construct(private readonly TourJourney $journey) {}

    public function build(Tour $tour, ?Tenant $tenant): Report
    {
        $steps = collect($this->journey->for($tour, $tenant))->where('kind', '!=', 'prep')->values();

        return new Report(
            key: 'tour_program',
            title: "{$tour->name} Programı",
            columns: [
                Column::date('start', 'Başlangıç'),
                Column::date('end', 'Bitiş'),
                Column::text('title', 'Program', 12),
                Column::text('detail', 'Otel / uçuş', 40),
            ],
            rows: $steps->map(fn (array $s) => [
                'start' => $s['start'],
                'end' => $s['end'],
                'title' => $s['title'],
                'detail' => $s['detail'],
            ])->all(),
            subtitle: [$tour->start_date->format('d.m.Y').' – '.$tour->end_date->format('d.m.Y')],
        );
    }
}
