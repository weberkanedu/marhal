<?php

namespace Tests\Feature\Flights;

use App\Enums\Feature;
use App\Enums\Gender;
use App\Enums\RegistrationStatus;
use App\Enums\RoomType;
use App\Enums\UserRole;
use App\Models\Flight;
use App\Models\FlightPassenger;
use App\Models\Group;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use App\Reports\Definitions\FlightManifest;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Faz 3 / 1. adım: uçuşlar, yolcu ekleme kuralları, PNR / bilet, havayolu listesi.
 */
class FlightTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Tour $tour;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::FlightLists, Feature::BasicReports])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $this->staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
        $this->tour = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'start_date' => '2026-11-01', 'end_date' => '2026-11-15']);
        $this->group = Group::factory()->create(['tour_id' => $this->tour->id, 'name' => 'A Grubu']);
    }

    public function test_a_flight_can_be_added_edited_and_deleted(): void
    {
        $this->actingAs($this->staff)->post(route('tours.flights.store', $this->tour), [
            'direction' => 'gidis', 'airline' => 'Türk Hava Yolları', 'flight_no' => 'tk 92',
            'departure_airport' => 'ist', 'arrival_airport' => 'jed',
            'departure_at' => '2026-11-01T10:00', 'arrival_at' => '2026-11-01T14:00', 'pnr' => 'abc123',
        ])->assertSessionHasNoErrors();

        $flight = Flight::sole();
        $this->assertSame(['TK 92', 'IST', 'JED', 'ABC123'], [$flight->flight_no, $flight->departure_airport, $flight->arrival_airport, $flight->pnr]);

        $this->actingAs($this->staff)->put(route('flights.update', $flight), [
            ...$this->flightData(), 'arrival_airport' => 'IST',
        ])->assertSessionHasErrors('arrival_airport'); // kalkışla aynı

        $this->actingAs($this->staff)->get(route('tours.show', $this->tour))
            ->assertInertia(fn (Assert $page) => $page->has('flights', 1)->where('flights.0.flight_no', 'TK 92'));

        $this->actingAs($this->staff)->delete(route('flights.destroy', $flight));
        $this->assertSame(0, Flight::count());
    }

    public function test_groups_are_added_in_bulk_and_overlapping_flights_are_blocked(): void
    {
        $outbound = Flight::factory()->create(['tour_id' => $this->tour->id, 'departure_at' => '2026-11-01 10:00', 'arrival_at' => '2026-11-01 14:00']);
        $clash = Flight::factory()->create(['tour_id' => $this->tour->id, 'departure_at' => '2026-11-01 12:00', 'arrival_at' => '2026-11-01 16:00']);
        [$a, $b] = [$this->registration(), $this->registration()];
        $this->registration(status: RegistrationStatus::Cancelled);

        $this->actingAs($this->staff)->post(route('flights.passengers.store', $outbound), ['group_ids' => [$this->group->id]])
            ->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $outbound->passengers()->pluck('registration_id')->all(), 'İptal edilen eklenmez');

        // Aynı saatlerde başka uçuş: eklenmez
        $this->actingAs($this->staff)->post(route('flights.passengers.store', $clash), ['registration_ids' => [$a->id]]);
        $this->assertSame(0, $clash->passengers()->count());

        // Saat değişince çakışma oluşacaksa kaydedilmez
        $later = Flight::factory()->create(['tour_id' => $this->tour->id, 'departure_at' => '2026-11-15 10:00', 'arrival_at' => '2026-11-15 14:00']);
        $later->passengers()->create(['registration_id' => $a->id]);
        $this->actingAs($this->staff)->put(route('flights.update', $later), $this->flightData())
            ->assertSessionHasErrors('departure_at');
    }

    public function test_pnr_and_ticket_can_be_set_and_cancelling_removes_the_passenger(): void
    {
        $flight = Flight::factory()->create(['tour_id' => $this->tour->id]);
        $registration = $this->registration();
        $passenger = $flight->passengers()->create(['registration_id' => $registration->id]);

        $this->actingAs($this->staff)->put(route('flight-passengers.update', $passenger), ['pnr' => 'xyz789', 'ticket_no' => '235-1234567890'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['XYZ789', '235-1234567890'], [$passenger->fresh()?->pnr, $passenger->fresh()?->ticket_no]);

        $this->actingAs($this->staff)->put(route('registrations.update', $registration), [
            'status' => RegistrationStatus::Cancelled->value, 'price' => 1500, 'currency' => 'USD', 'room_type' => RoomType::Quad->value,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, FlightPassenger::count());
    }

    public function test_titles_and_passport_warnings(): void
    {
        $flight = Flight::factory()->create(['tour_id' => $this->tour->id, 'pnr' => 'GRP111']);
        $man = $this->registration(Gender::Male, birth: '1980-05-05');
        $woman = $this->registration(Gender::Female, birth: '1985-05-05', expiry: '2027-01-01'); // dönüş + 6 aydan önce
        $child = $this->registration(Gender::Male, birth: '2020-01-01');
        $baby = $this->registration(Gender::Female, birth: '2026-01-01');
        foreach ([$man, $woman, $child, $baby] as $r) {
            $flight->passengers()->create(['registration_id' => $r->id]);
        }

        $this->actingAs($this->staff)->get(route('flights.show', $flight))
            ->assertInertia(fn (Assert $page) => $page
                ->component('flights/Show')
                ->has('passengers', 4)
                ->where('passengers', fn ($rows) => collect($rows)->pluck('title')->sort()->values()->all() === ['CHD', 'INF', 'MR', 'MRS'])
                ->where('passengers', fn ($rows) => collect($rows)->firstWhere('registration_id', $woman->id)['warnings'] === ['Pasaport dönüşten itibaren 6 aydan kısa geçerli']));

        $report = app(CurrentTenant::class)->run($this->tenant, fn () => app(FlightManifest::class)->build($flight, false));
        $this->assertCount(4, $report->rows);
        $this->assertSame('GRP111', $report->rows[0]['pnr'], 'Kişisel PNR yoksa grup PNR');
    }

    public function test_guide_sees_own_group_without_passport_data_and_cannot_edit(): void
    {
        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();
        $this->group->update(['guide_user_id' => $guide->id]);
        $other = Group::factory()->create(['tour_id' => $this->tour->id, 'name' => 'B Grubu']);
        $flight = Flight::factory()->create(['tour_id' => $this->tour->id]);
        $flight->passengers()->create(['registration_id' => $this->registration()->id]);
        $flight->passengers()->create(['registration_id' => $this->registration(group: $other)->id]);

        $this->actingAs($guide)->get(route('flights.show', $flight))
            ->assertInertia(fn (Assert $page) => $page
                ->has('passengers', 1)
                ->where('passengers.0.masked_passport_no', null)
                ->where('can.update', false));

        $this->actingAs($guide)->post(route('flights.passengers.store', $flight), ['group_ids' => [$this->group->id]])->assertForbidden();
        $this->actingAs($guide)->get(route('reports.flights.manifest', $flight))->assertForbidden();
    }

    public function test_other_agencies_flights_return_404_and_reports_download(): void
    {
        $foreign = Flight::factory()->create();
        $foreignRegistration = Registration::factory()->create(['tour_id' => $foreign->tour_id]);

        $this->actingAs($this->staff)->get(route('flights.show', $foreign))->assertNotFound();
        $this->actingAs($this->staff)->delete(route('flights.destroy', $foreign))->assertNotFound();
        $this->actingAs($this->staff)->get(route('reports.flights.manifest', $foreign))->assertNotFound();

        $flight = Flight::factory()->create(['tour_id' => $this->tour->id]);
        $this->actingAs($this->staff)->post(route('flights.passengers.store', $flight), ['registration_ids' => [$foreignRegistration->id]]);
        $this->assertSame(0, $flight->passengers()->count(), 'Başka acentenin yolcusu eklenemez');

        $flight->passengers()->create(['registration_id' => $this->registration()->id]);
        foreach (['xlsx', 'pdf'] as $format) {
            $this->actingAs($this->staff)->get(route('reports.flights.manifest', [$flight, 'format' => $format]))->assertOk();
        }
    }

    public function test_flight_screens_require_the_flight_lists_feature(): void
    {
        $this->tenant->update(['plan_id' => Plan::factory()->withFeatures([Feature::Passengers])->create()->id]);
        $flight = Flight::factory()->create(['tour_id' => $this->tour->id]);

        $this->actingAs($this->staff)->get(route('flights.show', $flight))->assertForbidden();
        $this->actingAs($this->staff)->get(route('tours.show', $this->tour))
            ->assertInertia(fn (Assert $page) => $page->where('flights', null));
    }

    /**
     * @return array<string, string>
     */
    private function flightData(): array
    {
        return [
            'direction' => 'gidis', 'airline' => 'THY', 'flight_no' => 'TK 92',
            'departure_airport' => 'IST', 'arrival_airport' => 'JED',
            'departure_at' => '2026-11-01T10:00', 'arrival_at' => '2026-11-01T14:00',
        ];
    }

    private function registration(
        Gender $gender = Gender::Male,
        ?string $birth = null,
        ?string $expiry = null,
        ?Group $group = null,
        RegistrationStatus $status = RegistrationStatus::Confirmed,
    ): Registration {
        $person = Person::factory()->create(array_filter([
            'tenant_id' => $this->tenant->id,
            'gender' => $gender,
            'birth_date' => $birth,
            'passport_expiry_date' => $expiry,
        ]));

        return Registration::factory()->create([
            'tour_id' => $this->tour->id,
            'group_id' => ($group ?? $this->group)->id,
            'person_id' => $person->id,
            'status' => $status,
        ]);
    }
}
