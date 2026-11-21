<?php

namespace App\Http\Controllers\Api\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Reports\CashierHistoryQuery;
use App\Services\ReportService;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashierReportController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    public function summary(Request $request): JsonResponse
    {
        $request->validate(ReportPeriod::rules());

        $period = ReportPeriod::fromRequest($request);
        $cashierId = (int) $request->user()->getKey();

        $row = $this->reports->cashierPerformance($period, $cashierId)['rows'][0] ?? [];

        $summary = [];

        foreach ([
            'handled', 'completed', 'revenue', 'average', 'units_sold', 'cancelled',
            'returns_count', 'returns_total', 'net_revenue',
        ] as $key) {
            $summary[$key] = $row[$key] ?? 0;
        }

        $summary['avg_service_minutes'] = $row['avg_service_minutes'] ?? null;

        return response()->json([
            'period' => $period->toArray(),
            'summary' => $summary,
            'series' => $this->reports->series($period, $cashierId),
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $request->validate(array_merge(ReportPeriod::rules(), [
            'status' => ['nullable', Rule::in(['SELESAI', 'DIBATALKAN'])],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]));

        $period = ReportPeriod::fromRequest($request);
        $search = $request->input('search');

        $base = CashierHistoryQuery::base($period, (int) $request->user()->getKey(), $request->input('status') ?: null)
            ->when($search, fn ($q) => $q->where(function ($group) use ($search) {
                $group->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            }));

        $paginator = CashierHistoryQuery::listing($base)
            ->withSum('items as total_quantity', 'quantity')
            ->paginate($request->integer('per_page', 10))
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'event_at' => CarbonImmutable::parse($order->event_at, config('app.timezone'))->toIso8601String(),
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'total_quantity' => (int) $order->total_quantity,
                'total_amount' => (float) $order->total_amount,
            ]);

        return response()->json($paginator);
    }
}
