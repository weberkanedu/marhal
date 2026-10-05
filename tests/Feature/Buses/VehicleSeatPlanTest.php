<?php

namespace Tests\Feature\Buses;

use App\Enums\Feature;
use App\Enums\UserRole;
use App\Enums\VehicleBody;
use App\Models\Bus;
use App\Models\Group;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\SeatAssignment;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use App\Models\VehicleType;
use App\Support\Buses\BusLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ReadsSpreadsheets;
use Tests\TestCase;

/**
 * Tasarım yenileme 3a: araç gövdesi, şoför yanı koltuk, ön bölge, sürükle-bırak (yer değiştirme),
 * "Temizle" ve şoför listesi.
 */
class VehicleSeatPlanTest extends TestCase
{
    use ReadsSpreadsheets, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Tour $tour;

    private Bus $bus;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::BusPlanning, Feature::BasicReports])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $this->staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
        $this->tour = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Kasım Umresi']);
        $group = Group::factory()->create(['tour_id' => $this->tour->id]);
        $this->bus = Bus::factory()->create(['tour_id' => $this->tour->id, 'name' => '1. Araç', 'plate' => '34 ABC 12', 'driver_name' => 'Hasan Usta']);
        $this->bus->groups()->attach($group);
    }

    public function test_front_seats_are_numbered_first_next_to_the_driver(): void
    {
        // Minibüs 2+1, şoför yanında 2 koltuk: ön sıra [şoför, 1, koridor, 2]
        $layout = new BusLayout(2, 1, rows: 3, frontSeats: 2);

        $this->assertSame([BusLayout::DRIVER, 1, BusLayout::AISLE, 2], $layout->grid()[0]);
        $this->assertSame([3, 4, BusLayout::AISLE, 5], $layout->grid()[1]);
        $this->assertSame(11, $layout->seatCount());
        $this->assertSame([], $layout->neighbours(1), 'Şoför yanındaki tek koltuğun yan yolcusu yok');

        // Ön bölge: şoför yanı + ilk 3 yolcu sırası.
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11], $layout->frontZone());
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12], (new BusLayout(2, 2, rows: 5))->frontZone());

        // Eski otobüsler (şoför yanı yok) aynı numaraları korur.
        $this->assertSame([1, 2, BusLayout::AISLE, 3, 4], (new BusLayout(2, 2, rows: 2))->grid()[0]);
    }

    public function test_vehicle_type_keeps_body_and_front_seats_and_bus_copies_them(): void
    {
        $this->actingAs($this->staff)->post(route('vehicle-types.store'), [
            'name' => 'Sprinter', 'body' => 'minibus', 'left_seats' => 1, 'right_seats' => 1, 'rows' => 5,
            'front_seats' => 3, // en çok 1 olabilir (1+1'de şoförün yanında tek yer)
        ])->assertSessionHasNoErrors();

        $type = VehicleType::query()->where('name', 'Sprinter')->sole();
        $this->assertSame([VehicleBody::Mini, 1, 11], [$type->body, $type->front_seats, $type->layout()->seatCount()]);

        $this->actingAs($this->staff)->post(route('tours.buses.store', $this->tour), [
            'name' => 'Van 1', 'vehicle_type_id' => $type->id,
        ])->assertSessionHasNoErrors();

        $van = Bus::query()->where('name', 'Van 1')->sole();
        $this->assertSame([VehicleBody::Mini, 1], [$van->body, $van->front_seats]);

        // Araç tipi sonradan değişirse araç kendi düzenini korur.
        $type->update(['front_seats' => 0, 'body' => VehicleBody::Bus]);
        $this->assertSame(1, $van->fresh()?->front_seats);
    }

    public function test_seat_page_shares_body_front_zone_and_family_units(): void
    {
        Registration::factory()->count(2)->create(['tour_id' => $this->tour->id, 'group_id' => $this->bus->groups()->value('groups.id')]);

        $this->actingAs($this->staff)->get(route('buses.seat-plan', $this->bus))
            ->assertInertia(fn (Assert $page) => $page
                ->where('bus.body', 'otobus')
                ->where('bus.front_zone', range(1, 12))
                ->has('units', 2)
                ->where('units.0.label', null)
                ->has('units.0.ids', 1));
    }

    public function test_dropping_a_seated_passenger_on_an_occupied_seat_swaps_them(): void
    {
        [$ali, $veli] = $this->seated([1, 2]);

        // Yer değiştirme istenmezse dolu koltuk engellenir.
        $this->actingAs($this->staff)->post(route('buses.seats.store', $this->bus), ['registration_id' => $ali->id, 'seat_no' => 2])
            ->assertSessionHasErrors('seat');

        $this->actingAs($this->staff)->post(route('buses.seats.store', $this->bus), ['registration_id' => $ali->id, 'seat_no' => 2, 'swap' => true])
            ->assertSessionHasNoErrors();

        $this->assertSame([2, 1], [
            SeatAssignment::query()->where('registration_id', $ali->id)->value('seat_no'),
            SeatAssignment::query()->where('registration_id', $veli->id)->value('seat_no'),
        ]);
    }

    public function test_clear_removes_everyone_but_only_in_own_agency(): void
    {
        $this->seated([1, 2, 3]);

        $foreign = Bus::factory()->create();
        $this->actingAs($this->staff)->delete(route('buses.seats.clear', $foreign))->assertNotFound();

        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();
        $this->actingAs($guide)->delete(route('buses.seats.clear', $this->bus))->assertForbidden();

        $this->actingAs($this->staff)->delete(route('buses.seats.clear', $this->bus))->assertRedirect();
        $this->assertSame(0, $this->bus->seats()->count());
    }

    public function test_driver_list_report(): void
    {
        $text = $this->sheetText($this->actingAs($this->staff)->get(route('reports.tours.drivers', $this->tour))->assertOk());

        $this->assertStringContainsString('Kasım Umresi Şoför Listesi', $text);
        $this->assertStringContainsString('Hasan Usta', $text);
        $this->assertStringContainsString('34 ABC 12', $text);

        $this->actingAs($this->staff)->get(route('reports.tours.drivers', Tour::factory()->create()))->assertNotFound();
    }

    /**
     * @param  list<int>  $seats
     * @return list<Registration>
     */
    private function seated(array $seats): array
    {
        return array_map(function (int $seat): Registration {
            $registration = Registration::factory()->create(['tour_id' => $this->tour->id]);
            (new SeatAssignment)->forceFill([
                'tenant_id' => $this->tenant->id, 'tour_id' => $this->tour->id, 'bus_id' => $this->bus->id,
                'registration_id' => $registration->id, 'seat_no' => $seat,
            ])->save();

            return $registration;
        }, $seats);
    }
}
