<?php

namespace Tests\Feature\Buses;

use App\Actions\Persons\AddPersonRelation;
use App\Enums\Feature;
use App\Enums\Gender;
use App\Enums\RegistrationStatus;
use App\Enums\Relation;
use App\Enums\RoomType;
use App\Enums\UserRole;
use App\Models\Bus;
use App\Models\Group;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\SeatAssignment;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use App\Models\VehicleType;
use App\Support\Buses\BusLayout;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Faz 2 / 3. adım: araç tipleri, otobüsler, koltuk planı, otomatik dağıtma ve otobüs listeleri.
 */
class BusPlanTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Tour $tour;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::BusPlanning, Feature::BasicReports])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $this->staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
        $this->tour = Tour::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->group = Group::factory()->create(['tour_id' => $this->tour->id, 'name' => 'A Grubu']);
    }

    // --- Koltuk düzeni ---

    public function test_layout_numbers_seats_and_skips_the_door(): void
    {
        $layout = new BusLayout(2, 2, rows: 11, backRow: 5, doorRow: 6);

        $this->assertSame(47, $layout->seatCount()); // 11×4 − 2 (kapı) + 5 (arka sıra)
        $this->assertSame([1, 2, 'aisle', 3, 4], $layout->grid()[0]);
        $this->assertSame([21, 22, 'aisle', 'door', 'door'], $layout->grid()[5]);
        $this->assertSame([43, 44, 45, 46, 47], $layout->grid()[11]);
        $this->assertSame([2], $layout->neighbours(1));
        $this->assertSame([3], $layout->neighbours(4));
        $this->assertSame([44, 46], $layout->neighbours(45));
        $this->assertSame('2+2 · 47 koltuk', $layout->label());
    }

    // --- Araç tipleri ---

    public function test_vehicle_types_can_be_managed_and_are_isolated(): void
    {
        $this->actingAs($this->staff)->post(route('vehicle-types.store'), [
            'name' => 'VIP 2+1', 'left_seats' => 2, 'right_seats' => 1, 'rows' => 10, 'back_row_seats' => 0,
        ])->assertSessionHasNoErrors();

        $type = VehicleType::sole();
        $this->assertSame($this->tenant->id, $type->tenant_id);
        $this->assertSame(30, $type->layout()->seatCount());

        $this->actingAs($this->staff)->get(route('vehicle-types.index'))
            ->assertInertia(fn (Assert $page) => $page->component('vehicle-types/Index')->where('types.0.label', '2+1 · 30 koltuk'));

        $foreign = VehicleType::factory()->create();
        $this->actingAs($this->staff)->put(route('vehicle-types.update', $foreign), ['name' => 'X', 'left_seats' => 2, 'right_seats' => 2, 'rows' => 5])->assertNotFound();
        $this->actingAs($this->staff)->delete(route('vehicle-types.destroy', $foreign))->assertNotFound();

        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();
        $this->actingAs($guide)->get(route('vehicle-types.index'))->assertForbidden();
    }

    // --- Otobüsler ---

    public function test_a_bus_copies_its_layout_and_keeps_it_when_the_type_changes(): void
    {
        $type = VehicleType::factory()->create(['tenant_id' => $this->tenant->id, 'rows' => 11, 'back_row_seats' => 5, 'door_row' => 6]);

        $this->actingAs($this->staff)->post(route('tours.buses.store', $this->tour), [
            'name' => '1. Otobüs', 'vehicle_type_id' => $type->id, 'reserved_seats' => '1, 2', 'group_ids' => [$this->group->id],
        ])->assertSessionHasNoErrors();

        $bus = Bus::sole();
        $this->assertSame([1, 2], $bus->reserved());
        $this->assertSame([$this->group->id], $bus->groups()->pluck('groups.id')->all());

        $type->update(['rows' => 5]);
        $this->assertSame(47, $bus->fresh()?->layout()->seatCount(), 'Araç tipi değişse de otobüsün düzeni korunur.');

        $this->actingAs($this->staff)->get(route('tours.show', $this->tour))
            ->assertInertia(fn (Assert $page) => $page->where('buses.0.seats', 45)->has('options.vehicleTypes', 1));
    }

    public function test_bus_changes_that_break_seats_are_blocked(): void
    {
        $bus = $this->bus();
        $small = VehicleType::factory()->create(['tenant_id' => $this->tenant->id, 'left_seats' => 1, 'right_seats' => 1, 'rows' => 2, 'back_row_seats' => 0, 'door_row' => null]);
        $this->seat($bus, $this->registration(Gender::Male), 10);

        $this->updateBus($bus, ['reserved_seats' => '99'])->assertSessionHasErrors('reserved_seats');
        $this->updateBus($bus, ['reserved_seats' => '10'])->assertSessionHasErrors('reserved_seats');
        $this->updateBus($bus, ['vehicle_type_id' => $small->id])->assertSessionHasErrors('vehicle_type_id');
        $this->updateBus($bus, ['reserved_seats' => '1', 'plate' => '34 ABC 34'])->assertSessionHasNoErrors();
        $this->assertSame('34 ABC 34', $bus->fresh()?->plate);
    }

    // --- Koltuk kuralları ---

    public function test_reserved_taken_and_missing_seats_are_blocked(): void
    {
        $bus = $this->bus(reserved: [1]);
        $first = $this->registration(Gender::Male);
        $second = $this->registration(Gender::Male);

        $this->seat($bus, $first, 1)->assertSessionHasErrors('seat');
        $this->seat($bus, $first, 13)->assertSessionHasErrors('seat');
        $this->seat($bus, $first, 2)->assertSessionHasNoErrors();
        $this->seat($bus, $second, 2)->assertSessionHasErrors('seat');
    }

    public function test_seating_again_moves_the_passenger_even_to_another_bus(): void
    {
        [$a, $b] = [$this->bus(), $this->bus()];
        $registration = $this->registration(Gender::Male);

        $this->seat($a, $registration, 3);
        $this->seat($a, $registration, 5)->assertSessionHasNoErrors();
        $this->seat($b, $registration, 7)->assertSessionHasNoErrors();

        $seat = SeatAssignment::sole();
        $this->assertSame([$b->id, 7], [$seat->bus_id, $seat->seat_no]);
    }

    public function test_cancelling_a_registration_frees_its_seat(): void
    {
        $registration = $this->registration(Gender::Male);
        $this->seat($this->bus(), $registration, 3);

        $this->actingAs($this->staff)->put(route('registrations.update', $registration), [
            'status' => RegistrationStatus::Cancelled->value, 'price' => 1500, 'currency' => 'USD', 'room_type' => RoomType::Quad->value,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, SeatAssignment::count());
    }

    public function test_unrelated_opposite_gender_neighbours_only_get_a_warning(): void
    {
        $bus = $this->bus();
        $man = $this->registration(Gender::Male);
        $woman = $this->registration(Gender::Female);
        $wife = $this->registration(Gender::Female);
        $husband = $this->registration(Gender::Male);
        $this->relate($husband->person, $wife->person, Relation::Spouse);

        $this->seat($bus, $man, 1)->assertSessionHasNoErrors();
        $this->seat($bus, $woman, 2)->assertSessionHasNoErrors();
        $this->seat($bus, $husband, 3);
        $this->seat($bus, $wife, 4);

        $this->actingAs($this->staff)->get(route('buses.seat-plan', $bus))
            ->assertInertia(fn (Assert $page) => $page
                ->component('buses/Seats')
                ->has('seats.1.warnings', 1)
                ->has('seats.2.warnings', 1)
                ->has('seats.3.warnings', 0)
                ->has('seats.4.warnings', 0)
                // Tasarımdaki çizim: düzen sayıları ve üstte turun araçları.
                ->where('bus.layout.left', $bus->layout()->left)
                ->where('bus.layout.rows', $bus->layout()->rows)
                ->has('buses', 1)
                ->where('buses.0.id', $bus->id));
    }

    // --- Otomatik dağıtma ---

    public function test_auto_assign_seats_families_together_and_elderly_in_front(): void
    {
        $bus = $this->bus(reserved: [1, 2]);
        $young = collect(range(1, 3))->map(fn () => $this->registration(Gender::Male, age: 30));
        $elder = $this->registration(Gender::Female, age: 75);
        $husband = $this->registration(Gender::Male, age: 40);
        $wife = $this->registration(Gender::Female, age: 38);
        $this->relate($husband->person, $wife->person, Relation::Spouse);

        $this->actingAs($this->staff)->getJson(route('buses.auto-assign-preview', $bus))
            ->assertOk()->assertJsonPath('placed', 6)->assertJsonCount(0, 'unplaced');
        $this->assertSame(0, SeatAssignment::count());

        $this->actingAs($this->staff)->post(route('buses.auto-assign', $bus))->assertSessionHasNoErrors();

        $seatOf = fn (Registration $r) => SeatAssignment::where('registration_id', $r->id)->sole()->seat_no;

        // Yaşlı yolcu ilk boş koltukta (1–2 rehberin)
        $this->assertSame(3, $seatOf($elder));
        // Karı-koca aynı sıranın aynı tarafında yan yana
        $this->assertContains($bus->layout()->neighbours($seatOf($husband)), [[$seatOf($wife)]]);
        $this->assertSame(6, SeatAssignment::count());
        $young->each(fn (Registration $r) => $this->assertNotContains($seatOf($r), [1, 2]));
    }

    public function test_large_families_sit_in_pairs_near_the_front_not_in_the_back_row(): void
    {
        $bus = $this->bus();
        $bus->update(['back_row_seats' => 5]); // 3 sıra 2+2 (1–12) + arka sıra 13–17
        $mother = $this->registration(Gender::Female, age: 50);
        $daughter = $this->registration(Gender::Female, age: 25);
        $sonInLaw = $this->registration(Gender::Male, age: 28);
        $this->relate($daughter->person, $mother->person, Relation::Mother);
        $this->relate($daughter->person, $sonInLaw->person, Relation::Spouse);

        $this->actingAs($this->staff)->post(route('buses.auto-assign', $bus))->assertSessionHasNoErrors();

        $seats = SeatAssignment::query()->pluck('seat_no')->sort()->values()->all();
        $this->assertSame([1, 2, 3], $seats, 'Aile ön sıraya, ikişer ve yan yana (arka sıraya değil)');
    }

    public function test_auto_assign_reports_passengers_without_seats(): void
    {
        $bus = $this->bus(rows: 1); // 4 koltuk
        collect(range(1, 5))->each(fn () => $this->registration(Gender::Male));

        $this->actingAs($this->staff)->getJson(route('buses.auto-assign-preview', $bus))
            ->assertJsonPath('placed', 4)->assertJsonCount(1, 'unplaced');
    }

    // --- Yetki, izolasyon, raporlar ---

    public function test_guide_sees_only_own_group_and_cannot_change_seats(): void
    {
        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();
        $this->group->update(['guide_user_id' => $guide->id]);
        $other = Group::factory()->create(['tour_id' => $this->tour->id, 'name' => 'B Grubu']);
        $bus = $this->bus();
        $bus->groups()->sync([$this->group->id, $other->id]);
        $mine = $this->registration(Gender::Male);
        $theirs = $this->registration(Gender::Male, group: $other);
        $this->seat($bus, $mine, 3);
        $this->seat($bus, $theirs, 4);

        $this->actingAs($guide)->get(route('buses.seat-plan', $bus))
            ->assertInertia(fn (Assert $page) => $page
                ->where('seats.4.full_name', 'Başka grup')
                ->where('can.update', false)
                ->has('others', 0));

        $this->actingAs($guide)->post(route('buses.seats.store', $bus), ['registration_id' => $mine->id, 'seat_no' => 5])->assertForbidden();
        $this->actingAs($guide)->get(route('reports.buses.passengers', $bus))->assertForbidden();

        $notMine = $this->bus();
        $notMine->groups()->sync([$other->id]);
        $this->actingAs($guide)->get(route('buses.seat-plan', $notMine))->assertNotFound();
    }

    public function test_other_agencies_buses_and_seats_return_404(): void
    {
        $foreignBus = Bus::factory()->create();
        $foreignRegistration = Registration::factory()->create(['tour_id' => $foreignBus->tour_id]);
        $foreignSeat = SeatAssignment::query()->forceCreate([
            'tenant_id' => $foreignBus->tenant_id, 'tour_id' => $foreignBus->tour_id,
            'bus_id' => $foreignBus->id, 'registration_id' => $foreignRegistration->id, 'seat_no' => 1,
        ]);

        $this->actingAs($this->staff)->get(route('buses.seat-plan', $foreignBus))->assertNotFound();
        $this->actingAs($this->staff)->delete(route('buses.destroy', $foreignBus))->assertNotFound();
        $this->actingAs($this->staff)->delete(route('seat-assignments.destroy', $foreignSeat))->assertNotFound();
        $this->actingAs($this->staff)->get(route('reports.buses.seat-chart', $foreignBus))->assertNotFound();
        $this->seat($this->bus(), $foreignRegistration, 3)->assertNotFound();
    }

    public function test_bus_reports_download(): void
    {
        $bus = $this->bus(reserved: [1]);
        $this->seat($bus, $this->registration(Gender::Male), 3);

        foreach (['xlsx', 'pdf'] as $format) {
            $this->actingAs($this->staff)->get(route('reports.buses.passengers', [$bus, 'format' => $format]))->assertOk();
        }
        $this->actingAs($this->staff)->get(route('reports.buses.seat-chart', $bus))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->assertDatabaseHas('audit_logs', ['action' => 'export']);
    }

    public function test_bus_screens_require_the_bus_planning_feature(): void
    {
        $this->tenant->update(['plan_id' => Plan::factory()->withFeatures([Feature::Passengers])->create()->id]);

        $this->actingAs($this->staff)->get(route('vehicle-types.index'))->assertForbidden();
        $this->actingAs($this->staff)->get(route('tours.show', $this->tour))
            ->assertInertia(fn (Assert $page) => $page->where('buses', null));
    }

    // --- Yardımcılar ---

    /**
     * @param  list<int>  $reserved
     */
    private function bus(array $reserved = [], int $rows = 3): Bus
    {
        $bus = Bus::factory()->create(['tour_id' => $this->tour->id, 'rows' => $rows, 'reserved_seats' => $reserved]);
        $bus->groups()->sync([$this->group->id]);

        return $bus;
    }

    private function registration(Gender $gender, ?int $age = null, ?Group $group = null): Registration
    {
        $person = Person::factory()->create(array_filter([
            'tenant_id' => $this->tenant->id,
            'gender' => $gender,
            'birth_date' => $age ? now()->subYears($age)->subMonth() : null,
        ]));

        return Registration::factory()->create([
            'tour_id' => $this->tour->id,
            'group_id' => ($group ?? $this->group)->id,
            'person_id' => $person->id,
        ]);
    }

    private function relate(Person $person, Person $related, Relation $relation): void
    {
        app(CurrentTenant::class)->run($this->tenant, fn () => app(AddPersonRelation::class)->handle($person, $related, $relation));
    }

    private function seat(Bus $bus, Registration $registration, int $seatNo): TestResponse
    {
        return $this->actingAs($this->staff)->post(route('buses.seats.store', $bus), [
            'registration_id' => $registration->id, 'seat_no' => $seatNo,
        ]);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function updateBus(Bus $bus, array $changes): TestResponse
    {
        $type = $bus->vehicle_type_id ?? VehicleType::factory()->create(['tenant_id' => $this->tenant->id, 'left_seats' => 2, 'right_seats' => 2, 'rows' => 3, 'back_row_seats' => 0, 'door_row' => null])->id;
        $bus->update(['vehicle_type_id' => $type]);

        return $this->actingAs($this->staff)->put(route('buses.update', $bus), [
            'name' => $bus->name, 'vehicle_type_id' => $type, 'group_ids' => [$this->group->id], ...$changes,
        ]);
    }
}
