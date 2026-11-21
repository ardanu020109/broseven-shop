<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\OrderWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BroSevenHelpers;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use BroSevenHelpers;
    use RefreshDatabase;

    private function body(User $user, array $override = []): array
    {
        return array_merge([
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role->value,
            'is_active' => $user->is_active,
        ], $override);
    }

    public function test_create_user_validates_username_email_and_password_strength(): void
    {
        $this->signIn(Role::SUPERADMIN);
        $existing = $this->makeUser(Role::CASHIER, ['username' => 'sudah.ada', 'email' => 'sudah.ada@broseven.test']);

        $valid = ['name' => 'Kasir Baru', 'username' => 'kasir.baru', 'email' => 'kasir.baru@broseven.test', 'role' => 'kasir', 'password' => 'Rahasia123'];

        $this->postJson('/api/users', array_merge($valid, ['password' => 'abc']))->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/users', array_merge($valid, ['password' => 'abcdefgh']))->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/users', array_merge($valid, ['username' => 'a b']))->assertUnprocessable()->assertJsonValidationErrors('username');
        $this->postJson('/api/users', array_merge($valid, ['username' => 'SUDAH.ADA']))->assertUnprocessable()->assertJsonValidationErrors('username');
        $this->postJson('/api/users', array_merge($valid, ['email' => 'SUDAH.ADA@broseven.test']))->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/users', array_merge($valid, ['role' => 'dewa']))->assertUnprocessable()->assertJsonValidationErrors('role');

        $this->postJson('/api/users', $valid)->assertCreated()->assertJsonPath('data.role', 'kasir');
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created']);
        $this->assertNotNull($existing->id);
    }

    public function test_update_keeps_own_email_valid_and_changes_roles(): void
    {
        $this->signIn(Role::SUPERADMIN);
        $user = $this->makeUser(Role::CASHIER, ['username' => 'ubah.saya', 'email' => 'ubah.saya@broseven.test']);

        $this->putJson("/api/users/{$user->id}", $this->body($user, ['name' => 'Nama Baru']))->assertOk()->assertJsonPath('data.name', 'Nama Baru');
        $this->putJson("/api/users/{$user->id}", $this->body($user, ['role' => 'admin_master']))->assertOk()->assertJsonPath('data.role', 'admin_master');
    }

    public function test_the_primary_superadmin_is_protected(): void
    {
        $primary = $this->signIn(Role::SUPERADMIN, ['is_primary' => true, 'username' => 'utama.uji']);

        $this->deleteJson("/api/users/{$primary->id}")->assertStatus(409);
        $this->postJson("/api/users/{$primary->id}/toggle")->assertStatus(409);
        $this->putJson("/api/users/{$primary->id}", $this->body($primary, ['role' => 'kasir']))->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->putJson("/api/users/{$primary->id}", $this->body($primary, ['is_active' => false]))->assertUnprocessable()->assertJsonValidationErrors('is_active');

        $this->signIn(Role::SUPERADMIN, ['username' => 'lainnya.uji']);

        $this->putJson("/api/users/{$primary->id}", $this->body($primary, ['name' => 'Diganti']))->assertForbidden();
        $this->deleteJson("/api/users/{$primary->id}")->assertForbidden();
        $this->postJson("/api/users/{$primary->id}/toggle")->assertForbidden();
        $this->postJson("/api/users/{$primary->id}/reset-password")->assertForbidden();

        $this->assertTrue($primary->fresh()->is_active);
        $this->assertNull($primary->fresh()->deleted_at);
    }

    public function test_users_cannot_demote_deactivate_or_delete_themselves(): void
    {
        $me = $this->signIn(Role::SUPERADMIN, ['username' => 'saya.sendiri']);

        $this->putJson("/api/users/{$me->id}", $this->body($me, ['role' => 'kasir']))->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->putJson("/api/users/{$me->id}", $this->body($me, ['is_active' => false]))->assertUnprocessable()->assertJsonValidationErrors('is_active');
        $this->postJson("/api/users/{$me->id}/toggle")->assertStatus(409);
        $this->deleteJson("/api/users/{$me->id}")->assertStatus(409);
    }

    public function test_a_cashier_holding_waiting_orders_cannot_be_removed_or_demoted(): void
    {
        $this->signIn(Role::SUPERADMIN);
        $cashier = $this->makeUser(Role::CASHIER, ['username' => 'kasir.pegang']);
        $order = $this->draftOrder([[$this->product(), 1]]);
        $workflow = app(OrderWorkflowService::class);
        $workflow->approve($order, $cashier);

        $delete = $this->deleteJson("/api/users/{$cashier->id}")->assertStatus(409);
        $this->assertStringContainsString('menunggu pembayaran', $delete->json('message'));
        $this->postJson("/api/users/{$cashier->id}/toggle")->assertStatus(409);
        $this->putJson("/api/users/{$cashier->id}", $this->body($cashier, ['role' => 'admin_master']))->assertStatus(409);

        $workflow->cancel($order->fresh(), $cashier, 'Dibatalkan untuk pengujian');

        $this->deleteJson("/api/users/{$cashier->id}")->assertOk();
        $this->assertSoftDeleted('users', ['id' => $cashier->id]);
    }

    public function test_deleted_users_keep_their_username_reserved_and_appear_in_cashier_lookups(): void
    {
        $this->signIn(Role::SUPERADMIN);
        $cashier = $this->makeUser(Role::CASHIER, ['username' => 'kasir.lama', 'email' => 'kasir.lama@broseven.test', 'name' => 'Kasir Lama']);

        $this->deleteJson("/api/users/{$cashier->id}")->assertOk();

        $this->postJson('/api/users', [
            'name' => 'Kasir Lain', 'username' => 'kasir.lama', 'email' => 'lain@broseven.test', 'role' => 'kasir', 'password' => 'Rahasia123',
        ])->assertUnprocessable()->assertJsonValidationErrors('username');

        $names = collect($this->getJson('/api/lookups/cashiers')->assertOk()->json('data'))->pluck('name')->all();
        $this->assertContains('Kasir Lama (dihapus)', $names);
    }

    public function test_password_reset_is_shown_once_and_never_written_to_the_audit_log(): void
    {
        $this->signIn(Role::SUPERADMIN);
        $user = $this->makeUser(Role::CASHIER, ['username' => 'reset.saya']);

        $response = $this->postJson("/api/users/{$user->id}/reset-password")->assertOk()->assertJsonPath('generated', true);
        $plain = $response->json('password');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertGreaterThanOrEqual(8, strlen($plain));
        $this->assertTrue(Hash::check($plain, $user->fresh()->password));

        $log = AuditLog::where('action', 'user.password_reset')->first();
        $this->assertStringNotContainsString($plain, (string) $log->description);
        $this->assertStringNotContainsString($plain, json_encode([$log->old_values, $log->new_values]));

        $this->postJson("/api/users/{$user->id}/reset-password", ['password' => 'lemah'])->assertUnprocessable();
        $this->postJson("/api/users/{$user->id}/reset-password", ['password' => 'KataSandi99'])->assertOk()->assertJsonPath('generated', false);
        $this->assertTrue(Hash::check('KataSandi99', $user->fresh()->password));
    }

    public function test_listing_filters(): void
    {
        $this->signIn(Role::SUPERADMIN);
        $this->makeUser(Role::CASHIER, ['name' => 'Budi Kasir', 'username' => 'budi.kasir']);
        $this->makeUser(Role::ADMIN_MASTER, ['name' => 'Sari Admin', 'username' => 'sari.admin']);
        $this->makeUser(Role::CASHIER, ['name' => 'Tono Nonaktif', 'username' => 'tono.nonaktif', 'is_active' => false]);

        $this->getJson('/api/users?role=kasir')->assertOk()->assertJsonPath('total', 2);
        $this->getJson('/api/users?search=Sari')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/users?status=inactive')->assertOk()->assertJsonPath('total', 1);
    }
}