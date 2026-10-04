<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_guests_to_login_and_users_to_dashboard()
    {
        $this->get(route('home'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->get(route('home'))->assertRedirect(route('dashboard'));
    }
}
