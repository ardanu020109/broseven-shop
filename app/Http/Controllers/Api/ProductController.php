<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    private const RELATIONS = [
        'category:id,name',
        'productType:id,name',
        'brand:id,name',
        'unit:id,name,abbreviation',
    ];

    public function __construct(private ProductService $products)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'product_type_id' => ['nullable', 'integer'],
            'brand_id' => ['nullable', 'integer'],
            'stock_status' => ['nullable', Rule::in(['tersedia', 'menipis', 'habis'])],
            'is_active' => ['nullable', Rule::in(['0', '1'])],
            'sort' => ['nullable', Rule::in(['created_at', 'name', 'selling_price', 'stock'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');

        $query = Product::query()
            ->with(self::RELATIONS)
            ->search($request->input('search'))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('product_type_id'), fn ($q) => $q->where('product_type_id', $request->integer('product_type_id')))
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', $request->integer('brand_id')))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->input('is_active') === '1'));

        $query = match ($request->input('stock_status')) {
            'tersedia' => $query->availableStock(),
            'menipis' => $query->lowStock(),
            'habis' => $query->outOfStock(),
            default => $query,
        };

        if ($sort === 'created_at') {
            $query->orderBy('id', $direction);
        } else {
            $query->orderBy($sort, $direction)->orderBy('id', 'desc');
        }

        $paginator = $query
            ->paginate($request->integer('per_page', 10))
            ->through(fn (Product $product) => ProductResource::make($product)->resolve());

        return response()->json($paginator);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load([...self::RELATIONS, 'creator:id,name', 'updater:id,name']);

        return response()->json(['data' => ProductResource::make($product)->resolve()]);
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['image', 'remove_image']);

        $product = $this->products->create($data, $request->file('image'), $request->user());
        $product->load([...self::RELATIONS, 'creator:id,name', 'updater:id,name']);

        return response()->json([
            'message' => 'Barang berhasil ditambahkan.',
            'data' => ProductResource::make($product)->resolve(),
        ], 201);
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->safe()->except(['image', 'remove_image']);

        $product = $this->products->update(
            $product,
            $data,
            $request->file('image'),
            $request->boolean('remove_image'),
            $request->user(),
        );
        $product->load([...self::RELATIONS, 'creator:id,name', 'updater:id,name']);

        return response()->json([
            'message' => 'Barang berhasil diperbarui.',
            'data' => ProductResource::make($product)->resolve(),
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $this->products->delete($product, $request->user());

        return response()->json(['message' => 'Barang berhasil dihapus.']);
    }
}
