<?php

namespace App\Actions\Buses;

use App\Models\Bus;
use App\Models\Tour;
use App\Models\VehicleType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Otobüs ekler / düzenler. Koltuk düzeni seçilen araç tipinden kopyalanır. Araç tipi değişirse,
 * oturan yolcuların koltuk numaraları yeni düzende yoksa engellenir. Rehbere ayrılan koltuklar
 * dolu olamaz.
 */
class SaveBus
{
    /**
     * @param  array{name: string, vehicle_type_id: string, plate?: string|null, driver_name?: string|null, driver_phone?: string|null, notes?: string|null, reserved_seats?: list<int>, group_ids?: list<string>}  $data
     */
    public function handle(Tour $tour, array $data, ?Bus $bus = null): Bus
    {
        $type = VehicleType::query()->whereKey($data['vehicle_type_id'])->firstOrFail();
        $layoutChanges = $bus === null || $bus->vehicle_type_id !== $type->id;
        $layout = $layoutChanges ? $type->layout() : $bus->layout();
        $reserved = array_values(array_unique(array_map('intval', $data['reserved_seats'] ?? [])));
        sort($reserved);

        if ($outside = array_values(array_filter($reserved, fn (int $s) => ! $layout->has($s)))) {
            $this->fail('reserved_seats', 'Bu araçta olmayan koltuk: '.implode(', ', $outside));
        }

        if ($bus !== null) {
            $occupied = $bus->seats()->pluck('seat_no')->map(fn ($s) => (int) $s);

            if ($missing = $occupied->reject(fn (int $s) => $layout->has($s))->values()->all()) {
                $this->fail('vehicle_type_id', 'Yeni araçta bu koltuklar yok, önce yolcularını taşıyın: '.implode(', ', $missing));
            }

            if ($busy = $occupied->intersect($reserved)->values()->all()) {
                $this->fail('reserved_seats', 'Ayrılmak istenen koltuklarda yolcu oturuyor: '.implode(', ', $busy));
            }
        }

        return DB::transaction(function () use ($tour, $data, $bus, $type, $layoutChanges, $reserved): Bus {
            $attributes = [
                'name' => $data['name'],
                'vehicle_type_id' => $type->id,
                'plate' => $data['plate'] ?? null,
                'driver_name' => $data['driver_name'] ?? null,
                'driver_phone' => $data['driver_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'reserved_seats' => $reserved,
                ...($layoutChanges ? [...$type->layout()->toAttributes(), 'body' => $type->body] : []),
            ];

            $bus = $bus === null ? $tour->buses()->create($attributes) : tap($bus)->update($attributes);
            $bus->groups()->sync($data['group_ids'] ?? []);

            return $bus;
        });
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
