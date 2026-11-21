<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashierOrderResource extends JsonResource
{
    private bool $summary = false;

    public function asSummary(): static
    {
        $this->summary = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $user = $request->user();
        $status = $this->status;
        $isDraft = $status === OrderStatus::DRAFT;
        $isOwner = $user !== null
            && $this->cashier_id !== null
            && (int) $this->cashier_id === (int) $user->getKey();

        $base = [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'status' => $status->value,
            'status_label' => $status->label(),
            'total_amount' => (float) $this->total_amount,
            'items_count' => (int) ($this->items_count ?? 0),
            'total_quantity' => (int) ($this->total_quantity ?? 0),
            'cashier' => $this->cashier?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
        ];

        if ($this->summary) {
            return $base;
        }

        $this->resource->loadMissing([
            'returns' => fn ($query) => $query
                ->with(['user:id,name', 'items', 'order:id,order_number,customer_name'])
                ->orderBy('id'),
        ]);

        $items = $this->items->map(function ($item) use ($isDraft) {
            $product = $item->product;
            $stock = $product ? (int) $product->stock : 0;
            $returned = (int) $item->returned_quantity;

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'code' => $item->product_code,
                'name' => $item->product_name,
                'image_url' => $product?->image_path ? '/storage/'.$product->image_path : null,
                'unit' => $product?->unit?->abbreviation ?: $product?->unit?->name,
                'unit_price' => (float) $item->unit_price,
                'quantity' => (int) $item->quantity,
                'subtotal' => (float) $item->subtotal,
                'stock' => $stock,
                'stock_ok' => $isDraft ? $stock >= (int) $item->quantity : null,
                'product_active' => $product ? ($product->is_active && $product->deleted_at === null) : false,
                'returned_quantity' => $returned,
                'returnable_quantity' => max(0, (int) $item->quantity - $returned),
            ];
        });

        $canReturn = $status === OrderStatus::SELESAI
            && $isOwner
            && $this->isWithinReturnWindow()
            && $items->contains(fn (array $item) => $item['returnable_quantity'] > 0);

        return array_merge($base, [
            'items_count' => $items->count(),
            'total_quantity' => (int) $items->sum('quantity'),
            'notes' => $this->notes,
            'status_description' => $status->description(),
            'payment_method_label' => 'Pembayaran langsung di kasir',
            'barcode_used' => $this->barcode_used_at !== null,
            'cancelled_by' => $this->canceller?->name,
            'cancel_reason' => $this->cancel_reason,
            'can_approve' => $isDraft && $this->barcode_used_at === null,
            'can_complete' => $status === OrderStatus::MENUNGGU && $isOwner,
            'can_cancel' => $isDraft || ($status === OrderStatus::MENUNGGU && $isOwner),
            'can_return' => $canReturn,
            'stock_ok' => $isDraft ? $items->every(fn (array $item) => $item['stock_ok']) : null,
            'returned_amount' => (float) $this->returns->sum('total_amount'),
            'items' => $items->values(),
            'returns' => $this->returns
                ->map(fn ($return) => OrderReturnResource::make($return)->resolve())
                ->values(),
            'histories' => $this->statusHistories->map(fn ($history) => [
                'from' => $history->from_status?->value,
                'to' => $history->to_status->value,
                'from_label' => $history->from_status?->label(),
                'to_label' => $history->to_status->label(),
                'user' => $history->user?->name,
                'note' => $history->note,
                'created_at' => $history->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }
}
