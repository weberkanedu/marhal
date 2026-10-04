<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\Person;
use App\Models\Tenant;
use App\Rules\TcKimlikNo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(Gender::cases());

        return [
            'tenant_id' => Tenant::factory(),
            'first_name' => fake('tr_TR')->firstName($gender === Gender::Male ? 'male' : 'female'),
            'last_name' => fake('tr_TR')->lastName(),
            'gender' => $gender,
            'birth_date' => fake()->dateTimeBetween('-80 years', '-18 years'),
            'nationality' => 'TR',
            'national_id' => TcKimlikNo::generate(),
            'passport_no' => fake()->bothify('U########'),
            'passport_issue_date' => now()->subYears(2),
            'passport_expiry_date' => now()->addYears(8),
            'phone' => fake('tr_TR')->numerify('05#########'),
            'kvkk_consent_at' => now(),
        ];
    }
}
