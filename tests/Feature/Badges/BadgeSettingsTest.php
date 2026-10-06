<?php

namespace Tests\Feature\Badges;

use App\Enums\BadgeSize;
use App\Enums\Feature;
use App\Enums\UserRole;
use App\Models\BadgeSetting;
use App\Models\Group;
use App\Models\NeedType;
use App\Models\Person;
use App\Models\PersonNeed;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use App\Reports\Definitions\TourBadges;
use App\Support\ArabicText;
use App\Support\GroupColors;
use App\Support\Needs\DefaultNeedTypes;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Tasarım yenileme 5: yaka kartı ayarı (boy, alanlar, arka yüz, sağlık notu), grup rengi, Arapça arka yüz.
 */
class BadgeSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Tour $tour;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::BadgeGeneration, Feature::BusPlanning, Feature::BasicReports])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $this->staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
        $this->tour = Tour::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_badge_screen_shows_settings_and_cards_and_saves_changes(): void
    {
        $group = Group::factory()->create(['tour_id' => $this->tour->id, 'name' => 'A Grubu']);
        $this->registration($group, 'Ali');

        $this->actingAs($this->staff)->get(route('tours.badge-cards', $this->tour))
            ->assertInertia(fn (Assert $page) => $page
                ->component('tours/Badges')
                ->where('settings.size', 'yatay')
                ->where('settings.fields', ['photo', 'hotels', 'bus', 'guide', 'qr'])
                ->where('settings.health_note', false)
                ->has('badges', 1)
                ->where('badges.0.first_name', 'ALİ')
                ->where('badges.0.qr', null)
                ->has('groups', 1)
                ->where('groups.0.color', GroupColors::PALETTE[0])
                ->has('palette', count(GroupColors::PALETTE)));

        $this->actingAs($this->staff)->put(route('badge-settings.update'), [
            'size' => 'dikey', 'fields' => ['qr', 'hotels'], 'back_languages' => ['ar', 'tr'],
            'back_side' => true, 'health_note' => true,
        ])->assertSessionHasNoErrors();

        $settings = BadgeSetting::sole();
        $this->assertSame([BadgeSize::Portrait, ['hotels', 'qr'], ['tr', 'ar'], true], [
            $settings->size, $settings->fields, $settings->back_languages, $settings->health_note,
        ]);

        $this->actingAs($this->staff)->put(route('badge-settings.update'), [
            'size' => 'dev', 'fields' => ['tc'], 'back_languages' => [], 'back_side' => true, 'health_note' => false,
        ])->assertSessionHasErrors(['size', 'fields.0', 'back_languages']);
    }

    public function test_group_color_is_picked_from_the_palette_on_the_badge_screen(): void
    {
        $group = Group::factory()->create(['tour_id' => $this->tour->id]);

        $this->actingAs($this->staff)->put(route('groups.color', $group), ['color' => '#123456'])
            ->assertSessionHasErrors('color');
        $this->actingAs($this->staff)->put(route('groups.color', $group), ['color' => '#1d4f91'])
            ->assertSessionHasNoErrors();
        $this->assertSame('#1d4f91', $group->fresh()?->color);
    }

    public function test_guides_and_agencies_without_the_module_cannot_open_the_badge_screen(): void
    {
        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();
        $this->actingAs($guide)->get(route('tours.badge-cards', $this->tour))->assertForbidden();
        $this->actingAs($guide)->put(route('badge-settings.update'), [
            'size' => 'dikey', 'fields' => [], 'back_languages' => ['tr'], 'back_side' => true, 'health_note' => false,
        ])->assertForbidden();

        $plain = User::factory()->forTenant(Tenant::factory()->create([
            'plan_id' => Plan::factory()->withFeatures([Feature::Passengers])->create()->id,
        ]))->role(UserRole::Admin)->create();
        $this->actingAs($plain)->put(route('badge-settings.update'), [])->assertForbidden();
    }

    public function test_another_agencys_tour_and_group_are_not_found(): void
    {
        $other = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id]);
        $tour = Tour::factory()->create(['tenant_id' => $other->id]);
        $group = Group::factory()->create(['tour_id' => $tour->id]);

        $this->actingAs($this->staff)->get(route('tours.badge-cards', $tour))->assertNotFound();
        $this->actingAs($this->staff)->put(route('groups.color', $group), ['color' => '#1d4f91'])->assertNotFound();
        $this->assertNull($group->fresh()?->color);
    }

    public function test_badge_follows_settings_and_prints_health_only_with_consent(): void
    {
        DefaultNeedTypes::seed($this->tenant);
        $group = Group::factory()->create(['tour_id' => $this->tour->id, 'name' => 'A Grubu', 'color' => '#9c3d2e']);
        $withConsent = $this->registration($group, 'Ali', consent: true);
        $withoutConsent = $this->registration($group, 'Veli', consent: false);

        $settings = new BadgeSetting(['fields' => ['hotels'], 'health_note' => true]);
        $badges = collect(app(CurrentTenant::class)->run($this->tenant, fn () => app(TourBadges::class)->build($this->tour, null, null, $settings)))
            ->keyBy('first_name');

        $this->assertSame('#9c3d2e', $badges['ALİ']['color']);
        $this->assertNull($badges['ALİ']['qr'], 'QR kapalı');
        $this->assertNull($badges['ALİ']['photo'], 'Fotoğraf kapalı');
        $this->assertSame('Diyabet (İnsülin)', $badges['ALİ']['health']);
        $this->assertNull($badges['VELİ']['health'], 'Rızası olmayanın sağlık notu basılmaz');

        $settings->health_note = false;
        $off = app(CurrentTenant::class)->run($this->tenant, fn () => app(TourBadges::class)->build($this->tour, null, $withConsent, $settings));
        $this->assertNull($off[0]['health'], 'Acente kapattıysa basılmaz');
        $this->assertNotNull($withoutConsent);
    }

    public function test_badge_pdf_downloads_in_every_size(): void
    {
        $group = Group::factory()->create(['tour_id' => $this->tour->id]);
        $this->registration($group, 'Ali');

        foreach (BadgeSize::cases() as $size) {
            BadgeSetting::query()->withoutGlobalScopes()->delete();
            $this->actingAs($this->staff)->put(route('badge-settings.update'), [
                'size' => $size->value, 'fields' => BadgeSetting::FIELDS, 'back_languages' => ['tr', 'en', 'ar'],
                'back_side' => true, 'health_note' => false,
            ]);

            $response = $this->actingAs($this->staff)->get(route('reports.tours.badges', $this->tour))->assertOk();
            $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        }
    }

    public function test_group_colors_are_saved_or_given_from_the_palette(): void
    {
        $a = Group::factory()->create(['tour_id' => $this->tour->id, 'name' => 'A', 'color' => GroupColors::PALETTE[0]]);
        $b = Group::factory()->create(['tour_id' => $this->tour->id, 'name' => 'B']);

        $colors = GroupColors::forGroups($this->tour->groups()->orderBy('name')->get());
        $this->assertSame(GroupColors::PALETTE[0], $colors[$a->id]);
        $this->assertSame(GroupColors::PALETTE[1], $colors[$b->id], 'Seçilmiş renk tekrar verilmez');

        $this->actingAs($this->staff)->put(route('groups.update', $b), ['name' => 'B', 'color' => '#ff00ff'])
            ->assertSessionHasErrors('color');
        $this->actingAs($this->staff)->put(route('groups.update', $b), ['name' => 'B', 'color' => '#6b3fa0'])
            ->assertSessionHasNoErrors();
        $this->assertSame('#6b3fa0', $b->fresh()?->color);
    }

    public function test_arabic_text_is_joined_and_right_to_left(): void
    {
        // "لا" bitişik tek harf; "بها" → ب (baş) ه (orta) ا (son), sağdan sola dizilir.
        $this->assertSame(mb_chr(0xFEFB), ArabicText::forPdf('لا'));
        $this->assertSame(mb_chr(0xFE8E).mb_chr(0xFEEC).mb_chr(0xFE91), ArabicText::forPdf('بها'));
        // Rakamlar soldan sağa kalır, Arapça kelimenin solunda görünür.
        $this->assertSame('0555 '.mb_chr(0xFE8E).mb_chr(0xFEEC).mb_chr(0xFE91), ArabicText::forPdf('بها 0555'));
    }

    private function registration(Group $group, string $name, bool $consent = false): Registration
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => $name]);

        if ($consent) {
            $person->forceFill(['health_consent_at' => now()])->save();
        }

        // Rızası olmayana da (eski kayıt gibi) profil yazılmış olsa bile kartta görünmemeli.
        if (NeedType::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->exists()) {
            (new PersonNeed)->forceFill([
                'tenant_id' => $this->tenant->id,
                'person_id' => $person->id,
                'items' => [['type_id' => NeedType::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', 'Diyabet')->value('id'), 'note' => 'İnsülin']],
            ])->save();
        }

        return Registration::factory()->create(['tour_id' => $this->tour->id, 'group_id' => $group->id, 'person_id' => $person->id]);
    }
}
