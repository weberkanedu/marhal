<?php

namespace Database\Factories;

use App\Enums\HotelCity;
use App\Models\Hotel;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hotel>
 */
class HotelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement(['Hilton', 'Swissôtel', 'Pullman', 'Mövenpick', 'Anwar Al Madinah']).' '.fake()->unique()->numberBetween(1, 999),
            'city' => HotelCity::Mecca,
            'stars' => 5,
        ];
    }
}
