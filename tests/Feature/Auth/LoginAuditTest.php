<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class LoginAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_logged(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('audit_logs', ['action' => 'login', 'user_id' => $user->id, 'tenant_id' => $user->tenant_id]);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_failed_login_is_logged(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'yanlis-sifre']);

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'login_failed']);
    }

    public function test_audit_logs_cannot_be_changed(): void
    {
        $log = AuditLog::create(['action' => 'login']);

        $this->expectException(LogicException::class);
        $log->update(['action' => 'baska']);
    }
}
