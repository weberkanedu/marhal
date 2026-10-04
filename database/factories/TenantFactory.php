<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company().' Turizm';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'plan_id' => Plan::factory(),
            'status' => TenantStatus::Active,
            'default_currency' => 'USD',
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => TenantStatus::Suspended]);
    }

    public function trialExpired(): static
    {
        return $this->state(fn () => [
            'status' => TenantStatus::Trial,
            'trial_ends_at' => now()->subDay(),
        ]);
    }
}
