<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_tenant_users_are_blocked(): void
    {
        $user = User::factory()->forTenant(Tenant::factory()->suspended()->create())->create();

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_expired_trial_tenant_users_are_blocked(): void
    {
        $user = User::factory()->forTenant(Tenant::factory()->trialExpired()->create())->create();

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_inactive_users_are_logged_out(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_inactive_users_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->assertGuest();
    }

    public function test_super_admin_is_redirected_to_platform(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('platform.tenants.index'));
        $this->actingAs($admin)->get(route('platform.tenants.index'))->assertOk();
    }

    public function test_tenant_users_cannot_access_platform(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('platform.tenants.index'))->assertForbidden();
    }

    public function test_role_middleware_checks_roles(): void
    {
        $user = User::factory()->role(UserRole::Guide)->create();

        $this->assertTrue($user->hasRole(UserRole::Guide, UserRole::Admin));
        $this->assertFalse($user->hasRole(UserRole::Admin));
        $this->assertFalse($user->role->canRevealSensitiveData());
    }

    public function test_plan_limits_are_enforced(): void
    {
        $plan = Plan::factory()->create(['user_limit' => 1]);
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        $this->assertTrue($tenant->canAddUser());

        User::factory()->forTenant($tenant)->role(UserRole::Guide)->create();
        $this->assertTrue($tenant->canAddUser(), 'Rehberler personel sınırına sayılmaz.');

        User::factory()->forTenant($tenant)->create();
        Tour::factory()->create(['tenant_id' => $tenant->id]);
        Tour::factory()->completed()->create(['tenant_id' => $tenant->id]);

        $this->assertFalse($tenant->canAddUser());
        $this->assertSame(1, $tenant->activeTourCount(), 'Tamamlanmış tur sayılmaz.');
    }
}
