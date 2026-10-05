<?php

namespace App\Reports\Definitions;

use App\Models\Bus;
use App\Models\Group;
use App\Models\Tour;
use App\Reports\Column;
use App\Reports\Report;

/**
 * Turun şoför listesi: araç, plaka, şoför, telefon, gruplar, koltuk / dolu. Rehberlere ve
 * transfer firmasına verilir.
 */
class BusDriverList
{
    public function build(Tour $tour): Report
    {
        $rows = $tour->buses()
            ->with(['groups:id,name', 'vehicleType:id,name'])
            ->withCount('seats')
            ->orderBy('name')
            ->get()
            ->map(fn (Bus $bus) => [
                'bus' => $bus->name,
                'vehicle' => trim(($bus->vehicleType->name ?? $bus->body->label()).' · '.$bus->layout()->label()),
                'plate' => $bus->plate,
                'driver' => $bus->driver_name,
                'phone' => $bus->driver_phone,
                'groups' => $bus->groups->map(fn (Group $g) => $g->name)->join(', ') ?: null,
                'occupied' => $bus->seats_count.' / '.($bus->layout()->seatCount() - count($bus->reserved())),
            ])
            ->all();

        return new Report(
            key: 'bus_drivers',
            title: "{$tour->name} Şoför Listesi",
            columns: [
                Column::text('bus', 'Araç', 10),
                Column::text('vehicle', 'Tip', 16),
                Column::text('plate', 'Plaka', 8),
                Column::text('driver', 'Şoför', 14),
                Column::text('phone', 'Telefon', 11),
                Column::text('groups', 'Gruplar', 12),
                Column::text('occupied', 'Dolu / koltuk', 7),
            ],
            rows: $rows,
            subtitle: [$tour->start_date->format('d.m.Y').' – '.$tour->end_date->format('d.m.Y'), count($rows).' araç'],
            landscape: true,
        );
    }
}
