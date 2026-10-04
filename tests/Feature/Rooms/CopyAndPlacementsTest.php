<?php

namespace Tests\Feature\Rooms;

use App\Actions\Persons\AddPersonRelation;
use App\Enums\Feature;
use App\Enums\Gender;
use App\Enums\Relation;
use App\Enums\RoomKind;
use App\Enums\UserRole;
use App\Models\Bus;
use App\Models\Group;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\SeatAssignment;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourHotel;
use App\Models\User;
use App\Reports\Definitions\TourPassengerList;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Faz 2 / 4. adım: oda düzenini başka otele kopyalama; tur listesinde ve raporda oda / koltuk bilgisi.
 */
class CopyAndPlacementsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Tour $tour;

    private Group $group;

    private TourHotel $mecca;

    private TourHotel $medina;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::RoomPlanning, Feature::BusPlanning, Feature::BasicReports])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $this->staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
        $this->tour = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'start_date' => '2026-11-01', 'end_date' => '2026-11-15']);
        $this->group = Group::factory()->create(['tour_id' => $this->tour->id, 'name' => 'A Grubu']);
        $this->mecca = $this->stay('2026-11-01', '2026-11-08');
        $this->medina = $this->stay('2026-11-08', '2026-11-15');
        $this->medina->hotel->update(['city' => 'medine']);
    }

    public function test_room_plan_is_copied_keeping_roommates_together(): void
    {
        $husband = $this->registration(Gender::Male);
        $wife = $this->registration(Gender::Female);
        app(CurrentTenant::class)->run($this->tenant, fn () => app(AddPersonRelation::class)->handle($husband->person, $wife->person, Relation::Spouse));
        [$m1, $m2] = [$this->registration(Gender::Male), $this->registration(Gender::Male)];

        $family = $this->room($this->mecca, 2, RoomKind::Family, '501');
        $men = $this->room($this->mecca, 2, RoomKind::Male, '502');
        $this->place($family, $husband);
        $this->place($family, $wife);
        $this->place($men, $m1);
        $this->place($men, $m2);

        $double = $this->room($this->medina, 2, RoomKind::Male, '301');
        $other = $this->room($this->medina, 2, RoomKind::Male, '302');

        $this->actingAs($this->staff)->getJson(route('stays.copy-preview', [$this->medina, 'from' => $this->mecca->id]))
            ->assertOk()->assertJsonPath('placed', 4)->assertJsonCount(0, 'unplaced');
        $this->assertSame(0, $this->medina->roomAssignments()->count(), 'Önizleme kaydetmez');

        $this->actingAs($this->staff)->post(route('stays.copy', $this->medina), ['from' => $this->mecca->id])->assertSessionHasNoErrors();

        $roomOf = fn (Registration $r) => RoomAssignment::where('tour_hotel_id', $this->medina->id)->where('registration_id', $r->id)->sole()->room_id;
        $this->assertSame($roomOf($husband), $roomOf($wife));
        $this->assertSame($roomOf($m1), $roomOf($m2));
        $this->assertNotSame($roomOf($husband), $roomOf($m1));

        // Karı-kocanın gittiği oda aile odası oldu
        $this->assertSame(RoomKind::Family, Room::find($roomOf($husband))?->kind);
        $this->assertEqualsCanonicalizing([$double->id, $other->id], [$roomOf($husband), $roomOf($m1)]);
    }

    public function test_copy_reports_groups_without_a_free_room_and_rejects_foreign_sources(): void
    {
        $a = $this->registration(Gender::Male);
        $b = $this->registration(Gender::Male);
        $source = $this->room($this->mecca, 2, RoomKind::Male);
        $this->place($source, $a);
        $this->place($source, $b);
        $this->room($this->medina, 1, RoomKind::Male); // çok küçük

        $this->actingAs($this->staff)->getJson(route('stays.copy-preview', [$this->medina, 'from' => $this->mecca->id]))
            ->assertJsonPath('placed', 0)->assertJsonCount(2, 'unplaced');

        $foreign = TourHotel::factory()->create();
        $this->actingAs($this->staff)->getJson(route('stays.copy-preview', [$this->medina, 'from' => $foreign->id]))->assertNotFound();
        $this->actingAs($this->staff)->getJson(route('stays.copy-preview', [$this->medina, 'from' => $this->medina->id]))->assertNotFound();
    }

    public function test_tour_list_shows_rooms_and_seat_also_to_the_guide(): void
    {
        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();
        $this->group->update(['guide_user_id' => $guide->id]);
        $registration = $this->registration(Gender::Male);
        $this->place($this->room($this->mecca, 2, RoomKind::Male, '501'), $registration);
        $this->place($this->room($this->medina, 2, RoomKind::Male, '301'), $registration);
        $bus = Bus::factory()->create(['tour_id' => $this->tour->id, 'name' => '1. Otobüs']);
        SeatAssignment::query()->forceCreate(['tenant_id' => $this->tenant->id, 'tour_id' => $this->tour->id, 'bus_id' => $bus->id, 'registration_id' => $registration->id, 'seat_no' => 12]);

        $expected = [
            ['label' => 'Mekke', 'value' => '501'],
            ['label' => 'Medine', 'value' => '301'],
            ['label' => '1. Otobüs', 'value' => '12'],
        ];

        foreach ([$this->staff, $guide] as $user) {
            $this->actingAs($user)->get(route('tours.show', $this->tour))
                ->assertInertia(fn (Assert $page) => $page->where('registrations.0.placements', $expected));
        }

        $report = app(CurrentTenant::class)->run($this->tenant, fn () => app(TourPassengerList::class)->build($this->tour, null, false));
        $this->assertSame('Mekke 501 · Medine 301', $report->rows[0]['rooms']);
        $this->assertSame('1. Otobüs 12', $report->rows[0]['bus']);
    }

    public function test_room_and_bus_columns_are_hidden_without_the_modules(): void
    {
        $this->tenant->update(['plan_id' => Plan::factory()->withFeatures([Feature::Passengers, Feature::BasicReports])->create()->id]);
        $this->registration(Gender::Male);

        $report = app(CurrentTenant::class)->run($this->tenant, fn () => app(TourPassengerList::class)->build($this->tour, null, false));
        $keys = array_map(fn ($c) => $c->key, $report->columns);

        $this->assertNotContains('rooms', $keys);
        $this->assertNotContains('bus', $keys);

        $this->actingAs($this->staff)->get(route('tours.show', $this->tour))
            ->assertInertia(fn (Assert $page) => $page->where('registrations.0.placements', []));
    }

    private function stay(string $in, string $out): TourHotel
    {
        $stay = TourHotel::factory()->create(['tour_id' => $this->tour->id, 'check_in' => $in, 'check_out' => $out]);
        $stay->groups()->sync([$this->group->id]);

        return $stay;
    }

    private function registration(Gender $gender): Registration
    {
        return Registration::factory()->create([
            'tour_id' => $this->tour->id,
            'group_id' => $this->group->id,
            'person_id' => Person::factory()->create(['tenant_id' => $this->tenant->id, 'gender' => $gender])->id,
        ]);
    }

    private function room(TourHotel $stay, int $capacity, RoomKind $kind, ?string $no = null): Room
    {
        return Room::factory()->create(array_filter(['tour_hotel_id' => $stay->id, 'capacity' => $capacity, 'kind' => $kind, 'room_no' => $no]));
    }

    private function place(Room $room, Registration $registration): void
    {
        $this->actingAs($this->staff)->post(route('rooms.assignments.store', $room), ['registration_id' => $registration->id])
            ->assertSessionHasNoErrors();
    }
}
