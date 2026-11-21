<?php

namespace App\Enums;

enum StockMovementType: string
{
    case STOCK_IN = 'STOCK_IN';
    case SALE = 'SALE';
    case SALE_CANCELLED = 'SALE_CANCELLED';
    case RETURN = 'RETURN';
    case ADJUSTMENT = 'ADJUSTMENT';

    public function label(): string
    {
        return match ($this) {
            self::STOCK_IN => 'Stok Masuk',
            self::SALE => 'Penjualan',
            self::SALE_CANCELLED => 'Pembatalan Penjualan',
            self::RETURN => 'Retur',
            self::ADJUSTMENT => 'Penyesuaian',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
