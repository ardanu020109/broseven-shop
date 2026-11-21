<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\StockService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedDemoHistoryCommand extends Command
{
    protected $signature = 'dev:seed-history {--orders=60 : Jumlah pesanan demo} {--days=45 : Sebaran hari ke belakang} {--purge : Hapus pesanan demo dan kembalikan stoknya}';

    protected $description = 'Membuat riwayat transaksi demo (hanya development) agar laporan dan grafik dapat diuji.';

    public function handle(StockService $stock): int
    {
        if (app()->environment('production')) {
            $this->error('Perintah ini tidak boleh dijalankan di production.');

            return self::FAILURE;
        }

        if ($this->option('purge')) {
            return $this->purge($stock);
        }

        $cashiers = User::query()->role(Role::CASHIER)->active()->get();
        $products = Product::query()->active()->where('selling_price', '>', 0)->get();

        if ($cashiers->isEmpty() || $products->isEmpty()) {
            $this->error('Butuh minimal satu kasir aktif dan satu produk aktif berharga jual > 0.');

            return self::FAILURE;
        }

        $count = max(1, (int) $this->option('orders'));
        $days = max(1, (int) $this->option('days'));
        $created = 0;

        for ($i = 0; $i < $count; $i++) {
            DB::transaction(function () use ($cashiers, $products, $days, $stock) {
                $cashier = $cashiers->random();
                $at = CarbonImmutable::now(config('app.timezone'))
                    ->subDays(random_int(0, $days - 1))
                    ->setTime(random_int(8, 20), random_int(0, 59), random_int(0, 59));
                $approvedAt = $at->addMinutes(random_int(1, 10));
                $finishedAt = $approvedAt->addMinutes(random_int(1, 15));
                $cancelled = random_int(1, 100) <= 15;

                $picked = $products->random(min($products->count(), random_int(1, 3)));
                $lines = [];
                $totalCents = 0;

                foreach ($picked as $product) {
                    $quantity = random_int(1, 4);
                    $unitCents = (int) round(((float) $product->selling_price) * 100);
                    $totalCents += $unitCents * $quantity;
                    $lines[] = [$product, $quantity, $unitCents];
                }

                $order = Order::query()->create([
                    'order_number' => 'BS-'.$at->format('ymd').'-'.strtoupper(Str::random(5)),
                    'customer_name' => '[DEMO] '.fake()->name(),
                    'customer_phone' => '08'.random_int(1000000000, 9999999999),
                    'status' => OrderStatus::DRAFT,
                    'total_amount' => sprintf('%d.%02d', intdiv($totalCents, 100), $totalCents % 100),
                    'payment_method' => 'CASH',
                    'barcode_code' => strtoupper(bin2hex(random_bytes(12))),
                ]);

                foreach ($lines as [$product, $quantity, $unitCents]) {
                    OrderItem::query()->create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_code' => $product->code,
                        'product_name' => $product->name,
                        'unit_price' => sprintf('%d.%02d', intdiv($unitCents, 100), $unitCents % 100),
                        'quantity' => $quantity,
                        'subtotal' => sprintf('%d.%02d', intdiv($unitCents * $quantity, 100), ($unitCents * $quantity) % 100),
                    ]);

                    $fresh = Product::query()->findOrFail($product->id);

                    if ($fresh->stock < $quantity) {
                        $this->backdate($stock->adjust($fresh, StockMovementType::STOCK_IN, $quantity * 5, 'Stok demo', null), $at->subMinute());
                        $fresh->refresh();
                    }

                    $this->backdate($stock->adjust($fresh, StockMovementType::SALE, -$quantity, "Penjualan {$order->order_number}", $cashier, $order), $approvedAt);

                    if ($cancelled) {
                        $this->backdate($stock->adjust($fresh, StockMovementType::SALE_CANCELLED, $quantity, "Pembatalan {$order->order_number}", $cashier, $order), $finishedAt);
                    }
                }

                $final = $cancelled ? OrderStatus::DIBATALKAN : OrderStatus::SELESAI;

                DB::table('orders')->where('id', $order->id)->update([
                    'status' => $final->value,
                    'cashier_id' => $cashier->id,
                    'approved_at' => $approvedAt,
                    'barcode_used_at' => $approvedAt,
                    'stock_deducted_at' => $approvedAt,
                    'stock_restored_at' => $cancelled ? $finishedAt : null,
                    'completed_at' => $cancelled ? null : $finishedAt,
                    'cancelled_by' => $cancelled ? $cashier->id : null,
                    'cancelled_at' => $cancelled ? $finishedAt : null,
                    'cancel_reason' => $cancelled ? 'Customer membatalkan pesanan (data demo)' : null,
                    'created_at' => $at,
                    'updated_at' => $finishedAt,
                ]);

                DB::table('order_status_histories')->insert([
                    ['order_id' => $order->id, 'from_status' => null, 'to_status' => 'DRAFT', 'user_id' => null, 'note' => 'Pesanan dibuat oleh customer.', 'created_at' => $at],
                    ['order_id' => $order->id, 'from_status' => 'DRAFT', 'to_status' => 'MENUNGGU', 'user_id' => $cashier->id, 'note' => 'Disetujui kasir (data demo).', 'created_at' => $approvedAt],
                    ['order_id' => $order->id, 'from_status' => 'MENUNGGU', 'to_status' => $final->value, 'user_id' => $cashier->id, 'note' => $cancelled ? 'Dibatalkan kasir (data demo).' : 'Transaksi diselesaikan (data demo).', 'created_at' => $finishedAt],
                ]);
            });

            $created++;
        }

        $this->info("{$created} pesanan demo dibuat. Hapus dengan: php artisan dev:seed-history --purge");

        return self::SUCCESS;
    }

    private function purge(StockService $stock): int
    {
        $orders = Order::query()->where('customer_name', 'like', '[DEMO]%')->with('items')->get();

        foreach ($orders as $order) {
            DB::transaction(function () use ($order, $stock) {
                if ($order->stock_deducted_at !== null && $order->stock_restored_at === null) {
                    foreach ($order->items as $item) {
                        $product = Product::withTrashed()->find($item->product_id);

                        if ($product !== null) {
                            $stock->adjust($product, StockMovementType::ADJUSTMENT, (int) $item->quantity, "Pembersihan data demo {$order->order_number}");
                        }
                    }
                }

                $order->delete();
            });
        }

        $this->info($orders->count().' pesanan demo dihapus dan stoknya dikembalikan.');

        return self::SUCCESS;
    }

    private function backdate($movement, CarbonImmutable $when): void
    {
        DB::table('stock_movements')->where('id', $movement->id)->update(['created_at' => $when]);
    }
}
