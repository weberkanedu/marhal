<?php

namespace Tests\Feature\Rooms;

use App\Enums\Feature;
use App\Enums\UserRole;
use App\Models\Group;
use App\Models\Hotel;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourHotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Faz 2 / 1. adım: oteller (acente geneli) ve turun grup bazında konaklamaları.
 */
class HotelStayTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Tour $tour;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::RoomPlanning])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $this->staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
        $this->tour = Tour::factory()->create([
            'tenant_id' => $this->tenant->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-15',
        ]);
    }

    public function test_staff_can_add_edit_and_delete_hotels(): void
    {
        $this->actingAs($this->staff)->post(route('hotels.store'), [
            'name' => 'Hilton Suites', 'city' => 'mekke', 'stars' => 5,
        ])->assertSessionHasNoErrors();

        $hotel = Hotel::sole();
        $this->assertSame($this->tenant->id, $hotel->tenant_id);

        $this->actingAs($this->staff)->put(route('hotels.update', $hotel), [
            'name' => 'Hilton Suites Makkah', 'city' => 'mekke',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Hilton Suites Makkah', $hotel->fresh()?->name);

        $this->actingAs($this->staff)->get(route('hotels.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('hotels/Index')
                ->has('hotels', 1)
                ->where('hotels.0.city', 'mekke'));

        $this->actingAs($this->staff)->delete(route('hotels.destroy', $hotel));
        $this->assertDatabaseMissing('hotels', ['id' => $hotel->id]);
    }

    public function test_a_hotel_used_by_a_tour_cannot_be_deleted(): void
    {
        $stay = TourHotel::factory()->create(['tour_id' => $this->tour->id]);

        $this->actingAs($this->staff)->delete(route('hotels.destroy', $stay->hotel_id));

        $this->assertDatabaseHas('hotels', ['id' => $stay->hotel_id]);
    }

    public function test_groups_of_the_same_tour_can_stay_in_different_hotels(): void
    {
        [$a, $b] = $this->makeGroups();
        $hilton = Hotel::factory()->create(['tenant_id' => $this->tenant->id]);
        $pullman = Hotel::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->stay($hilton, [$a->id])->assertSessionHasNoErrors();
        $this->stay($pullman, [$b->id])->assertSessionHasNoErrors();

        $this->assertSame([$a->id], TourHotel::where('hotel_id', $hilton->id)->sole()->groups()->pluck('groups.id')->all());

        $this->actingAs($this->staff)->get(route('tours.show', $this->tour))
            ->assertInertia(fn (Assert $page) => $page
                ->has('stays', 2)
                ->where('stays.0.nights', 7)
                ->has('options.hotels', 2));
    }

    public function test_a_group_cannot_stay_in_two_hotels_on_the_same_nights(): void
    {
        [$a] = $this->makeGroups();
        $mecca = Hotel::factory()->create(['tenant_id' => $this->tenant->id]);
        $medina = Hotel::factory()->create(['tenant_id' => $this->tenant->id, 'city' => 'medine']);

        $this->stay($mecca, [$a->id], '2026-11-01', '2026-11-08')->assertSessionHasNoErrors();

        // Çakışan tarihler: hata
        $this->stay($medina, [$a->id], '2026-11-07', '2026-11-15')->assertSessionHasErrors('group_ids');

        // Mekke çıkış günü Medine'ye giriş: izinli
        $this->stay($medina, [$a->id], '2026-11-08', '2026-11-15')->assertSessionHasNoErrors();
    }

    public function test_a_stay_without_groups_is_allowed_for_individual_exceptions(): void
    {
        $hotel = Hotel::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->stay($hotel, [])->assertSessionHasNoErrors();

        $this->assertSame(0, TourHotel::sole()->groups()->count());
    }

    public function test_editing_a_stay_updates_its_groups(): void
    {
        [$a, $b] = $this->makeGroups();
        $stay = TourHotel::factory()->create(['tour_id' => $this->tour->id]);
        $stay->groups()->sync([$a->id]);

        $this->actingAs($this->staff)->put(route('stays.update', $stay), [
            'hotel_id' => $stay->hotel_id,
            'check_in' => '2026-11-01',
            'check_out' => '2026-11-10',
            'group_ids' => [$b->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame([$b->id], $stay->groups()->pluck('groups.id')->all());
        $this->assertSame('2026-11-10', $stay->fresh()?->check_out->toDateString());
    }

    public function test_groups_of_another_tour_and_hotels_of_another_agency_are_rejected(): void
    {
        $otherTourGroup = Group::factory()->create(['tour_id' => Tour::factory()->create(['tenant_id' => $this->tenant->id])->id]);
        $foreignHotel = Hotel::factory()->create();
        $hotel = Hotel::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->stay($hotel, [$otherTourGroup->id])->assertSessionHasErrors('group_ids.0');
        $this->stay($foreignHotel, [])->assertSessionHasErrors('hotel_id');
    }

    public function test_other_agencies_hotels_and_stays_return_404(): void
    {
        $foreignStay = TourHotel::factory()->create();

        $this->actingAs($this->staff)->put(route('hotels.update', $foreignStay->hotel_id), ['name' => 'X', 'city' => 'mekke'])->assertNotFound();
        $this->actingAs($this->staff)->delete(route('hotels.destroy', $foreignStay->hotel_id))->assertNotFound();
        $this->actingAs($this->staff)->delete(route('stays.destroy', $foreignStay))->assertNotFound();
        $this->actingAs($this->staff)->get(route('hotels.index'))
            ->assertInertia(fn (Assert $page) => $page->has('hotels', 0));
    }

    public function test_guide_sees_only_own_groups_stays_and_cannot_manage_hotels(): void
    {
        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();
        [$a, $b] = $this->makeGroups();
        $a->update(['guide_user_id' => $guide->id]);
        TourHotel::factory()->create(['tour_id' => $this->tour->id])->groups()->sync([$a->id]);
        TourHotel::factory()->create(['tour_id' => $this->tour->id])->groups()->sync([$b->id]);

        $this->actingAs($guide)->get(route('tours.show', $this->tour))
            ->assertInertia(fn (Assert $page) => $page->has('stays', 1)->has('options.hotels', 0));

        $this->actingAs($guide)->get(route('hotels.index'))->assertForbidden();
        $this->actingAs($guide)->post(route('hotels.store'), ['name' => 'X', 'city' => 'mekke'])->assertForbidden();
    }

    public function test_hotel_screens_require_the_room_planning_feature(): void
    {
        $plan = Plan::factory()->withFeatures([Feature::Passengers])->create();
        $this->tenant->update(['plan_id' => $plan->id]);

        $this->actingAs($this->staff)->get(route('hotels.index'))->assertForbidden();
        $this->actingAs($this->staff)->get(route('tours.show', $this->tour))
            ->assertInertia(fn (Assert $page) => $page->where('stays', null));
    }

    /**
     * @return array{0: Group, 1: Group}
     */
    private function makeGroups(): array
    {
        return [
            Group::factory()->create(['tour_id' => $this->tour->id, 'name' => 'A Grubu']),
            Group::factory()->create(['tour_id' => $this->tour->id, 'name' => 'B Grubu']),
        ];
    }

    /**
     * @param  list<string>  $groupIds
     */
    private function stay(Hotel $hotel, array $groupIds, string $in = '2026-11-01', string $out = '2026-11-08'): TestResponse
    {
        return $this->actingAs($this->staff)->post(route('tours.stays.store', $this->tour), [
            'hotel_id' => $hotel->id,
            'check_in' => $in,
            'check_out' => $out,
            'group_ids' => $groupIds,
        ]);
    }
}
