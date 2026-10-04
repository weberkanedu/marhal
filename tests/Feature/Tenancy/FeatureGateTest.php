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

        Route::middleware(['web', 'auth', 'tenant', 'feature:room_planning'])
            ->get('/_test/room-planning', fn () => 'ok');
    }

    public function test_features_come_from_the_plan(): void
    {
        $starter = Tenant::factory()->create(['plan_id' => Plan::where('slug', 'baslangic')->value('id')]);
        $pro = Tenant::factory()->create(['plan_id' => Plan::where('slug', 'profesyonel')->value('id')]);

        $this->assertTrue($starter->hasFeature(Feature::Passengers));
        $this->assertFalse($starter->hasFeature(Feature::RoomPlanning));
        $this->assertTrue($pro->hasFeature(Feature::RoomPlanning));
        $this->assertFalse($pro->hasFeature(Feature::BadgeGeneration));
    }

    public function test_tenant_override_wins_over_plan(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => Plan::where('slug', 'baslangic')->value('id')]);
        $this->assertFalse($tenant->hasFeature(Feature::RoomPlanning));

        $tenant->featureOverrides()->create(['feature_key' => Feature::RoomPlanning->value, 'enabled' => true]);

        $this->assertTrue($tenant->hasFeature(Feature::RoomPlanning));
    }

    public function test_changing_plan_refreshes_features(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => Plan::where('slug', 'baslangic')->value('id')]);
        $this->assertFalse($tenant->hasFeature(Feature::RoomPlanning));

        $tenant->update(['plan_id' => Plan::where('slug', 'kurumsal')->value('id')]);

        $this->assertTrue($tenant->fresh()->hasFeature(Feature::RoomPlanning));
    }

    public function test_feature_middleware_blocks_disabled_modules(): void
    {
        $starter = Tenant::factory()->create(['plan_id' => Plan::where('slug', 'baslangic')->value('id')]);
        $pro = Tenant::factory()->create(['plan_id' => Plan::where('slug', 'profesyonel')->value('id')]);

        $this->actingAs(User::factory()->forTenant($starter)->create())
            ->get('/_test/room-planning')
            ->assertForbidden();

        $this->actingAs(User::factory()->forTenant($pro)->create())
            ->get('/_test/room-planning')
            ->assertOk();
    }
}
