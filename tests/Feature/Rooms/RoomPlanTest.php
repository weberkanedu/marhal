<?php

namespace Tests\Feature\Rooms;

use App\Actions\Persons\AddPersonRelation;
use App\Actions\Rooms\AutoAssignRooms;
use App\Enums\Feature;
use App\Enums\Gender;
use App\Enums\RegistrationStatus;
use App\Enums\Relation;
use App\Enums\RoomKind;
use App\Enums\RoomType;
use App\Enums\UserRole;
use App\Models\Group;
use App\Models\Person;
use App\Models\PersonRelation;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourHotel;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Faz 2 / 2. adım: yakınlıklar, oda planı kuralları, otomatik dağıtma ve oda raporları.
 */
class RoomPlanTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Tour $tour;

    private Group $group;

    private TourHotel $stay;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::RoomPlanning, Feature::BasicReports])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $this->staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
        $this->tour = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'start_date' => '2026-11-01', 'end_date' => '2026-11-15']);
        $this->group = Group::factory()->create(['tour_id' => $this->tour->id, 'name' => 'A Grubu']);
        $this->stay = TourHotel::factory()->create(['tour_id' => $this->tour->id, 'check_in' => '2026-11-01', 'check_out' => '2026-11-08']);
        $this->stay->groups()->sync([$this->group->id]);
    }

    // --- Yakınlıklar ---

    public function test_a_relation_is_stored_in_both_directions_with_the_right_inverse(): void
    {
        $bride = $this->person(Gender::Female);
        $motherInLaw = $this->person(Gender::Female);

        // Kayınvalide, gelinin kayınvalidesi → gelin, kayınvalidenin gelini
        $this->actingAs($this->staff)->post(route('persons.relations.store', $bride), [
            'related_person_id' => $motherInLaw->id,
            'relation' => Relation::MotherInLaw->value,
        ])->assertSessionHasNoErrors();

        $this->assertSame(Relation::MotherInLaw, PersonRelation::where('person_id', $bride->id)->sole()->relation);
        $this->assertSame(Relation::DaughterInLaw, PersonRelation::where('person_id', $motherInLaw->id)->sole()->relation);

        $this->actingAs($this->staff)->get(route('persons.show', $motherInLaw))
            ->assertInertia(fn (Assert $page) => $page->where('relations.0.relation_label', 'Gelini'));

        $this->actingAs($this->staff)->delete(route('person-relations.destroy', PersonRelation::where('person_id', $bride->id)->sole()));
        $this->assertSame(0, PersonRelation::count());
    }

    public function test_relations_to_self_or_other_agencies_are_rejected(): void
    {
        $person = $this->person(Gender::Male);
        $foreign = Person::factory()->create();

        $this->actingAs($this->staff)->post(route('persons.relations.store', $person), ['related_person_id' => $person->id, 'relation' => 'es'])
            ->assertSessionHasErrors('related_person_id');
        $this->actingAs($this->staff)->post(route('persons.relations.store', $person), ['related_person_id' => $foreign->id, 'relation' => 'es'])
            ->assertSessionHasErrors('related_person_id');

        $foreignRelation = $this->relateIn($foreign->tenant, $foreign, Person::factory()->create(['tenant_id' => $foreign->tenant_id]), Relation::Sibling);
        $this->actingAs($this->staff)->delete(route('person-relations.destroy', $foreignRelation))->assertNotFound();
    }

    // --- Odalar ---

    public function test_rooms_can_be_added_in_bulk_and_duplicates_are_rejected(): void
    {
        $this->addRooms('501', 3)->assertSessionHasNoErrors();
        $this->assertSame(['501', '502', '503'], $this->stay->rooms()->orderBy('room_no')->pluck('room_no')->all());

        $this->addRooms('503', 2)->assertSessionHasErrors('start_no');
        $this->assertSame(3, $this->stay->rooms()->count());
    }

    public function test_gender_and_capacity_rules_block_placement(): void
    {
        $room = $this->room(capacity: 2, kind: RoomKind::Male);
        [$m1, $m2, $m3] = [$this->registration(Gender::Male), $this->registration(Gender::Male), $this->registration(Gender::Male)];
        $woman = $this->registration(Gender::Female);

        $this->assign($room, $woman)->assertSessionHasErrors('room');
        $this->assign($room, $m1)->assertSessionHasNoErrors();
        $this->assign($room, $m2)->assertSessionHasNoErrors();
        $this->assign($room, $m3)->assertSessionHasErrors('room');

        $this->assertSame(2, $room->assignments()->count());
    }

    public function test_family_room_requires_a_family_relation(): void
    {
        $room = $this->room(capacity: 3, kind: RoomKind::Family);
        $husband = $this->registration(Gender::Male);
        $wife = $this->registration(Gender::Female);
        $stranger = $this->registration(Gender::Female);
        $friend = $this->registration(Gender::Male);
        $this->relate($husband->person, $wife->person, Relation::Spouse);
        $this->relate($husband->person, $friend->person, Relation::Other);

        $this->assign($room, $husband)->assertSessionHasNoErrors();
        $this->assign($room, $stranger)->assertSessionHasErrors('room');
        $this->assign($room, $friend)->assertSessionHasErrors('room'); // "diğer" aile sayılmaz
        $this->assign($room, $wife)->assertSessionHasNoErrors();
    }

    public function test_room_type_difference_is_only_a_warning(): void
    {
        $room = $this->room(capacity: 2, kind: RoomKind::Male);
        $registration = $this->registration(Gender::Male, RoomType::Quad);

        $this->assign($room, $registration)->assertSessionHasNoErrors();

        $this->actingAs($this->staff)->get(route('stays.room-plan', $this->stay))
            ->assertInertia(fn (Assert $page) => $page
                ->component('rooms/Plan')
                ->where('rooms.0.occupants.0.warnings.0', 'Ödediği oda 4 kişilik, yerleştiği oda 2 kişilik'));
    }

    public function test_placing_again_moves_the_passenger_and_clashing_hotels_are_blocked(): void
    {
        [$a, $b] = [$this->room(), $this->room()];
        $registration = $this->registration(Gender::Male);

        $this->assign($a, $registration);
        $this->assign($b, $registration)->assertSessionHasNoErrors();
        $this->assertSame($b->id, RoomAssignment::sole()->room_id);

        // Aynı tarihlerde başka bir otel (yolcu istisnası): ikinci otele yerleşemez.
        $other = TourHotel::factory()->create(['tour_id' => $this->tour->id, 'check_in' => '2026-11-05', 'check_out' => '2026-11-10']);
        $otherRoom = $this->room(stay: $other);
        $this->assign($otherRoom, $registration)->assertSessionHasErrors('room');

        // Medine (sonraki tarihler) ayrı plan: yerleşebilir.
        $medina = TourHotel::factory()->create(['tour_id' => $this->tour->id, 'check_in' => '2026-11-08', 'check_out' => '2026-11-15']);
        $this->assign($this->room(stay: $medina), $registration)->assertSessionHasNoErrors();
        $this->assertSame(2, RoomAssignment::count());
    }

    public function test_an_exception_passenger_in_another_hotel_leaves_the_group_hotel_list(): void
    {
        $registration = $this->registration(Gender::Male);
        $other = TourHotel::factory()->create(['tour_id' => $this->tour->id, 'check_in' => '2026-11-01', 'check_out' => '2026-11-08']);

        $this->assign($this->room(stay: $other), $registration)->assertSessionHasNoErrors();

        $this->actingAs($this->staff)->get(route('stays.room-plan', $this->stay))
            ->assertInertia(fn (Assert $page) => $page->has('unassigned', 0)->where('others.0.elsewhere', $other->hotel->name));
    }

    public function test_cancelling_a_registration_frees_its_beds(): void
    {
        $registration = $this->registration(Gender::Male);
        $this->assign($this->room(), $registration);

        $this->actingAs($this->staff)->put(route('registrations.update', $registration), [
            'status' => RegistrationStatus::Cancelled->value,
            'price' => 1500,
            'currency' => 'USD',
            'room_type' => RoomType::Quad->value,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, RoomAssignment::count());
    }

    public function test_room_changes_that_break_the_rules_are_blocked(): void
    {
        $room = $this->room(capacity: 3, kind: RoomKind::Male);
        $this->assign($room, $this->registration(Gender::Male));
        $this->assign($room, $this->registration(Gender::Male));

        $this->updateRoom($room, ['capacity' => 1])->assertSessionHasErrors('capacity');
        $this->updateRoom($room, ['kind' => 'kadin'])->assertSessionHasErrors('kind');
        $this->updateRoom($room, ['kind' => 'aile'])->assertSessionHasErrors('kind'); // akraba değiller
        $this->updateRoom($room, ['capacity' => 2, 'room_no' => '777'])->assertSessionHasNoErrors();
        $this->assertSame('777', $room->fresh()?->room_no);

        $this->actingAs($this->staff)->delete(route('rooms.destroy', $room));
        $this->assertSame(0, RoomAssignment::count());
    }

    // --- Otomatik dağıtma ---

    public function test_auto_assign_keeps_families_together_and_separates_genders(): void
    {
        $familyRoom = $this->room(capacity: 3, kind: RoomKind::Male, no: '101');
        $quadA = $this->room(capacity: 4, kind: RoomKind::Male, no: '102');
        $quadB = $this->room(capacity: 4, kind: RoomKind::Male, no: '103');

        $husband = $this->registration(Gender::Male, RoomType::Triple);
        $wife = $this->registration(Gender::Female, RoomType::Triple);
        $son = $this->registration(Gender::Male, RoomType::Triple);
        $this->relate($husband->person, $wife->person, Relation::Spouse);
        $this->relate($son->person, $wife->person, Relation::Mother);

        $men = collect(range(1, 3))->map(fn () => $this->registration(Gender::Male, RoomType::Quad));
        $women = collect(range(1, 2))->map(fn () => $this->registration(Gender::Female, RoomType::Quad));

        // Önizleme kaydetmez
        $this->actingAs($this->staff)->getJson(route('stays.auto-assign-preview', $this->stay))
            ->assertOk()->assertJsonPath('placed', 8)->assertJsonCount(0, 'unplaced');
        $this->assertSame(0, RoomAssignment::count());

        $this->actingAs($this->staff)->post(route('stays.auto-assign', $this->stay))->assertSessionHasNoErrors();

        $roomOf = fn (Registration $r) => RoomAssignment::where('registration_id', $r->id)->sole()->room_id;

        // Karma aile aynı odada, oda aile odası oldu (3 kişilik, ödedikleri tiple aynı)
        $this->assertSame($familyRoom->id, $roomOf($husband));
        $this->assertSame($familyRoom->id, $roomOf($wife));
        $this->assertSame($familyRoom->id, $roomOf($son));
        $this->assertSame(RoomKind::Family, $familyRoom->fresh()?->kind);

        // Erkekler bir odada, kadınlar diğerinde (kadınlar boş odaya, oda kadın odası oldu)
        $this->assertCount(1, $men->map($roomOf)->unique());
        $this->assertCount(1, $women->map($roomOf)->unique());
        $womenRoom = Room::find($roomOf($women->first()));
        $this->assertSame(RoomKind::Female, $womenRoom?->kind);
        $this->assertContains($womenRoom?->id, [$quadA->id, $quadB->id]);
    }

    public function test_auto_assign_preview_works_when_everyone_is_already_placed(): void
    {
        $this->assign($this->room(), $this->registration(Gender::Male));

        $this->actingAs($this->staff)->getJson(route('stays.auto-assign-preview', $this->stay))
            ->assertOk()->assertJsonPath('placed', 0);
    }

    public function test_auto_assign_reports_who_could_not_be_placed(): void
    {
        $this->room(capacity: 2, kind: RoomKind::Male);
        $husband = $this->registration(Gender::Male);
        $wife = $this->registration(Gender::Female);
        $this->relate($husband->person, $wife->person, Relation::Spouse);
        $this->registration(Gender::Male);

        $plan = app(CurrentTenant::class)->run($this->tenant, fn () => app(AutoAssignRooms::class)->plan($this->stay));

        // Karma aile 2 kişilik odaya sığar; üçüncü kişiye yer kalmaz.
        $this->assertCount(2, $plan['placements']);
        $this->assertCount(1, $plan['unplaced']);
    }

    // --- Yetki, izolasyon, raporlar ---

    public function test_plan_lists_the_tours_hotels_and_unplaced_families_and_can_be_cleared(): void
    {
        $medina = TourHotel::factory()->create(['tour_id' => $this->tour->id, 'check_in' => '2026-11-08', 'check_out' => '2026-11-15']);
        $husband = $this->registration(Gender::Male);
        $wife = $this->registration(Gender::Female);
        $husband->person->update(['birth_date' => '1960-06-01']);
        $this->relate($husband->person, $wife->person, Relation::Spouse);
        $single = $this->registration(Gender::Male);
        $this->assign($this->room(), $single);

        $this->actingAs($this->staff)->get(route('stays.room-plan', $this->stay))
            ->assertInertia(fn (Assert $page) => $page
                ->component('rooms/Plan')
                ->where('stays.0.id', $this->stay->id)
                ->where('stays.0.has_rooms', true)
                ->where('stays.0.placed', 1)
                ->where('stays.1.id', $medina->id)
                ->where('stays.1.has_rooms', false)
                ->has('units', 1)
                ->where('units.0.ids', fn ($ids) => collect($ids)->sort()->values()->all() === collect([$husband->id, $wife->id])->sort()->values()->all())
                ->where('unassigned', fn ($list) => collect($list)->firstWhere('registration_id', $husband->id)['age'] === 66));

        $this->actingAs($this->staff)->delete(route('stays.assignments.clear', $this->stay))->assertRedirect();
        $this->assertSame(0, RoomAssignment::query()->where('tour_hotel_id', $this->stay->id)->count());
        $this->assertSame(1, $this->stay->rooms()->count(), 'Odalar kalır');
    }

    public function test_guide_sees_only_own_group_and_cannot_change_the_plan(): void
    {
        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();
        $this->group->update(['guide_user_id' => $guide->id]);
        $room = $this->room(capacity: 4);
        $mine = $this->registration(Gender::Male);
        $otherGroup = Group::factory()->create(['tour_id' => $this->tour->id, 'name' => 'B Grubu']);
        $theirs = $this->registration(Gender::Male, group: $otherGroup);
        $this->assign($room, $mine);
        $this->assign($room, $theirs);

        $this->actingAs($guide)->get(route('stays.room-plan', $this->stay))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rooms.0.occupants.1.full_name', 'Başka grup')
                ->where('can.update', false)
                ->has('others', 0));

        $this->actingAs($guide)->post(route('rooms.assignments.store', $room), ['registration_id' => $mine->id])->assertForbidden();
        $this->actingAs($guide)->get(route('reports.stays.rooming-list', $this->stay))->assertForbidden();
        $this->actingAs($guide)->delete(route('stays.assignments.clear', $this->stay))->assertForbidden();
    }

    public function test_other_agencies_stays_rooms_and_registrations_return_404(): void
    {
        $foreignRoom = Room::factory()->create();
        $foreignRegistration = Registration::factory()->create();
        $foreignAssignment = RoomAssignment::query()->forceCreate([
            'tenant_id' => $foreignRoom->tenant_id, 'tour_hotel_id' => $foreignRoom->tour_hotel_id,
            'room_id' => $foreignRoom->id, 'registration_id' => $foreignRegistration->id,
        ]);

        $this->actingAs($this->staff)->get(route('stays.room-plan', $foreignRoom->tour_hotel_id))->assertNotFound();
        $this->actingAs($this->staff)->put(route('rooms.update', $foreignRoom), ['room_no' => '1', 'capacity' => 2, 'kind' => 'erkek'])->assertNotFound();
        $this->actingAs($this->staff)->delete(route('room-assignments.destroy', $foreignAssignment))->assertNotFound();
        $this->actingAs($this->staff)->get(route('reports.stays.rooming-list', $foreignRoom->tour_hotel_id))->assertNotFound();
        $this->actingAs($this->staff)->delete(route('stays.assignments.clear', $foreignRoom->tour_hotel_id))->assertNotFound();
        $this->assertSame(1, RoomAssignment::query()->withoutGlobalScopes()->whereKey($foreignAssignment->id)->count());

        // Kendi odasına başka acentenin yolcusu yerleştirilemez
        $this->assign($this->room(), $foreignRegistration)->assertNotFound();
    }

    public function test_rooming_list_and_occupancy_reports_download(): void
    {
        $room = $this->room(capacity: 2, no: '501');
        $registration = $this->registration(Gender::Male);
        $this->assign($room, $registration);
        $this->registration(Gender::Female); // yerleşmemiş

        foreach (['xlsx', 'pdf'] as $format) {
            $this->actingAs($this->staff)->get(route('reports.stays.rooming-list', [$this->stay, 'format' => $format]))->assertOk();
            $this->actingAs($this->staff)->get(route('reports.stays.room-occupancy', [$this->stay, 'format' => $format]))->assertOk();
        }

        $this->assertDatabaseHas('audit_logs', ['action' => 'export']);
    }

    // --- Yardımcılar ---

    private function person(Gender $gender): Person
    {
        return Person::factory()->create(['tenant_id' => $this->tenant->id, 'gender' => $gender]);
    }

    private function registration(Gender $gender, RoomType $type = RoomType::Quad, ?Group $group = null): Registration
    {
        return Registration::factory()->create([
            'tour_id' => $this->tour->id,
            'group_id' => ($group ?? $this->group)->id,
            'person_id' => $this->person($gender)->id,
            'room_type' => $type,
        ]);
    }

    private function room(int $capacity = 4, RoomKind $kind = RoomKind::Male, ?TourHotel $stay = null, ?string $no = null): Room
    {
        return Room::factory()->create(array_filter([
            'tour_hotel_id' => ($stay ?? $this->stay)->id,
            'capacity' => $capacity,
            'kind' => $kind,
            'room_no' => $no,
        ]));
    }

    private function relate(Person $person, Person $related, Relation $relation): void
    {
        $this->relateIn($this->tenant, $person, $related, $relation);
    }

    private function relateIn(Tenant $tenant, Person $person, Person $related, Relation $relation): PersonRelation
    {
        return app(CurrentTenant::class)->run($tenant, function () use ($person, $related, $relation): PersonRelation {
            app(AddPersonRelation::class)->handle($person, $related, $relation);

            return PersonRelation::where('person_id', $person->id)->where('related_person_id', $related->id)->sole();
        });
    }

    private function assign(Room $room, Registration $registration): TestResponse
    {
        return $this->actingAs($this->staff)->post(route('rooms.assignments.store', $room), ['registration_id' => $registration->id]);
    }

    private function addRooms(string $start, int $count): TestResponse
    {
        return $this->actingAs($this->staff)->post(route('stays.rooms.store', $this->stay), [
            'start_no' => $start, 'count' => $count, 'floor' => '5', 'capacity' => 4, 'kind' => 'erkek',
        ]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function updateRoom(Room $room, array $changes): TestResponse
    {
        return $this->actingAs($this->staff)->put(route('rooms.update', $room), [
            'room_no' => $room->room_no, 'capacity' => $room->capacity, 'kind' => $room->kind->value, ...$changes,
        ]);
    }
}
