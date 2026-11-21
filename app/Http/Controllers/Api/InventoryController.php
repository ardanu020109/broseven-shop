<?php

namespace App\Http\Controllers\Api;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Resources\StockLedgerResource;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function summary(): JsonResponse
    {
        $today = [now()->startOfDay(), now()->endOfDay()];

        return response()->json([
            'products_active' => Product::query()->active()->count(),
            'units_in_stock' => (int) Product::query()->active()->sum('stock'),
            'low_stock' => Product::query()->active()->lowStock()->count(),
            'out_of_stock' => Product::query()->active()->outOfStock()->count(),
            'movements_today' => DB::table('stock_movements')
                ->whereBetween('created_at', $today)
                ->select('type', DB::raw('COUNT(*) as total'))
                ->groupBy('type')
                ->pluck('total', 'type')
                ->map(fn ($total) => (int) $total),
        ]);
    }

    public function movements(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(StockMovementType::values())],
            'product_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $search = $request->input('search');

        $paginator = StockMovement::query()
            ->with(['product:id,code,name', 'user:id,name', 'reference'])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($search, fn ($q) => $q->whereHas('product', fn ($product) => $product
                ->withTrashed()
                ->where(function ($group) use ($search) {
                    $group->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
                })))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('date_to')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15))
            ->through(fn (StockMovement $movement) => StockLedgerResource::make($movement)->resolve());

        return response()->json($paginator);
    }
}
