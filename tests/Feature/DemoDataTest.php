<?php

namespace Tests\Feature;

use App\Actions\Persons\AddPersonRelation;
use App\Enums\Gender;
use App\Enums\Relation;
use App\Models\AircraftType;
use App\Models\Bus;
use App\Models\Flight;
use App\Models\FlightPassenger;
use App\Models\Hotel;
use App\Models\Person;
use App\Models\PersonRelation;
use App\Models\RoomAssignment;
use App\Models\SeatAssignment;
use App\Models\Tenant;
use App\Models\TourHotel;
use App\Models\VehicleType;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\Demo\FixParentAgesDemo;
use Database\Seeders\DemoSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Staging'in demo verisi: yeni özellik paketleri bir kez yüklenir, kullanıcının silmeleri korunur.
 */
class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_includes_room_plan_data(): void
    {
        $this->seed([PlanSeeder::class, DemoSeeder::class]);

        $this->assertSame(4, Hotel::count());
        $this->assertSame(3, TourHotel::count(), 'A ve B grupları Mekke\'de ayrı otellerde, Medine\'de aynı otelde');
        $this->assertGreaterThan(0, PersonRelation::count());
        $this->assertSame(12, RoomAssignment::count(), 'Mekke otelleri dağıtılmış (12 yolcu), Medine boş');
    }

    public function test_demo_seed_includes_bus_plan_data(): void
    {
        $this->seed([PlanSeeder::class, DemoSeeder::class]);

        $this->assertSame(6, VehicleType::count(), '4 otobüs + minibüs ve van (tasarim-3-arac-ucak)');
        $this->assertSame(2, Bus::count(), 'Grup başına bir otobüs');
        $this->assertSame(6, SeatAssignment::count(), '1. otobüs (A Grubu) dağıtılmış, 2. otobüs boş');
        $this->assertSame(2, AircraftType::count(), 'Uçuşlara A321neo ve A330 eklenir');
        $this->assertSame(0, Flight::query()->whereNull('cabin')->count());
    }

    public function test_demo_seed_includes_flights(): void
    {
        $this->seed([PlanSeeder::class, DemoSeeder::class]);

        $this->assertSame(2, Flight::count());
        $this->assertSame(24, FlightPassenger::count(), '12 aktif yolcu × gidiş + dönüş');
    }

    public function test_demo_parents_are_older_than_their_children(): void
    {
        $this->seed([PlanSeeder::class, DemoSeeder::class]);

        PersonRelation::query()
            ->whereIn('relation', [Relation::Mother, Relation::Father])
            ->with(['person', 'relatedPerson'])
            ->get()
            ->each(fn (PersonRelation $r) => $this->assertTrue(
                $r->relatedPerson->birth_date <= $r->person->birth_date,
                "{$r->relatedPerson->full_name}, {$r->person->full_name} adlı yolcudan genç olduğu halde ebeveyni görünüyor.",
            ));
    }

    public function test_fix_pack_reverses_parents_younger_than_their_children(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'demo-turizm']);
        $young = Person::factory()->create(['tenant_id' => $tenant->id, 'gender' => Gender::Female, 'birth_date' => '2005-01-01']);
        $old = Person::factory()->create(['tenant_id' => $tenant->id, 'gender' => Gender::Female, 'birth_date' => '1975-01-01']);
        app(CurrentTenant::class)->run($tenant, fn () => app(AddPersonRelation::class)->handle($old, $young, Relation::Mother)); // yanlış: genç olan "anne"

        app(CurrentTenant::class)->run($tenant, fn () => app(FixParentAgesDemo::class)->run($tenant));

        $this->assertSame(Relation::Mother, PersonRelation::where('person_id', $young->id)->sole()->relation);
        $this->assertSame(Relation::Child, PersonRelation::where('person_id', $old->id)->sole()->relation);
    }

    public function test_packs_are_applied_once_and_deletions_are_kept(): void
    {
        $this->seed([PlanSeeder::class, DemoSeeder::class]);
        TourHotel::query()->delete();

        $this->artisan('marhal:demo-data')->assertSuccessful();

        $this->assertSame(0, TourHotel::count());
        $this->assertSame(4, Hotel::count());
    }

    public function test_it_refuses_to_run_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('marhal:demo-data')->assertFailed();
    }
}
