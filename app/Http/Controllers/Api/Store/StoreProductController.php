<?php

namespace App\Http\Controllers\Api\Store;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\StoreProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StoreProductController extends Controller
{
    private const RELATIONS = [
        'category:id,name',
        'brand:id,name',
        'unit:id,name,abbreviation',
    ];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
            'sort' => ['nullable', Rule::in(['latest', 'popular', 'price_asc', 'price_desc'])],
            'only_sold' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:4', 'max:48'],
        ]);

        $completed = fn ($orders) => $orders->where('status', OrderStatus::SELESAI->value);

        $query = Product::query()
            ->with(self::RELATIONS)
            ->where('is_active', true)
            ->search($request->input('search'))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', $request->integer('brand_id')))
            ->when($request->boolean('only_sold'), fn ($q) => $q->whereHas(
                'orderItems',
                fn ($items) => $items->whereHas('order', $completed)
            ));

        $query = match ($request->input('sort', 'latest')) {
            'popular' => $query
                ->withSum([
                    'orderItems as sold_count' => fn ($items) => $items->whereHas('order', $completed),
                ], 'quantity')
                ->orderByDesc('sold_count')
                ->orderByDesc('id'),
            'price_asc' => $query->orderBy('selling_price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('selling_price')->orderByDesc('id'),
            default => $query->orderByDesc('id'),
        };

        $paginator = $query
            ->paginate($request->integer('per_page', 12))
            ->through(fn (Product $product) => StoreProductResource::make($product)->resolve());

        return response()->json($paginator);
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::query()
            ->with(self::RELATIONS)
            ->where('is_active', true)
            ->find($id);

        if ($product === null) {
            return response()->json(['message' => 'Produk tidak ditemukan.'], 404);
        }

        return response()->json([
            'data' => StoreProductResource::make($product)->withDetail()->resolve(),
        ]);
    }
}
