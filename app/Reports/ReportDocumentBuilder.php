<?php

namespace App\Reports;

use App\Enums\Role;
use App\Exceptions\ReportTooLargeException;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\User;
use App\Services\ReportService;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;

final class ReportDocumentBuilder
{
    private const STATUS_LABELS = [
        'tersedia' => 'Tersedia',
        'menipis' => 'Hampir habis',
        'habis' => 'Habis',
        'nonaktif' => 'Tidak aktif',
    ];

    public function __construct(private ReportService $reports)
    {
    }

    public function revenue(ReportPeriod $period, ?int $cashierId, int $limit, string $format): ReportDocument
    {
        $orders = $this->reports->completedOrders($period, $cashierId);
        $this->guard((clone $orders)->count(), $limit, $format);

        $summary = $this->reports->revenueSummary($period, $cashierId);

        $series = array_values(array_filter(
            $this->reports->series($period, $cashierId),
            fn (array $row) => $row['transactions'] > 0,
        ));

        $seriesRows = array_map(fn (array $row) => [
            ReportFormat::bucket($row['key'], $period->granularity),
            $row['transactions'],
            $row['revenue'],
        ], $series);

        $details = (clone $orders)
            ->with('cashier:id,name')
            ->withSum('items as total_quantity', 'quantity')
            ->orderBy('completed_at')
            ->orderBy('id')
            ->get();

        $detailRows = $details->map(fn (Order $order) => [
            ReportFormat::dateTime($order->completed_at),
            $order->order_number,
            $order->cashier?->name ?? '-',
            $order->customer_name,
            (int) $order->total_quantity,
            (float) $order->total_amount,
        ])->all();

        $groupTitle = match ($period->granularity) {
            'hour' => 'Jam',
            'month' => 'Bulan',
            default => 'Hari',
        };

        return new ReportDocument(
            title: 'Laporan Pendapatan',
            slug: 'laporan-pendapatan',
            fileTag: ReportFormat::periodTag($period),
            meta: [
                ['label' => 'Periode', 'value' => ReportFormat::period($period)],
                ['label' => 'Kasir', 'value' => $this->cashierName($cashierId)],
                ['label' => 'Dasar perhitungan', 'value' => 'Hanya transaksi berstatus SELESAI, dihitung per tanggal penyelesaian.'],
            ],
            kpis: [
                ['label' => 'Total Pendapatan (kotor)', 'value' => $summary['gross_revenue'], 'type' => 'money'],
                ['label' => 'Nilai Retur ('.ReportFormat::int($summary['returns_count']).' retur)', 'value' => $summary['returns_total'], 'type' => 'money'],
                ['label' => 'Pendapatan Bersih', 'value' => $summary['net_revenue'], 'type' => 'money'],
                ['label' => 'Transaksi Selesai', 'value' => $summary['transactions'], 'type' => 'int'],
                ['label' => 'Rata-rata Transaksi', 'value' => $summary['average'], 'type' => 'money'],
                ['label' => 'Total Produk Terjual', 'value' => $summary['units_sold'], 'type' => 'int'],
            ],
            tables: [
                [
                    'title' => "Rekap per {$groupTitle}",
                    'columns' => [
                        ['label' => $groupTitle, 'type' => 'text'],
                        ['label' => 'Transaksi', 'type' => 'int'],
                        ['label' => 'Pendapatan', 'type' => 'money'],
                    ],
                    'rows' => $seriesRows,
                    'totals' => ['Total', array_sum(array_column($series, 'transactions')), round(array_sum(array_column($series, 'revenue')), 2)],
                    'empty' => 'Tidak ada transaksi selesai pada periode ini.',
                ],
                [
                    'title' => 'Detail Transaksi',
                    'columns' => [
                        ['label' => 'Tanggal', 'type' => 'text'],
                        ['label' => 'No. Transaksi', 'type' => 'text'],
                        ['label' => 'Kasir', 'type' => 'text'],
                        ['label' => 'Customer', 'type' => 'text'],
                        ['label' => 'Barang', 'type' => 'int'],
                        ['label' => 'Total Pembayaran', 'type' => 'money'],
                    ],
                    'rows' => $detailRows,
                    'totals' => ['Total', null, null, null, array_sum(array_column($detailRows, 4)), round(array_sum(array_column($detailRows, 5)), 2)],
                    'empty' => 'Tidak ada transaksi selesai pada periode ini.',
                ],
            ],
        );
    }

    public function cashiers(ReportPeriod $period, ?int $cashierId, int $limit, string $format): ReportDocument
    {
        $report = $this->reports->cashierPerformance($period, $cashierId);

        $rows = array_map(fn (array $row) => [
            $row['name'].($row['is_active'] ? '' : ' (nonaktif)'),
            $row['handled'],
            $row['completed'],
            $row['revenue'],
            $row['average'],
            $row['units_sold'],
            $row['cancelled'],
            $row['returns_count'],
            $row['returns_total'],
            $row['net_revenue'],
            $row['avg_service_minutes'],
        ], $report['rows']);

        $totals = $report['totals'];

        $tables = [[
            'title' => 'Kinerja Kasir',
            'columns' => [
                ['label' => 'Kasir', 'type' => 'text'],
                ['label' => 'Diproses', 'type' => 'int'],
                ['label' => 'Selesai', 'type' => 'int'],
                ['label' => 'Pendapatan', 'type' => 'money'],
                ['label' => 'Rata-rata', 'type' => 'money'],
                ['label' => 'Barang Terjual', 'type' => 'int'],
                ['label' => 'Dibatalkan', 'type' => 'int'],
                ['label' => 'Retur', 'type' => 'int'],
                ['label' => 'Nilai Retur', 'type' => 'money'],
                ['label' => 'Pendapatan Bersih', 'type' => 'money'],
                ['label' => 'Waktu Pelayanan (menit)', 'type' => 'minutes'],
            ],
            'rows' => $rows,
            'totals' => [
                'Total',
                $totals['handled'],
                $totals['completed'],
                $totals['revenue'],
                $totals['average'],
                $totals['units_sold'],
                $totals['cancelled'],
                $totals['returns_count'],
                $totals['returns_total'],
                $totals['net_revenue'],
                $totals['avg_service_minutes'],
            ],
            'empty' => 'Belum ada data kasir pada periode ini.',
        ]];

        $activityQuery = OrderStatusHistory::query()
            ->whereNotNull('user_id')
            ->whereBetween('created_at', $period->bounds())
            ->whereHas('user', fn ($q) => $q->where('role', Role::CASHIER->value))
            ->when($cashierId, fn ($q) => $q->where('user_id', $cashierId));

        $activityCount = (clone $activityQuery)->count();
        $activityLimit = min($limit, (int) config('broseven.reports.activity_max_rows', 1000));
        $note = null;

        if ($activityCount <= $activityLimit) {
            $activities = (clone $activityQuery)
                ->with(['user:id,name', 'order:id,order_number,customer_name'])
                ->orderBy('id')
                ->get()
                ->map(fn (OrderStatusHistory $history) => [
                    ReportFormat::dateTime($history->created_at),
                    $history->user?->name ?? '-',
                    $history->order?->order_number ?? '-',
                    $history->order?->customer_name ?? '-',
                    ($history->from_status?->label() ?? '-').' → '.$history->to_status->label(),
                    $history->note ?? '-',
                ])->all();

            $tables[] = [
                'title' => 'Riwayat Aktivitas',
                'columns' => [
                    ['label' => 'Waktu', 'type' => 'text'],
                    ['label' => 'Kasir', 'type' => 'text'],
                    ['label' => 'Pesanan', 'type' => 'text'],
                    ['label' => 'Customer', 'type' => 'text'],
                    ['label' => 'Perubahan Status', 'type' => 'text'],
                    ['label' => 'Keterangan', 'type' => 'text'],
                ],
                'rows' => $activities,
                'totals' => null,
                'empty' => 'Belum ada aktivitas pada periode ini.',
            ];
        } else {
            $note = 'Riwayat aktivitas tidak disertakan karena jumlahnya ('.ReportFormat::int($activityCount).' baris) melebihi batas '.ReportFormat::int($activityLimit).'. Persempit periode atau pilih satu kasir.';
        }

        return new ReportDocument(
            title: 'Laporan Kasir',
            slug: 'laporan-kasir',
            fileTag: ReportFormat::periodTag($period),
            meta: [
                ['label' => 'Periode', 'value' => ReportFormat::period($period)],
                ['label' => 'Kasir', 'value' => $this->cashierName($cashierId)],
                ['label' => 'Waktu pelayanan', 'value' => 'Rata-rata dari persetujuan sampai penyelesaian transaksi.'],
            ],
            tables: $tables,
            orientation: 'landscape',
            note: $note,
        );
    }

    public function inventory(array $filters, int $limit, string $format): ReportDocument
    {
        $status = $filters['status'] ?? null;
        $query = $this->reports->applyInventoryStatus($this->reports->inventoryBase($filters), $status);

        $this->guard((clone $query)->count(), $limit, $format);

        $count = fn (?string $state) => $this->reports
            ->applyInventoryStatus($this->reports->inventoryBase($filters), $state)
            ->count();

        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? 'asc';

        $products = (clone $query)->orderBy($sort, $direction)->orderBy('id')->get();

        $rows = $products->map(fn (Product $product) => [
            $product->code,
            $product->name,
            $product->category?->name ?? '-',
            $product->productType?->name ?? '-',
            $product->brand?->name ?? '-',
            (float) $product->selling_price,
            (int) $product->stock,
            (int) $product->min_stock,
            $product->unit?->name ?? '-',
            self::STATUS_LABELS[$product->is_active ? $product->stock_status : 'nonaktif'] ?? '-',
        ])->all();

        $meta = [
            ['label' => 'Kategori', 'value' => ! empty($filters['category_id']) ? (Category::find($filters['category_id'])?->name ?? '-') : 'Semua'],
            ['label' => 'Tipe barang', 'value' => ! empty($filters['product_type_id']) ? (ProductType::find($filters['product_type_id'])?->name ?? '-') : 'Semua'],
            ['label' => 'Merek', 'value' => ! empty($filters['brand_id']) ? (Brand::find($filters['brand_id'])?->name ?? '-') : 'Semua'],
            ['label' => 'Status', 'value' => $status ? (self::STATUS_LABELS[$status] ?? '-') : 'Semua'],
        ];

        if (! empty($filters['search'])) {
            $meta[] = ['label' => 'Pencarian', 'value' => (string) $filters['search']];
        }

        return new ReportDocument(
            title: 'Laporan Barang',
            slug: 'laporan-barang',
            fileTag: now()->format('Y-m-d'),
            meta: $meta,
            kpis: [
                ['label' => 'Total Barang', 'value' => $count(null), 'type' => 'int'],
                ['label' => 'Tersedia', 'value' => $count('tersedia'), 'type' => 'int'],
                ['label' => 'Hampir Habis', 'value' => $count('menipis'), 'type' => 'int'],
                ['label' => 'Habis', 'value' => $count('habis'), 'type' => 'int'],
                ['label' => 'Tidak Aktif', 'value' => $count('nonaktif'), 'type' => 'int'],
            ],
            tables: [[
                'title' => 'Daftar Barang',
                'columns' => [
                    ['label' => 'Kode', 'type' => 'text'],
                    ['label' => 'Nama Barang', 'type' => 'text'],
                    ['label' => 'Kategori', 'type' => 'text'],
                    ['label' => 'Tipe', 'type' => 'text'],
                    ['label' => 'Merek', 'type' => 'text'],
                    ['label' => 'Harga Jual', 'type' => 'money'],
                    ['label' => 'Stok', 'type' => 'int'],
                    ['label' => 'Stok Min.', 'type' => 'int'],
                    ['label' => 'Satuan', 'type' => 'text'],
                    ['label' => 'Status', 'type' => 'text'],
                ],
                'rows' => $rows,
                'totals' => ['Total ('.ReportFormat::int(count($rows)).' barang)', null, null, null, null, null, array_sum(array_column($rows, 6)), null, null, null],
                'empty' => 'Tidak ada barang yang cocok dengan filter.',
            ]],
            orientation: 'landscape',
            note: 'Status "Tidak aktif" mengabaikan jumlah stok. Data per '.now()->format('d/m/Y H:i').'.',
        );
    }

    public function cashierSelf(ReportPeriod $period, User $user, ?string $status, int $limit, string $format): ReportDocument
    {
        $cashierId = (int) $user->getKey();
        $base = CashierHistoryQuery::base($period, $cashierId, $status);

        $this->guard((clone $base)->count(), $limit, $format);

        $row = $this->reports->cashierPerformance($period, $cashierId)['rows'][0] ?? [];
        $value = fn (string $key) => $row[$key] ?? 0;

        $orders = CashierHistoryQuery::listing(clone $base)
            ->withSum('items as total_quantity', 'quantity')
            ->get();

        $rows = $orders->map(fn (Order $order) => [
            ReportFormat::dateTime(CarbonImmutable::parse($order->event_at, config('app.timezone'))),
            $order->order_number,
            $order->customer_name,
            (int) $order->total_quantity,
            (float) $order->total_amount,
            $order->status->label(),
        ]);

        $done = $orders->filter(fn (Order $order) => $order->status->value === 'SELESAI');

        $statusLabel = match ($status) {
            'SELESAI' => 'Selesai saja',
            'DIBATALKAN' => 'Dibatalkan saja',
            default => 'Selesai dan dibatalkan',
        };

        return new ReportDocument(
            title: 'Laporan Transaksi Kasir',
            slug: 'laporan-transaksi-kasir',
            fileTag: ReportFormat::periodTag($period),
            meta: [
                ['label' => 'Kasir', 'value' => $user->name],
                ['label' => 'Periode', 'value' => ReportFormat::period($period)],
                ['label' => 'Status', 'value' => $statusLabel],
            ],
            kpis: [
                ['label' => 'Total Transaksi Diproses', 'value' => $value('handled'), 'type' => 'int'],
                ['label' => 'Transaksi Selesai', 'value' => $value('completed'), 'type' => 'int'],
                ['label' => 'Transaksi Dibatalkan', 'value' => $value('cancelled'), 'type' => 'int'],
                ['label' => 'Total Pendapatan', 'value' => $value('revenue'), 'type' => 'money'],
                ['label' => 'Jumlah Produk Terjual', 'value' => $value('units_sold'), 'type' => 'int'],
                ['label' => 'Rata-rata Transaksi', 'value' => $value('average'), 'type' => 'money'],
                ['label' => 'Nilai Retur', 'value' => $value('returns_total'), 'type' => 'money'],
                ['label' => 'Pendapatan Bersih', 'value' => $value('net_revenue'), 'type' => 'money'],
            ],
            tables: [[
                'title' => 'Riwayat Transaksi',
                'columns' => [
                    ['label' => 'Waktu', 'type' => 'text'],
                    ['label' => 'No. Pesanan', 'type' => 'text'],
                    ['label' => 'Customer', 'type' => 'text'],
                    ['label' => 'Barang', 'type' => 'int'],
                    ['label' => 'Total', 'type' => 'money'],
                    ['label' => 'Status', 'type' => 'text'],
                ],
                'rows' => $rows->all(),
                'totals' => ['Total selesai', null, null, (int) $done->sum('total_quantity'), round((float) $done->sum('total_amount'), 2), null],
                'empty' => 'Belum ada transaksi pada periode ini.',
            ]],
            note: 'Pendapatan dihitung dari transaksi SELESAI pada tanggal penyelesaian. Transaksi dibatalkan tidak dihitung.',
        );
    }

    private function guard(int $count, int $limit, string $format): void
    {
        if ($count > $limit) {
            throw new ReportTooLargeException($count, $limit, $format);
        }
    }

    private function cashierName(?int $cashierId): string
    {
        if ($cashierId === null) {
            return 'Semua kasir';
        }

        return User::withTrashed()->find($cashierId)?->name ?? 'Kasir tidak ditemukan';
    }
}
