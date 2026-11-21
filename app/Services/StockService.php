<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\StockGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    private const MAX_STOCK = 2000000000;

    public function adjust(
        Product $product,
        StockMovementType $type,
        int $change,
        ?string $note = null,
        ?User $user = null,
        ?Model $reference = null,
    ): StockMovement {
        if ($change === 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Jumlah perubahan tidak boleh nol.'],
            ]);
        }

        $movement = DB::transaction(function () use ($product, $type, $change, $note, $user, $reference) {
            $locked = Product::withTrashed()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $before = (int) $locked->stock;
            $after = $before + $change;

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ["Stok tidak mencukupi. Stok saat ini {$before}, perubahan {$change}."],
                ]);
            }

            if ($after > self::MAX_STOCK) {
                throw ValidationException::withMessages([
                    'quantity' => ['Jumlah stok melebihi batas yang diizinkan.'],
                ]);
            }

            $locked->stock = $after;

            if ($user !== null) {
                $locked->updated_by = $user->getKey();
            }

            StockGuard::run(fn () => $locked->save());

            return StockMovement::create([
                'product_id' => $locked->getKey(),
                'type' => $type,
                'quantity_change' => $change,
                'stock_before' => $before,
                'stock_after' => $after,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'note' => $note,
                'user_id' => $user?->getKey(),
            ]);
        });

        $product->refresh();

        return $movement;
    }
}
