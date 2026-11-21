<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\DB;

class ExplainQueriesCommand extends Command
{
    protected $signature = 'dev:explain';

    protected $description = 'Menjalankan EXPLAIN pada query laporan dan daftar utama (hanya development).';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Perintah ini tidak boleh dijalankan di production.');

            return self::FAILURE;
        }

        $range = [now()->startOfMonth(), now()->endOfMonth()];
        $cashierId = (int) (User::query()->where('role', 'kasir')->value('id') ?? 1);
        $productId = (int) (Product::query()->value('id') ?? 1);

        $this->line('Data: orders='.Order::count().', stock_movements='.StockMovement::count().', status_histories='.OrderStatusHistory::count().', products='.Product::count());

        $queries = [
            'Pendapatan periode' => Order::query()->where('status', 'SELESAI')->whereBetween('completed_at', $range)->selectRaw('SUM(total_amount)'),
            'Rekap harian' => Order::query()->where('status', 'SELESAI')->whereBetween('completed_at', $range)->selectRaw('DATE(completed_at) as bucket, COUNT(*) as c, SUM(total_amount) as r')->groupByRaw('DATE(completed_at)'),
            'Kasir: diproses' => Order::query()->where('cashier_id', $cashierId)->whereBetween('approved_at', $range)->selectRaw('COUNT(*)'),
            'Kasir: selesai' => Order::query()->where('cashier_id', $cashierId)->where('status', 'SELESAI')->whereBetween('completed_at', $range)->selectRaw('COUNT(*), SUM(total_amount)'),
            'Kasir: dibatalkan' => Order::query()->where('cancelled_by', $cashierId)->whereBetween('cancelled_at', $range)->selectRaw('COUNT(*)'),
            'Antrean draft' => Order::query()->where('status', 'DRAFT')->orderByDesc('id')->limit(20),
            'Cari barcode' => Order::query()->where('barcode_code', str_repeat('A', 24)),
            'Cari nomor pesanan' => Order::query()->where('order_number', 'BS-000000-AAAAA'),
            'Riwayat stok per produk' => StockMovement::query()->where('product_id', $productId)->orderByDesc('id')->limit(10),
            'Riwayat stok per tanggal' => StockMovement::query()->whereBetween('created_at', $range)->orderByDesc('id')->limit(15),
            'Aktivitas kasir' => OrderStatusHistory::query()->where('user_id', $cashierId)->whereBetween('created_at', $range),
            'Retur per tanggal' => OrderReturn::query()->whereBetween('created_at', $range),
            'Barang terlaris' => DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.status', 'SELESAI')
                ->whereBetween('orders.completed_at', $range)
                ->selectRaw('order_items.product_id, SUM(order_items.quantity) as q')
                ->groupBy('order_items.product_id'),
            'Produk menipis' => Product::query()->where('is_active', true)->where('stock', '>', 0)->whereColumn('stock', '<=', 'min_stock'),
            'Cari produk (LIKE %x%)' => Product::query()->where('name', 'like', '%teh%'),
            'Customer unik' => Order::query()->selectRaw('COUNT(DISTINCT customer_phone)'),
        ];

        $table = [];

        foreach ($queries as $name => $query) {
            $base = $query instanceof EloquentBuilder ? $query->toBase() : $query;

            foreach (DB::select('EXPLAIN '.$base->toSql(), $base->getBindings()) as $row) {
                $row = (array) $row;
                $type = (string) ($row['type'] ?? '-');
                $rows = $row['rows'] ?? '-';
                $extra = (string) ($row['Extra'] ?? '');
                $flags = [];

                if ($type === 'ALL' && (int) $rows > 500) {
                    $flags[] = 'FULL SCAN';
                }

                if (str_contains($extra, 'filesort')) {
                    $flags[] = 'filesort';
                }

                $table[] = [$name, $row['table'] ?? '-', $type, $row['key'] ?? '-', $rows, implode(', ', $flags)];
            }
        }

        $this->table(['Query', 'Tabel', 'Type', 'Index dipakai', 'Baris', 'Catatan'], $table);

        return self::SUCCESS;
    }
}