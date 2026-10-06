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
        $starter = Plan::where('slug', 'mikat')->sole();
        $tenant = Tenant::factory()->create(['plan_id' => $starter->id]);
        $this->assertFalse($tenant->hasFeature(Feature::FamilyScreen));

        $this->actingAs(User::factory()->superAdmin()->create())->put(route('platform.plans.update', $starter), [
            'price_monthly' => 990,
            'user_limit' => 2,
            'passenger_limit' => null,
            'is_public' => true,
            'features' => ['passengers', 'payments', 'basic_reports', 'family_screen'],
        ])->assertSessionHasNoErrors();

        $starter->refresh();
        $this->assertSame('Mikat', $starter->name);
        $this->assertSame('9900.00', $starter->price_yearly, 'Yıllık = aylık × 10.');
        $this->assertSame(2, $starter->user_limit);
        $this->assertNull($starter->passenger_limit, 'Boş = sınırsız.');
        $this->assertTrue($tenant->fresh()->hasFeature(Feature::FamilyScreen), 'Önbellek temizlenmeli.');
        $this->assertFalse($tenant->fresh()->hasFeature(Feature::RoomPlanning));
    }

    public function test_at_least_one_plan_stays_on_sale(): void
    {
        $this->seed(PlanSeeder::class);
        $admin = User::factory()->superAdmin()->create();
        $payload = fn (Plan $plan) => [
            'price_monthly' => (float) $plan->price_monthly,
            'user_limit' => $plan->user_limit,
            'passenger_limit' => $plan->passenger_limit,
            'is_public' => false,
            'features' => [],
        ];

        [$first, $second, $last] = Plan::orderBy('sort')->get()->all();
        $this->actingAs($admin)->put(route('platform.plans.update', $first), $payload($first))->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('platform.plans.update', $second), $payload($second))->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('platform.plans.update', $last), $payload($last))->assertSessionHasErrors('is_public');

        $this->assertTrue($last->fresh()->is_public);
        $this->assertFalse($first->fresh()->is_public);
    }

    public function test_plans_page_lists_plans_in_sale_order(): void
    {
        $this->seed(PlanSeeder::class);

        $this->actingAs(User::factory()->superAdmin()->create())->get(route('platform.plans.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('platform/Plans')
                ->where('plans.0.name', 'Mikat')
                ->where('plans.1.name', 'Kafile')
                ->where('plans.2.name', 'Kervan')
                ->where('plans.1.passenger_limit', 1500)
                ->where('yearlyMonths', 10));
    }

    public function test_tenant_admins_cannot_edit_plans(): void
    {
        $this->seed(PlanSeeder::class);

        $this->actingAs(User::factory()->create())
            ->put(route('platform.plans.update', Plan::first()), ['name' => 'X'])
            ->assertForbidden();
    }
}
