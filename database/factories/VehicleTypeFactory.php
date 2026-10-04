<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleType>
 */
class VehicleTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => 'Standart otobüs '.fake()->unique()->numberBetween(1, 999),
            'left_seats' => 2,
            'right_seats' => 2,
            'rows' => 11,
            'back_row_seats' => 5,
            'door_row' => 6,
        ];
    }
}
