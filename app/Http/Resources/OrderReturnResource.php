<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderReturnResource extends JsonResource
{
    private bool $summary = false;

    public function asSummary(): static
    {
        $this->summary = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $base = [
            'id' => $this->id,
            'return_number' => $this->return_number,
            'order_id' => $this->order_id,
            'order_number' => $this->order?->order_number,
            'customer_name' => $this->order?->customer_name,
            'total_amount' => (float) $this->total_amount,
            'user' => $this->user?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'items_count' => (int) ($this->items_count ?? 0),
            'total_quantity' => (int) ($this->total_quantity ?? 0),
        ];

        if ($this->summary) {
            return $base;
        }

        $items = $this->items->map(fn ($item) => [
            'id' => $item->id,
            'order_item_id' => $item->order_item_id,
            'product_id' => $item->product_id,
            'code' => $item->product_code,
            'name' => $item->product_name,
            'unit_price' => (float) $item->unit_price,
            'quantity' => (int) $item->quantity,
            'subtotal' => (float) $item->subtotal,
        ]);

        return array_merge($base, [
            'reason' => $this->reason,
            'items_count' => $items->count(),
            'total_quantity' => (int) $items->sum('quantity'),
            'items' => $items->values(),
        ]);
    }
}
