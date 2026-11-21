<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\ReportService;
use App\Support\ReportPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    public function revenue(Request $request): JsonResponse
    {
        $request->validate(array_merge(ReportPeriod::rules(), [
            'cashier_id' => ['nullable', 'integer', 'min:1'],
        ]));

        $period = ReportPeriod::fromRequest($request);
        $cashierId = $request->filled('cashier_id') ? $request->integer('cashier_id') : null;

        return response()->json([
            'period' => $period->toArray(),
            'summary' => $this->reports->revenueSummary($period, $cashierId),
            'series' => $this->reports->series($period, $cashierId),
        ]);
    }

    public function revenueTransactions(Request $request): JsonResponse
    {
        $request->validate(array_merge(ReportPeriod::rules(), [
            'cashier_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]));

        $period = ReportPeriod::fromRequest($request);
        $cashierId = $request->filled('cashier_id') ? $request->integer('cashier_id') : null;
        $search = $request->input('search');

        $paginator = $this->reports->completedOrders($period, $cashierId)
            ->with('cashier:id,name')
            ->withSum('items as total_quantity', 'quantity')
            ->when($search, fn ($q) => $q->where(function ($group) use ($search) {
                $group->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            }))
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 10))
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'completed_at' => $order->completed_at?->toIso8601String(),
                'cashier' => $order->cashier?->name,
                'customer_name' => $order->customer_name,
                'total_quantity' => (int) $order->total_quantity,
                'total_amount' => (float) $order->total_amount,
            ]);

        return response()->json($paginator);
    }

    public function cashiers(Request $request): JsonResponse
    {
        $request->validate(array_merge(ReportPeriod::rules(), [
            'cashier_id' => ['nullable', 'integer', 'min:1'],
        ]));

        $period = ReportPeriod::fromRequest($request);
        $cashierId = $request->filled('cashier_id') ? $request->integer('cashier_id') : null;

        return response()->json(array_merge(
            ['period' => $period->toArray()],
            $this->reports->cashierPerformance($period, $cashierId),
        ));
    }

    public function cashierActivities(Request $request): JsonResponse
    {
        $request->validate(array_merge(ReportPeriod::rules(), [
            'cashier_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]));

        $period = ReportPeriod::fromRequest($request);

        $paginator = OrderStatusHistory::query()
            ->whereNotNull('user_id')
            ->whereBetween('created_at', $period->bounds())
            ->whereHas('user', fn ($q) => $q->where('role', Role::CASHIER->value))
            ->when($request->filled('cashier_id'), fn ($q) => $q->where('user_id', $request->integer('cashier_id')))
            ->with(['user:id,name', 'order:id,order_number,customer_name'])
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 10))
            ->through(fn (OrderStatusHistory $history) => [
                'id' => $history->id,
                'cashier' => $history->user?->name,
                'order_number' => $history->order?->order_number,
                'customer_name' => $history->order?->customer_name,
                'from_label' => $history->from_status?->label(),
                'to_label' => $history->to_status->label(),
                'to_status' => $history->to_status->value,
                'note' => $history->note,
                'created_at' => $history->created_at?->toIso8601String(),
            ]);

        return response()->json($paginator);
    }
}
