<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\Tour;
use App\Models\TourHotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourHotel>
 */
class TourHotelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'tenant_id' => fn (array $attributes) => Tour::withoutGlobalScopes()->whereKey($attributes['tour_id'])->value('tenant_id'),
            'hotel_id' => fn (array $attributes) => Hotel::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'check_in' => fn (array $attributes) => Tour::withoutGlobalScopes()->whereKey($attributes['tour_id'])->value('start_date'),
            'check_out' => fn (array $attributes) => Tour::withoutGlobalScopes()->whereKey($attributes['tour_id'])->value('end_date'),
        ];
    }
}
