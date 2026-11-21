<?php

namespace App\Http\Controllers\Api;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockAdjustmentRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\AuditLogger;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductStockController extends Controller
{
    public function __construct(
        private StockService $stock,
        private AuditLogger $audit,
    ) {
    }

    public function movements(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', Rule::in(StockMovementType::values())],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $paginator = $product->stockMovements()
            ->with('user:id,name')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 10))
            ->through(fn (StockMovement $movement) => StockMovementResource::make($movement)->resolve());

        return response()->json($paginator);
    }

    public function adjust(StockAdjustmentRequest $request, Product $product): JsonResponse
    {
        $type = StockMovementType::from($request->validated('type'));
        $quantity = (int) $request->validated('quantity');
        $note = $request->validated('note');

        if ($note === null && $type === StockMovementType::STOCK_IN) {
            $note = 'Stok masuk';
        }

        $movement = $this->stock->adjust($product, $type, $quantity, $note, $request->user());

        $this->audit->log(
            'stock.adjusted',
            $product,
            "{$type->label()} {$quantity} untuk {$product->name} ({$product->code}).",
            ['stock' => $movement->stock_before],
            ['stock' => $movement->stock_after],
        );

        $product->load(['category:id,name', 'productType:id,name', 'brand:id,name', 'unit:id,name,abbreviation']);
        $movement->load('user:id,name');

        return response()->json([
            'message' => 'Stok berhasil diperbarui.',
            'data' => ProductResource::make($product)->resolve(),
            'movement' => StockMovementResource::make($movement)->resolve(),
        ]);
    }
}
