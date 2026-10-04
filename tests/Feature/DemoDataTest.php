<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\PersonRelation;
use App\Models\RoomAssignment;
use App\Models\TourHotel;
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
