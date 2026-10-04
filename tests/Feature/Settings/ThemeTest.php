<?php

namespace Tests\Feature\Settings;

use App\Enums\ColorTheme;
use App\Enums\Feature;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_theme_is_haremeyn_and_rendered_on_html(): void
    {
        $user = User::factory()->create();

        $this->assertSame(ColorTheme::Haremeyn, $user->theme);
        $this->actingAs($user)->get(route('appearance.edit'))
            ->assertOk()
            ->assertSee('data-theme="haremeyn"', false);
    }

    public function test_user_can_choose_a_theme_and_it_is_remembered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('theme.update'), ['theme' => 'kum'])
            ->assertSessionHasNoErrors()
            ->assertCookie('color_theme', 'kum', encrypted: false);

        $this->assertSame(ColorTheme::Kum, $user->fresh()->theme);
        $this->actingAs($user->fresh())->get(route('appearance.edit'))->assertSee('data-theme="kum"', false);
    }

    public function test_settings_pages_still_share_tenant_modules_for_the_menu(): void
    {
        $plan = Plan::factory()->withFeatures([Feature::Passengers, Feature::Payments])->create();
        $user = User::factory()->forTenant(Tenant::factory()->create(['plan_id' => $plan->id]))->create();

        $this->actingAs($user)->get(route('appearance.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('features', fn ($features) => collect($features)->contains('passengers'))
                ->where('tenant.id', $user->tenant_id));
    }

    public function test_invalid_theme_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->patch(route('theme.update'), ['theme' => 'pembe'])
            ->assertSessionHasErrors('theme');
    }

    public function test_login_page_uses_theme_cookie(): void
    {
        $this->withUnencryptedCookie('color_theme', 'kurumsal')
            ->get(route('login'))
            ->assertSee('data-theme="kurumsal"', false);
    }
}
