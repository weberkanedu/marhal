<?php

namespace Database\Factories;

use App\Enums\FlightDirection;
use App\Models\Flight;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Flight>
 */
class FlightFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'tenant_id' => fn (array $attributes) => Tour::withoutGlobalScopes()->whereKey($attributes['tour_id'])->value('tenant_id'),
            'direction' => FlightDirection::Outbound,
            'airline' => 'Türk Hava Yolları',
            'flight_no' => 'TK '.fake()->numberBetween(90, 999),
            'departure_airport' => 'IST',
            'arrival_airport' => 'JED',
            'departure_at' => '2026-11-01 10:00',
            'arrival_at' => '2026-11-01 14:00',
        ];
    }
}
