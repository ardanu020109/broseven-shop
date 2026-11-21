<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitorOrderResource extends JsonResource
{
    private bool $summary = false;

    public function asSummary(): static
    {
        $this->summary = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $status = $this->status;
        $items = $this->items;
        $preview = $items->take(3)->map(fn ($item) => "{$item->product_name} x{$item->quantity}")->implode(', ');

        if ($items->count() > 3) {
            $preview .= ' +'.($items->count() - 3).' lainnya';
        }

        $base = [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'status' => $status->value,
            'status_label' => $status->label(),
            'total_amount' => (float) $this->total_amount,
            'items_count' => $items->count(),
            'total_quantity' => (int) ($this->total_quantity ?? $items->sum('quantity')),
            'items_summary' => $preview,
            'cashier' => $this->cashier?->name,
            'barcode_used' => $this->barcode_used_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
        ];

        if ($this->summary) {
            return $base;
        }

        $serviceMinutes = $this->approved_at && $this->completed_at
            ? round($this->approved_at->diffInSeconds($this->completed_at) / 60, 1)
            : null;

        return array_merge($base, [
            'notes' => $this->notes,
            'status_description' => $status->description(),
            'payment_method_label' => 'Pembayaran langsung di kasir',
            'barcode_code' => $status === OrderStatus::DRAFT ? $this->barcode_code : null,
            'cancelled_by' => $this->canceller?->name,
            'cancel_reason' => $this->cancel_reason,
            'service_minutes' => $serviceMinutes,
            'returned_amount' => (float) $this->returns->sum('total_amount'),
            'items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'code' => $item->product_code,
                'name' => $item->product_name,
                'unit_price' => (float) $item->unit_price,
                'quantity' => (int) $item->quantity,
                'returned_quantity' => (int) $item->returned_quantity,
                'subtotal' => (float) $item->subtotal,
            ])->values(),
            'returns' => $this->returns->map(fn ($return) => [
                'id' => $return->id,
                'return_number' => $return->return_number,
                'reason' => $return->reason,
                'total_amount' => (float) $return->total_amount,
                'user' => $return->user?->name,
                'created_at' => $return->created_at?->toIso8601String(),
            ])->values(),
            'histories' => $this->statusHistories->map(fn ($history) => [
                'to' => $history->to_status->value,
                'to_label' => $history->to_status->label(),
                'user' => $history->user?->name,
                'note' => $history->note,
                'created_at' => $history->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }
}
