<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderWorkflowService;
use App\Services\StockService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class StressApproveCommand extends Command
{
    protected $signature = 'inventory:stress-approve {--orders=12 : Jumlah pesanan uji} {--stock=5 : Stok awal produk uji} {--workers=8 : Jumlah proses paralel} {--force : Lewati konfirmasi}';

    protected $description = 'Uji konkurensi: beberapa proses PHP menyetujui pesanan yang sama secara bersamaan.';

    public function handle(OrderWorkflowService $workflow, StockService $stock): int
    {
        if (app()->environment('production')) {
            $this->error('Perintah ini tidak boleh dijalankan di production.');

            return self::FAILURE;
        }

        $orderCount = max(2, (int) $this->option('orders'));
        $stockQty = max(1, (int) $this->option('stock'));
        $workers = max(2, min(16, (int) $this->option('workers')));

        if (! $this->option('force') && ! $this->confirm('Perintah ini membuat data uji (produk, pesanan, kasir nonaktif) di database "'.config('database.default').'". Lanjutkan?')) {
            return self::SUCCESS;
        }

        $cashier = User::query()->firstOrCreate(
            ['username' => 'uji-konkurensi'],
            [
                'name' => 'Uji Konkurensi',
                'email' => 'uji-konkurensi@broseven.invalid',
                'password' => Str::random(40),
                'role' => Role::CASHIER,
                'is_active' => false,
                'is_primary' => false,
            ],
        );

        $product = Product::query()->create([
            'code' => 'TMP-'.Str::random(20),
            'name' => '[UJI KONKURENSI] '.now()->format('His'),
            'selling_price' => 1000,
            'stock' => 0,
            'min_stock' => 0,
            'is_active' => false,
        ]);
        $product->update(['code' => sprintf('BRG-%06d', $product->id)]);
        $stock->adjust($product, StockMovementType::STOCK_IN, $stockQty, 'Stok awal uji konkurensi', $cashier);

        $orderIds = [];

        for ($i = 1; $i <= $orderCount; $i++) {
            $order = Order::query()->create([
                'order_number' => 'UJI-'.strtoupper(Str::random(8)),
                'customer_name' => 'Uji Konkurensi',
                'customer_phone' => '080000000000',
                'status' => OrderStatus::DRAFT,
                'total_amount' => '1000.00',
                'payment_method' => 'CASH',
                'barcode_code' => strtoupper(bin2hex(random_bytes(12))),
            ]);

            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_code' => $product->code,
                'product_name' => $product->name,
                'unit_price' => '1000.00',
                'quantity' => 1,
                'subtotal' => '1000.00',
            ]);

            $orderIds[] = $order->id;
        }

        $this->info("Data uji siap: {$orderCount} pesanan, stok {$stockQty}, {$workers} proses paralel.");

        $startAt = microtime(true) + 3.0;
        $processes = [];

        for ($i = 1; $i <= $workers; $i++) {
            $process = new Process(
                [PHP_BINARY, base_path('artisan'), 'inventory:stress-worker', (string) $cashier->id, implode(',', $orderIds), (string) $startAt, (string) $i],
                base_path(),
            );
            $process->setTimeout(180);
            $process->start();
            $processes[] = $process;
        }

        $reportedApprovals = [];
        $workerErrors = [];

        foreach ($processes as $index => $process) {
            $process->wait();
            $lines = array_values(array_filter(array_map('trim', explode("\n", $process->getOutput()))));
            $decoded = json_decode((string) end($lines), true);

            if (! is_array($decoded)) {
                $workerErrors[] = 'Worker '.($index + 1).' gagal: '.trim($process->getErrorOutput() ?: $process->getOutput());
                continue;
            }

            array_push($reportedApprovals, ...$decoded['approved']);

            foreach ($decoded['errors'] as $error) {
                $workerErrors[] = 'Worker '.($index + 1).': '.$error;
            }
        }

        $product->refresh();
        $approvedOrders = Order::query()->whereIn('id', $orderIds)->where('status', OrderStatus::MENUNGGU->value)->count();
        $expected = min($orderCount, $stockQty);
        $saleMovements = StockMovement::query()->where('product_id', $product->id)->where('type', StockMovementType::SALE->value)->count();
        $ledger = (int) StockMovement::query()->where('product_id', $product->id)->sum('quantity_change');

        $checks = [
            ['Stok akhir tidak negatif', $product->stock >= 0],
            ["Pesanan disetujui = {$expected}", $approvedOrders === $expected],
            ['Stok akhir = stok awal - disetujui', $product->stock === $stockQty - $approvedOrders],
            ['Tiap persetujuan dilaporkan tepat sekali', count($reportedApprovals) === count(array_unique($reportedApprovals)) && count($reportedApprovals) === $approvedOrders],
            ['Satu movement SALE per persetujuan', $saleMovements === $approvedOrders],
            ['Jumlah riwayat = stok', $ledger === $product->stock],
            ['Tanpa error (deadlock, dll)', $workerErrors === []],
        ];

        $this->table(['Pemeriksaan', 'Hasil'], array_map(fn ($check) => [$check[0], $check[1] ? 'LULUS' : 'GAGAL'], $checks));

        foreach ($workerErrors as $error) {
            $this->warn($error);
        }

        $orders = Order::query()->whereIn('id', $orderIds)->get();

        foreach ($orders as $order) {
            $workflow->cancel($order, $cashier, 'Pembersihan uji konkurensi');
        }

        $product->refresh();
        $restored = $product->stock === $stockQty;
        $product->delete();

        $this->line('Pembersihan: semua pesanan uji dibatalkan, stok '.($restored ? 'kembali ke stok awal' : 'TIDAK kembali ke stok awal').', produk uji diarsipkan.');

        $passed = collect($checks)->every(fn ($check) => $check[1]) && $restored;

        $passed ? $this->info('HASIL: LULUS') : $this->error('HASIL: GAGAL');

        return $passed ? self::SUCCESS : self::FAILURE;
    }
}
