<?php

namespace App\Http\Controllers\Api\AdminMaster;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;

class AdminMasterDashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $latest = Product::query()
            ->with('category:id,name')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'image_url' => $product->image_path ? '/storage/'.$product->image_path : null,
                'category' => $product->category?->name,
                'selling_price' => (float) $product->selling_price,
                'stock' => (int) $product->stock,
                'stock_status' => $product->stock_status,
                'is_active' => (bool) $product->is_active,
                'created_at' => $product->created_at?->toIso8601String(),
            ]);

        $activities = AuditLog::query()
            ->where(function ($query) {
                $query->where('action', 'like', 'product.%')
                    ->orWhere('action', 'like', 'master.%')
                    ->orWhere('action', 'stock.adjusted');
            })
            ->with('user:id,name')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'user' => $log->user?->name,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'totals' => [
                'products' => Product::query()->count(),
                'categories' => Category::query()->count(),
                'categories_active' => Category::query()->where('is_active', true)->count(),
                'brands' => Brand::query()->count(),
                'brands_active' => Brand::query()->where('is_active', true)->count(),
                'product_types' => ProductType::query()->count(),
                'product_types_active' => ProductType::query()->where('is_active', true)->count(),
                'units' => Unit::query()->count(),
            ],
            'stock' => [
                'available' => Product::query()->active()->availableStock()->count(),
                'low' => Product::query()->active()->lowStock()->count(),
                'out' => Product::query()->active()->outOfStock()->count(),
                'inactive' => Product::query()->where('is_active', false)->count(),
            ],
            'latest_products' => $latest,
            'activities' => $activities,
        ]);
    }
}
