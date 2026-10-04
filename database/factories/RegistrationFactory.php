<?php

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Enums\RoomType;
use App\Models\Person;
use App\Models\Registration;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'person_id' => fn (array $attributes) => Person::factory()->state([
                'tenant_id' => self::tenantOf($attributes['tour_id']),
            ]),
            'tenant_id' => fn (array $attributes) => self::tenantOf($attributes['tour_id']),
            'room_type' => fake()->randomElement(RoomType::cases()),
            'price' => 1500,
            'discount' => 0,
            'currency' => 'USD',
            'status' => RegistrationStatus::Confirmed,
            'registered_at' => now(),
        ];
    }

    private static function tenantOf(string $tourId): string
    {
        return Tour::withoutGlobalScopes()->whereKey($tourId)->value('tenant_id');
    }
}
