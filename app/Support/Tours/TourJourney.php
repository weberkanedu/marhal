<?php

namespace App\Support\Tours;

use App\Enums\Feature;
use App\Enums\FlightDirection;
use App\Models\Flight;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourHotel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Turun yolculuk çizelgesi: Hazırlık → gidiş → şehir konaklamaları (Mekke, Medine…) → dönüş.
 * Tek kaynak: tur sayfasındaki çizelge, "Tur programı" çıktısı ve (ileride) aile ekranı bunu kullanır.
 * Uçuş / konaklama modülü kapalıysa ya da girilmemişse adım turun tarihlerinden üretilir.
 */
class TourJourney
{
    /**
     * @return list<array{
     *     key: string,
     *     kind: 'prep'|'outbound'|'stay'|'return',
     *     title: string,
     *     detail: string|null,
     *     start: string,
     *     end: string,
     *     state: 'done'|'now'|'next',
     * }>
     */
    public function for(Tour $tour, ?Tenant $tenant, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();

        /** @var Collection<int, Flight> $flights */
        $flights = $tenant?->hasFeature(Feature::FlightLists)
            ? $tour->flights()->orderBy('departure_at')->get()
            : collect();
        /** @var Collection<int, TourHotel> $stays */
        $stays = $tenant?->hasFeature(Feature::RoomPlanning)
            ? $tour->stays()->with('hotel:id,name,city')->orderBy('check_in')->get()
            : collect();

        $outbound = $flights->where('direction', FlightDirection::Outbound)->values();
        $return = $flights->where('direction', FlightDirection::Return)->values();

        $departure = $outbound->first()?->departure_at->toImmutable()->startOfDay() ?? $tour->start_date->toImmutable();
        $homecoming = $return->last()?->arrival_at->toImmutable()->startOfDay() ?? $tour->end_date->toImmutable();

        $steps = [[
            'key' => 'prep',
            'kind' => 'prep',
            'title' => 'Hazırlık',
            'detail' => null,
            'start' => $departure->subDays(60),
            'end' => $departure->subDay(),
        ]];

        $steps[] = [
            'key' => 'outbound',
            'kind' => 'outbound',
            'title' => 'Gidiş',
            'detail' => $outbound->isNotEmpty() ? $this->flightText($outbound) : null,
            'start' => $departure,
            'end' => $outbound->last()?->arrival_at->toImmutable()->startOfDay() ?? $departure,
        ];

        foreach ($this->cityBlocks($stays) as $i => $block) {
            $steps[] = ['key' => "stay-{$i}", 'kind' => 'stay', ...$block];
        }

        $steps[] = [
            'key' => 'return',
            'kind' => 'return',
            'title' => 'Dönüş',
            'detail' => $return->isNotEmpty() ? $this->flightText($return) : null,
            'start' => $return->first()?->departure_at->toImmutable()->startOfDay() ?? $homecoming,
            'end' => $homecoming,
        ];

        $now = $this->currentIndex($steps, $today);

        return array_map(fn (array $step, int $i) => [
            ...$step,
            'start' => $step['start']->toDateString(),
            'end' => $step['end']->toDateString(),
            'state' => match (true) {
                $i < $now => 'done',
                $i === $now => 'now',
                default => 'next',
            },
        ], $steps, array_keys($steps));
    }

    /**
     * Aynı şehirde art arda konaklamalar (ör. A ve B grubu farklı Mekke otellerinde) tek adım olur.
     *
     * @param  Collection<int, TourHotel>  $stays
     * @return list<array{title: string, detail: string|null, start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function cityBlocks(Collection $stays): array
    {
        $blocks = [];

        foreach ($stays as $stay) {
            $city = $stay->hotel->city;
            $last = array_key_last($blocks);

            if ($last !== null && $blocks[$last]['city'] === $city && $stay->check_in->toImmutable() <= $blocks[$last]['end']) {
                $blocks[$last]['hotels'][] = $stay->hotel->name;
                $blocks[$last]['end'] = max($blocks[$last]['end'], $stay->check_out->toImmutable());

                continue;
            }

            $blocks[] = [
                'city' => $city,
                'hotels' => [$stay->hotel->name],
                'start' => $stay->check_in->toImmutable(),
                'end' => $stay->check_out->toImmutable(),
            ];
        }

        return array_map(fn (array $b) => [
            'title' => $b['city']->label(),
            'detail' => implode(' · ', array_unique($b['hotels'])),
            'start' => $b['start'],
            'end' => $b['end'],
        ], $blocks);
    }

    /**
     * @param  Collection<int, Flight>  $flights
     */
    private function flightText(Collection $flights): string
    {
        return $flights->map(fn (Flight $f) => $f->title())->unique()->join(' · ');
    }

    /**
     * Bugün hangi adımda? Tur bitmişse son adım geçilmiş sayılır (hepsi "done").
     *
     * @param  list<array{start: CarbonImmutable, end: CarbonImmutable}>  $steps
     */
    private function currentIndex(array $steps, CarbonImmutable $today): int
    {
        $last = end($steps);

        if ($last === false || $today->greaterThan($last['end'])) {
            return count($steps);
        }

        foreach ($steps as $i => $step) {
            if ($today->lessThanOrEqualTo($step['end'])) {
                return $i;
            }
        }

        return count($steps) - 1;
    }
}
