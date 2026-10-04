<?php

namespace Tests\Feature;

use App\Actions\Payments\ReplaceInstallmentPlan;
use App\Enums\Feature;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\Bus;
use App\Models\Flight;
use App\Models\Group;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\SeatAssignment;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourHotel;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Ana panel: vadesi geçmiş / yaklaşan taksitler ve yaklaşan turların hazırlık durumu.
 */
class DashboardReadinessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([
            Feature::Passengers, Feature::Payments, Feature::RoomPlanning, Feature::BusPlanning, Feature::FlightLists,
        ])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $this->staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
    }

    public function test_tour_readiness_counts_rooms_seats_flights_passports_and_pending(): void
    {
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'start_date' => now()->addDays(20), 'end_date' => now()->addDays(34)]);
        $group = Group::factory()->create(['tour_id' => $tour->id]);
        [$a, $b] = [$this->registration($tour, $group), $this->registration($tour, $group, status: RegistrationStatus::Pending)];
        $this->registration($tour, null, passportExpiry: now()->addMonths(2)->toDateString()); // grupsuz + pasaport kısa

        $stay = TourHotel::factory()->create(['tour_id' => $tour->id]);
        $stay->groups()->sync([$group->id]);
        $room = Room::factory()->create(['tour_hotel_id' => $stay->id]);
        RoomAssignment::query()->forceCreate(['tenant_id' => $this->tenant->id, 'tour_hotel_id' => $stay->id, 'room_id' => $room->id, 'registration_id' => $a->id]);

        $bus = Bus::factory()->create(['tour_id' => $tour->id]);
        SeatAssignment::query()->forceCreate(['tenant_id' => $this->tenant->id, 'tour_id' => $tour->id, 'bus_id' => $bus->id, 'registration_id' => $a->id, 'seat_no' => 1]);

        $flight = Flight::factory()->create(['tour_id' => $tour->id]);
        $flight->passengers()->create(['registration_id' => $a->id]);
        $flight->passengers()->create(['registration_id' => $b->id]);

        $this->actingAs($this->staff)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('tours.0.registered', 3)
                ->where('tours.0.pending', 1)
                ->where('tours.0.ungrouped', 1)
                ->where('tours.0.passport_issues', 1)
                ->where('tours.0.checks', [
                    ['key' => 'rooms', 'label' => 'Oda yerleşimi', 'done' => 1, 'total' => 2, 'tab' => 'konaklama'],
                    ['key' => 'seats', 'label' => 'Otobüs koltuğu', 'tab' => 'ulasim', 'done' => 1, 'total' => 3],
                    ['key' => 'flights', 'label' => 'Uçuş kaydı', 'tab' => 'ulasim', 'done' => 2, 'total' => 3],
                ]));
    }

    public function test_overdue_and_upcoming_installments_are_counted(): void
    {
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id]);
        $late = $this->registration($tour, null);
        $soon = $this->registration($tour, null);
        $cancelled = $this->registration($tour, null, status: RegistrationStatus::Cancelled);

        app(CurrentTenant::class)->run($this->tenant, function () use ($late, $soon, $cancelled): void {
            app(ReplaceInstallmentPlan::class)->handle($late, [['due_date' => now()->subDays(3)->toDateString(), 'amount' => 500]]);
            app(ReplaceInstallmentPlan::class)->handle($soon, [['due_date' => now()->addDays(3)->toDateString(), 'amount' => 500]]);
            app(ReplaceInstallmentPlan::class)->handle($cancelled, [['due_date' => now()->addDays(2)->toDateString(), 'amount' => 500]]);
        });

        $this->actingAs($this->staff)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('payments.overdue_count', 1)
                ->where('payments.overdue', ['USD' => '500.00'])
                ->where('payments.due_soon_count', 1));
    }

    public function test_activity_feed_trend_and_collection_rate(): void
    {
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'currency' => 'USD']);
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Ayşe', 'last_name' => 'Yılmaz']);

        // Ekrandan yapılan işlemler erişim kaydına düşer → akışa girer.
        $this->actingAs($this->staff)->post(route('tours.registrations.store', $tour), [
            'person_id' => $person->id, 'price' => 1000, 'currency' => 'USD', 'status' => 'kesin_kayit', 'room_type' => '4lu',
        ])->assertSessionHasNoErrors();
        $registration = Registration::where('person_id', $person->id)->sole();
        $this->actingAs($this->staff)->post(route('registrations.payments.store', $registration), [
            'type' => 'tahsilat', 'amount' => 400, 'currency' => 'USD', 'method' => 'nakit', 'paid_at' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        // Başka acentenin hareketi akışa girmez (erişim kayıtları acente kapsamı kullanmaz; elle filtrelenmeli).
        $foreign = Person::factory()->create(['first_name' => 'Yabancı', 'last_name' => 'Kişi']);
        DB::table('audit_logs')->insert([
            'tenant_id' => $foreign->tenant_id, 'action' => 'create', 'subject_type' => Person::class,
            'subject_id' => $foreign->id, 'created_at' => now()->addMinute(),
        ]);

        $this->actingAs($this->staff)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('activity.0.text', 'Ayşe Yılmaz için 400,00 USD tahsilat')
                ->where('activity.1.text', 'Ayşe Yılmaz, '.$tour->name.' turuna eklendi')
                ->where('activity', fn ($items) => collect($items)->doesntContain(fn ($i) => str_contains($i['text'], 'Yabancı')))
                ->where('trend.series.USD.5', '400.00')
                ->where('tours.0.collection', ['currency' => 'USD', 'paid' => '400.00', 'total' => '1000.00']));
    }

    public function test_modules_that_are_off_are_not_shown(): void
    {
        $this->tenant->update(['plan_id' => Plan::factory()->withFeatures([Feature::Passengers])->create()->id]);
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id]);
        TourHotel::factory()->create(['tour_id' => $tour->id]);
        $this->registration($tour, null);

        $this->actingAs($this->staff)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('payments', null)
                ->where('stats.outstanding', [])
                ->where('tours.0.checks', []));
    }

    public function test_other_agencies_data_is_not_counted(): void
    {
        $foreignTour = Tour::factory()->create();
        Registration::factory()->count(3)->create(['tour_id' => $foreignTour->id]);

        $this->actingAs($this->staff)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('tours', 0)->where('payments.overdue_count', 0));
    }

    private function registration(
        Tour $tour,
        ?Group $group,
        RegistrationStatus $status = RegistrationStatus::Confirmed,
        ?string $passportExpiry = null,
    ): Registration {
        $person = Person::factory()->create(array_filter([
            'tenant_id' => $this->tenant->id,
            'passport_expiry_date' => $passportExpiry,
        ]));

        return Registration::factory()->create([
            'tour_id' => $tour->id,
            'group_id' => $group?->id,
            'person_id' => $person->id,
            'status' => $status,
            'price' => 1500,
            'currency' => 'USD',
        ]);
    }
}
