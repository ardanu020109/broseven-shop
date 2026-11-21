<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

class CartPricingService
{
    private const MAX_TOTAL_CENTS = 999999999999999;

    public function price(array $items): array
    {
        $requested = [];

        foreach ($items as $item) {
            $id = (int) $item['product_id'];
            $requested[$id] = ($requested[$id] ?? 0) + (int) $item['quantity'];
        }

        $products = Product::query()
            ->with('unit:id,name,abbreviation')
            ->where('is_active', true)
            ->whereIn('id', array_keys($requested))
            ->get()
            ->keyBy('id');

        $lines = [];
        $removed = [];
        $totalCents = 0;

        foreach ($requested as $id => $requestedQuantity) {
            $product = $products->get($id);

            if ($product === null) {
                $removed[] = ['product_id' => $id, 'name' => null, 'reason' => 'unavailable'];
                continue;
            }

            if ($product->stock <= 0) {
                $removed[] = ['product_id' => $id, 'name' => $product->name, 'reason' => 'out_of_stock'];
                continue;
            }

            $quantity = min($requestedQuantity, (int) $product->stock);
            $unitCents = (int) round(((float) $product->selling_price) * 100);
            $subtotalCents = $unitCents * $quantity;
            $totalCents += $subtotalCents;

            $lines[] = [
                'product_id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'image_url' => $product->image_path ? '/storage/'.$product->image_path : null,
                'unit' => $product->unit?->abbreviation ?: $product->unit?->name,
                'unit_price' => $unitCents / 100,
                'stock' => (int) $product->stock,
                'requested_quantity' => $requestedQuantity,
                'quantity' => $quantity,
                'adjusted' => $quantity < $requestedQuantity,
                'subtotal' => $subtotalCents / 100,
            ];
        }

        if ($totalCents > self::MAX_TOTAL_CENTS) {
            throw ValidationException::withMessages([
                'items' => ['Total pesanan melebihi batas yang diizinkan.'],
            ]);
        }

        return [
            'lines' => $lines,
            'removed' => $removed,
            'total_cents' => $totalCents,
            'total' => $totalCents / 100,
        ];
    }

    public function priceStrict(array $items): array
    {
        $result = $this->price($items);
        $messages = [];

        foreach ($result['removed'] as $removed) {
            $messages[] = $removed['reason'] === 'out_of_stock'
                ? "Stok {$removed['name']} habis."
                : 'Ada produk di keranjang yang sudah tidak tersedia.';
        }

        foreach ($result['lines'] as $line) {
            if ($line['adjusted']) {
                $messages[] = "Stok {$line['name']} hanya tersisa {$line['stock']}, Anda memesan {$line['requested_quantity']}.";
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages([
                'items' => array_values(array_unique($messages)),
            ]);
        }

        return $result;
    }
}
