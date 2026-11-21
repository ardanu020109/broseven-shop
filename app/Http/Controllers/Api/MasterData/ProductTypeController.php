<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Models\ProductType;

class ProductTypeController extends MasterDataController
{
    protected function modelClass(): string
    {
        return ProductType::class;
    }

    protected function label(): string
    {
        return 'Tipe barang';
    }

    protected function table(): string
    {
        return 'product_types';
    }

    protected function extraRules(): array
    {
        return ['description' => ['nullable', 'string', 'max:1000']];
    }
}
