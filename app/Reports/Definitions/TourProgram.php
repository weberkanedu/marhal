<?php

namespace App\Reports\Definitions;

use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourProgramItem;
use App\Reports\Column;
use App\Reports\Report;
use App\Support\Tours\TourJourney;

/**
 * Tur programı: yolculuk çizelgesi (TourJourney) ve gün gün program (TourProgramItem) — tarih, adım / saat, otel / uçuş / etkinlik.
 * Hazırlık adımı yolcuya dağıtılan programda yer almaz.
 */
class TourProgram
{
    public function __construct(private readonly TourJourney $journey) {}

    public function build(Tour $tour, ?Tenant $tenant): Report
    {
        $steps = collect($this->journey->for($tour, $tenant))->where('kind', '!=', 'prep')->values();

        // Çizelge adımları + gün gün program (aynı gün önce çizelge adımı, sonra saat sırasıyla etkinlikler).
        $rows = $steps->map(fn (array $s) => [
            'start' => $s['start'],
            'end' => $s['end'],
            'title' => $s['title'],
            'detail' => $s['detail'],
            'sort' => $s['start'].' 0',
        ])->merge($tour->programItems()->get()->map(fn (TourProgramItem $i) => [
            'start' => $i->day->toDateString(),
            'end' => $i->day->toDateString(),
            'title' => $i->time ? substr($i->time, 0, 5) : 'Gün içinde',
            'detail' => $i->title.($i->place ? " · {$i->place}" : ''),
            'sort' => $i->day->toDateString().' 1 '.($i->time ?? '99'),
        ]))->sortBy('sort')->values();

        return new Report(
            key: 'tour_program',
            title: "{$tour->name} Programı",
            columns: [
                Column::date('start', 'Başlangıç'),
                Column::date('end', 'Bitiş'),
                Column::text('title', 'Program', 12),
                Column::text('detail', 'Otel / uçuş / etkinlik', 40),
            ],
            rows: $rows->map(fn (array $r) => array_diff_key($r, ['sort' => true]))->all(),
            subtitle: [$tour->start_date->format('d.m.Y').' – '.$tour->end_date->format('d.m.Y')],
        );
    }
}
