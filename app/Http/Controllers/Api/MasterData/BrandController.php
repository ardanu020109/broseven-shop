<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Models\Brand;

class BrandController extends MasterDataController
{
    protected function modelClass(): string
    {
        return Brand::class;
    }

    protected function label(): string
    {
        return 'Merek';
    }

    protected function table(): string
    {
        return 'brands';
    }

    protected function extraRules(): array
    {
        return ['description' => ['nullable', 'string', 'max:1000']];
    }
}
