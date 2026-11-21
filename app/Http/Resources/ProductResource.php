<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canSeeCost = $request->user()?->isAdminMaster() ?? false;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'image_url' => $this->image_path ? '/storage/'.$this->image_path : null,
            'category_id' => $this->category_id,
            'product_type_id' => $this->product_type_id,
            'brand_id' => $this->brand_id,
            'unit_id' => $this->unit_id,
            'category' => $this->whenLoaded('category', fn () => $this->category
                ? ['id' => $this->category->id, 'name' => $this->category->name] : null),
            'product_type' => $this->whenLoaded('productType', fn () => $this->productType
                ? ['id' => $this->productType->id, 'name' => $this->productType->name] : null),
            'brand' => $this->whenLoaded('brand', fn () => $this->brand
                ? ['id' => $this->brand->id, 'name' => $this->brand->name] : null),
            'unit' => $this->whenLoaded('unit', fn () => $this->unit
                ? ['id' => $this->unit->id, 'name' => $this->unit->name, 'abbreviation' => $this->unit->abbreviation] : null),
            'purchase_price' => $this->when(
                $canSeeCost,
                fn () => $this->purchase_price !== null ? (float) $this->purchase_price : null
            ),
            'selling_price' => (float) $this->selling_price,
            'stock' => (int) $this->stock,
            'min_stock' => (int) $this->min_stock,
            'stock_status' => $this->stock_status,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'updater' => $this->whenLoaded('updater', fn () => $this->updater?->name),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
