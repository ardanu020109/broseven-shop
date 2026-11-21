<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryReportController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
            'product_type_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['tersedia', 'menipis', 'habis', 'nonaktif'])],
            'sort' => ['nullable', Rule::in(['name', 'stock', 'selling_price'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $summary = [
            'total' => $this->reports->inventoryBase($filters)->count(),
            'tersedia' => $this->reports->applyInventoryStatus($this->reports->inventoryBase($filters), 'tersedia')->count(),
            'menipis' => $this->reports->applyInventoryStatus($this->reports->inventoryBase($filters), 'menipis')->count(),
            'habis' => $this->reports->applyInventoryStatus($this->reports->inventoryBase($filters), 'habis')->count(),
            'nonaktif' => $this->reports->applyInventoryStatus($this->reports->inventoryBase($filters), 'nonaktif')->count(),
        ];

        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc');

        $paginator = $this->reports
            ->applyInventoryStatus($this->reports->inventoryBase($filters), $filters['status'] ?? null)
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($request->integer('per_page', 15))
            ->through(fn (Product $product) => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'category' => $product->category?->name,
                'product_type' => $product->productType?->name,
                'brand' => $product->brand?->name,
                'unit' => $product->unit?->name,
                'selling_price' => (float) $product->selling_price,
                'stock' => (int) $product->stock,
                'min_stock' => (int) $product->min_stock,
                'is_active' => (bool) $product->is_active,
                'status' => $product->is_active ? $product->stock_status : 'nonaktif',
            ])
            ->toArray();

        $paginator['summary'] = $summary;

        return response()->json($paginator);
    }
}
