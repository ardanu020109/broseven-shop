<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BroSevenHelpers;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use BroSevenHelpers;
    use RefreshDatabase;

    public function test_user_can_login_with_username_and_with_email(): void
    {
        $user = $this->makeUser(Role::CASHIER, ['username' => 'kasir.uji', 'email' => 'kasir.uji@broseven.test']);

        $this->postJson('/api/auth/login', ['login' => 'kasir.uji', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.role', 'kasir')
            ->assertJsonPath('user.username', 'kasir.uji')
            ->assertJsonMissingPath('user.password');

        $this->assertAuthenticatedAs($user);

        $this->postJson('/api/auth/logout')->assertOk();
        $this->assertGuest();

        $this->postJson('/api/auth/login', ['login' => 'kasir.uji@broseven.test', 'password' => 'password'])->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_requires_both_fields(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['login', 'password']);
    }

    public function test_wrong_password_and_unknown_user_return_the_same_message(): void
    {
        $this->makeUser(Role::CASHIER, ['username' => 'ada.user']);

        $wrong = $this->postJson('/api/auth/login', ['login' => 'ada.user', 'password' => 'salah-total']);
        $unknown = $this->postJson('/api/auth/login', ['login' => 'tidak.ada', 'password' => 'salah-total']);

        $wrong->assertUnprocessable()->assertJsonValidationErrors('login');
        $unknown->assertUnprocessable()->assertJsonValidationErrors('login');
        $this->assertSame($wrong->json('errors.login.0'), $unknown->json('errors.login.0'));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_failed']);
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        $this->makeUser(Role::CASHIER, ['username' => 'target.user']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['login' => 'target.user', 'password' => 'salah'.$i])->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', ['login' => 'target.user', 'password' => 'password'])
            ->assertStatus(429)
            ->assertJsonStructure(['message', 'retry_after']);

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_throttled']);
    }

    public function test_inactive_and_deleted_accounts_cannot_login(): void
    {
        $this->makeUser(Role::CASHIER, ['username' => 'nonaktif.user', 'is_active' => false]);

        $this->postJson('/api/auth/login', ['login' => 'nonaktif.user', 'password' => 'password'])->assertForbidden();
        $this->assertGuest();

        $deleted = $this->makeUser(Role::CASHIER, ['username' => 'terhapus.user']);
        $deleted->delete();

        $this->postJson('/api/auth/login', ['login' => 'terhapus.user', 'password' => 'password'])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();

        $user = $this->signIn(Role::ADMIN_MASTER);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', 'admin_master')
            ->assertJsonMissingPath('user.password');
    }

    public function test_account_deactivated_during_a_session_is_kicked_out(): void
    {
        $user = $this->signIn(Role::CASHIER);

        $this->getJson('/api/auth/me')->assertOk();

        $user->update(['is_active' => false]);

        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_login_records_last_login_and_audit_and_passwords_are_hashed(): void
    {
        $user = $this->makeUser(Role::SUPERADMIN, ['username' => 'sa.uji']);

        $this->assertNotSame('password', $user->password);
        $this->assertTrue(Hash::check('password', $user->password));

        $this->postJson('/api/auth/login', ['login' => 'sa.uji', 'password' => 'password'])->assertOk();

        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertTrue(AuditLog::where('action', 'auth.login')->where('user_id', $user->id)->exists());
    }
}