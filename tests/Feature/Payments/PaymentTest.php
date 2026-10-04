<?php

namespace Tests\Feature\Payments;

use App\Actions\Payments\RecordPayment;
use App\Enums\Feature;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Registration $registration;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::Payments])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $this->staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'currency' => 'USD']);
        $this->registration = Registration::factory()->create(['tour_id' => $tour->id, 'price' => 1500, 'currency' => 'USD']);
    }

    public function test_staff_can_record_a_payment(): void
    {
        $this->actingAs($this->staff)->post(route('registrations.payments.store', $this->registration), $this->payment([
            'amount' => 500,
            'reference' => 'D-1001',
        ]))->assertSessionHasNoErrors();

        $payment = Payment::sole();
        $this->assertSame($this->staff->id, $payment->received_by);
        $this->assertSame($this->tenant->id, $payment->tenant_id);
        $this->assertSame('1000.00', $this->registration->balance());
    }

    public function test_foreign_currency_payment_requires_an_exchange_rate(): void
    {
        $url = route('registrations.payments.store', $this->registration);

        $this->actingAs($this->staff)->post($url, $this->payment(['amount' => 10000, 'currency' => 'TRY']))
            ->assertSessionHasErrors('exchange_rate');

        $this->actingAs($this->staff)->post($url, $this->payment(['amount' => 10000, 'currency' => 'TRY', 'exchange_rate' => 0.025]))
            ->assertSessionHasNoErrors();

        $this->assertSame('250.00', Payment::sole()->amount_in_registration_currency);
        $this->assertSame('1250.00', $this->registration->balance());
    }

    public function test_refund_cannot_exceed_paid_amount(): void
    {
        $url = route('registrations.payments.store', $this->registration);
        $this->actingAs($this->staff)->post($url, $this->payment(['amount' => 300]));

        $this->actingAs($this->staff)->post($url, $this->payment(['type' => 'iade', 'amount' => 400]))
            ->assertSessionHasErrors('amount');
        $this->actingAs($this->staff)->post($url, $this->payment(['type' => 'iade', 'amount' => 100]))
            ->assertSessionHasNoErrors();

        $this->assertSame('200.00', $this->registration->paidTotal());
    }

    public function test_cancelled_registration_accepts_refund_but_not_collection(): void
    {
        $url = route('registrations.payments.store', $this->registration);
        $this->actingAs($this->staff)->post($url, $this->payment(['amount' => 300]));
        $this->registration->update(['status' => RegistrationStatus::Cancelled]);

        $this->actingAs($this->staff)->post($url, $this->payment(['amount' => 100]))->assertSessionHasErrors('amount');
        $this->actingAs($this->staff)->post($url, $this->payment(['type' => 'iade', 'amount' => 300]))->assertSessionHasNoErrors();
    }

    public function test_future_dates_and_zero_amounts_are_rejected(): void
    {
        $url = route('registrations.payments.store', $this->registration);

        $this->actingAs($this->staff)->post($url, $this->payment(['paid_at' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('paid_at');
        $this->actingAs($this->staff)->post($url, $this->payment(['amount' => 0]))
            ->assertSessionHasErrors('amount');
    }

    public function test_only_admins_can_delete_payments_and_deleted_payments_do_not_count(): void
    {
        $payment = Payment::factory()->create(['registration_id' => $this->registration->id, 'amount' => 500]);
        $admin = User::factory()->forTenant($this->tenant)->create();

        $this->actingAs($this->staff)->delete(route('payments.destroy', $payment))->assertForbidden();
        $this->actingAs($admin)->delete(route('payments.destroy', $payment))->assertRedirect();

        $this->assertSoftDeleted($payment);
        $this->assertSame('1500.00', $this->registration->balance());
    }

    public function test_guides_cannot_record_payments(): void
    {
        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();

        $this->actingAs($guide)->post(route('registrations.payments.store', $this->registration), $this->payment())
            ->assertForbidden();
    }

    public function test_installment_plan_drives_overdue_amount(): void
    {
        $this->actingAs($this->staff)->put(route('registrations.installments.update', $this->registration), [
            'installments' => [
                ['due_date' => now()->subMonth()->toDateString(), 'amount' => 500],
                ['due_date' => now()->subDay()->toDateString(), 'amount' => 500],
                ['due_date' => now()->addMonth()->toDateString(), 'amount' => 500],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, Installment::count());
        $this->assertSame('1000.00', $this->registration->dueTotal());
        $this->assertSame('1000.00', $this->registration->overdue());

        Payment::factory()->create(['registration_id' => $this->registration->id, 'amount' => 600]);
        $this->assertSame('400.00', $this->registration->overdue());

        Payment::factory()->create(['registration_id' => $this->registration->id, 'amount' => 600]);
        $this->assertSame('0.00', $this->registration->overdue(), 'Fazla ödeme gecikmeyi sıfırlar, eksiye düşmez.');
    }

    public function test_installment_total_cannot_exceed_net_price(): void
    {
        $this->actingAs($this->staff)->put(route('registrations.installments.update', $this->registration), [
            'installments' => [
                ['due_date' => now()->toDateString(), 'amount' => 1000],
                ['due_date' => now()->addMonth()->toDateString(), 'amount' => 600],
            ],
        ])->assertSessionHasErrors('installments');

        $this->assertSame(0, Installment::count());
    }

    public function test_empty_plan_removes_installments(): void
    {
        Installment::query()->forceCreate([
            'tenant_id' => $this->tenant->id,
            'registration_id' => $this->registration->id,
            'due_date' => now(),
            'amount' => 100,
        ]);

        $this->actingAs($this->staff)->put(route('registrations.installments.update', $this->registration), ['installments' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Installment::count());
    }

    public function test_registration_page_shows_summary(): void
    {
        Payment::factory()->create(['registration_id' => $this->registration->id, 'amount' => 500]);

        $this->actingAs($this->staff)->get(route('registrations.show', $this->registration))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('registrations/Show')
                ->where('registration.paid', '500.00')
                ->where('registration.balance', '1000.00')
                ->has('payments', 1)
                ->where('can.pay', true)
                ->where('can.deletePayment', false));
    }

    public function test_collections_page_lists_debtors_and_completed(): void
    {
        $paidOff = Registration::factory()->create(['tour_id' => $this->registration->tour_id, 'price' => 1000]);
        Payment::factory()->create(['registration_id' => $paidOff->id, 'amount' => 1000]);
        Registration::factory()->create(['tour_id' => $this->registration->tour_id, 'status' => RegistrationStatus::Cancelled]);

        $this->actingAs($this->staff)->get(route('collections.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'borclu')
                ->has('rows.items', 1)
                ->where('rows.items.0.id', $this->registration->id)
                ->where('rows.totals.USD.balance', '1500.00'));

        $this->actingAs($this->staff)->get(route('collections.index', ['tab' => 'tamamlanan']))
            ->assertInertia(fn (Assert $page) => $page->has('rows.items', 1)->where('rows.items.0.id', $paidOff->id));

        $this->actingAs($this->staff)->get(route('collections.index', ['tab' => 'tahsilatlar']))
            ->assertInertia(fn (Assert $page) => $page->has('rows.items', 1)->where('rows.totals.USD.net', '1000.00'));
    }

    public function test_payments_module_is_blocked_when_not_in_plan(): void
    {
        $plan = Plan::factory()->withFeatures([Feature::Passengers])->create();
        $user = User::factory()->forTenant(Tenant::factory()->create(['plan_id' => $plan->id]))->create();

        $this->actingAs($user)->get(route('collections.index'))->assertForbidden();
    }

    public function test_action_can_be_used_without_http_context(): void
    {
        // Faz 4 API'si / komutlar için: acente bağlamı olmadan da doğru acenteye yazılır.
        $payment = app(RecordPayment::class)->handle($this->registration, $this->payment(['amount' => 250]));

        $this->assertSame($this->tenant->id, $payment->tenant_id);

        $this->expectException(ValidationException::class);
        app(RecordPayment::class)->handle($this->registration, $this->payment(['type' => 'iade', 'amount' => 9999]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payment(array $overrides = []): array
    {
        return [
            'type' => 'tahsilat',
            'amount' => 500,
            'currency' => 'USD',
            'method' => 'havale',
            'paid_at' => now()->toDateString(),
            ...$overrides,
        ];
    }
}
