<?php

namespace Tests\Feature\Settings;

use App\Enums\ColorTheme;
use App\Enums\Feature;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_theme_is_zumrut_dark_and_rendered_on_html(): void
    {
        $user = User::factory()->create();

        $this->assertSame(ColorTheme::Zumrut, $user->theme);
        $this->actingAs($user)->get(route('appearance.edit'))
            ->assertOk()
            ->assertSee('data-theme="zumrut" class="dark"', false)
            ->assertInertia(fn (Assert $page) => $page->has('themes', 2));
    }

    public function test_user_can_choose_a_theme_and_it_is_remembered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('theme.update'), ['theme' => 'safak'])
            ->assertSessionHasNoErrors()
            ->assertCookie('color_theme', 'safak', encrypted: false);

        $this->assertSame(ColorTheme::Safak, $user->fresh()->theme);
        $this->actingAs($user->fresh())->get(route('appearance.edit'))
            ->assertSee('data-theme="safak"', false)
            ->assertDontSee('class="dark"', false);
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
            ->patch(route('theme.update'), ['theme' => 'haremeyn'])
            ->assertSessionHasErrors('theme');
    }

    public function test_login_page_uses_theme_cookie(): void
    {
        $this->withUnencryptedCookie('color_theme', 'safak')
            ->get(route('login'))
            ->assertSee('data-theme="safak"', false);
    }

    public function test_old_theme_choices_are_moved_to_the_new_themes(): void
    {
        $migration = require database_path('migrations/2026_10_09_000001_switch_users_to_new_themes.php');
        $migration->down();

        // Eski değerler model üzerinden yazılamaz (enum'da yoklar); doğrudan tabloya.
        $ids = User::factory()->count(3)->create()->modelKeys();
        foreach (['haremeyn', 'kum', 'kurumsal'] as $i => $old) {
            DB::table('users')->where('id', $ids[$i])->update(['theme' => $old]);
        }

        $migration->up();

        $this->assertSame(['zumrut', 'zumrut', 'safak'], array_map(
            fn ($id) => DB::table('users')->where('id', $id)->value('theme'),
            $ids,
        ));
    }
}
