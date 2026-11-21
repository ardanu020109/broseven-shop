<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\CompanySetting;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DraftCleanupService
{
    public const MAX_HOURS = 720;

    public function __construct(private AuditLogger $audit)
    {
    }

    public function configuredHours(): int
    {
        return min(self::MAX_HOURS, max(0, (int) CompanySetting::current()->draft_expiry_hours));
    }

    /**
     * @return array{hours: int, found: int, deleted: int, skipped: int, orders: array<int, string>}
     */
    public function purge(?int $hours = null, bool $dryRun = false): array
    {
        $hours = $hours === null ? $this->configuredHours() : max(0, $hours);

        $result = ['hours' => $hours, 'found' => 0, 'deleted' => 0, 'skipped' => 0, 'orders' => []];

        if ($hours === 0) {
            return $result;
        }

        $cutoff = now()->subHours($hours);

        Order::query()
            ->select('id')
            ->where('status', OrderStatus::DRAFT->value)
            ->whereNull('barcode_used_at')
            ->where('created_at', '<', $cutoff)
            ->chunkById(200, function ($chunk) use ($cutoff, $dryRun, &$result) {
                foreach ($chunk as $row) {
                    $result['found']++;

                    $order = DB::transaction(fn () => $this->expire((int) $row->id, $cutoff, $dryRun));

                    if ($order === null) {
                        $result['skipped']++;

                        continue;
                    }

                    if (! $dryRun) {
                        $result['deleted']++;
                    }

                    if (count($result['orders']) < 50) {
                        $result['orders'][] = $order->order_number;
                    }
                }
            });

        return $result;
    }

    private function expire(int $id, CarbonInterface $cutoff, bool $dryRun): ?Order
    {
        $order = Order::query()->whereKey($id)->lockForUpdate()->first();

        if ($order === null
            || $order->status !== OrderStatus::DRAFT
            || $order->barcode_used_at !== null
            || $order->created_at === null
            || $order->created_at->gte($cutoff)) {
            return null;
        }

        if (! $dryRun) {
            $this->audit->log(
                'order.draft_expired',
                $order,
                "Pesanan draft {$order->order_number} dihapus otomatis karena tidak divalidasi kasir.",
                [
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'total_amount' => $order->total_amount,
                    'items' => $order->items()->count(),
                    'created_at' => $order->created_at->toIso8601String(),
                ],
            );

            $order->delete();
        }

        return $order;
    }
}