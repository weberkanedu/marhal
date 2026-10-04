<?php

namespace Database\Seeders\Demo;

use App\Actions\Buses\AutoAssignSeats;
use App\Actions\Buses\SaveBus;
use App\Models\Group;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\VehicleType;

/**
 * Faz 2 / otobüs planı örnek verisi: dört hazır araç tipi, aktif turda grup başına bir otobüs.
 * 1. otobüs (rehber koltukları ayrılmış) otomatik dağıtılmış gelir; 2. otobüs boş bırakılır ki
 * kullanıcı "Otomatik dağıt"ı ve elle oturtmayı kendisi denesin.
 */
class BusPlanDemo
{
    public function __construct(
        private readonly SaveBus $saveBus,
        private readonly AutoAssignSeats $autoAssign,
    ) {}

    public function run(Tenant $tenant): string
    {
        $types = collect([
            ['Standart otobüs 2+2', 2, 2, 11, 5, 6],
            ['VIP otobüs 2+1', 2, 1, 10, 0, 6],
            ['Midibüs 2+1', 2, 1, 8, 4, null],
            ['Sprinter 1+1', 1, 1, 7, 3, null],
        ])->map(fn (array $t) => VehicleType::query()->firstOrCreate(['name' => $t[0]], [
            'left_seats' => $t[1], 'right_seats' => $t[2], 'rows' => $t[3], 'back_row_seats' => $t[4], 'door_row' => $t[5],
        ]));

        $tour = Tour::query()->active()->orderBy('start_date')->first();

        if ($tour === null) {
            return 'Araç tipleri eklendi; aktif tur olmadığı için otobüs eklenmedi.';
        }

        $groups = $tour->groups()->orderBy('name')->get()
            // Kullanıcının kendi eklediği otobüsü olan gruplar atlanır.
            ->reject(fn (Group $g) => $tour->buses()->whereHas('groups', fn ($q) => $q->whereKey($g->id))->exists())
            ->values();

        foreach ($groups as $index => $group) {
            $name = ($index + 1).'. Otobüs';
            if ($tour->buses()->where('name', $name)->exists()) {
                $name .= ' (örnek)';
            }

            $bus = $this->saveBus->handle($tour, [
                'name' => $name,
                'vehicle_type_id' => $types[$index === 0 ? 0 : 1]->id,
                'plate' => $index === 0 ? '34 MRH 101' : '34 MRH 202',
                'driver_name' => $index === 0 ? 'Yusuf Şoför' : 'Hasan Şoför',
                'driver_phone' => '0555 000 00 0'.($index + 1),
                'reserved_seats' => $index === 0 ? [1, 2] : [1],
                'group_ids' => [$group->id],
            ]);

            if ($index === 0) {
                $this->autoAssign->apply($bus);
            }
        }

        return "Araç tipleri ve {$tour->name} için {$groups->count()} otobüs eklendi.";
    }
}
