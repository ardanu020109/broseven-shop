<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MonitorOrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MonitorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::in(OrderStatus::values())],
            'cashier_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $search = $request->input('search');

        $filtered = Order::query()
            ->when($request->filled('cashier_id'), fn ($q) => $q->where('cashier_id', $request->integer('cashier_id')))
            ->when($search, fn ($q) => $q->where(function ($group) use ($search) {
                $group->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            }))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('date_to')));

        $rawCounts = (clone $filtered)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [];

        foreach (OrderStatus::values() as $value) {
            $counts[$value] = (int) $rawCounts->get($value, 0);
        }

        $payload = (clone $filtered)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->with(['cashier:id,name', 'items:id,order_id,product_name,quantity'])
            ->withSum('items as total_quantity', 'quantity')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 10))
            ->through(fn (Order $order) => MonitorOrderResource::make($order)->asSummary()->resolve())
            ->toArray();

        $payload['counts'] = $counts;

        return response()->json($payload);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load([
            'items',
            'cashier:id,name',
            'canceller:id,name',
            'returns' => fn ($query) => $query->with('user:id,name')->orderBy('id'),
            'statusHistories' => fn ($query) => $query->with('user:id,name')->orderBy('id'),
        ]);

        return response()->json(['data' => MonitorOrderResource::make($order)->resolve()]);
    }
}
