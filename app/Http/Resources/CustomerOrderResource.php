<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isDraft = $this->status === OrderStatus::DRAFT;

        return [
            'order_number' => $this->order_number,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'notes' => $this->notes,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_description' => $this->status->description(),
            'total_amount' => (float) $this->total_amount,
            'payment_method_label' => 'Pembayaran langsung di kasir',
            'barcode_code' => $isDraft ? $this->barcode_code : null,
            'cancel_reason' => $this->status === OrderStatus::DIBATALKAN ? $this->cancel_reason : null,
            'items' => $this->items->map(fn ($item) => [
                'product_name' => $item->product_name,
                'image_url' => $item->product?->image_path ? '/storage/'.$item->product->image_path : null,
                'unit_price' => (float) $item->unit_price,
                'quantity' => (int) $item->quantity,
                'subtotal' => (float) $item->subtotal,
            ])->values(),
            'created_at' => $this->created_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
        ];
    }
}
