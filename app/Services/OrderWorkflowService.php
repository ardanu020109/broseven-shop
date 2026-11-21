<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\OrderWorkflowException;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class OrderWorkflowService
{
    public function __construct(
        private StockService $stock,
        private AuditLogger $audit,
    ) {
    }

    public function approve(Order $order, User $cashier, string $via = 'manual'): Order
    {
        $locked = DB::transaction(function () use ($order, $cashier, $via) {
            $locked = $this->lockOrder($order);

            if ($locked->status !== OrderStatus::DRAFT) {
                throw new OrderWorkflowException($this->approveBlockedMessage($locked->status));
            }

            if ($locked->barcode_used_at !== null) {
                throw new OrderWorkflowException('Barcode pesanan ini sudah pernah digunakan.');
            }

            $items = $locked->items()->orderBy('product_id')->get();
            $products = $this->lockProducts($items->pluck('product_id')->all());

            $issues = [];

            foreach ($items as $item) {
                $available = (int) ($products->get($item->product_id)?->stock ?? 0);

                if ($available < (int) $item->quantity) {
                    $issues[] = [
                        'product_id' => $item->product_id,
                        'name' => $item->product_name,
                        'requested' => (int) $item->quantity,
                        'available' => $available,
                    ];
                }
            }

            if ($issues !== []) {
                throw new OrderWorkflowException(
                    'Stok tidak mencukupi. Persetujuan ditolak, stok terbaru ditampilkan di bawah.',
                    422,
                    ['stock_issues' => $issues],
                );
            }

            foreach ($items as $item) {
                $this->stock->adjust(
                    $products->get($item->product_id),
                    StockMovementType::SALE,
                    -((int) $item->quantity),
                    "Penjualan {$locked->order_number}",
                    $cashier,
                    $locked,
                );
            }

            $now = now();

            $locked->forceFill([
                'status' => OrderStatus::MENUNGGU,
                'cashier_id' => $cashier->getKey(),
                'approved_at' => $now,
                'barcode_used_at' => $now,
                'stock_deducted_at' => $now,
            ])->save();

            $this->recordHistory(
                $locked,
                OrderStatus::DRAFT,
                OrderStatus::MENUNGGU,
                $cashier,
                $via === 'scan' ? 'Disetujui kasir (scan QR Code).' : 'Disetujui kasir (nomor pesanan).',
            );

            return $locked;
        });

        $this->audit->log(
            'order.approved',
            $locked,
            "Pesanan {$locked->order_number} disetujui.",
            ['status' => OrderStatus::DRAFT->value],
            ['status' => OrderStatus::MENUNGGU->value, 'cashier_id' => $cashier->getKey()],
            $cashier,
        );

        return $locked;
    }

    public function complete(Order $order, User $cashier): Order
    {
        $locked = DB::transaction(function () use ($order, $cashier) {
            $locked = $this->lockOrder($order);

            if ($locked->status !== OrderStatus::MENUNGGU) {
                throw new OrderWorkflowException($this->completeBlockedMessage($locked->status));
            }

            $this->assertOwner($locked, $cashier);

            $locked->forceFill([
                'status' => OrderStatus::SELESAI,
                'completed_at' => now(),
            ])->save();

            $this->recordHistory(
                $locked,
                OrderStatus::MENUNGGU,
                OrderStatus::SELESAI,
                $cashier,
                'Pembayaran diterima, transaksi diselesaikan kasir.',
            );

            return $locked;
        });

        $this->audit->log(
            'order.completed',
            $locked,
            "Pesanan {$locked->order_number} diselesaikan.",
            ['status' => OrderStatus::MENUNGGU->value],
            ['status' => OrderStatus::SELESAI->value, 'total_amount' => $locked->total_amount],
            $cashier,
        );

        return $locked;
    }

    public function cancel(Order $order, User $cashier, string $reason): Order
    {
        [$locked, $from] = DB::transaction(function () use ($order, $cashier, $reason) {
            $locked = $this->lockOrder($order);
            $from = $locked->status;

            if (! $from->canTransitionTo(OrderStatus::DIBATALKAN)) {
                throw new OrderWorkflowException($this->cancelBlockedMessage($from));
            }

            if ($from === OrderStatus::MENUNGGU) {
                $this->assertOwner($locked, $cashier);
                $this->restoreStock($locked, $cashier);
            }

            $locked->forceFill([
                'status' => OrderStatus::DIBATALKAN,
                'cancelled_by' => $cashier->getKey(),
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            $this->recordHistory($locked, $from, OrderStatus::DIBATALKAN, $cashier, "Dibatalkan kasir. Alasan: {$reason}");

            return [$locked, $from];
        });

        $this->audit->log(
            'order.cancelled',
            $locked,
            "Pesanan {$locked->order_number} dibatalkan. Alasan: {$reason}",
            ['status' => $from->value],
            ['status' => OrderStatus::DIBATALKAN->value, 'stock_restored' => $locked->stock_restored_at !== null],
            $cashier,
        );

        return $locked;
    }

    private function lockOrder(Order $order): Order
    {
        return Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
    }

    private function lockProducts(array $ids): Collection
    {
        return Product::withTrashed()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    private function restoreStock(Order $order, User $cashier): void
    {
        if ($order->stock_deducted_at === null || $order->stock_restored_at !== null) {
            return;
        }

        $items = $order->items()->orderBy('product_id')->get();
        $products = $this->lockProducts($items->pluck('product_id')->all());

        foreach ($items as $item) {
            $this->stock->adjust(
                $products->get($item->product_id),
                StockMovementType::SALE_CANCELLED,
                (int) $item->quantity,
                "Pembatalan {$order->order_number}",
                $cashier,
                $order,
            );
        }

        $order->forceFill(['stock_restored_at' => now()]);
    }

    private function assertOwner(Order $order, User $cashier): void
    {
        if (! $order->isHandledBy($cashier)) {
            throw new OrderWorkflowException('Pesanan ini ditangani oleh kasir lain.', 403);
        }
    }

    private function recordHistory(Order $order, OrderStatus $from, OrderStatus $to, User $user, string $note): void
    {
        OrderStatusHistory::create([
            'order_id' => $order->getKey(),
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $user->getKey(),
            'note' => $note,
        ]);
    }

    private function approveBlockedMessage(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::MENUNGGU => 'Pesanan ini sudah disetujui. Barcode tidak dapat digunakan lagi.',
            OrderStatus::SELESAI => 'Pesanan ini sudah selesai dan tidak dapat diproses ulang.',
            OrderStatus::DIBATALKAN => 'Pesanan ini sudah dibatalkan dan tidak dapat dilanjutkan.',
            default => 'Pesanan tidak dapat disetujui.',
        };
    }

    private function completeBlockedMessage(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::DRAFT => 'Pesanan belum disetujui. Setujui pesanan terlebih dahulu.',
            OrderStatus::SELESAI => 'Pesanan ini sudah selesai.',
            OrderStatus::DIBATALKAN => 'Pesanan ini sudah dibatalkan dan tidak dapat diselesaikan.',
            default => 'Pesanan tidak dapat diselesaikan.',
        };
    }

    private function cancelBlockedMessage(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::SELESAI => 'Pesanan yang sudah selesai tidak dapat dibatalkan.',
            OrderStatus::DIBATALKAN => 'Pesanan ini sudah dibatalkan.',
            default => 'Pesanan tidak dapat dibatalkan.',
        };
    }
}
