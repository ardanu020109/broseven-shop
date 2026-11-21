<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BroSevenHelpers;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use BroSevenHelpers;
    use RefreshDatabase;

    public static function endpoints(): array
    {
        $su = ['superadmin'];
        $kasir = ['kasir'];
        $admin = ['admin_master'];
        $both = ['superadmin', 'admin_master'];
        $all = ['superadmin', 'kasir', 'admin_master'];

        return [
            'profile' => ['GET', '/api/profile', $all],
            'superadmin dashboard' => ['GET', '/api/superadmin/dashboard', $su],
            'users' => ['GET', '/api/users', $su],
            'cashier lookup' => ['GET', '/api/lookups/cashiers', $su],
            'revenue report' => ['GET', '/api/reports/revenue', $su],
            'revenue export' => ['GET', '/api/reports/revenue/export?format=print', $su],
            'cashier report' => ['GET', '/api/reports/cashiers', $su],
            'cashier export' => ['GET', '/api/reports/cashiers/export?format=print', $su],
            'monitor' => ['GET', '/api/monitor/orders', $su],
            'settings company' => ['GET', '/api/settings/company', $su],
            'settings colors' => ['GET', '/api/settings/colors', $su],
            'settings colors reset' => ['POST', '/api/settings/colors/reset', $su],
            'inventory report' => ['GET', '/api/reports/inventory', $both],
            'inventory export' => ['GET', '/api/reports/inventory/export?format=print', $both],
            'products list' => ['GET', '/api/products', $both],
            'lookups' => ['GET', '/api/lookups', $both],
            'inventory summary' => ['GET', '/api/inventory/summary', $both],
            'stock movements' => ['GET', '/api/inventory/movements', $both],
            'product create' => ['POST', '/api/products', $admin],
            'categories list' => ['GET', '/api/categories', $admin],
            'brands create' => ['POST', '/api/brands', $admin],
            'admin master dashboard' => ['GET', '/api/admin-master/dashboard', $admin],
            'kasir dashboard' => ['GET', '/api/kasir/dashboard', $kasir],
            'kasir orders' => ['GET', '/api/kasir/orders', $kasir],
            'kasir counts' => ['GET', '/api/kasir/orders/counts', $kasir],
            'kasir lookup' => ['POST', '/api/kasir/orders/lookup', $kasir],
            'kasir returns' => ['GET', '/api/kasir/returns', $kasir],
            'kasir report summary' => ['GET', '/api/kasir/reports/summary', $kasir],
            'kasir report transactions' => ['GET', '/api/kasir/reports/transactions', $kasir],
            'kasir report export' => ['GET', '/api/kasir/reports/export?format=print', $kasir],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_role_matrix(string $method, string $uri, array $allowed): void
    {
        $this->json($method, $uri)->assertUnauthorized();

        foreach (Role::cases() as $role) {
            $this->signIn($role);
            $status = $this->json($method, $uri)->getStatusCode();

            if (in_array($role->value, $allowed, true)) {
                $this->assertContains($status, [200, 201, 422], "{$role->value} seharusnya boleh {$method} {$uri} (status {$status})");
            } else {
                $this->assertSame(403, $status, "{$role->value} seharusnya ditolak {$method} {$uri} (status {$status})");
            }
        }
    }
}