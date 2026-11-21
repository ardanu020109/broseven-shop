<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CompanySetting;
use App\Models\WebsiteSetting;
use Illuminate\Http\JsonResponse;

class StoreInfoController extends Controller
{
    public function info(): JsonResponse
    {
        $company = CompanySetting::current();
        $site = WebsiteSetting::current();

        return response()->json([
            'company' => [
                'store_name' => $company->store_name,
                'logo_url' => $company->logo_path ? '/storage/'.$company->logo_path : null,
                'favicon_url' => $company->favicon_path ? '/storage/'.$company->favicon_path : null,
                'address' => $company->address,
                'phone' => $company->phone,
                'email' => $company->email,
                'description' => $company->description,
                'footer_text' => $company->footer_text,
                'draft_expiry_hours' => (int) $company->draft_expiry_hours,
            ],
            'colors' => [
                'primary' => $site->primary_color,
                'secondary' => $site->secondary_color,
                'danger' => $site->danger_color,
                'background' => $site->background_color,
                'dark' => $site->dark_color,
            ],
        ]);
    }

    public function filters(): JsonResponse
    {
        $activeProducts = fn ($query) => $query->where('is_active', true);

        return response()->json([
            'categories' => Category::query()
                ->where('is_active', true)
                ->whereHas('products', $activeProducts)
                ->orderBy('name')
                ->get(['id', 'name']),
            'brands' => Brand::query()
                ->where('is_active', true)
                ->whereHas('products', $activeProducts)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
