<?php

namespace Tests\Feature\Tours;

use App\Actions\Persons\AddPersonRelation;
use App\Enums\Feature;
use App\Enums\Gender;
use App\Enums\Relation;
use App\Enums\RoomKind;
use App\Enums\UserRole;
use App\Models\FamilyLink;
use App\Models\Group;
use App\Models\Hotel;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\TourHotel;
use App\Models\TourProgramItem;
use App\Models\User;
use App\Support\Family\FamilyView;
use App\Support\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Tasarım yenileme 7a: tur programı ve aile ekranı (izinle link, iptal / süre, gösterilenler, izolasyon).
 */
class FamilyScreenTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Tour $tour;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::FamilyScreen, Feature::RoomPlanning])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id, 'phone' => '0212 555 00 00']);
        $this->staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
        $this->tour = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'start_date' => '2026-11-01', 'end_date' => '2026-11-10']);
        $this->group = Group::factory()->create(['tour_id' => $this->tour->id, 'name' => 'A Grubu', 'guide_name' => 'Hasan T.']);
    }

    public function test_program_items_are_added_within_tour_dates_and_listed(): void
    {
        $this->actingAs($this->staff)->post(route('tours.program.store', $this->tour), ['day' => '2026-11-02', 'time' => '10:00', 'title' => 'Umre ibadeti', 'place' => 'Harem-i Şerif'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->post(route('tours.program.store', $this->tour), ['day' => '2026-12-01', 'title' => 'Tur dışında'])
            ->assertSessionHasErrors('day');

        $item = TourProgramItem::sole();
        $this->actingAs($this->staff)->put(route('program-items.update', $item), ['day' => '2026-11-02', 'time' => '09:30', 'title' => 'Umre ibadeti, rehberle'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->staff)->get(route('tours.show', $this->tour))
            ->assertInertia(fn (Assert $page) => $page
                ->has('program', 1)
                ->where('program.0.time', '09:30')
                ->where('program.0.title', 'Umre ibadeti, rehberle')
                ->where('program.0.place', null));

        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();
        $this->group->update(['guide_user_id' => $guide->id]);
        $this->actingAs($guide)->delete(route('program-items.destroy', $item))->assertForbidden();
        $this->actingAs($this->staff)->delete(route('program-items.destroy', $item))->assertRedirect();
        $this->assertSame(0, TourProgramItem::count());
    }

    public function test_family_link_needs_consent_and_old_link_is_revoked(): void
    {
        $registration = $this->registration();

        $this->actingAs($this->staff)->post(route('registrations.family-link.store', $registration), ['consent' => false])
            ->assertSessionHasErrors('consent');
        $this->actingAs($this->staff)->post(route('registrations.family-link.store', $registration), ['consent' => true])
            ->assertSessionHasNoErrors();
        $first = FamilyLink::sole();
        $this->assertSame($this->staff->id, $first->consent_by);
        $this->assertSame('2026-11-17', $first->expires_at->toDateString());
        $this->assertNotSame($first->token, $first->getRawOriginal('token'), 'Token şifreli saklanır');

        $this->actingAs($this->staff)->post(route('registrations.family-link.store', $registration), ['consent' => true]);
        $this->assertNotNull($first->fresh()?->revoked_at, 'Yeni link eskisini iptal eder');
        $this->get(route('family.show', $first->token))->assertNotFound();

        $this->actingAs($this->staff)->get(route('tours.show', $this->tour))
            ->assertInertia(fn (Assert $page) => $page->where('registrations.0.family_link.url', fn (string $url) => str_contains($url, '/aile/')));
    }

    public function test_family_page_shows_place_roommate_family_program_and_hides_private_data(): void
    {
        $mother = $this->registration(['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'gender' => Gender::Female, 'passport_no' => 'U12345678']);
        $son = $this->registration(['first_name' => 'Mehmet', 'last_name' => 'Yılmaz', 'gender' => Gender::Male]);
        $stranger = $this->registration(['first_name' => 'Zeki', 'gender' => Gender::Male]);
        app(CurrentTenant::class)->run($this->tenant, fn () => app(AddPersonRelation::class)->handle($mother->person, $son->person, Relation::Child));

        $hotel = Hotel::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ajyad Otel', 'city' => 'mekke']);
        $stay = TourHotel::factory()->create(['tour_id' => $this->tour->id, 'hotel_id' => $hotel->id, 'check_in' => '2026-11-01', 'check_out' => '2026-11-06']);
        $room = Room::factory()->create(['tour_hotel_id' => $stay->id, 'room_no' => '1201', 'capacity' => 4, 'kind' => RoomKind::Family]);
        foreach ([$mother, $son, $stranger] as $r) {
            $room->assignments()->forceCreate(['tenant_id' => $this->tenant->id, 'tour_hotel_id' => $stay->id, 'registration_id' => $r->id]);
        }
        TourProgramItem::query()->forceCreate(['tenant_id' => $this->tenant->id, 'tour_id' => $this->tour->id, 'day' => '2026-11-03', 'time' => '05:10', 'title' => 'Sabah namazı']);
        TourProgramItem::query()->forceCreate(['tenant_id' => $this->tenant->id, 'tour_id' => $this->tour->id, 'day' => '2026-11-03', 'time' => '21:00', 'title' => 'Serbest tavaf']);

        $this->actingAs($this->staff)->post(route('registrations.family-link.store', $mother), ['consent' => true]);
        $link = FamilyLink::sole();

        $data = app(CurrentTenant::class)->run($this->tenant, fn () => app(FamilyView::class)->for($link, $this->tenant, CarbonImmutable::parse('2026-11-03 12:00', FamilyView::TIMEZONE)));
        $this->assertSame('during', $data['phase']);
        $this->assertSame(['city' => 'Mekke', 'hotel' => 'Ajyad Otel', 'room' => '1201', 'with' => ['Mehmet Bey']], $data['now'], 'Yalnız aile bağı olan oda arkadaşı görünür');
        $this->assertSame('Hasan T.', $data['guide']);
        $day = collect($data['days'])->firstWhere('date', '2026-11-03');
        $this->assertSame([true, false], array_column($day['events'], 'past'));

        auth()->logout();
        $response = $this->get(route('family.show', $link->token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('public/Family')->where('family.name', 'Ayşe Yılmaz'));
        $this->assertStringNotContainsString('U12345678', (string) $response->getContent());
        $this->assertStringContainsString('noindex', (string) $response->getContent());
        $this->assertSame(1, $link->fresh()?->view_count);
    }

    public function test_expired_revoked_unknown_or_module_off_links_are_not_found(): void
    {
        $registration = $this->registration();
        $this->actingAs($this->staff)->post(route('registrations.family-link.store', $registration), ['consent' => true]);
        $link = FamilyLink::sole();
        auth()->logout();

        $this->get(route('family.show', 'BilinmeyenTokenBilinmeyen'))->assertNotFound();
        $link->forceFill(['expires_at' => now()->subDay()])->save();
        $this->get(route('family.show', $link->token))->assertNotFound();
        $link->forceFill(['expires_at' => now()->addDay(), 'revoked_at' => now()])->save();
        $this->get(route('family.show', $link->token))->assertNotFound();

        $link->forceFill(['revoked_at' => null])->save();
        $this->get(route('family.show', $link->token))->assertOk();
        $this->tenant->plan->features()->where('feature_key', Feature::FamilyScreen->value)->update(['enabled' => false]);
        $this->tenant->plan->flushTenantFeatureCache();
        $this->get(route('family.show', $link->token))->assertNotFound();
    }

    public function test_other_agencies_and_guides_cannot_create_or_revoke_links(): void
    {
        $other = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id]);
        $foreign = Registration::factory()->create(['tour_id' => Tour::factory()->create(['tenant_id' => $other->id])->id]);
        $this->actingAs($this->staff)->post(route('registrations.family-link.store', $foreign), ['consent' => true])->assertNotFound();

        $mine = $this->registration();
        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();
        $this->group->update(['guide_user_id' => $guide->id]);
        $this->actingAs($guide)->post(route('registrations.family-link.store', $mine), ['consent' => true])->assertForbidden();

        $this->actingAs($this->staff)->post(route('registrations.family-link.store', $mine), ['consent' => true]);
        $link = FamilyLink::sole();
        $otherStaff = User::factory()->forTenant($other)->role(UserRole::Admin)->create();
        $this->actingAs($otherStaff)->delete(route('family-links.destroy', $link))->assertNotFound();
        $this->assertNull($link->fresh()?->revoked_at);
    }

    /**
     * @param  array<string, mixed>  $person
     */
    private function registration(array $person = []): Registration
    {
        return Registration::factory()->create([
            'tour_id' => $this->tour->id,
            'group_id' => $this->group->id,
            'person_id' => Person::factory()->create(['tenant_id' => $this->tenant->id, ...$person])->id,
        ]);
    }
}
