<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Exceptions\OrderWorkflowException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private StockService $stock,
        private AuditLogger $audit,
    ) {
    }

    /**
     * @return array{0: OrderReturn, 1: bool}
     */
    public function create(Order $order, User $cashier, array $data): array
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                [$return, $created] = DB::transaction(fn () => $this->persist($order, $cashier, $data), 3);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 3) {
                    throw $e;
                }

                continue;
            }

            if ($created) {
                $this->audit->log(
                    'order.returned',
                    $order,
                    "Retur {$return->return_number} untuk pesanan {$order->order_number} senilai {$return->total_amount}.",
                    null,
                    ['return_number' => $return->return_number, 'total_amount' => $return->total_amount],
                    $cashier,
                );
            }

            return [$return, $created];
        }
    }

    /**
     * @return array{0: OrderReturn, 1: bool}
     */
    private function persist(Order $order, User $cashier, array $data): array
    {
        $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

        $duplicate = OrderReturn::query()
            ->where('idempotency_key', $data['idempotency_key'])
            ->lockForUpdate()
            ->first();

        if ($duplicate !== null) {
            if ((int) $duplicate->order_id !== (int) $locked->getKey() || (int) $duplicate->user_id !== (int) $cashier->getKey()) {
                throw new OrderWorkflowException('Kunci permintaan sudah digunakan. Muat ulang halaman lalu coba lagi.', 409);
            }

            return [$duplicate, false];
        }

        if ($locked->status !== OrderStatus::SELESAI) {
            throw new OrderWorkflowException('Retur hanya dapat dilakukan untuk pesanan yang sudah selesai.');
        }

        if (! $locked->isHandledBy($cashier)) {
            throw new OrderWorkflowException('Pesanan ini ditangani oleh kasir lain.', 403);
        }

        if (! $locked->isWithinReturnWindow()) {
            $days = (int) config('broseven.return_window_days');

            throw new OrderWorkflowException("Batas waktu retur ({$days} hari sejak transaksi selesai) telah lewat.", 422);
        }

        $requested = [];

        foreach ($data['items'] as $line) {
            $id = (int) $line['order_item_id'];
            $requested[$id] = ($requested[$id] ?? 0) + (int) $line['quantity'];
        }

        $items = OrderItem::query()
            ->where('order_id', $locked->getKey())
            ->whereIn('id', array_keys($requested))
            ->orderBy('product_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($items->count() !== count($requested)) {
            throw new OrderWorkflowException('Ada barang yang bukan bagian dari pesanan ini.', 422);
        }

        $issues = [];

        foreach ($items as $item) {
            $returnable = (int) $item->quantity - (int) $item->returned_quantity;

            if ($requested[$item->id] > $returnable) {
                $issues[] = [
                    'order_item_id' => $item->id,
                    'name' => $item->product_name,
                    'requested' => $requested[$item->id],
                    'returnable' => $returnable,
                ];
            }
        }

        if ($issues !== []) {
            throw new OrderWorkflowException(
                'Jumlah retur melebihi sisa barang yang dapat diretur.',
                422,
                ['return_issues' => $issues],
            );
        }

        $products = Product::withTrashed()
            ->whereIn('id', $items->pluck('product_id')->unique()->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $lines = [];
        $totalCents = 0;

        foreach ($items as $item) {
            $quantity = $requested[$item->id];
            $unitCents = (int) round(((float) $item->unit_price) * 100);
            $totalCents += $unitCents * $quantity;
            $lines[] = [$item, $quantity, $unitCents];
        }

        $return = OrderReturn::create([
            'return_number' => $this->generateNumber(),
            'order_id' => $locked->getKey(),
            'user_id' => $cashier->getKey(),
            'reason' => $data['reason'],
            'total_amount' => $this->decimal($totalCents),
            'idempotency_key' => $data['idempotency_key'],
        ]);

        foreach ($lines as [$item, $quantity, $unitCents]) {
            OrderReturnItem::create([
                'order_return_id' => $return->getKey(),
                'order_item_id' => $item->getKey(),
                'product_id' => $item->product_id,
                'product_code' => $item->product_code,
                'product_name' => $item->product_name,
                'unit_price' => $this->decimal($unitCents),
                'quantity' => $quantity,
                'subtotal' => $this->decimal($unitCents * $quantity),
            ]);

            $this->stock->adjust(
                $products->get($item->product_id),
                StockMovementType::RETURN,
                $quantity,
                "Retur {$return->return_number} (pesanan {$locked->order_number})",
                $cashier,
                $return,
            );

            $item->forceFill(['returned_quantity' => (int) $item->returned_quantity + $quantity])->save();
        }

        return [$return, true];
    }

    private function generateNumber(): string
    {
        $prefix = 'RT-'.now()->format('ymd').'-';

        do {
            $suffix = '';

            for ($i = 0; $i < 5; $i++) {
                $suffix .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }

            $number = $prefix.$suffix;
        } while (OrderReturn::query()->where('return_number', $number)->exists());

        return $number;
    }

    private function decimal(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
