<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Payment;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_id' => Registration::factory(),
            'tenant_id' => fn (array $attributes) => Registration::withoutGlobalScopes()
                ->whereKey($attributes['registration_id'])
                ->value('tenant_id'),
            'type' => PaymentType::Collection,
            'amount' => 500,
            'currency' => 'USD',
            'exchange_rate' => 1,
            'method' => fake()->randomElement(PaymentMethod::cases()),
            'paid_at' => now(),
        ];
    }

    public function refund(): static
    {
        return $this->state(fn () => ['type' => PaymentType::Refund]);
    }
}
