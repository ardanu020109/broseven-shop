<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Services\OrderWorkflowService;
use App\Services\ReturnService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BroSevenHelpers;
use Tests\TestCase;

class InventoryIntegrityTest extends TestCase
{
    use BroSevenHelpers;
    use RefreshDatabase;

    public function test_a_mixed_flow_keeps_stock_ledger_orders_and_returns_consistent(): void
    {
        $cashier = $this->makeUser(Role::CASHIER);
        $p1 = $this->product(['stock' => 50]);
        $p2 = $this->product(['stock' => 30]);
        $workflow = app(OrderWorkflowService::class);

        $o1 = $this->completedOrder([[$p1, 5], [$p2, 2]], $cashier);
        $item = $o1->items()->where('product_id', $p1->id)->first();

        app(ReturnService::class)->create($o1, $cashier, [
            'reason' => 'Barang rusak',
            'idempotency_key' => (string) Str::uuid(),
            'items' => [['order_item_id' => $item->id, 'quantity' => 2]],
        ]);

        $o2 = $this->draftOrder([[$p1, 4]]);
        $workflow->approve($o2, $cashier);
        $workflow->cancel($o2->fresh(), $cashier, 'Customer batal');

        $o3 = $this->draftOrder([[$p2, 1]]);
        $workflow->cancel($o3, $cashier, 'Salah pesan barang');

        $o4 = $this->draftOrder([[$p2, 3]]);
        $workflow->approve($o4, $cashier);

        app(StockService::class)->adjust($p1, StockMovementType::STOCK_IN, 10, 'Tambah stok');

        $this->assertSame(57, $this->stockOf($p1));
        $this->assertSame(25, $this->stockOf($p2));

        foreach (Product::all() as $product) {
            $this->assertSame($this->ledgerSum($product), (int) $product->stock, "Riwayat tidak cocok untuk {$product->name}");
        }

        $this->assertSame(0, Artisan::call('inventory:reconcile'), Artisan::output());
    }

    // public function test_reconcile_detects_manual_stock_drift_and_fix_aligns_the_ledger(): void
    // {
    //     $product = $this->product(['stock' => 10]);

    //     DB::table('products')->where('id', $product->id)->update(['stock' => 13]);

    //     $this->assertSame(1, Artisan::call('inventory:reconcile'));
    //     $this->assertStringContainsString('Stok vs riwayat', Artisan::output());

    //     Artisan::call('inventory:reconcile', ['--fix' => true]);

    //     $this->assertSame(0, Artisan::call('inventory:reconcile'), Artisan::output());
    //     $this->assertSame(13, $this->stockOf($product));
    //     $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'type' => 'ADJUSTMENT', 'quantity_change' => 3]);
    // }

    // public function test_reconcile_detects_inconsistent_order_markers_and_return_counters(): void
    // {
    //     $cashier = $this->makeUser(Role::CASHIER);
    //     $product = $this->product(['stock' => 10]);

    //     $draft = $this->draftOrder([[$product, 1]]);
    //     DB::table('orders')->where('id', $draft->id)->update(['stock_deducted_at' => now()]);

    //     $this->assertSame(1, Artisan::call('inventory:reconcile'));
    //     $this->assertStringContainsString('Pesanan vs stok', Artisan::output());

    //     DB::table('orders')->where('id', $draft->id)->update(['stock_deducted_at' => null]);
    //     $this->assertSame(0, Artisan::call('inventory:reconcile'), Artisan::output());

    //     $done = $this->completedOrder([[$product, 2]], $cashier);
    //     DB::table('order_items')->where('order_id', $done->id)->update(['returned_quantity' => 1]);

    //     $this->assertSame(1, Artisan::call('inventory:reconcile'));
    //     $this->assertStringContainsString('Retur', Artisan::output());
    // }

    public function test_database_check_constraints_are_the_last_line_of_defence(): void
    {
        $product = $this->product(['stock' => 5]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('products')->where('id', $product->id)->update(['selling_price' => -1]);
    }
}
