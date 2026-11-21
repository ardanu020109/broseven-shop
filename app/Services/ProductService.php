<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProductService
{
    public function __construct(
        private StockService $stock,
        private AuditLogger $audit,
    ) {
    }

    public function create(array $data, ?UploadedFile $image, User $actor): Product
    {
        $initialStock = (int) ($data['stock'] ?? 0);
        unset($data['stock']);

        $data['is_active'] = $data['is_active'] ?? true;
        $data['min_stock'] = $data['min_stock'] ?? 0;

        $path = $image ? $this->storeImage($image) : null;

        try {
            return DB::transaction(function () use ($data, $path, $initialStock, $actor) {
                $product = Product::create(array_merge($data, [
                    'code' => 'TMP-'.Str::random(20),
                    'image_path' => $path,
                    'stock' => 0,
                    'created_by' => $actor->getKey(),
                    'updated_by' => $actor->getKey(),
                ]));

                $product->update(['code' => sprintf('BRG-%06d', $product->getKey())]);

                if ($initialStock > 0) {
                    $this->stock->adjust($product, StockMovementType::STOCK_IN, $initialStock, 'Stok awal', $actor);
                }

                $this->audit->log(
                    'product.created',
                    $product,
                    "Produk {$product->name} ({$product->code}) dibuat.",
                    null,
                    $product->only(['code', 'name', 'selling_price', 'stock', 'min_stock', 'is_active']),
                    $actor,
                );

                return $product->refresh();
            });
        } catch (Throwable $e) {
            if ($path !== null) {
                Storage::disk('public')->delete($path);
            }

            throw $e;
        }
    }

    public function update(Product $product, array $data, ?UploadedFile $image, bool $removeImage, User $actor): Product
    {
        unset($data['stock']);

        $oldPath = $product->image_path;
        $newPath = $oldPath;
        $uploaded = null;

        if ($image !== null) {
            $uploaded = $this->storeImage($image);
            $newPath = $uploaded;
        } elseif ($removeImage) {
            $newPath = null;
        }

        try {
            DB::transaction(function () use ($product, $data, $newPath, $actor) {
                $product->fill($data);
                $product->image_path = $newPath;
                $product->updated_by = $actor->getKey();

                $dirty = $product->getDirty();
                $oldValues = array_intersect_key($product->getOriginal(), $dirty);

                $product->save();

                $this->audit->log(
                    'product.updated',
                    $product,
                    "Produk {$product->name} ({$product->code}) diperbarui.",
                    $oldValues,
                    $dirty,
                    $actor,
                );
            });
        } catch (Throwable $e) {
            if ($uploaded !== null) {
                Storage::disk('public')->delete($uploaded);
            }

            throw $e;
        }

        if ($oldPath !== null && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return $product->refresh();
    }

    public function delete(Product $product, User $actor): void
    {
        DB::transaction(function () use ($product, $actor) {
            $product->updated_by = $actor->getKey();
            $product->save();
            $product->delete();

            $this->audit->log(
                'product.deleted',
                $product,
                "Produk {$product->name} ({$product->code}) dihapus (soft delete).",
                null,
                null,
                $actor,
            );
        });
    }

    private function storeImage(UploadedFile $image): string
    {
        $path = $image->store('products', 'public');

        if ($path === false) {
            throw new RuntimeException('Gagal menyimpan foto produk.');
        }

        return $path;
    }
}
