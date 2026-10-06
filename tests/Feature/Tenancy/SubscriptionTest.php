<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Family\CreateFamilyLink;
use App\Actions\Signup\SignupLinks;
use App\Enums\BillingCycle;
use App\Enums\Feature;
use App\Enums\SubscriptionState;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Abonelik (10b): durum tarihlerden hesaplanır; salt okunurda değişiklik yapılamaz; paket seçimi talep
 * olarak platforma düşer; "Ödeme geldi" dönem açar; "+7 gün" süre uzatır.
 */
class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private Plan $kafile;

    private Plan $mikat;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-07 10:00');
        $this->seed(PlanSeeder::class);
        $this->kafile = Plan::where('slug', 'kafile')->sole();
        $this->mikat = Plan::where('slug', 'mikat')->sole();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function tenant(array $attributes = []): Tenant
    {
        return Tenant::factory()->create(['plan_id' => $this->kafile->id, ...$attributes]);
    }

    private function admin(Tenant $tenant): User
    {
        return User::factory()->forTenant($tenant)->role(UserRole::Admin)->create();
    }

    public function test_state_is_derived_from_dates(): void
    {
        $cases = [
            [['status' => TenantStatus::Trial, 'trial_ends_at' => '2026-10-15'], SubscriptionState::Trial],
            [['status' => TenantStatus::Trial, 'trial_ends_at' => '2026-10-01'], SubscriptionState::ReadOnly],
            [['status' => TenantStatus::Active, 'subscription_ends_at' => null], SubscriptionState::Active],
            [['status' => TenantStatus::Active, 'subscription_ends_at' => '2026-12-01'], SubscriptionState::Active],
            [['status' => TenantStatus::Active, 'subscription_ends_at' => '2026-10-03'], SubscriptionState::PastDue],
            [['status' => TenantStatus::Active, 'subscription_ends_at' => '2026-09-20'], SubscriptionState::ReadOnly],
            [['status' => TenantStatus::Suspended], SubscriptionState::Suspended],
        ];

        foreach ($cases as [$attributes, $expected]) {
            $this->assertSame($expected, $this->tenant($attributes)->subscriptionState(), json_encode($attributes) ?: '');
        }
    }

    public function test_read_only_agency_can_see_but_not_change(): void
    {
        $tenant = $this->tenant(['status' => TenantStatus::Trial, 'trial_ends_at' => '2026-10-01']);
        $admin = $this->admin($tenant);

        $this->actingAs($admin)->get(route('tours.index'))->assertOk();
        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('subscription.state', 'salt_okunur'));

        $this->actingAs($admin)->from(route('tours.index'))->post(route('tours.store'), [
            'name' => 'Engellenen tur', 'type' => 'umre', 'status' => 'taslak',
            'start_date' => '2026-11-01', 'end_date' => '2026-11-10', 'currency' => 'TRY',
        ])->assertRedirect(route('tours.index'));
        $this->assertSame(0, Tour::withoutGlobalScopes()->count());

        // Paket talebi ve geri bildirim salt okunurken de gönderilebilir.
        $this->actingAs($admin)->post(route('agency.plan-request.store'), ['plan_id' => $this->kafile->id, 'billing_cycle' => 'yillik'])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->kafile->id, $tenant->fresh()?->requested_plan_id);
    }

    public function test_public_signup_closes_but_family_page_stays_open_when_read_only(): void
    {
        $tenant = $this->tenant(['status' => TenantStatus::Active, 'subscription_ends_at' => '2026-12-01']);
        $admin = $this->admin($tenant);
        $tour = Tour::factory()->create(['tenant_id' => $tenant->id, 'start_date' => '2026-11-01', 'end_date' => '2026-11-14']);
        $registration = Registration::factory()->create(['tour_id' => $tour->id]);

        [$signup, $family] = app(CurrentTenant::class)->run($tenant, fn () => [
            (string) app(SignupLinks::class)->create($tour, $admin)->token,
            (string) app(CreateFamilyLink::class)->handle($registration, true, $admin)->token,
        ]);
        app(CurrentTenant::class)->set(null);

        $this->get(route('signup.show', $signup))->assertOk();

        $tenant->update(['subscription_ends_at' => '2026-09-01']);
        $this->assertTrue($tenant->fresh()?->isReadOnly());

        $this->get(route('signup.show', $signup))->assertNotFound();
        $this->get(route('family.show', $family))->assertOk();
    }

    public function test_suspended_agency_cannot_enter(): void
    {
        $tenant = $this->tenant(['status' => TenantStatus::Suspended]);

        $this->actingAs($this->admin($tenant))->get(route('dashboard'))->assertForbidden();
    }

    public function test_plan_page_is_for_admins_and_shows_only_own_payments(): void
    {
        $tenant = $this->tenant(['status' => TenantStatus::Active, 'subscription_ends_at' => '2027-01-01', 'billing_cycle' => 'yillik']);
        $other = $this->tenant();
        $platform = User::factory()->superAdmin()->create();
        $pay = ['plan_id' => $this->kafile->id, 'billing_cycle' => 'aylik', 'amount' => 3490, 'method' => 'havale', 'paid_at' => '2026-10-07'];
        $this->actingAs($platform)->post(route('platform.tenants.payments.store', $other), $pay)->assertSessionHasNoErrors();

        $staff = User::factory()->forTenant($tenant)->role(UserRole::Operations)->create();
        $this->actingAs($staff)->get(route('agency.plan'))->assertForbidden();

        $this->actingAs($this->admin($tenant))->get(route('agency.plan'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('agency/Plan')
                ->where('current.plan.name', 'Kafile')
                ->where('current.state.value', 'aktif')
                ->where('current.billing_cycle', 'yillik')
                ->has('current.payments', 0)
                ->has('plans', 3)
                ->where('plans.1.is_featured', true)
                ->where('bank.reference', $tenant->slug));

        $this->assertSame(1, SubscriptionPayment::withoutGlobalScopes()->where('tenant_id', $other->id)->count());
    }

    public function test_plan_request_can_be_sent_and_cancelled_but_not_for_hidden_plans(): void
    {
        $tenant = $this->tenant(['status' => TenantStatus::Trial, 'trial_ends_at' => '2026-10-20']);
        $admin = $this->admin($tenant);

        $this->actingAs($admin)->post(route('agency.plan-request.store'), ['plan_id' => $this->mikat->id, 'billing_cycle' => 'aylik'])
            ->assertSessionHasNoErrors();
        $tenant->refresh();
        $this->assertSame($this->mikat->id, $tenant->requested_plan_id);
        $this->assertSame(BillingCycle::Monthly, $tenant->requested_billing_cycle);
        $this->assertSame($this->kafile->id, $tenant->plan_id, 'Paket ödeme gelene kadar değişmez.');

        $this->actingAs($admin)->delete(route('agency.plan-request.destroy'))->assertSessionHasNoErrors();
        $this->assertNull($tenant->fresh()?->requested_plan_id);

        $this->mikat->update(['is_public' => false]);
        $this->actingAs($admin)->post(route('agency.plan-request.store'), ['plan_id' => $this->mikat->id, 'billing_cycle' => 'aylik'])
            ->assertSessionHasErrors('plan_id');
    }

    public function test_payment_opens_a_period_switches_plan_and_closes_the_request(): void
    {
        $tenant = $this->tenant(['status' => TenantStatus::Trial, 'trial_ends_at' => '2026-10-20',
            'requested_plan_id' => $this->mikat->id, 'requested_billing_cycle' => 'yillik', 'requested_at' => now()]);
        $platform = User::factory()->superAdmin()->create();

        $this->actingAs($platform)->post(route('platform.tenants.payments.store', $tenant), [
            'plan_id' => $this->mikat->id, 'billing_cycle' => 'yillik', 'amount' => 14900, 'method' => 'havale', 'paid_at' => '2026-10-07',
        ])->assertSessionHasNoErrors();

        $tenant->refresh();
        $this->assertSame(SubscriptionState::Active, $tenant->subscriptionState());
        $this->assertSame($this->mikat->id, $tenant->plan_id);
        $this->assertSame('2027-10-07', $tenant->subscription_ends_at?->toDateString(), 'Denemeden geçişte dönem bugünden başlar.');
        $this->assertSame('2026-10-07', $tenant->subscription_started_at?->toDateString());
        $this->assertNull($tenant->requested_plan_id);
        $payment = SubscriptionPayment::withoutGlobalScopes()->sole();
        $this->assertSame($platform->id, $payment->recorded_by);
        $this->assertSame('14900.00', $payment->amount);

        // Gecikmedeki abonede yeni dönem eski dönemin bittiği yerden devam eder; kota yılı kaymaz.
        Carbon::setTestNow('2027-10-10 09:00');
        $this->actingAs($platform)->post(route('platform.tenants.payments.store', $tenant), [
            'plan_id' => $this->mikat->id, 'billing_cycle' => 'aylik', 'amount' => 1490, 'method' => 'kart', 'paid_at' => '2027-10-10',
        ])->assertSessionHasNoErrors();
        $tenant->refresh();
        $this->assertSame('2027-11-07', $tenant->subscription_ends_at?->toDateString());
        $this->assertSame('2026-10-07', $tenant->subscription_started_at?->toDateString());
        $this->assertSame(BillingCycle::Monthly, $tenant->billing_cycle);

        // Salt okunurdan dönüşte dönem bugünden başlar.
        Carbon::setTestNow('2028-03-01 09:00');
        $this->assertTrue($tenant->fresh()?->isReadOnly());
        $this->actingAs($platform)->post(route('platform.tenants.payments.store', $tenant), [
            'plan_id' => $this->kafile->id, 'billing_cycle' => 'aylik', 'amount' => 3490, 'method' => 'havale', 'paid_at' => '2028-03-01',
        ])->assertSessionHasNoErrors();
        $tenant->refresh();
        $this->assertSame('2028-04-01', $tenant->subscription_ends_at?->toDateString());
        $this->assertSame('2028-03-01', $tenant->subscription_started_at?->toDateString());
        $this->assertTrue($tenant->hasFeature(Feature::FamilyScreen));
    }

    public function test_extend_adds_seven_days(): void
    {
        $platform = User::factory()->superAdmin()->create();
        $trial = $this->tenant(['status' => TenantStatus::Trial, 'trial_ends_at' => '2026-10-10']);
        $lapsed = $this->tenant(['status' => TenantStatus::Active, 'subscription_ends_at' => '2026-08-01']);

        $this->actingAs($platform)->post(route('platform.tenants.extend', $trial))->assertSessionHasNoErrors();
        $this->assertSame('2026-10-17', $trial->fresh()?->trial_ends_at?->toDateString());

        $this->actingAs($platform)->post(route('platform.tenants.extend', $lapsed))->assertSessionHasNoErrors();
        $this->assertSame('2026-10-14', $lapsed->fresh()?->subscription_ends_at?->toDateString(), 'Geçmişteyse bugünden sayılır.');
        $this->assertSame(SubscriptionState::Active, $lapsed->fresh()?->subscriptionState());
    }

    public function test_only_platform_admin_records_payments(): void
    {
        $tenant = $this->tenant();
        $admin = $this->admin($tenant);

        $this->actingAs($admin)->post(route('platform.tenants.payments.store', $tenant), [
            'plan_id' => $this->kervan()->id, 'billing_cycle' => 'yillik', 'amount' => 1, 'method' => 'havale', 'paid_at' => '2026-10-07',
        ])->assertForbidden();
        $this->actingAs($admin)->post(route('platform.tenants.extend', $tenant))->assertForbidden();
        $this->assertSame(0, SubscriptionPayment::withoutGlobalScopes()->count());
    }

    public function test_banner_warns_during_trial_and_past_due(): void
    {
        $trial = $this->tenant(['status' => TenantStatus::Trial, 'trial_ends_at' => '2026-10-16 10:00']);
        $this->actingAs($this->admin($trial))->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('subscription.state', 'deneme')
                ->where('subscription.message', fn (string $m) => str_contains($m, '9 gün kaldı')));

        $late = $this->tenant(['status' => TenantStatus::Active, 'subscription_ends_at' => '2026-10-05']);
        $this->actingAs($this->admin($late))->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('subscription.state', 'gecikmede'));

        $ok = $this->tenant(['status' => TenantStatus::Active, 'subscription_ends_at' => '2027-10-05']);
        $this->actingAs($this->admin($ok))->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('subscription', null));
    }

    private function kervan(): Plan
    {
        return Plan::where('slug', 'kervan')->sole();
    }
}
