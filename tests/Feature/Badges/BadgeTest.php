<?php

namespace Tests\Feature\Badges;

use App\Enums\Feature;
use App\Enums\HotelCity;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\Bus;
use App\Models\Group;
use App\Models\Hotel;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\SeatAssignment;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;
use App\Models\Tour;
use App\Models\TourHotel;
use App\Models\User;
use App\Reports\Definitions\TourBadges;
use App\Support\Media\PersonPhotoStore;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Faz 3 / 2. adım: yaka kartı (tur, grup, tek yolcu) PDF'i ve kart içeriği.
 */
class BadgeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Tour $tour;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::BadgeGeneration, Feature::BasicReports])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id, 'phone' => '0212 555 00 00']);
        $this->staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
        $this->tour = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'start_date' => '2026-11-01', 'end_date' => '2026-11-15']);
        $this->group = Group::factory()->create(['tour_id' => $this->tour->id, 'name' => 'A Grubu', 'guide_name' => 'Ahmet Rehber', 'guide_phone' => '0555 111 22 33']);
    }

    public function test_badges_download_for_tour_group_and_single_passenger(): void
    {
        $registration = $this->registration();
        $this->registration();

        $this->actingAs($this->staff)->get(route('reports.tours.badges', $this->tour))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->staff)->get(route('reports.tours.badges', [$this->tour, 'group' => $this->group->id]))->assertOk();
        $this->actingAs($this->staff)->get(route('reports.tours.badges', [$this->tour, 'registration' => $registration->id]))->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'export']);
    }

    public function test_badge_shows_group_guide_hotels_rooms_and_bus_but_no_identity_numbers(): void
    {
        $registration = $this->registration(first: 'Ayşe', last: 'Yılmaz');
        $mecca = $this->stay('Swissôtel Al Maqam', HotelCity::Mecca, '2026-11-01', '2026-11-08');
        $medina = $this->stay('Pullman Zamzam', HotelCity::Medina, '2026-11-08', '2026-11-15');
        $room = Room::factory()->create(['tour_hotel_id' => $mecca->id, 'room_no' => '501']);
        RoomAssignment::query()->forceCreate(['tenant_id' => $this->tenant->id, 'tour_hotel_id' => $mecca->id, 'room_id' => $room->id, 'registration_id' => $registration->id]);
        $bus = Bus::factory()->create(['tour_id' => $this->tour->id, 'name' => '1. Otobüs']);
        SeatAssignment::query()->forceCreate(['tenant_id' => $this->tenant->id, 'tour_id' => $this->tour->id, 'bus_id' => $bus->id, 'registration_id' => $registration->id, 'seat_no' => 7]);

        [$badge] = $this->badges();

        $this->assertSame('AYŞE YILMAZ', $badge['name']);
        $this->assertSame('A Grubu', $badge['group']);
        $this->assertSame('Ahmet Rehber 0555 111 22 33', $badge['guide']);
        $this->assertSame([
            ['Mekke', 'Swissôtel Al Maqam', '501'],
            ['Medine', 'Pullman Zamzam', null], // oda henüz yok: sadece grubun oteli
        ], array_map(fn (array $h) => [$h['city'], $h['hotel'], $h['room']], $badge['hotels']));
        $this->assertSame(['label' => '1. Otobüs', 'value' => '7'], $badge['bus']);

        $flat = json_encode($badge);
        $this->assertStringNotContainsString((string) $registration->person->passport_no, (string) $flat);
        $this->assertStringNotContainsString((string) $registration->person->national_id, (string) $flat);
        $this->assertNotNull($medina);
    }

    public function test_cancelled_passengers_get_no_badge_and_photos_are_downscaled(): void
    {
        Storage::fake(config('marhal.media_disk'));
        $registration = $this->registration();
        $this->registration(status: RegistrationStatus::Cancelled);
        app(PersonPhotoStore::class)->store($registration->person, UploadedFile::fake()->image('foto.jpg', 1200, 1600));

        $badges = $this->badges();

        $this->assertCount(1, $badges);
        // Yuvarlak kesilmiş (köşeleri saydam) kare PNG.
        $this->assertStringStartsWith('data:image/png;base64,', (string) $badges[0]['photo']);
        $size = getimagesizefromstring((string) base64_decode(substr((string) $badges[0]['photo'], 22)));
        $this->assertSame([240, 240], [$size[0] ?? 0, $size[1] ?? 0]);
    }

    public function test_access_rules(): void
    {
        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();
        $this->group->update(['guide_user_id' => $guide->id]);
        $this->actingAs($guide)->get(route('reports.tours.badges', $this->tour))->assertForbidden();

        $otherTourGroup = Group::factory()->create(['tour_id' => Tour::factory()->create(['tenant_id' => $this->tenant->id])->id]);
        $this->actingAs($this->staff)->get(route('reports.tours.badges', [$this->tour, 'group' => $otherTourGroup->id]))->assertNotFound();
        $this->actingAs($this->staff)->get(route('reports.tours.badges', Tour::factory()->create()))->assertNotFound();

        // Paket özelliği yoksa kapalı; acenteye özel açılırsa açık
        $this->tenant->update(['plan_id' => Plan::factory()->withFeatures([Feature::Passengers, Feature::BasicReports])->create()->id]);
        $this->staff = $this->staff->fresh() ?? $this->staff; // önceki istekten kalan eski paket bilgisi olmasın
        $this->actingAs($this->staff)->get(route('reports.tours.badges', $this->tour))->assertForbidden();

        TenantFeatureOverride::query()->create(['tenant_id' => $this->tenant->id, 'feature_key' => Feature::BadgeGeneration->value, 'enabled' => true]);
        $this->actingAs($this->staff)->get(route('reports.tours.badges', $this->tour))->assertOk();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function badges(): array
    {
        return app(CurrentTenant::class)->run($this->tenant, fn () => app(TourBadges::class)->build($this->tour));
    }

    private function stay(string $hotel, HotelCity $city, string $in, string $out): TourHotel
    {
        $stay = TourHotel::factory()->create([
            'tour_id' => $this->tour->id,
            'hotel_id' => Hotel::factory()->create(['tenant_id' => $this->tenant->id, 'name' => $hotel, 'city' => $city])->id,
            'check_in' => $in,
            'check_out' => $out,
        ]);
        $stay->groups()->sync([$this->group->id]);

        return $stay;
    }

    private function registration(string $first = 'Ali', string $last = 'Kaya', RegistrationStatus $status = RegistrationStatus::Confirmed): Registration
    {
        return Registration::factory()->create([
            'tour_id' => $this->tour->id,
            'group_id' => $this->group->id,
            'person_id' => Person::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => $first, 'last_name' => $last])->id,
            'status' => $status,
        ]);
    }
}
