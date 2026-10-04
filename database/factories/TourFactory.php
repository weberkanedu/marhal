<?php

namespace Database\Factories;

use App\Enums\TourStatus;
use App\Enums\TourType;
use App\Models\Tenant;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tour>
 */
class TourFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 week', '+6 months');

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $start->format('F Y').' Umre Turu',
            'type' => TourType::Umrah,
            'start_date' => $start,
            'end_date' => (clone $start)->modify('+14 days'),
            'status' => TourStatus::OnSale,
            'capacity' => 45,
            'default_price' => 1500,
            'currency' => 'USD',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => TourStatus::Completed,
            'start_date' => now()->subMonths(2),
            'end_date' => now()->subMonths(2)->addDays(14),
        ]);
    }
}
