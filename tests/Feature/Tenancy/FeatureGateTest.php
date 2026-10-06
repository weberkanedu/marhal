<?php

namespace Tests\Feature\Tenancy;

use App\Enums\Feature;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FeatureGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        Route::middleware(['web', 'auth', 'tenant', 'feature:family_screen'])
            ->get('/_test/family-screen', fn () => 'ok');
    }

    public function test_features_come_from_the_plan(): void
    {
        $starter = Tenant::factory()->create(['plan_id' => Plan::where('slug', 'mikat')->value('id')]);
        $pro = Tenant::factory()->create(['plan_id' => Plan::where('slug', 'kafile')->value('id')]);

        $this->assertTrue($starter->hasFeature(Feature::Passengers));
        $this->assertFalse($starter->hasFeature(Feature::FamilyScreen));
        $this->assertTrue($pro->hasFeature(Feature::FamilyScreen));
        $this->assertFalse($pro->hasFeature(Feature::ApiAccess));
        $this->assertTrue($starter->hasFeature(Feature::RoomPlanning), 'Görsel yerleşim her pakette.');
        $this->assertFalse($starter->hasFeature(Feature::FlightSeats));
        $this->assertTrue($pro->hasFeature(Feature::FlightSeats));
    }

    public function test_tenant_override_wins_over_plan(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => Plan::where('slug', 'mikat')->value('id')]);
        $this->assertFalse($tenant->hasFeature(Feature::FamilyScreen));

        $tenant->featureOverrides()->create(['feature_key' => Feature::FamilyScreen->value, 'enabled' => true]);

        $this->assertTrue($tenant->hasFeature(Feature::FamilyScreen));
    }

    public function test_changing_plan_refreshes_features(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => Plan::where('slug', 'mikat')->value('id')]);
        $this->assertFalse($tenant->hasFeature(Feature::FamilyScreen));

        $tenant->update(['plan_id' => Plan::where('slug', 'kervan')->value('id')]);

        $this->assertTrue($tenant->fresh()->hasFeature(Feature::FamilyScreen));
    }

    public function test_feature_middleware_blocks_disabled_modules(): void
    {
        $starter = Tenant::factory()->create(['plan_id' => Plan::where('slug', 'mikat')->value('id')]);
        $pro = Tenant::factory()->create(['plan_id' => Plan::where('slug', 'kafile')->value('id')]);

        $this->actingAs(User::factory()->forTenant($starter)->create())
            ->get('/_test/family-screen')
            ->assertForbidden();

        $this->actingAs(User::factory()->forTenant($pro)->create())
            ->get('/_test/family-screen')
            ->assertOk();
    }
}
