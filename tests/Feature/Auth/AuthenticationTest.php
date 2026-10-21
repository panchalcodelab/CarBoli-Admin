<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_inactive_staff_cannot_authenticate(): void
    {
        $user = User::factory()->create(['status' => 'inactive']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_successful_login_records_a_single_audit_log(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $logs = AuditLog::query()
            ->where('module', 'auth')
            ->where('action', 'user.login')
            ->where('entity_type', $user->getMorphClass())
            ->where('entity_id', $user->getKey())
            ->get();

        $this->assertCount(1, $logs);
    }

    public function test_failed_login_does_not_record_a_login_audit_log(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertSame(0, AuditLog::query()
            ->where('module', 'auth')
            ->where('action', 'user.login')
            ->count());
    }

    public function test_logout_records_a_single_audit_log(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout');

        $logs = AuditLog::query()
            ->where('module', 'auth')
            ->where('action', 'user.logout')
            ->where('entity_type', $user->getMorphClass())
            ->where('entity_id', $user->getKey())
            ->get();

        $this->assertCount(1, $logs);
    }
}
