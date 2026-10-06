<?php

namespace Tests\Feature\Tenancy;

use App\Enums\Feature;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use App\Support\Plans\PassengerQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Yıllık yolcu kotası (10a): abonelik yılı içindeki iptal edilmemiş tur kayıtları sayılır.
 */
class PassengerQuotaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Tour $tour;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::Payments])->create(['passenger_limit' => 2]);
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $this->staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
        $this->tour = Tour::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function register(Person $person, string $status = 'kesin_kayit'): TestResponse
    {
        return $this->actingAs($this->staff)->post(route('tours.registrations.store', $this->tour), [
            'person_id' => $person->id, 'price' => 1000, 'status' => $status,
        ]);
    }

    public function test_registration_is_blocked_when_the_yearly_quota_is_full(): void
    {
        [$a, $b, $c] = Person::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->register($a)->assertSessionHasNoErrors();
        $this->register($b)->assertSessionHasNoErrors();
        $this->register($c)->assertSessionHasErrors('person_id');

        // İptal olarak girilen kayıt kotayı yemez.
        $this->register($c, 'iptal')->assertSessionHasNoErrors();
        $this->assertSame(2, app(PassengerQuota::class)->used($this->tenant));
    }

    public function test_cancelling_returns_the_seat_and_reopening_needs_room(): void
    {
        $first = Registration::factory()->create(['tour_id' => $this->tour->id]);
        $second = Registration::factory()->create(['tour_id' => $this->tour->id]);
        $third = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->staff)->put(route('registrations.update', $first), ['price' => 1000, 'status' => 'iptal'])
            ->assertSessionHasNoErrors();
        $this->register($third)->assertSessionHasNoErrors();

        // Kota yine dolu: iptal edilen kayıt geri açılamaz.
        $this->actingAs($this->staff)->put(route('registrations.update', $first), ['price' => 1000, 'status' => 'kesin_kayit'])
            ->assertSessionHasErrors('person_id');
        $this->assertSame(RegistrationStatus::Cancelled, $first->fresh()->status);

        // Silinen kayıt da kotaya geri döner.
        $second->delete();
        $this->actingAs($this->staff)->put(route('registrations.update', $first), ['price' => 1000, 'status' => 'kesin_kayit'])
            ->assertSessionHasNoErrors();
    }

    public function test_other_agencies_registrations_do_not_count(): void
    {
        $otherTour = Tour::factory()->create();
        Registration::factory()->count(3)->create(['tour_id' => $otherTour->id]);

        $this->assertSame(0, app(PassengerQuota::class)->used($this->tenant));
        $this->register(Person::factory()->create(['tenant_id' => $this->tenant->id]))->assertSessionHasNoErrors();
    }

    public function test_quota_resets_on_the_subscription_anniversary(): void
    {
        Carbon::setTestNow('2026-10-07 10:00');
        $this->tenant->update(['subscription_started_at' => '2025-03-01 00:00', 'subscription_ends_at' => '2026-11-01 00:00']);

        [$start, $end] = app(PassengerQuota::class)->period($this->tenant->fresh());
        $this->assertSame('2026-03-01', $start->toDateString());
        $this->assertSame('2027-03-01', $end->toDateString());

        // Geçen abonelik yılındaki kayıtlar sayılmaz.
        Registration::factory()->count(2)->create(['tour_id' => $this->tour->id, 'registered_at' => '2026-02-20 12:00']);
        Registration::factory()->create(['tour_id' => $this->tour->id, 'registered_at' => '2026-03-02 12:00']);

        $this->assertSame(1, app(PassengerQuota::class)->used($this->tenant->fresh()));

        // Aboneliği olmayan (deneme) acentede yıl, açıldığı günden sayılır.
        $trial = Tenant::factory()->create(['created_at' => '2025-11-15 09:00', 'subscription_started_at' => null]);
        $this->assertSame('2025-11-15', app(PassengerQuota::class)->period($trial)[0]->toDateString());

        Carbon::setTestNow();
    }

    public function test_unlimited_plan_never_blocks(): void
    {
        $this->tenant->plan->update(['passenger_limit' => null]);
        Registration::factory()->count(5)->create(['tour_id' => $this->tour->id]);

        $this->register(Person::factory()->create(['tenant_id' => $this->tenant->id]))->assertSessionHasNoErrors();
    }

    public function test_dashboard_shows_quota_usage(): void
    {
        Registration::factory()->create(['tour_id' => $this->tour->id]);
        Registration::factory()->create(['tour_id' => $this->tour->id, 'status' => RegistrationStatus::Cancelled]);

        $this->actingAs($this->staff)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.quota.used', 1)
                ->where('stats.quota.limit', 2));
    }
}
