<?php

namespace App\Observers;

use App\Models\Product;
use App\Support\StockGuard;
use LogicException;

class ProductObserver
{
    public function updating(Product $product): void
    {
        if ($product->isDirty('stock') && ! StockGuard::isOpen()) {
            throw new LogicException('Stok hanya dapat diubah melalui StockService agar tercatat pada riwayat stok.');
        }
    }
}
