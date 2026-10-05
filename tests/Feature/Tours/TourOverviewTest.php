<?php

namespace Tests\Feature\Tours;

use App\Enums\Feature;
use App\Enums\FlightDirection;
use App\Enums\HotelCity;
use App\Enums\UserRole;
use App\Models\Flight;
use App\Models\Group;
use App\Models\Hotel;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourHotel;
use App\Models\User;
use App\Support\Tours\TourJourney;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Tur sayfasının üst bölümü: yolculuk çizelgesi, hazırlık halkaları, WhatsApp grubu, tur programı çıktısı.
 */
class TourOverviewTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Tour $tour;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([
            Feature::Passengers, Feature::Payments, Feature::RoomPlanning, Feature::FlightLists, Feature::BasicReports,
        ])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $this->staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();

        $this->tour = Tour::factory()->create([
            'tenant_id' => $this->tenant->id, 'name' => 'Kasım Umresi',
            'start_date' => '2026-11-01', 'end_date' => '2026-11-12',
        ]);

        Flight::factory()->create(['tour_id' => $this->tour->id, 'flight_no' => 'TK 92', 'departure_at' => '2026-11-01 10:00', 'arrival_at' => '2026-11-01 14:00']);
        Flight::factory()->create([
            'tour_id' => $this->tour->id, 'direction' => FlightDirection::Return, 'flight_no' => 'TK 109',
            'departure_airport' => 'MED', 'arrival_airport' => 'IST',
            'departure_at' => '2026-11-12 09:00', 'arrival_at' => '2026-11-12 13:00',
        ]);

        // İki grup aynı günlerde farklı Mekke otellerinde: çizelgede tek "Mekke" adımı olmalı.
        foreach (['Swissôtel', 'Hilton'] as $name) {
            $this->stay($name, HotelCity::Mecca, '2026-11-01', '2026-11-07');
        }
        $this->stay('Pullman', HotelCity::Medina, '2026-11-07', '2026-11-12');
    }

    public function test_journey_merges_same_city_stays_and_marks_the_current_step(): void
    {
        $steps = app(TourJourney::class)->for($this->tour, $this->tenant, CarbonImmutable::parse('2026-11-03'));

        $this->assertSame(['Hazırlık', 'Gidiş', 'Mekke', 'Medine', 'Dönüş'], array_column($steps, 'title'));
        $this->assertSame(['done', 'done', 'now', 'next', 'next'], array_column($steps, 'state'));
        $this->assertSame('Swissôtel · Hilton', $steps[2]['detail']);
        $this->assertSame('TK 92 · IST → JED', $steps[1]['detail']);

        $before = app(TourJourney::class)->for($this->tour, $this->tenant, CarbonImmutable::parse('2026-10-01'));
        $this->assertSame('now', $before[0]['state']);

        $after = app(TourJourney::class)->for($this->tour, $this->tenant, CarbonImmutable::parse('2026-12-01'));
        $this->assertSame(['done'], array_values(array_unique(array_column($after, 'state'))));
    }

    public function test_journey_falls_back_to_tour_dates_when_modules_are_off(): void
    {
        $plan = Plan::factory()->withFeatures([Feature::Passengers])->create();
        $this->tenant->update(['plan_id' => $plan->id]);

        $steps = app(TourJourney::class)->for($this->tour, $this->tenant->fresh(), CarbonImmutable::parse('2026-10-01'));

        $this->assertSame(['Hazırlık', 'Gidiş', 'Dönüş'], array_column($steps, 'title'));
        $this->assertSame(['2026-11-01', '2026-11-12'], [$steps[1]['start'], $steps[2]['end']]);
    }

    public function test_tour_page_shares_journey_and_readiness_with_staff_only(): void
    {
        $group = Group::factory()->create(['tour_id' => $this->tour->id]);
        Registration::factory()->count(2)->create(['tour_id' => $this->tour->id, 'group_id' => $group->id]);

        $this->actingAs($this->staff)->get(route('tours.show', $this->tour))
            ->assertInertia(fn (Assert $page) => $page
                ->has('journey', 5)
                ->where('readiness.registered', 2)
                ->where('readiness.checks.0.key', 'rooms')
                ->has('readiness.collection'));

        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();
        $group->update(['guide_user_id' => $guide->id]);

        $this->actingAs($guide)->get(route('tours.show', $this->tour))
            ->assertInertia(fn (Assert $page) => $page
                ->has('journey', 5)
                ->where('readiness', null));
    }

    public function test_whatsapp_link_is_saved_and_must_be_a_whatsapp_invite(): void
    {
        $data = [
            'name' => $this->tour->name, 'type' => 'umre', 'status' => $this->tour->status->value,
            'start_date' => '2026-11-01', 'end_date' => '2026-11-12', 'currency' => 'USD',
        ];

        $this->actingAs($this->staff)->put(route('tours.update', $this->tour), [...$data, 'whatsapp_link' => 'https://example.com/grup'])
            ->assertSessionHasErrors('whatsapp_link');

        $this->actingAs($this->staff)->put(route('tours.update', $this->tour), [...$data, 'whatsapp_link' => 'https://chat.whatsapp.com/AbCdEf123'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->staff)->get(route('tours.show', $this->tour))
            ->assertInertia(fn (Assert $page) => $page->where('tour.whatsapp_link', 'https://chat.whatsapp.com/AbCdEf123'));
    }

    public function test_tour_program_report_downloads_and_is_tenant_isolated(): void
    {
        $this->actingAs($this->staff)->get(route('reports.tours.program', $this->tour))
            ->assertOk()
            ->assertDownload();

        $this->actingAs($this->staff)->get(route('reports.tours.program', ['tour' => $this->tour, 'format' => 'pdf']))
            ->assertOk();

        $foreign = Tour::factory()->create();
        $this->actingAs($this->staff)->get(route('reports.tours.program', $foreign))->assertNotFound();
    }

    private function stay(string $hotel, HotelCity $city, string $in, string $out): void
    {
        TourHotel::factory()->create([
            'tour_id' => $this->tour->id,
            'hotel_id' => Hotel::factory()->create(['tenant_id' => $this->tenant->id, 'name' => $hotel, 'city' => $city])->id,
            'check_in' => $in,
            'check_out' => $out,
        ]);
    }
}
