<?php

namespace Tests\Feature\Security;

use App\Actions\Security\RecordLogin;
use App\Enums\Feature;
use App\Enums\SecurityAlertKind;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\SecurityAlert;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDevice;
use App\Support\Security\SecuritySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Hesap paylaşımı koruması (10d-1): cihaz kaydı, tek oturum, şüpheli kullanım uyarısı, yöneticiye iki adımlı
 * doğrulama zorunluluğu, platform güvenlik kuralları, "Cihazlarım" ve çıktılarda acente kimliği.
 */
class AccountSharingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::BasicReports])->create();
        $this->tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        $this->user = User::factory()->forTenant($this->tenant)->role(UserRole::Operations)->create();
    }

    private function login(string $agent = 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0'): void
    {
        $this->withHeader('User-Agent', $agent)
            ->post(route('login.store'), ['email' => $this->user->email, 'password' => 'password'])
            ->assertRedirect();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function device(array $attributes = []): UserDevice
    {
        return UserDevice::query()->create([
            'user_id' => $this->user->id,
            'token_hash' => hash('sha256', (string) mt_rand()),
            'label' => 'Chrome · Windows',
            'last_network' => '10.0.'.mt_rand(0, 9999),
            'last_seen_at' => now()->subDays(3),
            ...$attributes,
        ]);
    }

    public function test_login_registers_the_device_and_sets_it_current(): void
    {
        $this->login();

        $device = UserDevice::sole();
        $this->assertSame('Chrome · Windows', $device->label);
        $this->assertSame($device->id, $this->user->fresh()?->current_device_id);
        $this->assertSame($device->id, session('device_id'));
    }

    public function test_other_device_login_closes_this_session(): void
    {
        $this->login();
        $this->get(route('dashboard'))->assertOk();

        // Başka bir cihazdan giriş yapıldı (testte kullanıcı nesnesi önbellekte kalmasın; uygulamada her istekte okunur).
        $this->user->forceFill(['current_device_id' => $this->device()->id])->save();
        $this->app['auth']->forgetGuards();

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', fn (string $s) => str_contains($s, 'başka bir cihazdan'));
        $this->assertGuest();
    }

    public function test_single_session_can_be_turned_off(): void
    {
        app(SecuritySettings::class)->save(['single_session' => false]);
        $this->login();
        $this->user->forceFill(['current_device_id' => $this->device()->id])->save();

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_remember_me_from_another_device_needs_password(): void
    {
        $this->user->forceFill(['current_device_id' => $this->device()->id])->save();

        $request = Request::create('/dashboard');
        $this->assertFalse(app(RecordLogin::class)->handle($this->user, $request, viaRemember: true));
        $this->assertTrue(app(RecordLogin::class)->handle($this->user->fresh() ?? $this->user, $request, viaRemember: false));
    }

    public function test_device_limit_and_many_devices_raise_alerts_but_login_works(): void
    {
        $this->device();
        $this->device();
        $this->device();

        $this->login();
        $this->assertAuthenticated();

        $limit = SecurityAlert::query()->where('kind', SecurityAlertKind::DeviceLimit)->sole();
        $this->assertSame($this->tenant->id, $limit->tenant_id);
        $this->assertSame(['devices' => 4, 'limit' => 3], $limit->details);
        $this->assertSame(0, SecurityAlert::query()->where('kind', SecurityAlertKind::ManyDevices)->count(), 'Eski cihazlar pencereye girmez.');

        // 2 saat içinde 4 cihaz / 4 ağ → şüpheli; aynı tür ikinci uyarı açılmaz.
        UserDevice::query()->update(['last_seen_at' => now()->subMinutes(30)]);
        auth()->logout();
        $this->login();
        $this->login();

        $this->assertSame(1, SecurityAlert::query()->where('kind', SecurityAlertKind::ManyDevices)->count());
        $this->assertSame(1, SecurityAlert::query()->where('kind', SecurityAlertKind::ManyNetworks)->count());
        $this->assertSame(1, SecurityAlert::query()->where('kind', SecurityAlertKind::DeviceLimit)->count());
    }

    public function test_platform_verifies_the_user_and_agency_admin_sees_only_own_alerts(): void
    {
        $this->device();
        $this->user->forceFill(['current_device_id' => 1, 'remember_token' => 'eski'])->save();
        $alert = SecurityAlert::query()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id, 'kind' => SecurityAlertKind::ManyDevices, 'details' => ['devices' => 5, 'hours' => 2]]);
        $otherTenant = Tenant::factory()->create(['plan_id' => $this->tenant->plan_id]);
        $stranger = User::factory()->forTenant($otherTenant)->create();
        SecurityAlert::query()->create(['tenant_id' => $otherTenant->id, 'user_id' => $stranger->id, 'kind' => SecurityAlertKind::DeviceLimit]);

        $admin = User::factory()->forTenant($this->tenant)->role(UserRole::Admin)->create();
        $this->actingAs($admin)->get(route('users.index'))
            ->assertInertia(fn (Assert $page) => $page->has('alerts', 1)->where('alerts.0.summary', '2 saat içinde 5 farklı cihazdan girdi.'));
        $this->actingAs($admin)->post(route('platform.security-alerts.resolve', $alert))->assertForbidden();

        $platform = User::factory()->superAdmin()->create();
        $this->actingAs($platform)->get(route('platform.tenants.index'))
            ->assertInertia(fn (Assert $page) => $page->has('alerts', 2)->where('platformCounts.tenants', 2));
        $this->actingAs($platform)->post(route('platform.security-alerts.resolve', $alert))->assertSessionHasNoErrors();

        $this->assertNotNull($alert->fresh()?->resolved_at);
        $this->assertSame(0, $this->user->devices()->count());
        $this->assertNull($this->user->fresh()?->current_device_id);
        $this->assertNull($this->user->fresh()?->remember_token);
    }

    public function test_admins_must_set_up_two_factor_when_required(): void
    {
        $admin = User::factory()->forTenant($this->tenant)->role(UserRole::Admin)->create();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();

        app(SecuritySettings::class)->save(['admin_two_factor' => true]);
        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('security.edit'));
        $this->actingAs($this->user)->get(route('dashboard'))->assertOk();

        $admin->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    }

    public function test_platform_security_rules_are_saved_and_mail_code_waits_for_mail(): void
    {
        $platform = User::factory()->superAdmin()->create();
        $payload = ['single_session' => false, 'device_limit' => 5, 'new_device_code' => true, 'admin_two_factor' => true, 'suspicious_alerts' => true, 'device_limit_alert' => false];

        $this->actingAs($this->user)->put(route('platform.security.update'), $payload)->assertForbidden();
        $this->actingAs($platform)->put(route('platform.security.update'), $payload)->assertSessionHasNoErrors();

        $rules = app(SecuritySettings::class)->all();
        $this->assertSame(5, $rules['device_limit']);
        $this->assertFalse($rules['single_session']);
        $this->assertFalse($rules['new_device_code'], 'E-posta servisi yokken e-posta kodu açılmaz.');

        $this->actingAs($platform)->get(route('platform.security.index'))
            ->assertInertia(fn (Assert $page) => $page->component('platform/Security')->where('mailReady', false)->where('settings.device_limit', 5));
    }

    public function test_users_manage_only_their_own_devices(): void
    {
        $old = $this->device();
        $current = $this->device();
        $other = UserDevice::query()->create(['user_id' => User::factory()->forTenant($this->tenant)->create()->id, 'token_hash' => 'x', 'label' => 'Safari · Mac', 'last_seen_at' => now()]);

        $this->actingAs($this->user)->withSession(['device_id' => $current->id, 'auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertInertia(fn (Assert $page) => $page->has('devices', 2)->where('deviceLimit', 3));

        $this->actingAs($this->user)->withSession(['device_id' => $current->id])->delete(route('devices.destroy', $other->id))->assertNotFound();
        $this->actingAs($this->user)->withSession(['device_id' => $current->id])->delete(route('devices.destroy', $current->id))->assertStatus(422);
        $this->actingAs($this->user)->withSession(['device_id' => $current->id])->delete(route('devices.destroy', $old->id))->assertSessionHasNoErrors();

        $this->assertSame([$current->id], $this->user->devices()->pluck('id')->all());
    }

    public function test_agency_identity_is_saved_and_printed_on_exports(): void
    {
        $admin = User::factory()->forTenant($this->tenant)->role(UserRole::Admin)->create();

        $this->actingAs($admin)->post(route('agency.update'), ['name' => 'Örnek Tur', 'default_currency' => 'TRY', 'tax_no' => '12AB'])
            ->assertSessionHasErrors('tax_no');
        $this->actingAs($admin)->post(route('agency.update'), [
            'name' => 'Örnek Tur', 'default_currency' => 'TRY', 'tursab_no' => '1234',
            'tax_office' => 'Kadıköy', 'tax_no' => '1234567890', 'diyanet_license_no' => '56',
        ])->assertSessionHasNoErrors();

        $this->assertSame('TÜRSAB 1234 · Kadıköy VD 1234567890 · Diyanet yetki 56', $this->tenant->fresh()?->identityLine());

        $this->actingAs($admin)->get(route('reports.persons.list', ['format' => 'pdf']))->assertOk();
    }
}
