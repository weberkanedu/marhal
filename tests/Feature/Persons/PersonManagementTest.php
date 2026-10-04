<?php

namespace Tests\Feature\Persons;

use App\Enums\Feature;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Person;
use App\Models\Plan;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Tour;
use App\Models\User;
use App\Rules\TcKimlikNo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersonManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->tenant = $this->tenantWith([Feature::Passengers]);
    }

    public function test_staff_can_list_only_their_own_tenants_persons(): void
    {
        Person::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
        Person::factory()->count(2)->create();

        $this->actingAs($this->user(UserRole::Operations))
            ->get(route('persons.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('persons/Index')
                ->where('persons.total', 3)
                ->missing('persons.data.0.national_id')
                ->has('persons.data.0.masked_national_id'));
    }

    public function test_persons_can_be_searched_by_name_and_national_id(): void
    {
        $ali = Person::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Ali', 'last_name' => 'Yılmaz']);
        Person::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Veli', 'last_name' => 'Demir']);
        $user = $this->user(UserRole::Operations);

        $this->actingAs($user)->get(route('persons.index', ['q' => 'ali yıl']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('persons.total', 1)
                ->where('persons.data.0.id', $ali->id));

        $this->actingAs($user)->get(route('persons.index', ['q' => $ali->national_id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('persons.total', 1)
                ->where('persons.data.0.id', $ali->id));
    }

    public function test_a_person_can_be_created_with_a_photo(): void
    {
        $nationalId = TcKimlikNo::generate();

        $response = $this->actingAs($this->user(UserRole::Operations))->post(route('persons.store'), [
            'first_name' => 'Ayşe',
            'last_name' => 'Kaya',
            'gender' => 'kadin',
            'birth_date' => '1965-04-12',
            'nationality' => 'TR',
            'national_id' => $nationalId,
            'passport_no' => 'u 1234567',
            'phone' => '05321234567',
            'kvkk_consent' => '1',
            'photo' => UploadedFile::fake()->image('ayse.jpg', 400, 500),
        ]);

        $person = Person::whereNationalId($nationalId)->sole();

        $response->assertRedirect(route('persons.show', $person));
        $this->assertSame($this->tenant->id, $person->tenant_id);
        $this->assertSame('U1234567', $person->passport_no);
        $this->assertNotNull($person->kvkk_consent_at);
        $this->assertNotNull($person->photo_path);
        Storage::disk('local')->assertExists($person->photo_path);
    }

    public function test_invalid_and_duplicate_national_ids_are_rejected(): void
    {
        $user = $this->user(UserRole::Operations);
        $existing = Person::factory()->create(['tenant_id' => $this->tenant->id]);
        $base = ['first_name' => 'Test', 'last_name' => 'Kişi', 'gender' => 'erkek', 'nationality' => 'TR'];

        $this->actingAs($user)->post(route('persons.store'), [...$base, 'national_id' => '12345678901'])
            ->assertSessionHasErrors('national_id');

        $this->actingAs($user)->post(route('persons.store'), [...$base, 'national_id' => $existing->national_id])
            ->assertSessionHasErrors('national_id');

        $this->assertSame(1, Person::count());
    }

    public function test_updating_without_national_id_keeps_the_existing_value(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);
        $original = $person->national_id;

        $this->actingAs($this->user(UserRole::Operations))->put(route('persons.update', $person), [
            'first_name' => 'Yeni',
            'last_name' => $person->last_name,
            'gender' => $person->gender->value,
            'nationality' => 'TR',
            'national_id' => '',
        ])->assertRedirect(route('persons.show', $person));

        $person->refresh();
        $this->assertSame('Yeni', $person->first_name);
        $this->assertSame($original, $person->national_id);
    }

    public function test_other_tenants_persons_are_not_accessible(): void
    {
        $foreign = Person::factory()->create();
        $user = $this->user(UserRole::Admin);

        $this->actingAs($user)->get(route('persons.show', $foreign))->assertNotFound();
        $this->actingAs($user)->get(route('persons.photo', $foreign))->assertNotFound();
        $this->actingAs($user)->post(route('persons.reveal', $foreign))->assertNotFound();
        $this->actingAs($user)->delete(route('persons.destroy', $foreign))->assertNotFound();
    }

    public function test_guides_cannot_access_the_persons_module(): void
    {
        $this->actingAs($this->user(UserRole::Guide))
            ->get(route('persons.index'))
            ->assertForbidden();
    }

    public function test_module_is_blocked_when_not_in_plan(): void
    {
        $tenant = $this->tenantWith([]);
        $user = User::factory()->forTenant($tenant)->create();

        $this->actingAs($user)->get(route('persons.index'))->assertForbidden();
    }

    public function test_only_admins_can_reveal_sensitive_data_and_it_is_logged(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user(UserRole::Operations))
            ->postJson(route('persons.reveal', $person))
            ->assertForbidden();

        $admin = $this->user(UserRole::Admin);
        $this->actingAs($admin)
            ->postJson(route('persons.reveal', $person))
            ->assertOk()
            ->assertJson(['national_id' => $person->national_id]);

        $this->assertTrue(AuditLog::where('action', 'view_sensitive')
            ->where('subject_id', $person->id)
            ->where('user_id', $admin->id)
            ->exists());
    }

    public function test_show_page_exposes_permissions_by_role(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user(UserRole::Operations))
            ->get(route('persons.show', $person))
            ->assertInertia(fn (Assert $page) => $page
                ->component('persons/Show')
                ->where('can.reveal', false)
                ->where('can.update', true)
                ->missing('person.national_id'));
    }

    public function test_photo_is_served_only_to_authorized_users(): void
    {
        $user = $this->user(UserRole::Operations);
        $this->actingAs($user)->post(route('persons.store'), [
            'first_name' => 'Foto', 'last_name' => 'Test', 'gender' => 'erkek', 'nationality' => 'TR',
            'photo' => UploadedFile::fake()->image('p.png'),
        ]);
        $person = Person::sole();

        $this->actingAs($user)->get(route('persons.photo', $person))->assertOk();
        $this->actingAs($this->user(UserRole::Guide))->get(route('persons.photo', $person))->assertForbidden();
    }

    public function test_person_with_an_ongoing_tour_cannot_be_deleted(): void
    {
        $person = Person::factory()->create(['tenant_id' => $this->tenant->id]);
        $tour = Tour::factory()->create(['tenant_id' => $this->tenant->id]);
        $registration = Registration::factory()->create(['tour_id' => $tour->id, 'person_id' => $person->id]);
        $user = $this->user(UserRole::Admin);

        $this->actingAs($user)->delete(route('persons.destroy', $person));
        $this->assertNotSoftDeleted($person);

        $registration->update(['status' => RegistrationStatus::Cancelled]);

        $this->actingAs($user)->delete(route('persons.destroy', $person))
            ->assertRedirect(route('persons.index'));
        $this->assertSoftDeleted($person);
    }

    /**
     * @param  list<Feature>  $features
     */
    private function tenantWith(array $features): Tenant
    {
        return Tenant::factory()->create([
            'plan_id' => Plan::factory()->withFeatures($features)->create()->id,
        ]);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->forTenant($this->tenant)->role($role)->create();
    }
}
