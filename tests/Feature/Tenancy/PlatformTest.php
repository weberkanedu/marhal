<?php

namespace Tests\Feature\Tenancy;

use App\Enums\Feature;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->superAdmin = User::factory()->superAdmin()->create();
    }

    public function test_super_admin_can_open_a_tenant_with_first_admin(): void
    {
        $this->actingAs($this->superAdmin)->post(route('platform.tenants.store'), [
            'name' => 'Yeni Umre Turizm',
            'plan_id' => Plan::where('slug', 'mikat')->value('id'),
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14)->toDateString(),
            'default_currency' => 'USD',
            'admin_name' => 'Ali Yönetici',
            'admin_email' => 'ali@yeniumre.test',
        ])->assertSessionHasNoErrors();

        $tenant = Tenant::where('name', 'Yeni Umre Turizm')->sole();
        $this->assertSame('yeni-umre-turizm', $tenant->slug);
        $this->assertTrue($tenant->isAccessible());

        $admin = User::where('email', 'ali@yeniumre.test')->sole();
        $this->assertSame($tenant->id, $admin->tenant_id);
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue($admin->must_change_password);
    }

    public function test_super_admin_can_change_plan_status_and_feature_overrides(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => Plan::where('slug', 'mikat')->value('id')]);
        $this->assertFalse($tenant->hasFeature(Feature::FamilyScreen));

        $this->actingAs($this->superAdmin)->put(route('platform.tenants.features.update', $tenant), [
            'feature' => 'family_screen', 'enabled' => true,
        ])->assertSessionHasNoErrors();
        $this->assertTrue($tenant->fresh()->hasFeature(Feature::FamilyScreen));

        $this->actingAs($this->superAdmin)->put(route('platform.tenants.features.update', $tenant), [
            'feature' => 'family_screen', 'enabled' => null,
        ]);
        $this->assertFalse($tenant->fresh()->hasFeature(Feature::FamilyScreen), 'Paket varsayılanına döner.');

        $this->actingAs($this->superAdmin)->put(route('platform.tenants.update', $tenant), [
            'name' => $tenant->name,
            'plan_id' => Plan::where('slug', 'kervan')->value('id'),
            'status' => 'suspended',
            'default_currency' => 'USD',
        ])->assertSessionHasNoErrors();

        $tenant->refresh();
        $this->assertSame(TenantStatus::Suspended, $tenant->status);
        $this->assertTrue($tenant->hasFeature(Feature::BadgeGeneration));
        $this->assertFalse($tenant->isAccessible());
    }

    public function test_tenant_detail_page_lists_features_and_users_but_no_business_data(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => Plan::where('slug', 'kafile')->value('id')]);
        User::factory()->forTenant($tenant)->count(2)->create();

        $this->actingAs($this->superAdmin)->get(route('platform.tenants.show', $tenant))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/TenantShow')
                ->has('features', count(Feature::cases()))
                ->has('users', 2)
                ->missing('persons')
                ->missing('registrations'));
    }

    public function test_tenant_users_cannot_use_platform_actions(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->forTenant($tenant)->create();

        $this->actingAs($admin)->post(route('platform.tenants.store'), [])->assertForbidden();
        $this->actingAs($admin)->put(route('platform.tenants.features.update', $tenant), [])->assertForbidden();
    }
}
