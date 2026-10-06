<?php

namespace Tests\Feature\Users;

use App\Actions\Users\CreateTenantUser;
use App\Enums\Feature;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers])->create(['user_limit' => 3]);
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $this->admin = User::factory()->forTenant($this->tenant)->create();
    }

    public function test_admin_can_create_a_user_with_a_temporary_password(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Ayşe Operasyon',
            'email' => 'Ayse@Acente.test',
            'role' => 'operasyon',
        ])->assertSessionHasNoErrors();

        $user = User::where('email', 'ayse@acente.test')->sole();
        $this->assertSame($this->tenant->id, $user->tenant_id);
        $this->assertSame(UserRole::Operations, $user->role);
        $this->assertTrue($user->must_change_password);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_user_limit_counts_only_active_staff_and_guides_are_free(): void
    {
        $extra = User::factory()->forTenant($this->tenant)->count(2)->create();

        $this->actingAs($this->admin)->post(route('users.store'), ['name' => 'X', 'email' => 'x@t.test', 'role' => 'operasyon'])
            ->assertSessionHasErrors('email');

        // Rehber hesapları her pakette ücretsiz ve sınırsız.
        $this->actingAs($this->admin)->post(route('users.store'), ['name' => 'R', 'email' => 'r@t.test', 'role' => 'rehber'])
            ->assertSessionHasNoErrors();
        $guide = User::where('email', 'r@t.test')->sole();

        // Rehberi personele çevirmek sınıra takılır.
        $this->actingAs($this->admin)->put(route('users.update', $guide), ['name' => 'R', 'role' => 'operasyon', 'is_active' => true])
            ->assertSessionHasErrors('role');

        $extra[0]->update(['is_active' => false]);

        $this->actingAs($this->admin)->post(route('users.store'), ['name' => 'X', 'email' => 'x@t.test', 'role' => 'operasyon'])
            ->assertSessionHasNoErrors();
    }

    public function test_non_admins_cannot_manage_users_or_agency(): void
    {
        $staff = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();

        $this->actingAs($staff)->get(route('users.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('agency.edit'))->assertForbidden();
    }

    public function test_last_active_admin_cannot_be_demoted_and_self_cannot_be_deactivated(): void
    {
        $this->actingAs($this->admin)->put(route('users.update', $this->admin), [
            'name' => $this->admin->name, 'role' => 'admin', 'is_active' => false,
        ])->assertSessionHasErrors('role');

        $second = User::factory()->forTenant($this->tenant)->create();

        // İkinci yönetici varken birini operasyona almak serbest…
        $this->actingAs($this->admin)->put(route('users.update', $second), [
            'name' => $second->name, 'role' => 'operasyon', 'is_active' => true,
        ])->assertSessionHasNoErrors();

        // …ama son yönetici (kendisi) düşürülemez.
        $this->actingAs($this->admin)->put(route('users.update', $this->admin), [
            'name' => $this->admin->name, 'role' => 'operasyon', 'is_active' => true,
        ])->assertSessionHasErrors('role');
    }

    public function test_other_tenants_users_are_not_accessible(): void
    {
        $foreign = User::factory()->create();

        $this->actingAs($this->admin)->put(route('users.update', $foreign), [
            'name' => 'X', 'role' => 'operasyon', 'is_active' => true,
        ])->assertNotFound();
        $this->actingAs($this->admin)->post(route('users.reset-password', $foreign))->assertNotFound();
    }

    public function test_password_reset_invalidates_old_password_and_forces_change(): void
    {
        $user = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();

        $this->actingAs($this->admin)->post(route('users.reset-password', $user))->assertRedirect();

        $user->refresh();
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertTrue($user->must_change_password);
    }

    public function test_temporary_password_user_must_change_password_before_using_the_app(): void
    {
        ['user' => $user, 'password' => $password] = app(CreateTenantUser::class)
            ->handle($this->tenant, 'Yeni Kişi', 'yeni@t.test', UserRole::Operations);

        $this->post(route('login.store'), ['email' => 'yeni@t.test', 'password' => $password]);
        $this->assertAuthenticatedAs($user);

        $this->get(route('dashboard'))->assertRedirect(route('security.edit'));

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('user-password.update'), [
                'current_password' => $password,
                'password' => 'Yeni-Guclu-Sifre-2026',
                'password_confirmation' => 'Yeni-Guclu-Sifre-2026',
            ])->assertSessionHasNoErrors();

        $this->assertFalse($user->fresh()->must_change_password);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_admin_can_update_agency_details_and_logo(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->post(route('agency.update'), [
            'name' => 'Yeni Ad Turizm',
            'phone' => '0212 000 00 00',
            'default_currency' => 'EUR',
            'logo' => UploadedFile::fake()->image('logo.png', 400, 120),
        ])->assertSessionHasNoErrors();

        $tenant = $this->tenant->fresh();
        $this->assertSame('Yeni Ad Turizm', $tenant->name);
        $this->assertSame('EUR', $tenant->default_currency);
        Storage::disk('local')->assertExists($tenant->logo_path);

        $this->actingAs($this->admin)->get(route('agency.logo'))->assertOk();

        $this->actingAs($this->admin)->get(route('agency.edit'))
            ->assertInertia(fn (Assert $page) => $page->component('agency/Edit')->where('agency.name', 'Yeni Ad Turizm'));
    }
}
