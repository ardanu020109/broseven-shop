<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\CustomerAccessToken;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderService
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private CartPricingService $pricing,
        private AuditLogger $audit,
    ) {
    }

    /**
     * @return array{0: Order, 1: string, 2: bool}
     */
    public function createFromCart(array $data): array
    {
        $key = $data['idempotency_key'];

        $existing = Order::query()->where('idempotency_key', $key)->first();

        if ($existing !== null) {
            return $this->replay($existing);
        }

        $priced = $this->pricing->priceStrict($data['items']);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                [$order, $plainToken] = DB::transaction(fn () => $this->persist($data, $priced));
            } catch (UniqueConstraintViolationException $e) {
                $existing = Order::query()->where('idempotency_key', $key)->first();

                if ($existing !== null) {
                    return $this->replay($existing);
                }

                if ($attempt === 3) {
                    throw $e;
                }

                continue;
            }

            $this->audit->log(
                'order.created',
                $order,
                "Pesanan {$order->order_number} dibuat oleh customer.",
                null,
                ['total_amount' => $order->total_amount, 'items' => count($priced['lines'])],
            );

            return [$order, $plainToken, true];
        }

        throw new RuntimeException('Gagal membuat pesanan.');
    }

    private function replay(Order $order): array
    {
        [, $plainToken] = CustomerAccessToken::issueFor($order);

        return [$order, $plainToken, false];
    }

    private function persist(array $data, array $priced): array
    {
        $order = Order::create([
            'order_number' => $this->generateOrderNumber(),
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'notes' => $data['notes'] ?? null,
            'status' => OrderStatus::DRAFT,
            'total_amount' => $this->decimal($priced['total_cents']),
            'payment_method' => 'CASH',
            'barcode_code' => strtoupper(bin2hex(random_bytes(12))),
            'idempotency_key' => $data['idempotency_key'],
        ]);

        foreach ($priced['lines'] as $line) {
            $unitCents = (int) round($line['unit_price'] * 100);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $line['product_id'],
                'product_code' => $line['code'],
                'product_name' => $line['name'],
                'unit_price' => $this->decimal($unitCents),
                'quantity' => $line['quantity'],
                'subtotal' => $this->decimal($unitCents * $line['quantity']),
            ]);
        }

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => OrderStatus::DRAFT,
            'user_id' => null,
            'note' => 'Pesanan dibuat oleh customer.',
        ]);

        [, $plainToken] = CustomerAccessToken::issueFor($order);

        return [$order, $plainToken];
    }

    private function generateOrderNumber(): string
    {
        $prefix = 'BS-'.now()->format('ymd').'-';

        do {
            $suffix = '';

            for ($i = 0; $i < 5; $i++) {
                $suffix .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }

            $number = $prefix.$suffix;
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }

    private function decimal(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
