<?php

namespace Database\Factories;

use App\Models\Bus;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bus>
 */
class BusFactory extends Factory
{
    /**
     * Varsayılan: küçük 2+2 otobüs, 3 sıra (12 koltuk) — testlerde kolay hesap için.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'tenant_id' => fn (array $attributes) => Tour::withoutGlobalScopes()->whereKey($attributes['tour_id'])->value('tenant_id'),
            'name' => fake()->unique()->numberBetween(1, 99).'. Otobüs',
            'left_seats' => 2,
            'right_seats' => 2,
            'rows' => 3,
            'back_row_seats' => 0,
            'door_row' => null,
        ];
    }
}
