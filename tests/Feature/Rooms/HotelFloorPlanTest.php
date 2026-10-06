<?php

namespace Tests\Feature\Rooms;

use App\Actions\Rooms\AutoAssignRooms;
use App\Enums\Feature;
use App\Enums\Gender;
use App\Enums\RoomKind;
use App\Enums\UserRole;
use App\Models\Group;
use App\Models\NeedType;
use App\Models\Person;
use App\Models\PersonNeed;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourHotel;
use App\Models\User;
use App\Support\Needs\DefaultNeedTypes;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ReadsSpreadsheets;
use Tests\TestCase;

/**
 * Tasarım yenileme 4b: otel kat planı (kat sayısı, bize verilen katlar), asansöre yakın odalar,
 * hareket güçlüğü kuralları ve otel çıktıları (Kat planı, İhtiyaç listesi).
 */
class HotelFloorPlanTest extends TestCase
{
    use ReadsSpreadsheets, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private TourHotel $stay;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::RoomPlanning, Feature::NeedRules, Feature::BasicReports])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        DefaultNeedTypes::seed($this->tenant);
        $this->staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'start_date' => '2026-11-01', 'end_date' => '2026-11-15']);
        $this->group = Group::factory()->create(['tour_id' => $tour->id]);
        $this->stay = TourHotel::factory()->create(['tour_id' => $tour->id, 'check_in' => '2026-11-01', 'check_out' => '2026-11-08']);
        $this->stay->groups()->sync([$this->group->id]);
    }

    public function test_floors_are_defined_and_floors_with_rooms_are_locked(): void
    {
        $this->actingAs($this->staff)->put(route('stays.floors', $this->stay), ['floors_count' => 20, 'used_floors' => [5, 6, 99]])
            ->assertSessionHasNoErrors();

        $this->assertSame(20, $this->stay->hotel->fresh()?->floors_count);
        $this->assertSame([5, 6], $this->stay->fresh()?->used_floors, 'Bina dışındaki kat atılır');

        Room::factory()->create(['tour_hotel_id' => $this->stay->id, 'floor' => '6']);
        $this->actingAs($this->staff)->put(route('stays.floors', $this->stay), ['floors_count' => 20, 'used_floors' => [5]])
            ->assertSessionHasErrors('used_floors');

        $this->actingAs($this->staff)->get(route('stays.room-plan', $this->stay))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stay.floors_count', 20)
                ->where('stay.used_floors', [5, 6])
                ->where('rooms.0.near_elevator', false));
    }

    public function test_rooms_can_be_marked_near_the_elevator(): void
    {
        $this->actingAs($this->staff)->post(route('stays.rooms.store', $this->stay), [
            'start_no' => '501', 'count' => 4, 'floor' => '5', 'capacity' => 4, 'kind' => 'erkek', 'near_elevator' => 2,
        ])->assertSessionHasNoErrors();

        $this->assertSame(['501', '502'], Room::query()->where('near_elevator', true)->orderBy('room_no')->pluck('room_no')->all());

        $room = Room::query()->where('room_no', '504')->sole();
        $this->actingAs($this->staff)->put(route('rooms.update', $room), [
            'room_no' => '504', 'floor' => '5', 'capacity' => 4, 'kind' => 'erkek', 'near_elevator' => true,
        ])->assertSessionHasNoErrors();
        $this->assertTrue($room->fresh()?->near_elevator);
    }

    public function test_mobility_passenger_gets_elevator_room_and_warning_when_far(): void
    {
        $near = Room::factory()->create(['tour_hotel_id' => $this->stay->id, 'room_no' => '501', 'capacity' => 2, 'kind' => RoomKind::Male, 'near_elevator' => true]);
        $far = Room::factory()->create(['tour_hotel_id' => $this->stay->id, 'room_no' => '520', 'capacity' => 2, 'kind' => RoomKind::Male]);

        $walker = $this->registration('Hasan', ['Yürüme güçlüğü']);
        $other = $this->registration('Ali');

        // Otomatik dağıtma: hareket güçlüğü olan asansöre yakın odaya, diğeri uzak odaya.
        $plan = app(CurrentTenant::class)->run($this->tenant, fn () => app(AutoAssignRooms::class)->plan($this->stay));
        $rooms = collect($plan['placements'])->mapWithKeys(fn (array $p) => [$p['registration']->id => $p['room']->room_no]);
        $this->assertSame('501', $rooms[$walker->id]);
        $this->assertSame('520', $rooms[$other->id]);

        // Elle uzak odaya konursa uyarı.
        (new RoomAssignment)->forceFill(['tenant_id' => $this->tenant->id, 'tour_hotel_id' => $this->stay->id, 'room_id' => $far->id, 'registration_id' => $walker->id])->save();
        $this->actingAs($this->staff)->get(route('stays.room-plan', $this->stay))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rooms.1.room_no', '520')
                ->where('rooms.1.occupants.0.needs', ['Yürüme güçlüğü'])
                ->where('rooms.1.occupants.0.warnings', fn ($w) => collect($w)->contains('Hareket güçlüğü var; asansöre uzak oda')));

        $this->assertNotNull($near);
    }

    public function test_floor_plan_and_needs_reports(): void
    {
        $room = Room::factory()->create(['tour_hotel_id' => $this->stay->id, 'room_no' => '501', 'floor' => '5', 'near_elevator' => true]);
        $walker = $this->registration('Hasan', ['Tekerlekli sandalye'], note: 'Kendi sandalyesi var');
        (new RoomAssignment)->forceFill(['tenant_id' => $this->tenant->id, 'tour_hotel_id' => $this->stay->id, 'room_id' => $room->id, 'registration_id' => $walker->id])->save();

        $plan = $this->sheetText($this->actingAs($this->staff)->get(route('reports.stays.floor-plan', $this->stay))->assertOk());
        $this->assertStringContainsString('Kat Planı', $plan);
        $this->assertStringContainsString('Hasan', $plan);
        $this->assertStringNotContainsString('sandalye', $plan, 'Kat planında sağlık bilgisi yok');

        $needs = $this->sheetText($this->actingAs($this->staff)->get(route('reports.stays.needs', $this->stay)));
        $this->assertStringContainsString('Tekerlekli sandalye', $needs);
        $this->assertStringContainsString('Kendi sandalyesi var', $needs);

        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();
        $this->actingAs($guide)->get(route('reports.stays.needs', $this->stay))->assertForbidden();
    }

    public function test_other_agency_cannot_define_floors(): void
    {
        $foreign = TourHotel::factory()->create();

        $this->actingAs($this->staff)->put(route('stays.floors', $foreign), ['floors_count' => 5, 'used_floors' => [1]])->assertNotFound();
        $this->actingAs($this->staff)->get(route('reports.stays.floor-plan', $foreign))->assertNotFound();
    }

    /**
     * @param  list<string>  $needs
     */
    private function registration(string $name, array $needs = [], ?string $note = null): Registration
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => $name, 'gender' => Gender::Male]);

        if ($needs !== []) {
            $person->forceFill(['health_consent_at' => now()])->save();
            (new PersonNeed)->forceFill([
                'tenant_id' => $this->tenant->id,
                'person_id' => $person->id,
                'items' => array_map(fn (string $type) => [
                    'type_id' => NeedType::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', $type)->value('id'),
                    'note' => $note,
                ], $needs),
            ])->save();
        }

        return Registration::factory()->create(['tour_id' => $this->stay->tour_id, 'group_id' => $this->group->id, 'person_id' => $person->id]);
    }
}
