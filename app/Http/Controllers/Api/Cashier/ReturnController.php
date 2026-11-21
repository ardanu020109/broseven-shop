<?php

namespace App\Http\Controllers\Api\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\CreateReturnRequest;
use App\Http\Resources\CashierOrderResource;
use App\Http\Resources\OrderReturnResource;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Services\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    public function __construct(private ReturnService $returns)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $search = $request->input('search');

        $paginator = OrderReturn::query()
            ->where('user_id', $request->user()->getKey())
            ->with(['order:id,order_number,customer_name', 'user:id,name'])
            ->withCount('items')
            ->withSum('items as total_quantity', 'quantity')
            ->when($search, fn ($q) => $q->where(function ($group) use ($search) {
                $group->where('return_number', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($order) => $order
                        ->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%"));
            }))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('date_to')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 10))
            ->through(fn (OrderReturn $return) => OrderReturnResource::make($return)->asSummary()->resolve());

        return response()->json($paginator);
    }

    public function show(Request $request, OrderReturn $orderReturn): JsonResponse
    {
        if ((int) $orderReturn->user_id !== (int) $request->user()->getKey()) {
            return response()->json(['message' => 'Retur tidak ditemukan.'], 404);
        }

        $orderReturn->load(['order:id,order_number,customer_name', 'user:id,name', 'items']);

        return response()->json(['data' => OrderReturnResource::make($orderReturn)->resolve()]);
    }

    public function store(CreateReturnRequest $request, Order $order): JsonResponse
    {
        $user = $request->user();

        if (! $order->isVisibleToCashier($user)) {
            return response()->json(['message' => 'Pesanan tidak ditemukan.'], 404);
        }

        [$return, $created] = $this->returns->create($order, $user, $request->validated());

        $return->load(['order:id,order_number,customer_name', 'user:id,name', 'items']);
        $order->refresh()->loadDetail();

        return response()->json([
            'message' => $created
                ? "Retur {$return->return_number} berhasil diproses. Stok barang telah ditambahkan."
                : "Retur {$return->return_number} sudah diproses sebelumnya.",
            'return' => OrderReturnResource::make($return)->resolve(),
            'data' => CashierOrderResource::make($order)->resolve(),
        ], $created ? 201 : 200);
    }
}
