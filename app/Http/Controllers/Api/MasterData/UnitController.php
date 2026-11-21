<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Models\Unit;

class UnitController extends MasterDataController
{
    protected function modelClass(): string
    {
        return Unit::class;
    }

    protected function label(): string
    {
        return 'Satuan';
    }

    protected function table(): string
    {
        return 'units';
    }

    protected function nameMax(): int
    {
        return 50;
    }

    protected function extraRules(): array
    {
        return ['abbreviation' => ['nullable', 'string', 'max:20']];
    }
}
