<?php

namespace App\Reports\Definitions;

use App\Enums\ReadinessStatus;
use App\Models\Tour;
use App\Reports\Column;
use App\Reports\Report;
use App\Support\Readiness\ReadinessBoard;

/**
 * Turun hazırlık listesi: yolcu × takip edilen maddeler (✓ tamam, ! sorun, boş bekliyor) ve hazır sayısı.
 * Ekrandaki "Hazırlık" sekmesiyle aynı kaynaktan (ReadinessBoard); rehber yalnız kendi gruplarını alır.
 */
class TourReadinessList
{
    public function __construct(private readonly ReadinessBoard $board) {}

    /**
     * @param  list<string>|null  $groupIds
     */
    public function build(Tour $tour, ?array $groupIds = null): Report
    {
        $board = $this->board->build($tour, $groupIds);
        $mark = fn (?string $status) => match ($status) {
            ReadinessStatus::Done->value => '✓',
            ReadinessStatus::Problem->value => '!',
            default => '',
        };

        $rows = array_map(fn (array $row) => [
            'name' => $row['name'],
            'group' => $row['group_name'],
            ...array_combine(
                array_map(fn (array $item) => "item_{$item['id']}", $board['items']),
                array_map(fn (array $item) => $mark($row['cells'][$item['id']]['status']), $board['items']),
            ),
            'ready' => "{$row['done']} / ".count($board['items']),
        ], $board['rows']);

        return new Report(
            key: 'tour_readiness',
            title: "{$tour->name} Hazırlık Listesi",
            columns: [
                Column::text('name', 'Yolcu', 24),
                Column::text('group', 'Grup', 10),
                ...array_map(fn (array $item) => Column::text("item_{$item['id']}", $item['name'], 9), $board['items']),
                Column::text('ready', 'Hazır', 8),
            ],
            rows: $rows,
            subtitle: [
                $tour->start_date->format('d.m.Y').' – '.$tour->end_date->format('d.m.Y'),
                "{$board['ready']} / {$board['total']} yolcu hazır · ✓ tamam, ! sorun, boş: bekliyor",
            ],
        );
    }
}
