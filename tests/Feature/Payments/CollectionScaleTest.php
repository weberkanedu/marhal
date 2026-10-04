<?php

namespace Tests\Feature\Payments;

use App\Actions\Payments\ReplaceInstallmentPlan;
use App\Enums\Feature;
use App\Enums\UserRole;
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
 * Tahsilat ekranı veritabanında hesaplar ve sayfalar: sayfa 50 satır, toplamlar tüm kayıtlardan.
 */
class CollectionScaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_debtor_list_is_paginated_and_totals_cover_all_rows(): void
    {
        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::Payments])->create();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $staff = User::factory()->forTenant($tenant)->role(UserRole::Operations)->create();
        $tour = Tour::factory()->create(['tenant_id' => $tenant->id, 'currency' => 'USD']);

        $registrations = Registration::factory()->count(120)->create(['tour_id' => $tour->id, 'price' => 1000]);
        // Birine gecikmiş taksit: listenin en üstünde olmalı.
        $late = $registrations[77];
        app(ReplaceInstallmentPlan::class)->handle($late, [['due_date' => now()->subWeek()->toDateString(), 'amount' => 1000]]);
        // Biri tamamen ödenmiş: borçlu listesinde olmamalı.
        Payment::factory()->create(['registration_id' => $registrations[5]->id, 'amount' => 1000]);

        $this->actingAs($staff)->get(route('collections.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.items.data', 50)
                ->where('rows.items.total', 119)
                ->where('rows.items.data.0.id', $late->id)
                ->where('rows.totals.USD.balance', '119000.00')
                ->where('rows.totals.USD.overdue', '1000.00'));

        $this->actingAs($staff)->get(route('collections.index', ['page' => 3]))
            ->assertInertia(fn (Assert $page) => $page->has('rows.items.data', 19));
    }
}
