<?php

namespace Database\Factories;

use App\Enums\Feature;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Paket '.Str::upper(Str::random(4));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'price_monthly' => 0,
            'price_yearly' => 0,
            'currency' => 'TRY',
            'user_limit' => null,
            'active_tour_limit' => null,
        ];
    }

    /**
     * @param  list<Feature>  $features
     */
    public function withFeatures(array $features): static
    {
        return $this->afterCreating(function (Plan $plan) use ($features): void {
            foreach (Feature::cases() as $feature) {
                $plan->features()->create([
                    'feature_key' => $feature->value,
                    'enabled' => in_array($feature, $features, true),
                ]);
            }
        });
    }
}
