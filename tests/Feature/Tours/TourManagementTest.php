<?php

namespace Tests\Feature\Tours;

use App\Enums\Feature;
use App\Enums\RegistrationStatus;
use App\Enums\TourStatus;
use App\Enums\UserRole;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TourManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers])->create(['active_tour_limit' => 2]);
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $this->staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
    }

    public function test_a_tour_can_be_created_with_a_default_group(): void
    {
        $this->actingAs($this->staff)->post(route('tours.store'), $this->tourData())
            ->assertSessionHasNoErrors();

        $tour = Tour::sole();
        $this->assertSame($this->tenant->id, $tour->tenant_id);
        $this->assertSame(['A Grubu'], $tour->groups()->pluck('name')->all());
    }

    public function test_active_tour_limit_of_the_plan_is_enforced(): void
    {
        Tour::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->staff)->post(route('tours.store'), $this->tourData())
            ->assertSessionHasErrors('status');

        // Taslak olmayan, tamamlanmış bir tur limite takılmaz
        $this->actingAs($this->staff)->post(route('tours.store'), $this->tourData([
            'status' => TourStatus::Completed->value,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->subWeeks(2)->toDateString(),
        ]))->assertSessionHasNoErrors();
    }

    public function test_editing_an_already_active_tour_does_not_hit_the_limit(): void
    {
        [$tour] = Tour::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->staff)->put(route('tours.update', $tour), $this->tourData(['name' => 'Yeni ad']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Yeni ad', $tour->fresh()->name);
    }

    public function test_show_page_lists_groups_registrations_and_totals(): void
    {
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'currency' => 'USD']);
        $group = Group::factory()->create(['tour_id' => $tour->id, 'name' => 'A Grubu']);
        $registration = Registration::factory()->create(['tour_id' => $tour->id, 'group_id' => $group->id, 'price' => 1000]);
        Registration::factory()->create(['tour_id' => $tour->id, 'price' => 1000, 'status' => RegistrationStatus::Cancelled]);
        Payment::factory()->create(['registration_id' => $registration->id, 'amount' => 400]);

        $this->actingAs($this->staff)->get(route('tours.show', $tour))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tours/Show')
                ->has('groups', 1)
                ->has('registrations', 2)
                ->where('stats.registered', 1)
                ->where('stats.cancelled', 1)
                ->where('stats.total', '1000.00')
                ->where('stats.paid', '400.00')
                ->where('stats.balance', '600.00')
                // Tablodaki Oda / Koltuk sütunları ve yaş (tasarımdaki tur ekranı).
                ->where('registrations.0.rooms', [])
                ->where('registrations.0.seat', null)
                ->has('registrations.0.person.age'));

        $this->actingAs($this->staff)->get(route('tours.edit', $tour))
            ->assertInertia(fn (Assert $page) => $page->component('tours/Edit')->has('canDelete'));
    }

    public function test_a_person_can_be_registered_to_a_tour_and_group(): void
    {
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'currency' => 'EUR']);
        $group = Group::factory()->create(['tour_id' => $tour->id]);
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->staff)->post(route('tours.registrations.store', $tour), [
            'person_id' => $person->id,
            'group_id' => $group->id,
            'room_type' => '4lu',
            'price' => 1250,
            'discount' => 50,
            'status' => RegistrationStatus::Confirmed->value,
        ])->assertSessionHasNoErrors();

        $registration = Registration::sole();
        $this->assertSame($group->id, $registration->group_id);
        $this->assertSame('EUR', $registration->currency, 'Kayıt para birimi turdan gelir.');
        $this->assertSame('1200.00', $registration->netPrice());
    }

    public function test_duplicate_foreign_and_over_capacity_registrations_are_rejected(): void
    {
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'capacity' => 1]);
        $otherTour = Tour::factory()->create(['tenant_id' => $this->tenant->id]);
        $foreignGroup = Group::factory()->create(['tour_id' => $otherTour->id]);
        [$first, $second] = Person::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
        $foreignPerson = Person::factory()->create();

        $post = fn (array $data) => $this->actingAs($this->staff)
            ->post(route('tours.registrations.store', $tour), [...['price' => 1000, 'status' => 'kesin_kayit'], ...$data]);

        $post(['person_id' => $foreignPerson->id])->assertSessionHasErrors('person_id');
        $post(['person_id' => $first->id, 'group_id' => $foreignGroup->id])->assertSessionHasErrors('group_id');
        $post(['person_id' => $first->id])->assertSessionHasNoErrors();
        $post(['person_id' => $first->id])->assertSessionHasErrors('person_id');
        $post(['person_id' => $second->id])->assertSessionHasErrors('person_id'); // kapasite dolu
        $post(['person_id' => $second->id, 'status' => 'iptal'])->assertSessionHasNoErrors();
    }

    public function test_cancelling_a_registration_records_the_date(): void
    {
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id]);
        $registration = Registration::factory()->create(['tour_id' => $tour->id]);

        $this->actingAs($this->staff)->put(route('registrations.update', $registration), [
            'price' => 1500,
            'status' => 'iptal',
            'cancel_reason' => 'Sağlık sorunu',
        ])->assertSessionHasNoErrors();

        $registration->refresh();
        $this->assertSame(RegistrationStatus::Cancelled, $registration->status);
        $this->assertNotNull($registration->cancelled_at);
        $this->assertSame('Sağlık sorunu', $registration->cancel_reason);
    }

    public function test_registration_with_payments_cannot_be_removed(): void
    {
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id]);
        $paid = Registration::factory()->create(['tour_id' => $tour->id]);
        $unpaid = Registration::factory()->create(['tour_id' => $tour->id]);
        Payment::factory()->create(['registration_id' => $paid->id]);

        $this->actingAs($this->staff)->delete(route('registrations.destroy', $paid));
        $this->actingAs($this->staff)->delete(route('registrations.destroy', $unpaid));

        $this->assertNotNull(Registration::find($paid->id));
        $this->assertNull(Registration::withTrashed()->find($unpaid->id));
    }

    public function test_deleting_a_group_keeps_its_passengers_in_the_tour(): void
    {
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id]);
        $group = Group::factory()->create(['tour_id' => $tour->id]);
        $registration = Registration::factory()->create(['tour_id' => $tour->id, 'group_id' => $group->id]);

        $this->actingAs($this->staff)->delete(route('groups.destroy', $group))->assertRedirect();

        $this->assertNull(Group::withTrashed()->find($group->id));
        $this->assertNull($registration->fresh()->group_id);
    }

    public function test_group_names_are_unique_within_a_tour(): void
    {
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id]);
        Group::factory()->create(['tour_id' => $tour->id, 'name' => 'A Grubu']);

        $this->actingAs($this->staff)->post(route('tours.groups.store', $tour), ['name' => 'A Grubu'])
            ->assertSessionHasErrors('name');
        $this->actingAs($this->staff)->post(route('tours.groups.store', $tour), ['name' => 'B Grubu', 'guide_name' => 'Ahmet Hoca'])
            ->assertSessionHasNoErrors();
    }

    public function test_only_admins_can_delete_tours_and_only_without_active_registrations(): void
    {
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id]);
        $registration = Registration::factory()->create(['tour_id' => $tour->id]);
        $admin = User::factory()->forTenant($this->tenant)->create();

        $this->actingAs($this->staff)->delete(route('tours.destroy', $tour))->assertForbidden();

        $this->actingAs($admin)->delete(route('tours.destroy', $tour));
        $this->assertNotSoftDeleted($tour);

        $registration->update(['status' => RegistrationStatus::Cancelled]);
        $this->actingAs($admin)->delete(route('tours.destroy', $tour))->assertRedirect(route('tours.index'));
        $this->assertSoftDeleted($tour);
    }

    public function test_other_tenants_tours_groups_and_registrations_are_not_accessible(): void
    {
        $registration = Registration::factory()->create();
        $group = Group::factory()->create(['tour_id' => $registration->tour_id]);

        $this->actingAs($this->staff)->get(route('tours.show', $registration->tour_id))->assertNotFound();
        $this->actingAs($this->staff)->put(route('groups.update', $group), ['name' => 'X'])->assertNotFound();
        $this->actingAs($this->staff)->delete(route('registrations.destroy', $registration))->assertNotFound();
    }

    public function test_person_lookup_marks_already_registered_people(): void
    {
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id]);
        $registered = Person::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Zeynep', 'last_name' => 'Arslan']);
        Person::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Zeynep', 'last_name' => 'Kurt']);
        Registration::factory()->create(['tour_id' => $tour->id, 'person_id' => $registered->id]);

        $this->actingAs($this->staff)
            ->getJson(route('persons.lookup', ['q' => 'zeynep', 'exclude_tour' => $tour->id]))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['full_name' => 'Zeynep Arslan', 'already_registered' => true])
            ->assertJsonFragment(['full_name' => 'Zeynep Kurt', 'already_registered' => false]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function tourData(array $overrides = []): array
    {
        return [
            'name' => 'Kasım 2026 Umre Turu',
            'type' => 'umre',
            'status' => TourStatus::OnSale->value,
            'start_date' => now()->addMonth()->toDateString(),
            'end_date' => now()->addMonth()->addDays(12)->toDateString(),
            'capacity' => 45,
            'default_price' => 1450,
            'currency' => 'USD',
            ...$overrides,
        ];
    }
}
