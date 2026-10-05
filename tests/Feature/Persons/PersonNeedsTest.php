<?php

namespace Tests\Feature\Persons;

use App\Actions\Tenants\CreateTenant;
use App\Enums\Feature;
use App\Enums\UserRole;
use App\Models\AircraftType;
use App\Models\Bus;
use App\Models\Flight;
use App\Models\FlightPassenger;
use App\Models\Group;
use App\Models\NeedType;
use App\Models\Person;
use App\Models\PersonNeed;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\SeatAssignment;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use App\Support\Needs\DefaultNeedTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ReadsSpreadsheets;
use Tests\TestCase;

/**
 * İhtiyaç profili (sağlık verisi): ayrı açık rıza, şifreli saklama, görünürlük ve yerleşim kuralları.
 */
class PersonNeedsTest extends TestCase
{
    use ReadsSpreadsheets, RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Person $person;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::BusPlanning, Feature::FlightLists, Feature::BasicReports])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        DefaultNeedTypes::seed($this->tenant);
        $this->staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
        $this->person = Person::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Hasan', 'last_name' => 'Kaya']);
    }

    public function test_needs_require_separate_consent_and_are_stored_encrypted(): void
    {
        $wheelchair = $this->type('Tekerlekli sandalye');

        $this->saveNeeds([['type_id' => $wheelchair->id, 'note' => 'Kendi sandalyesi var']])->assertSessionHasErrors('items');
        $this->assertSame(0, PersonNeed::count());

        $this->actingAs($this->staff)->put(route('persons.health-consent', $this->person), ['granted' => true])->assertSessionHasNoErrors();
        $this->assertNotNull($this->person->fresh()?->health_consent_at);
        $this->assertSame($this->staff->id, $this->person->fresh()?->health_consent_by);

        $this->saveNeeds([['type_id' => $wheelchair->id, 'note' => 'Kendi sandalyesi var']])->assertSessionHasNoErrors();

        // Veritabanında okunamaz; uygulamada çözülür.
        $raw = (string) DB::table('person_needs')->value('items');
        $this->assertStringNotContainsString('sandalye', $raw);
        $this->assertStringNotContainsString($wheelchair->id, $raw);
        $this->assertSame([['type_id' => $wheelchair->id, 'note' => 'Kendi sandalyesi var']], PersonNeed::sole()->items);

        // Erişim kaydına içerik yazılmaz.
        $log = (string) DB::table('audit_logs')->where('subject_type', PersonNeed::class)->value('changes');
        $this->assertNotSame('', $log, 'Değişiklik kaydı yazılır');
        $this->assertStringNotContainsString('sandalye', $log);
        $this->assertStringNotContainsString($wheelchair->id, $log);

        // Rıza geri alınınca ihtiyaçlar silinir.
        $this->actingAs($this->staff)->put(route('persons.health-consent', $this->person), ['granted' => false]);
        $this->assertSame(0, PersonNeed::count());
        $this->assertNull($this->person->fresh()?->health_consent_at);
    }

    public function test_person_page_and_list_show_needs_and_filter(): void
    {
        $this->withNeeds($this->person, ['Diyabet' => 'İnsülin kullanıyor']);
        Person::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->staff)->get(route('persons.show', $this->person))
            ->assertInertia(fn (Assert $page) => $page
                ->where('needs.items.0.note', 'İnsülin kullanıyor')
                ->has('needs.types', 11));

        $this->actingAs($this->staff)->get(route('persons.index', ['filtre' => 'ihtiyac']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('persons.data', 1)
                ->where('persons.data.0.needs', ['Diyabet'])
                ->where('stats.ihtiyac', 1));
    }

    public function test_guide_sees_only_need_names_on_tour_page(): void
    {
        $this->withNeeds($this->person, ['Yürüme güçlüğü' => 'Bastonla yürüyor']);
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id]);
        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();
        $group = Group::factory()->create(['tour_id' => $tour->id, 'guide_user_id' => $guide->id]);
        Registration::factory()->create(['tour_id' => $tour->id, 'group_id' => $group->id, 'person_id' => $this->person->id]);

        $this->actingAs($guide)->get(route('tours.show', $tour))
            ->assertInertia(fn (Assert $page) => $page->where('registrations.0.needs', ['Yürüme güçlüğü']))
            ->assertDontSee('Bastonla');

        // Rehber ihtiyacı değiştiremez, yolcu sayfasını göremez.
        $this->actingAs($guide)->put(route('persons.health-consent', $this->person), ['granted' => false])->assertForbidden();
    }

    public function test_other_agency_cannot_read_or_change_needs(): void
    {
        $foreign = Person::factory()->create();

        $this->actingAs($this->staff)->put(route('persons.health-consent', $foreign), ['granted' => true])->assertNotFound();
        $this->actingAs($this->staff)->put(route('persons.needs.update', $foreign), ['items' => []])->assertNotFound();

        // Başka acentenin türü seçilemez.
        $otherTenant = Tenant::factory()->create();
        DefaultNeedTypes::seed($otherTenant);
        $foreignType = NeedType::query()->withoutGlobalScopes()->where('tenant_id', $otherTenant->id)->firstOrFail();
        $this->actingAs($this->staff)->put(route('persons.health-consent', $this->person), ['granted' => true]);
        $this->saveNeeds([['type_id' => $foreignType->id]])->assertSessionHasErrors('items');
    }

    public function test_mobility_need_drives_bus_and_flight_warnings_and_assistance_list(): void
    {
        $this->withNeeds($this->person, ['Tekerlekli sandalye' => null, 'Diyet yemeği' => 'Tuzsuz']);
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id]);
        $registration = Registration::factory()->create(['tour_id' => $tour->id, 'person_id' => $this->person->id]);

        // Otobüs: 6. sıra ön bölgede değil → uyarı.
        $bus = Bus::factory()->create(['tour_id' => $tour->id, 'rows' => 8]);
        (new SeatAssignment)->forceFill(['tenant_id' => $this->tenant->id, 'tour_id' => $tour->id, 'bus_id' => $bus->id, 'registration_id' => $registration->id, 'seat_no' => 21])->save();
        $this->actingAs($this->staff)->get(route('buses.seat-plan', $bus))
            ->assertInertia(fn (Assert $page) => $page->where('seats.21.warnings.0', fn (string $w) => str_contains($w, 'ön bölge')));

        // Uçak: acil çıkış sırası → uyarı; özel yardım listesinde kod ve not.
        $flight = Flight::factory()->create(['tour_id' => $tour->id]);
        $type = tap((new AircraftType)->forceFill(['tenant_id' => $this->tenant->id, 'name' => 'A321', 'cabin' => '3-3', 'first_row' => 1, 'last_row' => 20, 'exit_rows' => [12]]))->save();
        $flight->update(['aircraft_type_id' => $type->id, ...$type->layout()->toAttributes()]);
        tap((new FlightPassenger)->forceFill(['tenant_id' => $this->tenant->id, 'flight_id' => $flight->id, 'registration_id' => $registration->id, 'seat_no' => '12A']))->save();

        $this->actingAs($this->staff)->get(route('flights.seat-plan', $flight))
            ->assertInertia(fn (Assert $page) => $page->where('passengers.0.warnings', fn ($w) => collect($w)->contains(fn (string $t) => str_contains($t, 'Hareket güçlüğü'))));

        $text = $this->sheetText($this->actingAs($this->staff)->get(route('reports.flights.assistance', $flight)));
        $this->assertStringContainsString('WCHS', $text);
        $this->assertStringContainsString('Tuzsuz', $text);
        $this->assertStringContainsString('KAYA / HASAN', $text);
    }

    public function test_new_agency_gets_default_need_types(): void
    {
        $plan = Plan::factory()->create();
        $result = app(CreateTenant::class)->handle([
            'name' => 'Yeni Turizm', 'plan_id' => $plan->id, 'status' => 'active', 'default_currency' => 'USD',
            'admin_name' => 'Yönetici', 'admin_email' => 'yeni@acente.test',
        ]);

        $this->assertSame(11, NeedType::query()->withoutGlobalScopes()->where('tenant_id', $result['tenant']->id)->count());
    }

    /**
     * @param  array<string, string|null>  $needs  tür adı → not
     */
    private function withNeeds(Person $person, array $needs): void
    {
        $person->forceFill(['health_consent_at' => now()])->save();
        (new PersonNeed)->forceFill([
            'tenant_id' => $this->tenant->id,
            'person_id' => $person->id,
            'items' => collect($needs)->map(fn (?string $note, string $name) => ['type_id' => $this->type($name)->id, 'note' => $note])->values()->all(),
        ])->save();
    }

    private function type(string $name): NeedType
    {
        return NeedType::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', $name)->firstOrFail();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function saveNeeds(array $items): TestResponse
    {
        return $this->actingAs($this->staff)->put(route('persons.needs.update', $this->person), ['items' => $items]);
    }
}
