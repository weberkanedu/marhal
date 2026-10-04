<?php

namespace Tests\Feature\Tenancy;

use App\Models\Person;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_queries_are_limited_to_the_current_tenant(): void
    {
        [$a, $b] = Tenant::factory()->count(2)->create();
        Person::factory()->count(3)->create(['tenant_id' => $a->id]);
        Person::factory()->count(2)->create(['tenant_id' => $b->id]);

        $context = app(CurrentTenant::class);

        $this->assertSame(3, $context->run($a, fn () => Person::count()));
        $this->assertSame(2, $context->run($b, fn () => Person::count()));
        $this->assertSame(5, Person::count(), 'Bağlam yokken (CLI) kapsam uygulanmaz.');
    }

    public function test_other_tenants_records_cannot_be_found_by_id(): void
    {
        [$a, $b] = Tenant::factory()->count(2)->create();
        $foreign = Person::factory()->create(['tenant_id' => $b->id]);

        $found = app(CurrentTenant::class)->run($a, fn () => Person::find($foreign->id));

        $this->assertNull($found);
    }

    public function test_new_records_get_the_current_tenant_automatically(): void
    {
        $tenant = Tenant::factory()->create();

        $tour = app(CurrentTenant::class)->run($tenant, fn () => Tour::create([
            'name' => 'Test Turu',
            'start_date' => now()->addMonth(),
            'end_date' => now()->addMonth()->addDays(10),
            'currency' => 'USD',
        ]));

        $this->assertSame($tenant->id, $tour->tenant_id);
    }

    public function test_dashboard_only_counts_own_tenant_data(): void
    {
        $user = User::factory()->create();
        Person::factory()->count(2)->create(['tenant_id' => $user->tenant_id]);
        Person::factory()->count(5)->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.persons', 2)
                ->where('tenant.id', $user->tenant_id)
            );
    }
}
