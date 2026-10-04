<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'tenant_id' => fn (array $attributes) => Tour::withoutGlobalScopes()->whereKey($attributes['tour_id'])->value('tenant_id'),
            'name' => fake()->unique()->randomElement(['A', 'B', 'C', 'D', 'E', 'F']).' Grubu',
            'guide_name' => fake('tr_TR')->name('male'),
            'guide_phone' => fake('tr_TR')->numerify('05#########'),
        ];
    }
}
