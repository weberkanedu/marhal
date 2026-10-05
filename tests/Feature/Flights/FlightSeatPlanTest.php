<?php

namespace Tests\Feature\Flights;

use App\Enums\Feature;
use App\Enums\UserRole;
use App\Models\AircraftType;
use App\Models\Flight;
use App\Models\FlightPassenger;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use App\Support\Flights\AircraftLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ReadsSpreadsheets;
use Tests\TestCase;

/**
 * Tasarım yenileme 3b: uçak tipleri, uçuş koltuk planı (havayoluna koltuk tercihi), acil çıkış uyarısı.
 */
class FlightSeatPlanTest extends TestCase
{
    use ReadsSpreadsheets, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Flight $flight;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::FlightLists, Feature::BasicReports])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $this->staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->flight = Flight::factory()->create(['tour_id' => $tour->id, 'flight_no' => 'TK 92', 'departure_at' => '2026-11-01 10:00']);
    }

    public function test_cabin_layout_letters_rows_and_neighbours(): void
    {
        $a330 = new AircraftLayout('2-4-2', 10, 12, [10]);

        $this->assertSame([['A', 'C'], ['D', 'E', 'F', 'G'], ['H', 'K']], $a330->groups());
        $this->assertSame(24, $a330->seatCount());
        $this->assertTrue($a330->has('11E'));
        $this->assertFalse($a330->has('11B'));
        $this->assertSame(['11D', '11F'], $a330->neighbours('11E'));
        $this->assertSame(['11C'], $a330->neighbours('11A'));
        $this->assertTrue($a330->isExitRow(10));

        $this->assertSame([['A', 'B'], ['C', 'D', 'E']], (new AircraftLayout('2-3', 1, 2))->groups(), 'Tanımsız düzende harfler sırayla');
        $this->assertFalse(AircraftLayout::validCabin('3x3'));
    }

    public function test_choosing_a_preset_adds_it_to_the_agency_and_copies_the_cabin_to_the_flight(): void
    {
        $this->actingAs($this->staff)->put(route('flights.aircraft', $this->flight), ['preset' => 'a321neo'])
            ->assertSessionHasNoErrors();

        $type = AircraftType::sole();
        $flight = $this->flight->fresh();
        $this->assertSame(['Airbus A321neo', $type->id, '3-3', 38, [11, 12, 25]], [
            $type->name, $flight?->aircraft_type_id, $flight?->cabin, $flight?->last_row, $flight?->exit_rows,
        ]);

        // Tip sonradan değişse de uçuşun düzeni korunur.
        $type->update(['last_row' => 20]);
        $this->assertSame(38, $this->flight->fresh()?->last_row);
    }

    public function test_seats_can_be_assigned_swapped_blocked_and_cleared(): void
    {
        $this->withCabin('3-3', 1, 10);
        [$ali, $veli] = $this->passengers(2);

        $this->assign($ali, '1a')->assertSessionHasNoErrors();
        $this->assertSame('1A', $ali->fresh()?->seat_no);

        $this->assign($veli, '1A')->assertSessionHasErrors('seat');
        $this->assign($veli, '1B')->assertSessionHasNoErrors();
        $this->assign($ali, '1B', swap: true)->assertSessionHasNoErrors();
        $this->assertSame(['1B', '1A'], [$ali->fresh()?->seat_no, $veli->fresh()?->seat_no]);

        // Başkasına ait koltuk: işaretlenir, yolcu oturtulamaz; kendi yolcunun koltuğu işaretlenemez.
        $this->actingAs($this->staff)->post(route('flights.blocked-seats', $this->flight), ['seat' => '2C'])->assertSessionHasNoErrors();
        $this->assign($ali, '2C')->assertSessionHasErrors('seat');
        $this->actingAs($this->staff)->post(route('flights.blocked-seats', $this->flight), ['seat' => '1A'])->assertSessionHasErrors('seat');
        $this->assign($ali, '99Z')->assertSessionHasErrors('seat');

        $this->actingAs($this->staff)->delete(route('flight-passengers.seat.destroy', $veli))->assertRedirect();
        $this->assertNull($veli->fresh()?->seat_no);

        $this->actingAs($this->staff)->delete(route('flights.seats.clear', $this->flight))->assertRedirect();
        $this->assertSame(0, $this->flight->passengers()->whereNotNull('seat_no')->count());
    }

    public function test_exit_row_age_warning_and_seat_preference_list(): void
    {
        $this->withCabin('3-3', 1, 15, [12]);
        [$elder] = $this->passengers(1, ['first_name' => 'Hasan', 'last_name' => 'Yaşlı', 'birth_date' => '1950-01-01']);
        $this->assign($elder, '12A');

        $this->actingAs($this->staff)->get(route('flights.seat-plan', $this->flight))
            ->assertInertia(fn (Assert $page) => $page
                ->component('flights/Seats')
                ->where('cabin.exit_rows', [12])
                ->where('passengers.0.seat_no', '12A')
                ->where('passengers.0.warnings.0', fn (string $w) => str_contains($w, 'Acil çıkış')));

        $text = $this->sheetText($this->actingAs($this->staff)->get(route('reports.flights.seats', $this->flight)));
        $this->assertStringContainsString('Koltuk Tercih Listesi', $text);
        $this->assertStringContainsString('12A', $text);
        $this->assertStringContainsString('YAŞLI / HASAN', $text);
    }

    public function test_changing_aircraft_is_blocked_when_seated_passengers_would_not_fit(): void
    {
        $this->withCabin('3-3', 1, 30);
        [$ali] = $this->passengers(1);
        $this->assign($ali, '30F');

        $small = $this->type('Küçük', '2-2', 1, 10);
        $this->actingAs($this->staff)->put(route('flights.aircraft', $this->flight), ['aircraft_type_id' => $small->id])
            ->assertSessionHasErrors('aircraft_type_id');
    }

    public function test_other_agencies_and_guides_cannot_change_seats(): void
    {
        $foreign = Flight::factory()->create();
        $this->actingAs($this->staff)->get(route('flights.seat-plan', $foreign))->assertNotFound();
        $this->actingAs($this->staff)->delete(route('flights.seats.clear', $foreign))->assertNotFound();

        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();
        $this->actingAs($guide)->put(route('flights.aircraft', $this->flight), ['preset' => 'a321neo'])->assertForbidden();
        $this->actingAs($guide)->get(route('aircraft-types.index'))->assertForbidden();
    }

    public function test_agency_manages_its_aircraft_types(): void
    {
        $this->actingAs($this->staff)->post(route('aircraft-types.store'), [
            'name' => 'THY A321', 'cabin' => ' 3 - 3 ', 'first_row' => 1, 'last_row' => 30, 'exit_rows' => '11, 12 ,99',
        ])->assertSessionHasNoErrors();

        $type = AircraftType::sole();
        $this->assertSame(['3-3', [11, 12]], [$type->cabin, $type->exit_rows], 'Aralık dışındaki acil çıkış atılır');

        $this->actingAs($this->staff)->post(route('aircraft-types.store'), ['name' => 'X', 'cabin' => '3x3', 'first_row' => 1, 'last_row' => 5])
            ->assertSessionHasErrors('cabin');

        $this->actingAs($this->staff)->get(route('aircraft-types.index'))
            ->assertInertia(fn (Assert $page) => $page->has('types', 1)->has('presets', 5));
    }

    /**
     * @param  list<int>  $exits
     */
    private function withCabin(string $cabin, int $first, int $last, array $exits = []): void
    {
        $type = $this->type("Uçak {$cabin}", $cabin, $first, $last, $exits);
        $this->actingAs($this->staff)->put(route('flights.aircraft', $this->flight), ['aircraft_type_id' => $type->id])->assertSessionHasNoErrors();
        $this->flight->refresh();
    }

    /**
     * @param  list<int>  $exits
     */
    private function type(string $name, string $cabin, int $first, int $last, array $exits = []): AircraftType
    {
        return tap((new AircraftType)->forceFill([
            'tenant_id' => $this->tenant->id, 'name' => $name, 'cabin' => $cabin,
            'first_row' => $first, 'last_row' => $last, 'exit_rows' => $exits,
        ]))->save();
    }

    /**
     * @param  array<string, mixed>  $person
     * @return list<FlightPassenger>
     */
    private function passengers(int $count, array $person = []): array
    {
        return array_map(function () use ($person): FlightPassenger {
            $registration = Registration::factory()->create([
                'tour_id' => $this->flight->tour_id,
                'person_id' => Person::factory()->create(['tenant_id' => $this->tenant->id, ...$person])->id,
            ]);

            return tap((new FlightPassenger)->forceFill([
                'tenant_id' => $this->tenant->id, 'flight_id' => $this->flight->id, 'registration_id' => $registration->id,
            ]))->save();
        }, range(1, $count));
    }

    private function assign(FlightPassenger $passenger, string $seat, bool $swap = false): TestResponse
    {
        return $this->actingAs($this->staff)->post(route('flights.seats.store', $this->flight), [
            'passenger_id' => $passenger->id, 'seat' => $seat, 'swap' => $swap,
        ]);
    }
}
