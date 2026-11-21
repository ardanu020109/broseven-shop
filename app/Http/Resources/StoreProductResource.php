<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreProductResource extends JsonResource
{
    private bool $detail = false;

    public function withDetail(): static
    {
        $this->detail = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $stock = (int) $this->stock;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'image_url' => $this->image_path ? '/storage/'.$this->image_path : null,
            'price' => (float) $this->selling_price,
            'stock' => $stock,
            'in_stock' => $stock > 0,
            'category' => $this->category?->name,
            'brand' => $this->brand?->name,
            'unit' => $this->unit?->abbreviation ?: $this->unit?->name,
            'description' => $this->when($this->detail, $this->description),
        ];
    }
}
