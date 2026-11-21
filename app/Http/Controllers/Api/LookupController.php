<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ProductType;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;

class LookupController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'is_active']),
            'product_types' => ProductType::query()->orderBy('name')->get(['id', 'name', 'is_active']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name', 'is_active']),
            'units' => Unit::query()->orderBy('name')->get(['id', 'name', 'abbreviation', 'is_active']),
        ]);
    }
}
