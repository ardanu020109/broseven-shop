<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockLedgerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reference = $this->reference;

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'quantity_change' => (int) $this->quantity_change,
            'stock_before' => (int) $this->stock_before,
            'stock_after' => (int) $this->stock_after,
            'note' => $this->note,
            'user' => $this->user?->name,
            'product' => $this->product
                ? ['id' => $this->product->id, 'code' => $this->product->code, 'name' => $this->product->name]
                : null,
            'reference' => match (true) {
                $reference instanceof Order => ['kind' => 'order', 'label' => $reference->order_number],
                $reference instanceof OrderReturn => ['kind' => 'return', 'label' => $reference->return_number],
                default => null,
            },
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
