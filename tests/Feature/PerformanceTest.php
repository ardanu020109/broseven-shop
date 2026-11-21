<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BroSevenHelpers;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use BroSevenHelpers;
    use RefreshDatabase;

    private User $cashier;

    private function grow(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $a = $this->product(['stock' => 100]);
            $b = $this->product(['stock' => 100]);

            $this->completedOrder([[$a, 2], [$b, 1]], $this->cashier, null, ['customer_name' => 'Pelanggan '.Str::random(6)]);
            $this->draftOrder([[$a, 1]]);
        }
    }

    private function measure(User|Role $actor, string $uri): int
    {
        $user = $actor instanceof User ? $actor : $this->makeUser($actor);
        $this->actingAs($user);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson($uri);
        $count = count(DB::getQueryLog());

        DB::disableQueryLog();
        $response->assertOk();

        return $count;
    }

    public function test_listing_endpoints_do_not_gain_queries_when_the_data_grows(): void
    {
        $this->cashier = $this->makeUser(Role::CASHIER);

        $endpoints = [
            [Role::ADMIN_MASTER, '/api/products?per_page=50'],
            [Role::ADMIN_MASTER, '/api/inventory/movements?per_page=50'],
            [Role::ADMIN_MASTER, '/api/reports/inventory?per_page=50'],
            [Role::ADMIN_MASTER, '/api/categories?per_page=50'],
            [Role::ADMIN_MASTER, '/api/admin-master/dashboard'],
            [Role::SUPERADMIN, '/api/users?per_page=50'],
            [Role::SUPERADMIN, '/api/monitor/orders?per_page=50'],
            [Role::SUPERADMIN, '/api/reports/revenue/transactions?period=month&per_page=50'],
            [Role::SUPERADMIN, '/api/superadmin/dashboard'],
            [$this->cashier, '/api/kasir/orders?status=DRAFT&per_page=50'],
            [$this->cashier, '/api/kasir/orders?status=SELESAI&per_page=50'],
            [$this->cashier, '/api/kasir/reports/transactions?period=month&per_page=50'],
            [Role::ADMIN_MASTER, '/api/store/products?per_page=48'],
        ];

        $this->grow(3);

        $before = [];

        foreach ($endpoints as [$actor, $uri]) {
            $before[$uri] = $this->measure($actor, $uri);
        }

        $this->grow(8);

        foreach ($endpoints as [$actor, $uri]) {
            $after = $this->measure($actor, $uri);

            $this->assertSame($before[$uri], $after, "Jumlah query {$uri} naik dari {$before[$uri]} menjadi {$after}: kemungkinan N+1.");
            $this->assertLessThanOrEqual(40, $after, "{$uri} memakai {$after} query.");
        }
    }
}