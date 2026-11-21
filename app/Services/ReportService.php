<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\User;
use App\Support\ReportPeriod;
use Illuminate\Database\Eloquent\Builder;

class ReportService
{
    public function completedOrders(ReportPeriod $period, ?int $cashierId = null): Builder
    {
        return Order::query()
            ->where('status', OrderStatus::SELESAI->value)
            ->whereBetween('completed_at', $period->bounds())
            ->when($cashierId, fn ($q) => $q->where('cashier_id', $cashierId));
    }

    public function revenueSummary(ReportPeriod $period, ?int $cashierId = null): array
    {
        $count = $this->completedOrders($period, $cashierId)->count();
        $gross = (float) $this->completedOrders($period, $cashierId)->sum('total_amount');
        $units = (int) $this->completedItems($period, $cashierId)->sum('order_items.quantity');
        $returnsCount = $this->returns($period, $cashierId)->count();
        $returnsTotal = (float) $this->returns($period, $cashierId)->sum('total_amount');

        return [
            'gross_revenue' => round($gross, 2),
            'returns_total' => round($returnsTotal, 2),
            'returns_count' => $returnsCount,
            'net_revenue' => round($gross - $returnsTotal, 2),
            'transactions' => $count,
            'average' => $count > 0 ? round($gross / $count, 2) : 0.0,
            'units_sold' => $units,
        ];
    }

    public function series(ReportPeriod $period, ?int $cashierId = null): array
    {
        $expression = match ($period->granularity) {
            'hour' => 'HOUR(completed_at)',
            'month' => "DATE_FORMAT(completed_at, '%Y-%m')",
            default => 'DATE(completed_at)',
        };

        $rows = $this->completedOrders($period, $cashierId)
            ->selectRaw("{$expression} as bucket, COUNT(*) as transactions, SUM(total_amount) as revenue")
            ->groupByRaw($expression)
            ->get()
            ->keyBy(fn ($row) => (string) $row->bucket);

        return array_map(function (string $key) use ($rows) {
            $row = $rows->get($key);

            return [
                'key' => $key,
                'transactions' => (int) ($row?->transactions ?? 0),
                'revenue' => (float) ($row?->revenue ?? 0),
            ];
        }, $period->bucketKeys());
    }

    public function topProducts(ReportPeriod $period, int $limit = 5): array
    {
        return $this->completedItems($period)
            ->selectRaw('order_items.product_id, MAX(order_items.product_name) as name, MAX(order_items.product_code) as code, SUM(order_items.quantity) as quantity, SUM(order_items.subtotal) as revenue')
            ->groupBy('order_items.product_id')
            ->orderByDesc('quantity')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'name' => $row->name,
                'code' => $row->code,
                'quantity' => (int) $row->quantity,
                'revenue' => (float) $row->revenue,
            ])
            ->all();
    }

    public function cashierPerformance(ReportPeriod $period, ?int $cashierId = null): array
    {
        $completed = $this->completedOrders($period, $cashierId)
            ->whereNotNull('cashier_id')
            ->selectRaw('cashier_id, COUNT(*) as total, SUM(total_amount) as revenue, AVG(TIMESTAMPDIFF(SECOND, approved_at, completed_at)) as service_seconds')
            ->groupBy('cashier_id')
            ->get()
            ->keyBy('cashier_id');

        $units = $this->completedItems($period, $cashierId)
            ->selectRaw('orders.cashier_id as cashier_id, SUM(order_items.quantity) as units')
            ->groupBy('orders.cashier_id')
            ->pluck('units', 'cashier_id');

        $cancelled = Order::query()
            ->where('status', OrderStatus::DIBATALKAN->value)
            ->whereNotNull('cancelled_by')
            ->whereBetween('cancelled_at', $period->bounds())
            ->when($cashierId, fn ($q) => $q->where('cancelled_by', $cashierId))
            ->selectRaw('cancelled_by, COUNT(*) as total')
            ->groupBy('cancelled_by')
            ->pluck('total', 'cancelled_by');

        $handled = Order::query()
            ->whereNotNull('cashier_id')
            ->whereBetween('approved_at', $period->bounds())
            ->when($cashierId, fn ($q) => $q->where('cashier_id', $cashierId))
            ->selectRaw('cashier_id, COUNT(*) as total')
            ->groupBy('cashier_id')
            ->pluck('total', 'cashier_id');

        $returns = $this->returns($period, $cashierId)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, COUNT(*) as total, SUM(total_amount) as amount')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $activeIds = collect([$completed->keys(), $units->keys(), $cancelled->keys(), $handled->keys(), $returns->keys()])
            ->flatten()
            ->unique()
            ->values();

        $users = User::withTrashed()
            ->where(function ($q) use ($activeIds) {
                $q->whereIn('id', $activeIds)
                    ->orWhere(fn ($active) => $active
                        ->where('role', Role::CASHIER->value)
                        ->where('is_active', true)
                        ->whereNull('deleted_at'));
            })
            ->when($cashierId, fn ($q) => $q->whereKey($cashierId))
            ->orderBy('name')
            ->get(['id', 'name', 'is_active', 'deleted_at']);

        $rows = $users->map(function (User $user) use ($completed, $units, $cancelled, $handled, $returns) {
            $done = $completed->get($user->id);
            $count = (int) ($done?->total ?? 0);
            $revenue = (float) ($done?->revenue ?? 0);
            $refund = $returns->get($user->id);
            $refundTotal = (float) ($refund?->amount ?? 0);
            $seconds = $done?->service_seconds;

            return [
                'id' => $user->id,
                'name' => $user->name.($user->trashed() ? ' (dihapus)' : ''),
                'is_active' => (bool) $user->is_active && ! $user->trashed(),
                'handled' => (int) $handled->get($user->id, 0),
                'completed' => $count,
                'revenue' => round($revenue, 2),
                'average' => $count > 0 ? round($revenue / $count, 2) : 0.0,
                'units_sold' => (int) $units->get($user->id, 0),
                'cancelled' => (int) $cancelled->get($user->id, 0),
                'returns_count' => (int) ($refund?->total ?? 0),
                'returns_total' => round($refundTotal, 2),
                'net_revenue' => round($revenue - $refundTotal, 2),
                'avg_service_minutes' => $seconds !== null ? round(((float) $seconds) / 60, 1) : null,
            ];
        })->values();

        $completedTotal = (int) $rows->sum('completed');
        $weighted = $rows->filter(fn ($row) => $row['avg_service_minutes'] !== null && $row['completed'] > 0);
        $weightedCount = (int) $weighted->sum('completed');

        return [
            'rows' => $rows->all(),
            'totals' => [
                'handled' => (int) $rows->sum('handled'),
                'completed' => $completedTotal,
                'revenue' => round((float) $rows->sum('revenue'), 2),
                'average' => $completedTotal > 0 ? round((float) $rows->sum('revenue') / $completedTotal, 2) : 0.0,
                'units_sold' => (int) $rows->sum('units_sold'),
                'cancelled' => (int) $rows->sum('cancelled'),
                'returns_count' => (int) $rows->sum('returns_count'),
                'returns_total' => round((float) $rows->sum('returns_total'), 2),
                'net_revenue' => round((float) $rows->sum('net_revenue'), 2),
                'avg_service_minutes' => $weightedCount > 0
                    ? round($weighted->sum(fn ($row) => $row['avg_service_minutes'] * $row['completed']) / $weightedCount, 1)
                    : null,
            ],
        ];
    }

    public function inventoryBase(array $filters): Builder
    {
        return Product::query()
            ->with(['category:id,name', 'productType:id,name', 'brand:id,name', 'unit:id,name,abbreviation'])
            ->search($filters['search'] ?? null)
            ->when(! empty($filters['category_id']), fn ($q) => $q->where('category_id', $filters['category_id']))
            ->when(! empty($filters['brand_id']), fn ($q) => $q->where('brand_id', $filters['brand_id']))
            ->when(! empty($filters['product_type_id']), fn ($q) => $q->where('product_type_id', $filters['product_type_id']));
    }

    public function applyInventoryStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'tersedia' => $query->active()->availableStock(),
            'menipis' => $query->active()->lowStock(),
            'habis' => $query->active()->outOfStock(),
            'nonaktif' => $query->where('is_active', false),
            default => $query,
        };
    }

    private function completedItems(ReportPeriod $period, ?int $cashierId = null): Builder
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', OrderStatus::SELESAI->value)
            ->whereBetween('orders.completed_at', $period->bounds())
            ->when($cashierId, fn ($q) => $q->where('orders.cashier_id', $cashierId));
    }

    private function returns(ReportPeriod $period, ?int $cashierId = null): Builder
    {
        return OrderReturn::query()
            ->whereBetween('created_at', $period->bounds())
            ->when($cashierId, fn ($q) => $q->where('user_id', $cashierId));
    }
}
