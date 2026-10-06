<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\Flight;
use App\Models\NeedType;
use App\Models\Person;
use App\Models\PersonNeed;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use App\Support\Needs\DefaultNeedTypes;
use App\Support\Needs\NeedProfiles;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Paketler (10a): uçak koltuk planı ve ihtiyaç kuralları Kafile ve üstünde; Mikat'ta kapalı.
 */
class PlanModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    private function staffOn(string $slug): User
    {
        $tenant = Tenant::factory()->create(['plan_id' => Plan::where('slug', $slug)->value('id')]);
        DefaultNeedTypes::seed($tenant);

        return User::factory()->forTenant($tenant)->role(UserRole::Operations)->create();
    }

    public function test_mikat_has_no_flight_seat_plan_or_need_rules(): void
    {
        $staff = $this->staffOn('mikat');
        $tour = Tour::factory()->create(['tenant_id' => $staff->tenant_id]);
        $flight = Flight::factory()->create(['tour_id' => $tour->id]);
        $person = Person::factory()->create(['tenant_id' => $staff->tenant_id]);

        $this->actingAs($staff)->get(route('flights.show', $flight))->assertOk();
        $this->actingAs($staff)->get(route('flights.seat-plan', $flight))->assertForbidden();
        $this->actingAs($staff)->get(route('aircraft-types.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('need-types.index'))->assertForbidden();
        $this->actingAs($staff)->put(route('persons.health-consent', $person), ['granted' => true])->assertForbidden();

        $this->actingAs($staff)->get(route('persons.show', $person))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('needs', null));
    }

    public function test_kafile_has_both_modules(): void
    {
        $staff = $this->staffOn('kafile');
        $tour = Tour::factory()->create(['tenant_id' => $staff->tenant_id]);
        $flight = Flight::factory()->create(['tour_id' => $tour->id]);

        $this->actingAs($staff)->get(route('flights.seat-plan', $flight))->assertOk();
        $this->actingAs($staff)->get(route('need-types.index'))->assertOk();
    }

    public function test_needs_are_hidden_but_kept_when_the_module_is_off(): void
    {
        $staff = $this->staffOn('kafile');
        $person = Person::factory()->create(['tenant_id' => $staff->tenant_id, 'health_consent_at' => now()]);
        $type = NeedType::withoutGlobalScopes()->where('tenant_id', $staff->tenant_id)->firstOrFail();
        $profile = new PersonNeed(['person_id' => $person->id, 'items' => [['type_id' => $type->id, 'note' => null]]]);
        $profile->tenant_id = $staff->tenant_id;
        $profile->save();

        app(CurrentTenant::class)->set($staff->tenant);
        $this->assertCount(1, app(NeedProfiles::class)->forPersons([$person->id])[$person->id]);

        // Paket Mikat'a inince ihtiyaçlar okunmaz (kurallar, listeler ihtiyaç yokmuş gibi), veri silinmez.
        $staff->tenant->update(['plan_id' => Plan::where('slug', 'mikat')->value('id')]);
        app(CurrentTenant::class)->set($staff->tenant->fresh());

        $this->assertSame([], app(NeedProfiles::class)->forPersons([$person->id]));
        $this->assertSame(1, PersonNeed::withoutGlobalScopes()->where('person_id', $person->id)->count());
    }
}
