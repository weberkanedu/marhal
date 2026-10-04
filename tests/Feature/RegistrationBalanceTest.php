<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_is_net_price_minus_payments_plus_refunds(): void
    {
        $registration = Registration::factory()->create(['price' => 1500, 'discount' => 100, 'currency' => 'USD']);

        Payment::factory()->create(['registration_id' => $registration->id, 'amount' => 600]);
        Payment::factory()->create(['registration_id' => $registration->id, 'amount' => 400]);
        Payment::factory()->refund()->create(['registration_id' => $registration->id, 'amount' => 100]);

        $this->assertSame('1400.00', $registration->netPrice());
        $this->assertSame('900.00', $registration->paidTotal());
        $this->assertSame('500.00', $registration->balance());
    }

    public function test_payments_in_another_currency_are_converted(): void
    {
        $registration = Registration::factory()->create(['price' => 1000, 'currency' => 'USD']);

        // 6.000 TL, 1 TL = 0,025 USD → 150 USD
        $payment = Payment::factory()->create([
            'registration_id' => $registration->id,
            'amount' => 6000,
            'currency' => 'TRY',
            'exchange_rate' => 0.025,
        ]);

        $this->assertSame('150.00', $payment->amount_in_registration_currency);
        $this->assertSame('850.00', $registration->balance());
    }

    public function test_same_currency_payments_ignore_exchange_rate(): void
    {
        $registration = Registration::factory()->create(['currency' => 'USD']);

        $payment = Payment::factory()->create([
            'registration_id' => $registration->id,
            'amount' => 200,
            'currency' => 'USD',
            'exchange_rate' => 35,
        ]);

        $this->assertSame('200.00', $payment->amount_in_registration_currency);
    }

    public function test_deleted_payments_are_not_counted(): void
    {
        $registration = Registration::factory()->create(['price' => 1000]);
        $payment = Payment::factory()->create(['registration_id' => $registration->id, 'amount' => 300]);

        $payment->delete();

        $this->assertSame('1000.00', $registration->balance());
    }

    public function test_with_paid_total_scope_matches_per_record_calculation(): void
    {
        $registrations = Registration::factory()->count(3)->create(['price' => 1000]);
        Payment::factory()->create(['registration_id' => $registrations[0]->id, 'amount' => 250]);
        Payment::factory()->create(['registration_id' => $registrations[1]->id, 'amount' => 1000]);

        $loaded = Registration::withPaidTotal()->get()->keyBy('id');

        foreach ($registrations as $registration) {
            $this->assertSame($registration->balance(), $loaded[$registration->id]->balance());
        }
    }
}
