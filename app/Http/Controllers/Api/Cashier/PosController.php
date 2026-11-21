<?php

namespace App\Http\Controllers\Api\Cashier;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\ApproveOrderRequest;
use App\Http\Requests\Cashier\CancelOrderRequest;
use App\Http\Requests\Cashier\LookupOrderRequest;
use App\Http\Resources\CashierOrderResource;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Services\OrderWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PosController extends Controller
{
    public function __construct(
        private OrderWorkflowService $workflow,
        private AuditLogger $audit,
    ) {
    }

    public function counts(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'draft' => Order::query()->where('status', OrderStatus::DRAFT->value)->count(),
            'waiting' => Order::query()
                ->where('status', OrderStatus::MENUNGGU->value)
                ->where('cashier_id', $user->getKey())
                ->count(),
        ]);
    }

    public function lookup(LookupOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        $code = $request->validated('code');

        $isBarcode = (bool) preg_match('/^[0-9A-F]{24}$/', $code);
        $isNumber = (bool) preg_match('/^BS-\d{6}-[A-Z0-9]{5}$/', $code);

        if (! $isBarcode && ! $isNumber) {
            return response()->json([
                'message' => 'Format kode tidak dikenali. Gunakan QR Code pesanan atau nomor pesanan (BS-YYMMDD-XXXXX).',
            ], 422);
        }

        $order = Order::query()
            ->where($isBarcode ? 'barcode_code' : 'order_number', $code)
            ->first();

        if ($order === null) {
            $this->audit->log('pos.scan_failed', description: 'Kode tidak ditemukan: '.substr($code, 0, 4).'...');

            return response()->json([
                'message' => $isBarcode
                    ? 'Barcode tidak dikenali. Pastikan QR Code berasal dari pesanan toko ini.'
                    : 'Nomor pesanan tidak ditemukan.',
            ], 404);
        }

        if (! $order->isVisibleToCashier($user)) {
            return response()->json([
                'message' => 'Pesanan ini sudah diproses oleh kasir lain.',
            ], 409);
        }

        $barcode = null;

        if ($isBarcode) {
            $valid = $order->status === OrderStatus::DRAFT && $order->barcode_used_at === null;

            $barcode = [
                'valid' => $valid,
                'reason' => $valid ? null : match ($order->status) {
                    OrderStatus::MENUNGGU => 'Barcode sudah digunakan untuk persetujuan.',
                    OrderStatus::SELESAI => 'Pesanan sudah selesai. Barcode tidak berlaku lagi.',
                    OrderStatus::DIBATALKAN => 'Pesanan sudah dibatalkan. Barcode tidak berlaku.',
                    default => 'Barcode sudah digunakan.',
                },
            ];
        }

        return response()->json([
            'data' => $this->present($order),
            'matched_by' => $isBarcode ? 'barcode' : 'order_number',
            'barcode' => $barcode,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::in([...OrderStatus::values(), 'closed'])],
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $user = $request->user();
        $status = $request->input('status');
        $search = $request->input('search');

        $paginator = Order::query()
            ->visibleToCashier($user)
            ->with('cashier:id,name')
            ->withCount('items')
            ->withSum('items as total_quantity', 'quantity')
            ->when($status === 'closed', fn ($q) => $q->whereIn('status', [
                OrderStatus::SELESAI->value,
                OrderStatus::DIBATALKAN->value,
            ]))
            ->when($status && $status !== 'closed', fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->where(function ($group) use ($search) {
                $group->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            }))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('date_to')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 10))
            ->through(fn (Order $order) => CashierOrderResource::make($order)->asSummary()->resolve());

        return response()->json($paginator);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if (! $order->isVisibleToCashier($request->user())) {
            return $this->notFound();
        }

        return response()->json(['data' => $this->present($order)]);
    }

    public function approve(ApproveOrderRequest $request, Order $order): JsonResponse
    {
        $user = $request->user();

        if (! $order->isVisibleToCashier($user)) {
            return $this->notFound();
        }

        $order = $this->workflow->approve($order, $user, $request->validated('via') ?? 'manual');

        return response()->json([
            'message' => "Pesanan {$order->order_number} disetujui. Stok barang telah dikurangi.",
            'data' => $this->present($order),
        ]);
    }

    public function complete(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();

        if (! $order->isVisibleToCashier($user)) {
            return $this->notFound();
        }

        $order = $this->workflow->complete($order, $user);

        return response()->json([
            'message' => "Transaksi {$order->order_number} selesai.",
            'data' => $this->present($order),
        ]);
    }

    public function cancel(CancelOrderRequest $request, Order $order): JsonResponse
    {
        $user = $request->user();

        if (! $order->isVisibleToCashier($user)) {
            return $this->notFound();
        }

        $order = $this->workflow->cancel($order, $user, $request->validated('reason'));

        return response()->json([
            'message' => "Pesanan {$order->order_number} dibatalkan.",
            'data' => $this->present($order),
        ]);
    }

    private function present(Order $order): array
    {
        $order->load([
            'items.product.unit',
            'cashier:id,name',
            'canceller:id,name',
            'statusHistories' => fn ($query) => $query->with('user:id,name')->orderBy('id'),
        ]);

        return CashierOrderResource::make($order)->resolve();
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
    }
}
