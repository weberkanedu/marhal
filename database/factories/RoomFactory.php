<?php

namespace Database\Factories;

use App\Enums\RoomKind;
use App\Models\Room;
use App\Models\TourHotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tour_hotel_id' => TourHotel::factory(),
            'tenant_id' => fn (array $attributes) => TourHotel::withoutGlobalScopes()->whereKey($attributes['tour_hotel_id'])->value('tenant_id'),
            'room_no' => (string) fake()->unique()->numberBetween(100, 9999),
            'capacity' => 4,
            'kind' => RoomKind::Male,
        ];
    }
}
