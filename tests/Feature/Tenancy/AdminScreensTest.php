<?php

namespace Tests\Feature\Tenancy;

use App\Enums\Feature;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_only_own_tenant_audit_logs_with_filters(): void
    {
        $admin = User::factory()->create();
        $other = User::factory()->create();

        app(CurrentTenant::class)->run($admin->tenant, fn () => Person::factory()->create(['tenant_id' => $admin->tenant_id]));
        AuditLog::create(['tenant_id' => $admin->tenant_id, 'user_id' => $admin->id, 'action' => 'export', 'changes' => ['report' => 'tour_passengers', 'format' => 'pdf', 'rows' => 3]]);
        AuditLog::create(['tenant_id' => $other->tenant_id, 'user_id' => $other->id, 'action' => 'export']);

        $this->actingAs($admin)->get(route('audit.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('audit/Index')
                ->where('logs.total', 2));

        $this->actingAs($admin)->get(route('audit.index', ['action' => 'export']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('logs.total', 1)
                ->where('logs.data.0.details', 'tour_passengers · PDF · 3 satır'));
    }

    public function test_only_admins_can_view_audit_logs(): void
    {
        $staff = User::factory()->role(UserRole::Operations)->create();

        $this->actingAs($staff)->get(route('audit.index'))->assertForbidden();
    }

    public function test_super_admin_can_edit_a_plan_and_tenants_get_new_features(): void
    {
        $this->seed(PlanSeeder::class);
        $starter = Plan::where('slug', 'baslangic')->sole();
        $tenant = Tenant::factory()->create(['plan_id' => $starter->id]);
        $this->assertFalse($tenant->hasFeature(Feature::RoomPlanning));

        $this->actingAs(User::factory()->superAdmin()->create())->put(route('platform.plans.update', $starter), [
            'name' => 'Başlangıç+',
            'price_monthly' => 990,
            'price_yearly' => 9900,
            'currency' => 'TRY',
            'user_limit' => 2,
            'active_tour_limit' => null,
            'features' => ['passengers', 'payments', 'basic_reports', 'room_planning'],
        ])->assertSessionHasNoErrors();

        $starter->refresh();
        $this->assertSame('Başlangıç+', $starter->name);
        $this->assertSame(2, $starter->user_limit);
        $this->assertNull($starter->active_tour_limit);
        $this->assertTrue($tenant->fresh()->hasFeature(Feature::RoomPlanning), 'Önbellek temizlenmeli.');
    }

    public function test_tenant_admins_cannot_edit_plans(): void
    {
        $this->seed(PlanSeeder::class);

        $this->actingAs(User::factory()->create())
            ->put(route('platform.plans.update', Plan::first()), ['name' => 'X'])
            ->assertForbidden();
    }
}
