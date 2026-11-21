<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\StockMovement;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $purchase = fake()->numberBetween(1000, 80000);

        return [
            'code' => 'BRG-'.fake()->unique()->numerify('######'),
            'barcode' => null,
            'name' => fake()->unique()->words(3, true),
            'category_id' => Category::factory(),
            'product_type_id' => ProductType::factory(),
            'brand_id' => Brand::factory(),
            'unit_id' => Unit::factory(),
            'purchase_price' => $purchase,
            'selling_price' => $purchase + fake()->numberBetween(500, 20000),
            'stock' => fake()->numberBetween(5, 100),
            'min_stock' => 5,
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product) {
            if ($product->stock > 0) {
                StockMovement::create([
                    'product_id' => $product->getKey(),
                    'type' => StockMovementType::STOCK_IN,
                    'quantity_change' => $product->stock,
                    'stock_before' => 0,
                    'stock_after' => $product->stock,
                    'note' => 'Stok awal (factory)',
                ]);
            }
        });
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock' => 0]);
    }

    public function lowStock(): static
    {
        return $this->state(fn () => ['stock' => 3, 'min_stock' => 5]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
