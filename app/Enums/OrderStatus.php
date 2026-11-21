<?php

namespace App\Enums;

enum OrderStatus: string
{
    case DRAFT = 'DRAFT';
    case MENUNGGU = 'MENUNGGU';
    case SELESAI = 'SELESAI';
    case DIBATALKAN = 'DIBATALKAN';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::MENUNGGU => 'Menunggu',
            self::SELESAI => 'Selesai',
            self::DIBATALKAN => 'Dibatalkan',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DRAFT => 'Pesanan berhasil dibuat dan menunggu validasi kasir.',
            self::MENUNGGU => 'Pesanan sudah disetujui kasir dan menunggu penyelesaian pembayaran.',
            self::SELESAI => 'Pembayaran telah diterima dan transaksi selesai.',
            self::DIBATALKAN => 'Pesanan dibatalkan.',
        };
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT => [self::MENUNGGU, self::DIBATALKAN],
            self::MENUNGGU => [self::SELESAI, self::DIBATALKAN],
            self::SELESAI, self::DIBATALKAN => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
