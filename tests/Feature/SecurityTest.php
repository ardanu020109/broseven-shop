<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BroSevenHelpers;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use BroSevenHelpers;
    use RefreshDatabase;

    public function test_purchase_price_is_visible_only_to_admin_master(): void
    {
        $product = $this->product(['purchase_price' => 4000]);

        $this->signIn(Role::ADMIN_MASTER);
        $this->getJson("/api/products/{$product->id}")->assertOk()->assertJsonPath('data.purchase_price', 4000);

        $this->signIn(Role::SUPERADMIN);
        $this->getJson("/api/products/{$product->id}")->assertOk()->assertJsonMissingPath('data.purchase_price');
        $this->getJson('/api/products')->assertOk()->assertJsonMissingPath('data.0.purchase_price');
    }

    public function test_profile_update_cannot_change_role_or_primary_flag(): void
    {
        $user = $this->signIn(Role::CASHIER, ['username' => 'kasir.aman', 'email' => 'kasir.aman@broseven.test']);

        $this->post('/api/profile', [
            'name' => 'Kasir Aman',
            'username' => 'kasir.aman',
            'email' => 'kasir.aman@broseven.test',
            'role' => 'superadmin',
            'is_primary' => 1,
            'is_active' => 0,
        ], ['Accept' => 'application/json'])->assertOk();

        $fresh = $user->fresh();
        $this->assertSame(Role::CASHIER, $fresh->role);
        $this->assertFalse($fresh->is_primary);
        $this->assertTrue($fresh->is_active);
    }

    public function test_creating_users_cannot_set_the_primary_flag(): void
    {
        $this->signIn(Role::SUPERADMIN);

        $response = $this->postJson('/api/users', [
            'name' => 'Kasir Baru',
            'username' => 'kasir.baru',
            'email' => 'kasir.baru@broseven.test',
            'role' => 'kasir',
            'password' => 'Rahasia123',
            'is_primary' => true,
        ])->assertCreated();

        $this->assertFalse(User::find($response->json('data.id'))->is_primary);
        $this->assertTrue(Hash::check('Rahasia123', User::find($response->json('data.id'))->password));
    }

    public function test_order_creation_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/store/orders', [])->assertUnprocessable();
        }

        $this->postJson('/api/store/orders', [])->assertStatus(429);
    }

    public function test_api_routes_run_inside_the_web_group_so_csrf_is_enforced(): void
    {
        foreach (['/api/store/orders', '/api/auth/login', '/api/kasir/orders/lookup', '/api/users'] as $uri) {
            $route = app('router')->getRoutes()->match(Request::create($uri, 'POST'));

            $this->assertContains('web', $route->gatherMiddleware(), "Route {$uri} harus berada di grup web.");
        }
    }

    public function test_unknown_api_paths_return_an_indonesian_json_404(): void
    {
        $this->getJson('/api/tidak-ada')->assertNotFound()->assertJsonPath('message', 'Data tidak ditemukan.');
    }

    public function test_guests_never_reach_staff_endpoints(): void
    {
        foreach (['/api/users', '/api/products', '/api/kasir/orders', '/api/reports/revenue', '/api/settings/colors'] as $uri) {
            $this->getJson($uri)->assertUnauthorized();
        }
    }
}