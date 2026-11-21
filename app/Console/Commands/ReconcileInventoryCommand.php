<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileInventoryCommand extends Command
{
    protected $signature = 'inventory:reconcile {--fix : Tambahkan movement ADJUSTMENT agar riwayat cocok dengan stok saat ini (stok tidak diubah)}';

    protected $description = 'Memeriksa konsistensi stok, riwayat pergerakan stok, pesanan, dan retur.';

    private array $issues = [];

    public function handle(): int
    {
        if ($this->option('fix')) {
            $fixed = $this->applyFix();
            $this->info("{$fixed} selisih riwayat diselaraskan dengan movement ADJUSTMENT.");
        }

        $this->checkStockAgainstLedger();
        $this->checkLedgerChain();
        $this->checkOrders();
        $this->checkReturns();

        $total = array_sum(array_map('count', $this->issues));

        if ($total === 0) {
            $this->info('Semua konsisten. Stok, riwayat, pesanan, dan retur saling cocok.');

            return self::SUCCESS;
        }

        foreach ($this->issues as $group => $rows) {
            if ($rows === []) {
                continue;
            }

            $this->newLine();
            $this->error("{$group}: ".count($rows).' masalah');
            $this->table(['Referensi', 'Keterangan'], array_slice($rows, 0, 15));

            if (count($rows) > 15) {
                $this->line('... dan '.(count($rows) - 15).' lainnya.');
            }
        }

        $this->newLine();
        $this->error("Ditemukan {$total} masalah konsistensi.");

        return self::FAILURE;
    }

    private function addIssue(string $group, string $reference, string $detail): void
    {
        $this->issues[$group][] = [$reference, $detail];
    }

    private function driftRows(): array
    {
        $ledger = DB::table('stock_movements')
            ->select('product_id', DB::raw('SUM(quantity_change) as total'))
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        $rows = [];

        Product::withTrashed()->orderBy('id')->chunkById(200, function ($products) use ($ledger, &$rows) {
            foreach ($products as $product) {
                $sum = (int) ($ledger[$product->id] ?? 0);

                if ($sum !== (int) $product->stock) {
                    $rows[] = ['product' => $product, 'ledger' => $sum];
                }
            }
        });

        return $rows;
    }

    private function applyFix(): int
    {
        $fixed = 0;

        foreach ($this->driftRows() as $row) {
            $product = $row['product'];

            if ($row['ledger'] < 0) {
                continue;
            }

            StockMovement::create([
                'product_id' => $product->id,
                'type' => StockMovementType::ADJUSTMENT,
                'quantity_change' => (int) $product->stock - $row['ledger'],
                'stock_before' => $row['ledger'],
                'stock_after' => (int) $product->stock,
                'note' => 'Rekonsiliasi otomatis: menyelaraskan riwayat dengan stok tercatat',
                'user_id' => null,
            ]);

            $fixed++;
        }

        return $fixed;
    }

    private function checkStockAgainstLedger(): void
    {
        foreach ($this->driftRows() as $row) {
            $product = $row['product'];

            $this->addIssue(
                'Stok vs riwayat',
                "{$product->code} {$product->name}",
                "Stok {$product->stock}, jumlah riwayat {$row['ledger']}",
            );
        }
    }

    private function checkLedgerChain(): void
    {
        $previous = [];

        DB::table('stock_movements')->orderBy('id')->chunkById(1000, function ($movements) use (&$previous) {
            foreach ($movements as $movement) {
                $expectedBefore = $previous[$movement->product_id] ?? 0;

                if ((int) $movement->stock_before !== $expectedBefore) {
                    $this->addIssue(
                        'Rantai riwayat',
                        "Movement #{$movement->id} (produk {$movement->product_id})",
                        "stock_before {$movement->stock_before}, seharusnya {$expectedBefore}",
                    );
                }

                if ((int) $movement->stock_before + (int) $movement->quantity_change !== (int) $movement->stock_after) {
                    $this->addIssue(
                        'Rantai riwayat',
                        "Movement #{$movement->id} (produk {$movement->product_id})",
                        'stock_before + perubahan tidak sama dengan stock_after',
                    );
                }

                $previous[$movement->product_id] = (int) $movement->stock_after;
            }
        });
    }

    private function checkOrders(): void
    {
        $orderType = (new Order)->getMorphClass();

        $sums = fn (StockMovementType $type) => DB::table('stock_movements')
            ->where('type', $type->value)
            ->where('reference_type', $orderType)
            ->select('reference_id', DB::raw('SUM(quantity_change) as total'))
            ->groupBy('reference_id')
            ->pluck('total', 'reference_id');

        $sold = $sums(StockMovementType::SALE);
        $restored = $sums(StockMovementType::SALE_CANCELLED);

        $units = DB::table('order_items')
            ->select('order_id', DB::raw('SUM(quantity) as total'))
            ->groupBy('order_id')
            ->pluck('total', 'order_id');

        Order::query()->orderBy('id')->chunkById(200, function ($orders) use ($sold, $restored, $units) {
            foreach ($orders as $order) {
                $quantity = (int) ($units[$order->id] ?? 0);
                $deducted = $order->stock_deducted_at !== null;
                $wasRestored = $order->stock_restored_at !== null;
                $reference = $order->order_number;

                $expectedSold = $deducted ? -$quantity : 0;
                $actualSold = (int) ($sold[$order->id] ?? 0);

                if ($actualSold !== $expectedSold) {
                    $this->addIssue('Pesanan vs stok', $reference, "Movement SALE {$actualSold}, seharusnya {$expectedSold}");
                }

                $expectedRestored = $wasRestored ? $quantity : 0;
                $actualRestored = (int) ($restored[$order->id] ?? 0);

                if ($actualRestored !== $expectedRestored) {
                    $this->addIssue('Pesanan vs stok', $reference, "Movement SALE_CANCELLED {$actualRestored}, seharusnya {$expectedRestored}");
                }

                $statusProblem = match ($order->status) {
                    OrderStatus::DRAFT => $deducted || $wasRestored,
                    OrderStatus::MENUNGGU, OrderStatus::SELESAI => ! $deducted || $wasRestored,
                    OrderStatus::DIBATALKAN => $deducted !== $wasRestored,
                };

                if ($statusProblem) {
                    $this->addIssue('Pesanan vs stok', $reference, "Penanda stok tidak sesuai status {$order->status->value}");
                }
            }
        });
    }

    private function checkReturns(): void
    {
        $returnedByItem = DB::table('order_return_items')
            ->select('order_item_id', DB::raw('SUM(quantity) as total'))
            ->groupBy('order_item_id')
            ->pluck('total', 'order_item_id');

        OrderItem::query()->orderBy('id')->chunkById(500, function ($items) use ($returnedByItem) {
            foreach ($items as $item) {
                $recorded = (int) ($returnedByItem[$item->id] ?? 0);

                if ((int) $item->returned_quantity !== $recorded) {
                    $this->addIssue(
                        'Retur',
                        "Item pesanan #{$item->id} ({$item->product_name})",
                        "returned_quantity {$item->returned_quantity}, jumlah di data retur {$recorded}",
                    );
                }
            }
        });

        $returnType = (new OrderReturn)->getMorphClass();

        $movedByReturn = DB::table('stock_movements')
            ->where('type', StockMovementType::RETURN->value)
            ->where('reference_type', $returnType)
            ->select('reference_id', DB::raw('SUM(quantity_change) as total'))
            ->groupBy('reference_id')
            ->pluck('total', 'reference_id');

        $quantityByReturn = DB::table('order_return_items')
            ->select('order_return_id', DB::raw('SUM(quantity) as total'))
            ->groupBy('order_return_id')
            ->pluck('total', 'order_return_id');

        OrderReturn::query()->orderBy('id')->chunkById(200, function ($returns) use ($movedByReturn, $quantityByReturn) {
            foreach ($returns as $return) {
                $quantity = (int) ($quantityByReturn[$return->id] ?? 0);
                $moved = (int) ($movedByReturn[$return->id] ?? 0);

                if ($quantity !== $moved) {
                    $this->addIssue('Retur', $return->return_number, "Jumlah retur {$quantity}, movement RETURN {$moved}");
                }
            }
        });

        OrderReturnItem::query()->count();
    }
}
