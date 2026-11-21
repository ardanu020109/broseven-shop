<?php

namespace App\Models\Concerns;

use LogicException;

trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(fn () => throw new LogicException('Data riwayat bersifat append-only dan tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Data riwayat tidak boleh dihapus.'));
    }
}
