<?php

namespace Tests\Feature\Tours;

use App\Enums\Feature;
use App\Enums\RegistrationStatus;
use App\Enums\SignupStatus;
use App\Enums\UserRole;
use App\Models\NeedType;
use App\Models\Person;
use App\Models\PersonNeed;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\SignupLink;
use App\Models\SignupRequest;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use App\Support\Needs\DefaultNeedTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Tasarım yenileme 7b: telefonla ön kayıt (tur linki, başvuru kuralları, şifreli saklama, onay / ret, izolasyon).
 */
class OnlineSignupTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Tour $tour;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::OnlineSignup])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        DefaultNeedTypes::seed($this->tenant);
        $this->staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
        $this->tour = Tour::factory()->create(['tenant_id' => $this->tenant->id, 'start_date' => now()->addMonth(), 'end_date' => now()->addMonth()->addDays(14), 'default_price' => 1500]);
    }

    public function test_link_opens_the_page_counts_visits_and_can_be_renewed_or_closed(): void
    {
        $token = $this->link();

        $this->get(route('signup.show', $token))
            ->assertOk()
            ->assertSee('noindex', false)
            ->assertInertia(fn (Assert $page) => $page->component('public/Signup')->where('tour.name', $this->tour->name)->has('needTypes', 11));
        $this->assertSame(1, SignupLink::sole()->opened_count);

        $new = $this->link();
        $this->get(route('signup.show', $token))->assertNotFound();
        $this->get(route('signup.show', $new))->assertOk();

        $this->actingAs($this->staff)->delete(route('tours.signup-link.close', $this->tour));
        $this->get(route('signup.show', $new))->assertNotFound();
        $this->get(route('signup.show', 'BilinmeyenTokenBilinmeyen'))->assertNotFound();
    }

    public function test_link_closes_after_the_tour_and_when_the_module_is_off(): void
    {
        $token = $this->link();

        $this->tour->update(['end_date' => now()->subDay(), 'start_date' => now()->subDays(10)]);
        $this->get(route('signup.show', $token))->assertNotFound();

        $this->tour->update(['start_date' => now()->addMonth(), 'end_date' => now()->addMonth()->addDays(5)]);
        $this->tenant->plan->features()->where('feature_key', Feature::OnlineSignup->value)->update(['enabled' => false]);
        $this->tenant->plan->flushTenantFeatureCache();
        $this->get(route('signup.show', $token))->assertNotFound();
    }

    public function test_submission_rules_and_encrypted_storage(): void
    {
        $token = $this->link();
        $need = NeedType::query()->withoutGlobalScopes()->where('name', 'Yürüme güçlüğü')->value('id');

        $this->submit($token, ['kvkk' => false])->assertSessionHasErrors('kvkk');
        $this->submit($token, ['needs' => [$need], 'health_consent' => false])->assertSessionHasErrors('health_consent');
        $this->submit($token, ['needs' => ['01a00000-0000-7000-8000-000000000000'], 'health_consent' => true])->assertSessionHasErrors('needs');
        $this->submit($token, ['phone' => ''])->assertSessionHasErrors('phone');

        $this->submit($token, ['needs' => [$need], 'health_consent' => true])->assertSessionHasNoErrors();
        $request = SignupRequest::query()->withoutGlobalScopes()->sole();
        $this->assertSame('U28401234', $request->data['passport_no']);
        $this->assertSame([$need], $request->needs);
        $this->assertNotNull($request->health_consent_at);
        $this->assertStringNotContainsString('U28401234', (string) DB::table('signup_requests')->value('data'), 'Pasaport no şifreli saklanır');

        $this->submit($token)->assertSessionHasErrors('passport_no');
    }

    public function test_approval_creates_the_person_and_a_pre_registration_with_needs_and_minimises_data(): void
    {
        $token = $this->link();
        $need = NeedType::query()->withoutGlobalScopes()->where('name', 'Yürüme güçlüğü')->value('id');
        $this->submit($token, ['needs' => [$need], 'health_consent' => true]);
        $request = SignupRequest::query()->withoutGlobalScopes()->sole();

        $this->actingAs($this->staff)->get(route('tours.show', $this->tour))
            ->assertInertia(fn (Assert $page) => $page
                ->where('signup.completed', 1)
                ->where('signup.pending.0.passport_no', 'U28401234')
                ->where('signup.pending.0.needs', ['Yürüme güçlüğü'])
                ->where('signup.pending.0.existing', false));

        $this->actingAs($this->staff)->post(route('signup-requests.approve', $request))->assertSessionHasNoErrors();

        $person = Person::query()->wherePassportNo('U28401234')->sole();
        $registration = Registration::query()->where('person_id', $person->id)->sole();
        $this->assertSame(['Ayşe', RegistrationStatus::Pending, '1500.00'], [$person->first_name, $registration->status, $registration->price]);
        $this->assertNotNull($person->kvkk_consent_at);
        $this->assertNotNull($person->health_consent_at);
        $this->assertSame(1, PersonNeed::query()->where('person_id', $person->id)->count());

        $request->refresh();
        $this->assertSame(SignupStatus::Approved, $request->status);
        $this->assertSame(['first_name' => 'Ayşe', 'last_name' => 'Yılmaz'], $request->data, 'Onaydan sonra kimlik bilgisi başvuruda kalmaz');
        $this->actingAs($this->staff)->post(route('signup-requests.approve', $request))->assertSessionHasErrors('request');
    }

    public function test_existing_person_is_reused_and_rejection_deletes_details(): void
    {
        $existing = Person::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Eski', 'passport_no' => 'U28401234']);
        $token = $this->link();
        $this->submit($token);
        $request = SignupRequest::query()->withoutGlobalScopes()->sole();

        $this->actingAs($this->staff)->post(route('signup-requests.approve', $request));
        $this->assertSame(1, Person::query()->wherePassportNo('U28401234')->count(), 'Aynı pasaportla ikinci kişi açılmaz');
        $this->assertSame('Eski', $existing->fresh()?->first_name, 'Kayıtlı kişinin bilgisi ezilmez');
        $this->assertTrue(Registration::query()->where('person_id', $existing->id)->exists());

        $this->submit($token, ['passport_no' => 'U99999999']);
        $second = SignupRequest::query()->withoutGlobalScopes()->where('status', 'bekliyor')->sole();
        $this->actingAs($this->staff)->post(route('signup-requests.reject', $second))->assertSessionHasNoErrors();
        $this->assertSame(['first_name' => 'Ayşe', 'last_name' => 'Yılmaz'], $second->fresh()?->data);
    }

    public function test_guides_and_other_agencies_cannot_manage_signups(): void
    {
        $this->link();
        $this->submit((string) SignupLink::query()->withoutGlobalScopes()->sole()->token);
        $request = SignupRequest::query()->withoutGlobalScopes()->sole();

        $guide = User::factory()->forTenant($this->tenant)->role(UserRole::Guide)->create();
        $this->actingAs($guide)->post(route('tours.signup-link', $this->tour))->assertForbidden();
        $this->actingAs($guide)->post(route('signup-requests.approve', $request))->assertForbidden();

        $other = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id]);
        $otherAdmin = User::factory()->forTenant($other)->role(UserRole::Admin)->create();
        $this->actingAs($otherAdmin)->post(route('signup-requests.approve', $request))->assertNotFound();
        $this->actingAs($otherAdmin)->post(route('tours.signup-link', $this->tour))->assertNotFound();
        $this->assertSame(SignupStatus::Pending, $request->fresh()?->status);
    }

    private function link(): string
    {
        $this->actingAs($this->staff)->post(route('tours.signup-link', $this->tour))->assertSessionHasNoErrors();
        auth()->logout();

        return (string) SignupLink::query()->withoutGlobalScopes()->where('is_active', true)->sole()->token;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submit(string $token, array $overrides = []): TestResponse
    {
        return $this->post(route('signup.store', $token), [
            'first_name' => 'Ayşe',
            'last_name' => 'Yılmaz',
            'gender' => 'kadin',
            'birth_date' => '1971-04-12',
            'nationality' => 'TR',
            'passport_no' => 'U28401234',
            'passport_expiry_date' => now()->addYears(5)->toDateString(),
            'phone' => '0532 000 00 01',
            'needs' => [],
            'kvkk' => true,
            'health_consent' => false,
            'read_from_passport' => true,
            ...$overrides,
        ]);
    }
}
