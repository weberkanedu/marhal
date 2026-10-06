<?php

namespace Tests\Feature\Payments;

use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReplaceInstallmentPlan;
use App\Enums\Feature;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Tahsilat ekranının özeti (bu ay, vadesi gelen, gecikmiş, kalan) ve "Ödeme al" penceresinin borçlu listesi.
 */
class CollectionScreenTest extends TestCase
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
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'currency' => 'USD', 'name' => 'Kasım Umresi']);
        $this->registration = Registration::factory()->create(['tour_id' => $tour->id, 'price' => 1500, 'currency' => 'USD']);
    }

    public function test_summary_matches_payments_and_installments(): void
    {
        // Geçen ay 400, bu ay 600 tahsilat; bu ay vadesi gelen taksitler 1100 + 400.
        app(RecordPayment::class)->handle($this->registration, $this->payment(400, now()->subMonthNoOverflow()->startOfMonth()->addDays(2)->toDateString()));
        app(RecordPayment::class)->handle($this->registration, $this->payment(600, now()->toDateString()));
        app(ReplaceInstallmentPlan::class)->handle($this->registration, [
            ['due_date' => now()->startOfMonth()->toDateString(), 'amount' => 1100],
            ['due_date' => now()->endOfMonth()->toDateString(), 'amount' => 400],
        ]);

        // Başka acentenin borcu özete girmez.
        Registration::factory()->create(['price' => 9999, 'currency' => 'USD']);

        $this->actingAs($this->staff)->get(route('collections.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.outstanding.USD', '500.00')
                ->where('summary.collected.USD.this', '600.00')
                ->where('summary.collected.USD.last', '400.00')
                ->where('summary.due_this_month.USD', '1500.00')
                ->where('canPay', true)
                // Ödenen 1000 < ilk taksit 1100: sonraki taksit ayın başındaki, o günden beri gecikmede.
                ->where('rows.items.data.0.next_due', now()->startOfMonth()->toDateString())
                ->where('rows.items.data.0.late_days', (int) now()->startOfMonth()->startOfDay()->diffInDays(today()))
                ->missing('debtors')
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('debtors', 1)
                    ->where('debtors.0.id', $this->registration->id)
                    ->where('debtors.0.balance', '500.00')
                    ->where('debtors.0.tour', 'Kasım Umresi')));
    }

    public function test_payment_from_collections_screen_returns_to_it(): void
    {
        $this->actingAs($this->staff)
            ->from(route('collections.index'))
            ->post(route('registrations.payments.store', $this->registration), [
                ...$this->payment(500, now()->toDateString()),
                'reference' => 'M-1',
            ])
            ->assertRedirect(route('collections.index'));

        $this->assertSame('500.00', $this->registration->fresh()?->paidTotal());
    }

    /**
     * @return array<string, mixed>
     */
    private function payment(int $amount, string $date): array
    {
        return ['type' => 'tahsilat', 'amount' => $amount, 'currency' => 'USD', 'method' => 'havale', 'paid_at' => $date];
    }
}
