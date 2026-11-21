<?php

namespace App\Http\Controllers\Api\Cashier;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CashierOrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashierDashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $range = [now()->startOfDay(), now()->endOfDay()];

        $completedToday = fn () => Order::query()
            ->where('cashier_id', $user->getKey())
            ->where('status', OrderStatus::SELESAI->value)
            ->whereBetween('completed_at', $range);

        $recent = Order::query()
            ->visibleToCashier($user)
            ->where('status', '!=', OrderStatus::DRAFT->value)
            ->with('cashier:id,name')
            ->withCount('items')
            ->withSum('items as total_quantity', 'quantity')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (Order $order) => CashierOrderResource::make($order)->asSummary()->resolve());

        $activities = OrderStatusHistory::query()
            ->where('user_id', $user->getKey())
            ->with('order:id,order_number')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (OrderStatusHistory $history) => [
                'id' => $history->id,
                'order_number' => $history->order?->order_number,
                'to_status' => $history->to_status->value,
                'to_label' => $history->to_status->label(),
                'note' => $history->note,
                'created_at' => $history->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'stats' => [
                'processed_today' => Order::query()
                    ->where('cashier_id', $user->getKey())
                    ->whereBetween('approved_at', $range)
                    ->count(),
                'revenue_today' => (float) $completedToday()->sum('total_amount'),
                'completed_today' => $completedToday()->count(),
                'cancelled_today' => Order::query()
                    ->where('cancelled_by', $user->getKey())
                    ->whereBetween('cancelled_at', $range)
                    ->count(),
                'waiting' => Order::query()
                    ->where('cashier_id', $user->getKey())
                    ->where('status', OrderStatus::MENUNGGU->value)
                    ->count(),
                'draft_queue' => Order::query()->where('status', OrderStatus::DRAFT->value)->count(),
                'items_sold_today' => (int) OrderItem::query()
                    ->whereHas('order', fn ($q) => $q
                        ->where('cashier_id', $user->getKey())
                        ->where('status', OrderStatus::SELESAI->value)
                        ->whereBetween('completed_at', $range))
                    ->sum('quantity'),
            ],
            'recent_orders' => $recent,
            'activities' => $activities,
        ]);
    }
}
