<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Models\Category;

class CategoryController extends MasterDataController
{
    protected function modelClass(): string
    {
        return Category::class;
    }

    protected function label(): string
    {
        return 'Kategori';
    }

    protected function table(): string
    {
        return 'categories';
    }

    protected function extraRules(): array
    {
        return ['description' => ['nullable', 'string', 'max:1000']];
    }
}
