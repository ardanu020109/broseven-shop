<?php

namespace App\Reports;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\ReportPeriod;
use Illuminate\Database\Eloquent\Builder;

final class CashierHistoryQuery
{
    public static function base(ReportPeriod $period, int $cashierId, ?string $status = null): Builder
    {
        return Order::query()->where(function (Builder $group) use ($period, $cashierId, $status) {
            if ($status !== OrderStatus::DIBATALKAN->value) {
                $group->orWhere(fn (Builder $done) => $done
                    ->where('status', OrderStatus::SELESAI->value)
                    ->where('cashier_id', $cashierId)
                    ->whereBetween('completed_at', $period->bounds()));
            }

            if ($status !== OrderStatus::SELESAI->value) {
                $group->orWhere(fn (Builder $cancelled) => $cancelled
                    ->where('status', OrderStatus::DIBATALKAN->value)
                    ->where('cancelled_by', $cashierId)
                    ->whereBetween('cancelled_at', $period->bounds()));
            }
        });
    }

    public static function listing(Builder $base): Builder
    {
        return $base
            ->select('orders.*')
            ->selectRaw('COALESCE(orders.completed_at, orders.cancelled_at) as event_at')
            ->orderByDesc('event_at')
            ->orderByDesc('orders.id');
    }
}
