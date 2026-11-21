<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\MonitorOrderResource;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use App\Services\ReportService;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperadminDashboardController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(ReportPeriod::rules());

        $period = ReportPeriod::fromRequest($request);
        $tz = config('app.timezone');
        $now = CarbonImmutable::now($tz);
        $today = ReportPeriod::day($now);

        $revenueOf = fn (ReportPeriod $p) => round((float) $this->reports->completedOrders($p)->sum('total_amount'), 2);

        $recent = Order::query()
            ->with(['cashier:id,name', 'items:id,order_id,product_name,quantity'])
            ->withCount('items')
            ->withSum('items as total_quantity', 'quantity')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (Order $order) => MonitorOrderResource::make($order)->asSummary()->resolve());

        $activities = OrderStatusHistory::query()
            ->whereNotNull('user_id')
            ->whereHas('user', fn ($q) => $q->where('role', Role::CASHIER->value))
            ->with(['user:id,name', 'order:id,order_number'])
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (OrderStatusHistory $history) => [
                'id' => $history->id,
                'cashier' => $history->user?->name,
                'order_number' => $history->order?->order_number,
                'to_label' => $history->to_status->label(),
                'to_status' => $history->to_status->value,
                'note' => $history->note,
                'created_at' => $history->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'totals' => [
                'revenue_today' => $revenueOf($today),
                'revenue_month' => $revenueOf(ReportPeriod::month($now)),
                'revenue_year' => $revenueOf(ReportPeriod::year($now->year, $tz)),
                'transactions_today' => $this->reports->completedOrders($today)->count(),
                'customers' => Order::query()->distinct()->count('customer_phone'),
                'cashiers' => User::query()->role(Role::CASHIER)->count(),
                'cashiers_active' => User::query()->role(Role::CASHIER)->active()->count(),
            ],
            'products' => [
                'total' => Product::query()->count(),
                'available' => Product::query()->active()->availableStock()->count(),
                'low' => Product::query()->active()->lowStock()->count(),
                'out' => Product::query()->active()->outOfStock()->count(),
                'inactive' => Product::query()->where('is_active', false)->count(),
            ],
            'period' => $period->toArray(),
            'period_summary' => $this->reports->revenueSummary($period),
            'series' => $this->reports->series($period),
            'top_products' => $this->reports->topProducts($period),
            'recent_orders' => $recent,
            'activities' => $activities,
        ]);
    }
}
