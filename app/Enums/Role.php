<?php

namespace App\Enums;

enum Role: string
{
    case SUPERADMIN = 'superadmin';
    case CASHIER = 'kasir';
    case ADMIN_MASTER = 'admin_master';

    public function label(): string
    {
        return match ($this) {
            self::SUPERADMIN => 'Superadmin',
            self::CASHIER => 'Kasir',
            self::ADMIN_MASTER => 'Admin Master',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
