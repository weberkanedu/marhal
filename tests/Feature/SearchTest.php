<?php

namespace Tests\Feature;

use App\Enums\Feature;
use App\Enums\UserRole;
use App\Models\Group;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Yan menüdeki "Ara" (Ctrl K): yolcu ve tur arar; acente dışına çıkmaz, rehber yalnız kendi turunu bulur.
 */
class SearchTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
    }

    public function test_staff_finds_persons_and_tours_of_own_agency_only(): void
    {
        $staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ramazan Umresi']);
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Zehra', 'last_name' => 'Ramazanoğlu']);
        Registration::factory()->create(['tour_id' => $tour->id, 'person_id' => $person->id]);

        // Başka acentenin aynı adlı yolcusu ve turu bulunmaz.
        $other = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id]);
        Person::factory()->create(['tenant_id' => $other->id, 'first_name' => 'Zehra', 'last_name' => 'Ramazanoğlu']);
        Tour::factory()->create(['tenant_id' => $other->id, 'name' => 'Ramazan Umresi']);

        $this->actingAs($staff)->getJson(route('search', ['q' => 'ramazan']))
            ->assertOk()
            ->assertJsonCount(1, 'persons')
            ->assertJsonPath('persons.0.id', $person->id)
            ->assertJsonPath('persons.0.sub', 'Ramazan Umresi')
            ->assertJsonCount(1, 'tours')
            ->assertJsonPath('tours.0.id', $tour->id);

        $this->actingAs($staff)->getJson(route('search', ['q' => 'r']))
            ->assertJsonCount(0, 'persons')
            ->assertJsonCount(0, 'tours');
    }

    public function test_guide_finds_only_own_tours_and_no_persons(): void
    {
        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();
        $mine = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ekim Umresi A']);
        Group::factory()->create(['tour_id' => $mine->id, 'guide_user_id' => $guide->id]);
        Tour::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ekim Umresi B']);
        Person::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Ekim']);

        $this->actingAs($guide)->getJson(route('search', ['q' => 'ekim']))
            ->assertJsonCount(0, 'persons')
            ->assertJsonCount(1, 'tours')
            ->assertJsonPath('tours.0.id', $mine->id);
    }

    public function test_agency_without_the_passenger_module_gets_nothing(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => Plan::factory()->withFeatures([])->create()->id]);
        $admin = User::factory()->forTenant($tenant)->role(UserRole::Admin)->create();
        Tour::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Kasım Umresi']);

        $this->actingAs($admin)->getJson(route('search', ['q' => 'kasım']))
            ->assertOk()
            ->assertJsonCount(0, 'tours');
    }
}
