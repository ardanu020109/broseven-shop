<?php

namespace Tests\Support;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderWorkflowService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use ZipArchive;

trait BroSevenHelpers
{
    protected function makeUser(Role $role, array $attributes = []): User
    {
        $factory = User::factory();

        $factory = match ($role) {
            Role::SUPERADMIN => $factory->superadmin(),
            Role::CASHIER => $factory->cashier(),
            Role::ADMIN_MASTER => $factory->adminMaster(),
        };

        return $factory->create($attributes);
    }

    protected function signIn(Role $role, array $attributes = []): User
    {
        $user = $this->makeUser($role, $attributes);
        $this->actingAs($user);

        return $user;
    }

    protected function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'selling_price' => 10000,
            'stock' => 10,
            'min_stock' => 2,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * @param  array<int, array{0: Product, 1: int}>  $lines
     */
    protected function draftOrder(array $lines, array $attributes = []): Order
    {
        $total = 0;

        foreach ($lines as [$product, $quantity]) {
            $total += (float) $product->selling_price * $quantity;
        }

        $order = Order::create(array_merge([
            'order_number' => 'BS-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
            'customer_name' => 'Customer Uji',
            'customer_phone' => '081234567890',
            'status' => OrderStatus::DRAFT,
            'total_amount' => number_format($total, 2, '.', ''),
            'payment_method' => 'CASH',
            'barcode_code' => strtoupper(bin2hex(random_bytes(12))),
        ], $attributes));

        foreach ($lines as [$product, $quantity]) {
            $unit = (float) $product->selling_price;

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_code' => $product->code,
                'product_name' => $product->name,
                'unit_price' => number_format($unit, 2, '.', ''),
                'quantity' => $quantity,
                'subtotal' => number_format($unit * $quantity, 2, '.', ''),
            ]);
        }

        return $order->fresh();
    }

    protected function completedOrder(array $lines, User $cashier, ?string $at = null, array $attributes = []): Order
    {
        $order = $this->draftOrder($lines, $attributes);

        if ($at !== null) {
            Carbon::setTestNow($at);
        }

        $workflow = app(OrderWorkflowService::class);
        $workflow->approve($order, $cashier, 'manual');

        return $workflow->complete($order->fresh(), $cashier)->fresh();
    }

    protected function stockOf(Product $product): int
    {
        return (int) Product::withTrashed()->whereKey($product->getKey())->value('stock');
    }

    protected function ledgerSum(Product $product): int
    {
        return (int) StockMovement::query()->where('product_id', $product->getKey())->sum('quantity_change');
    }

    protected function binaryBody($response): string
    {
        $file = $response->baseResponse->getFile()->getPathname();
        $content = (string) file_get_contents($file);
        @unlink($file);

        return $content;
    }

    /**
     * @return array{sheets: array<string, string>, all: string}
     */
    protected function xlsxContents(string $binary): array
    {
        $path = tempnam(sys_get_temp_dir(), 'bsx');
        file_put_contents($path, $binary);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'Berkas xlsx tidak dapat dibuka sebagai zip.');

        $sheets = [];
        $all = '';

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $xml = (string) $zip->getFromIndex($i);
            $all .= $xml;

            if (str_starts_with($name, 'xl/worksheets/')) {
                $sheets[$name] = $xml;
            }
        }

        $zip->close();
        @unlink($path);

        return ['sheets' => $sheets, 'all' => $all];
    }
}