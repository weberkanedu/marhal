<?php

namespace Tests\Feature\Users;

use App\Enums\Feature;
use App\Enums\UserRole;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Rehber: sadece kendi grubunu görür, para ve kimlik bilgisi görmez (SPEC.md §1 roller).
 */
class GuideAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $guide;

    private Tour $tour;

    private Tour $otherTour;

    private Registration $mine;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::Payments, Feature::BasicReports])->create();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $this->guide = User::factory()->forTenant($tenant)->role(UserRole::Guide)->create();

        $this->tour = Tour::factory()->create(['tenant_id' => $tenant->id]);
        $myGroup = Group::factory()->create(['tour_id' => $this->tour->id, 'name' => 'A Grubu', 'guide_user_id' => $this->guide->id]);
        $otherGroup = Group::factory()->create(['tour_id' => $this->tour->id, 'name' => 'B Grubu']);
        $this->mine = Registration::factory()->create(['tour_id' => $this->tour->id, 'group_id' => $myGroup->id, 'price' => 1500]);
        Registration::factory()->create(['tour_id' => $this->tour->id, 'group_id' => $otherGroup->id]);
        Payment::factory()->create(['registration_id' => $this->mine->id, 'amount' => 500]);

        $this->otherTour = Tour::factory()->create(['tenant_id' => $tenant->id]);
    }

    public function test_guide_sees_only_tours_of_their_groups(): void
    {
        $this->actingAs($this->guide)->get(route('tours.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('tours', 1)
                ->where('tours.0.id', $this->tour->id)
                ->where('tours.0.default_price', null)
                ->where('limits', null)
                ->where('can.create', false));

        $this->actingAs($this->guide)->get(route('tours.show', $this->otherTour))->assertForbidden();
    }

    public function test_guide_sees_only_own_group_without_money(): void
    {
        $this->actingAs($this->guide)->get(route('tours.show', $this->tour))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('groups', 1)
                ->has('registrations', 1)
                ->where('registrations.0.id', $this->mine->id)
                ->where('registrations.0.net_price', null)
                ->where('registrations.0.balance', null)
                ->where('registrations.0.person.masked_passport_no', null)
                ->where('stats.total', null)
                ->where('tour.default_price', null)
                ->where('can.viewFinance', false)
                ->where('can.update', false)
                ->where('can.viewPersons', false));
    }

    public function test_guide_cannot_reach_finance_or_person_screens(): void
    {
        $this->actingAs($this->guide)->get(route('dashboard'))->assertRedirect(route('tours.index'));
        $this->actingAs($this->guide)->get(route('registrations.show', $this->mine))->assertForbidden();
        $this->actingAs($this->guide)->get(route('collections.index'))->assertForbidden();
        $this->actingAs($this->guide)->get(route('reports.tours.payments', $this->tour))->assertForbidden();
        $this->actingAs($this->guide)->get(route('reports.collections'))->assertForbidden();
        $this->actingAs($this->guide)->get(route('persons.show', $this->mine->person_id))->assertForbidden();
    }

    public function test_guide_passenger_report_is_limited_to_own_group(): void
    {
        $this->actingAs($this->guide)
            ->get(route('reports.tours.passengers', [$this->tour, 'format' => 'pdf']))
            ->assertOk();

        $otherGroup = Group::where('name', 'B Grubu')->sole();
        $this->actingAs($this->guide)
            ->get(route('reports.tours.passengers', [$this->tour, 'group' => $otherGroup->id]))
            ->assertNotFound();
    }
}
