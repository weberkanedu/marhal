<?php

namespace Tests\Feature\Tours;

use App\Enums\Feature;
use App\Enums\Gender;
use App\Enums\UserRole;
use App\Models\Group;
use App\Models\Person;
use App\Models\Plan;
use App\Models\ReadinessCheck;
use App\Models\ReadinessItem;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use App\Support\Dashboard\TourReadiness;
use App\Support\Readiness\DefaultReadinessItems;
use App\Support\Readiness\ReadinessBoard;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Tasarım yenileme 6: hazırlık takibi (maddeler, işaretleme, kendiliğinden dolan pasaport / fotoğraf,
 * Ravza randevuları, rehberin kendi grubu, acente izolasyonu, çıktı).
 */
class ReadinessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Tour $tour;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::Readiness, Feature::BasicReports])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        DefaultReadinessItems::seed($this->tenant);
        $this->staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
        $this->tour = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'start_date' => now()->addMonth(), 'end_date' => now()->addMonth()->addDays(14)]);
        $this->group = Group::factory()->create(['tour_id' => $this->tour->id, 'name' => 'A Grubu']);
    }

    public function test_board_uses_default_items_and_fills_passport_and_photo_automatically(): void
    {
        $valid = $this->registration();
        $missing = $this->registration(['passport_expiry_date' => null]);
        $this->item('Fotoğraf');
        $this->actingAs($this->staff)->put(route('tours.readiness.items', $this->tour), [
            'item_ids' => [$this->item('Pasaport')->id, $this->item('Aşı')->id, $this->item('Fotoğraf')->id],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->staff)->get(route('tours.show', $this->tour))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('readinessBoard')
                ->loadDeferredProps('readiness', fn (Assert $reload) => $reload
                    ->has('readinessBoard.items', 3)
                    ->where('readinessBoard.items.0.automatic', true)
                    ->where('readinessBoard.rows', fn ($rows) => collect($rows)->firstWhere('registration_id', $valid->id)['cells'][$this->item('Pasaport')->id]['status'] === 'tamam'
                        && collect($rows)->firstWhere('registration_id', $missing->id)['cells'][$this->item('Pasaport')->id]['status'] === 'sorun'
                        && collect($rows)->firstWhere('registration_id', $valid->id)['cells'][$this->item('Fotoğraf')->id]['status'] === null)
                    ->has('readinessBoard.all_items', 8)));

        // Yeni turda (seçim yapılmamış) "yeni turlarda seçili" beş madde gelir.
        $other = Tour::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertSame(['Pasaport', 'Aşı', 'Vize', 'Nusuk', 'Ravza'], $this->board($other));
    }

    public function test_staff_marks_a_cell_through_the_cycle_and_a_whole_column(): void
    {
        $a = $this->registration();
        $b = $this->registration();
        $item = $this->item('Aşı');

        $this->mark($a, $item, 'tamam')->assertSessionHasNoErrors();
        $this->assertSame('tamam', ReadinessCheck::sole()->status->value);
        $this->assertSame($this->staff->id, ReadinessCheck::sole()->checked_by);

        $this->mark($a, $item, 'sorun');
        $this->assertSame('sorun', ReadinessCheck::sole()->status->value);
        $this->mark($a, $item, null);
        $this->assertSame(0, ReadinessCheck::count());

        $this->actingAs($this->staff)->post(route('tours.readiness.column', $this->tour), ['item_id' => $item->id])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, ReadinessCheck::where('status', 'tamam')->whereIn('registration_id', [$a->id, $b->id])->count());

        // Pasaport elle işaretlenmez; turda takip edilmeyen madde de işaretlenmez.
        $this->mark($a, $this->item('Pasaport'), 'tamam')->assertSessionHasErrors('item_id');
        $this->mark($a, $this->item('Sözleşme'), 'tamam')->assertSessionHasErrors('item_id');

        // En az bir madde kalır.
        $this->actingAs($this->staff)->put(route('tours.readiness.items', $this->tour), ['item_ids' => []])
            ->assertSessionHasErrors('item_ids');
    }

    public function test_ravza_appointments_and_waiting_counts_feed_the_dashboard(): void
    {
        $man = $this->registration(['gender' => Gender::Male]);
        $this->registration(['gender' => Gender::Female]);
        $this->registration(['gender' => Gender::Female]);
        $this->mark($man, $this->item('Ravza'), 'tamam');

        $this->actingAs($this->staff)->put(route('tours.ravza', $this->tour), ['men' => '2026-10-31 02:00', 'women' => null])
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-10-31 02:00', $this->tour->fresh()?->ravza_men_at?->format('Y-m-d H:i'));

        $summary = app(CurrentTenant::class)->run($this->tenant, fn () => app(TourReadiness::class)->for($this->tour, $this->tenant));
        $this->assertSame(['men' => 0, 'women' => 2], $summary['ravza_waiting']);
        $this->assertSame(['key' => 'readiness', 'label' => 'Hazırlık', 'done' => 0, 'total' => 3, 'tab' => 'hazirlik'], collect($summary['checks'])->firstWhere('key', 'readiness'));
    }

    public function test_guide_marks_only_own_group_and_cannot_change_items_or_ravza(): void
    {
        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();
        $this->group->update(['guide_user_id' => $guide->id]);
        $mine = $this->registration();
        $otherGroup = Group::factory()->create(['tour_id' => $this->tour->id, 'name' => 'Z Grubu']);
        $theirs = $this->registration([], $otherGroup);
        $item = $this->item('Aşı');

        $this->actingAs($guide)->post(route('tours.readiness.mark', $this->tour), ['registration_id' => $mine->id, 'item_id' => $item->id, 'status' => 'tamam'])
            ->assertSessionHasNoErrors();
        $this->actingAs($guide)->post(route('tours.readiness.mark', $this->tour), ['registration_id' => $theirs->id, 'item_id' => $item->id, 'status' => 'tamam'])
            ->assertNotFound();
        $this->actingAs($guide)->post(route('tours.readiness.column', $this->tour), ['item_id' => $item->id, 'group_id' => $otherGroup->id])
            ->assertForbidden();
        $this->actingAs($guide)->put(route('tours.readiness.items', $this->tour), ['item_ids' => [$item->id]])->assertForbidden();
        $this->actingAs($guide)->put(route('tours.ravza', $this->tour), ['men' => null])->assertForbidden();
        $this->actingAs($guide)->get(route('readiness-items.index'))->assertForbidden();

        $this->actingAs($guide)->get(route('tours.show', $this->tour))
            ->assertInertia(fn (Assert $page) => $page->loadDeferredProps('readiness', fn (Assert $reload) => $reload
                ->has('readinessBoard.rows', 1)
                ->where('readinessBoard.rows.0.registration_id', $mine->id)
                ->where('readinessBoard.all_items', [])));
    }

    public function test_other_agencies_tours_items_and_registrations_are_not_found(): void
    {
        $other = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id]);
        DefaultReadinessItems::seed($other);
        $foreignTour = Tour::factory()->create(['tenant_id' => $other->id]);
        $foreignItem = ReadinessItem::query()->withoutGlobalScopes()->where('tenant_id', $other->id)->where('name', 'Aşı')->sole();
        $foreignRegistration = Registration::factory()->create(['tour_id' => $foreignTour->id]);
        $mine = $this->registration();

        $this->mark($mine, $foreignItem, 'tamam')->assertNotFound();
        $this->actingAs($this->staff)->post(route('tours.readiness.mark', $this->tour), ['registration_id' => $foreignRegistration->id, 'item_id' => $this->item('Aşı')->id, 'status' => 'tamam'])
            ->assertNotFound();
        $this->actingAs($this->staff)->post(route('tours.readiness.mark', $foreignTour), ['registration_id' => $foreignRegistration->id, 'item_id' => $foreignItem->id, 'status' => 'tamam'])
            ->assertNotFound();
        $this->actingAs($this->staff)->put(route('readiness-items.update', $foreignItem), ['name' => 'X', 'kind' => 'elle'])->assertNotFound();
        $this->actingAs($this->staff)->get(route('reports.tours.readiness', $foreignTour))->assertNotFound();
        $this->assertSame(0, ReadinessCheck::query()->withoutGlobalScopes()->count());
    }

    public function test_agency_manages_items_and_the_list_downloads(): void
    {
        $this->actingAs($this->staff)->post(route('readiness-items.store'), ['name' => 'Kurban vekâleti', 'kind' => 'elle', 'default_on' => false])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->post(route('readiness-items.store'), ['name' => 'Aşı', 'kind' => 'elle'])
            ->assertSessionHasErrors('name');
        $this->actingAs($this->staff)->get(route('readiness-items.index'))
            ->assertInertia(fn (Assert $page) => $page->component('readiness-items/Index')->has('items', 9));

        $this->registration();
        foreach (['xlsx', 'pdf'] as $format) {
            $this->actingAs($this->staff)->get(route('reports.tours.readiness', [$this->tour, 'format' => $format]))->assertOk();
        }
    }

    public function test_agency_without_the_module_has_no_tab_and_no_routes(): void
    {
        $plan = Plan::factory()->withFeatures([Feature::Passengers])->create();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $admin = User::factory()->forTenant($tenant)->role(UserRole::Admin)->create();
        $tour = Tour::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($admin)->get(route('tours.show', $tour))
            ->assertInertia(fn (Assert $page) => $page->where('readinessBoard', null));
        $this->actingAs($admin)->get(route('readiness-items.index'))->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $person
     */
    private function registration(array $person = [], ?Group $group = null): Registration
    {
        return Registration::factory()->create([
            'tour_id' => $this->tour->id,
            'group_id' => ($group ?? $this->group)->id,
            'person_id' => Person::factory()->create(['tenant_id' => $this->tenant->id, ...$person])->id,
        ]);
    }

    private function item(string $name): ReadinessItem
    {
        return ReadinessItem::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', $name)->sole();
    }

    private function mark(Registration $registration, ReadinessItem $item, ?string $status): TestResponse
    {
        return $this->actingAs($this->staff)->post(route('tours.readiness.mark', $this->tour), [
            'registration_id' => $registration->id, 'item_id' => $item->id, 'status' => $status,
        ]);
    }

    /**
     * @return list<string>
     */
    private function board(Tour $tour): array
    {
        return app(CurrentTenant::class)->run($this->tenant, fn () => array_column(app(ReadinessBoard::class)->build($tour)['items'], 'name'));
    }
}
