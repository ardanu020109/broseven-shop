<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\Support\BroSevenHelpers;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use BroSevenHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn(Role::ADMIN_MASTER);
    }

    public function test_creating_a_product_generates_the_code_and_the_initial_stock_movement(): void
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/products', [
            'name' => 'Teh Botol',
            'category_id' => $category->id,
            'selling_price' => 5000,
            'purchase_price' => 3000,
            'stock' => 12,
            'min_stock' => 3,
        ])->assertCreated();

        $id = $response->json('data.id');
        $product = Product::find($id);

        $this->assertSame(sprintf('BRG-%06d', $id), $response->json('data.code'));
        $this->assertSame(12, $product->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $id,
            'type' => 'STOCK_IN',
            'quantity_change' => 12,
            'stock_before' => 0,
            'stock_after' => 12,
        ]);
        $this->assertSame(12, $this->ledgerSum($product));
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.created']);
    }

    public function test_product_validation(): void
    {
        $this->postJson('/api/products', ['name' => '', 'selling_price' => -1, 'stock' => -5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'selling_price', 'stock']);

        $this->product(['barcode' => 'ABC123']);

        $this->postJson('/api/products', ['name' => 'Lain', 'selling_price' => 1, 'stock' => 0, 'barcode' => 'ABC123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('barcode');
    }

    public function test_inactive_master_data_cannot_be_chosen_but_the_current_one_is_kept(): void
    {
        $inactive = Category::factory()->create(['is_active' => false]);

        $this->postJson('/api/products', ['name' => 'Baru', 'selling_price' => 1000, 'stock' => 1, 'category_id' => $inactive->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');

        $product = $this->product(['category_id' => $inactive->id]);

        $this->putJson("/api/products/{$product->id}", [
            'name' => 'Ubah Nama',
            'selling_price' => 7000,
            'category_id' => $inactive->id,
            'min_stock' => 1,
        ])->assertOk();
    }

    public function test_stock_cannot_be_changed_through_the_edit_form(): void
    {
        $product = $this->product(['stock' => 5]);

        $this->putJson("/api/products/{$product->id}", ['name' => 'Ubah', 'selling_price' => 2000, 'stock' => 999])->assertOk();

        $this->assertSame(5, $this->stockOf($product));
    }

    public function test_deleting_a_product_is_a_soft_delete_and_keeps_its_history(): void
    {
        $product = $this->product(['stock' => 4]);

        $this->deleteJson("/api/products/{$product->id}")->assertOk();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertSame(1, StockMovement::where('product_id', $product->id)->count());
        $this->getJson("/api/products/{$product->id}")->assertNotFound();
    }

    public function test_stock_adjustments_record_movements_and_block_negative_stock(): void
    {
        $product = $this->product(['stock' => 5]);
        $url = "/api/products/{$product->id}/stock-adjustments";

        $this->postJson($url, ['type' => 'STOCK_IN', 'quantity' => 10])->assertOk()->assertJsonPath('data.stock', 15);
        $this->postJson($url, ['type' => 'ADJUSTMENT', 'quantity' => -3, 'note' => 'Koreksi stok opname'])->assertOk()->assertJsonPath('data.stock', 12);
        $this->postJson($url, ['type' => 'ADJUSTMENT', 'quantity' => -99, 'note' => 'Terlalu banyak'])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->postJson($url, ['type' => 'ADJUSTMENT', 'quantity' => -1])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->postJson($url, ['type' => 'STOCK_IN', 'quantity' => -2])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->postJson($url, ['type' => 'STOCK_IN', 'quantity' => 0])->assertUnprocessable();

        $this->assertSame(12, $this->stockOf($product));
        $this->assertSame(12, $this->ledgerSum($product));
    }

    public function test_stock_status_filters_match_the_badges(): void
    {
        $ok = $this->product(['stock' => 10, 'min_stock' => 2]);
        $low = $this->product(['stock' => 2, 'min_stock' => 2]);
        $out = $this->product(['stock' => 0, 'min_stock' => 2]);

        $ids = fn (string $status) => collect($this->getJson("/api/products?stock_status={$status}&per_page=50")->assertOk()->json('data'))
            ->pluck('id')->all();

        $this->assertSame([$ok->id], $ids('tersedia'));
        $this->assertSame([$low->id], $ids('menipis'));
        $this->assertSame([$out->id], $ids('habis'));
    }

    public function test_search_is_safe_against_sql_injection(): void
    {
        $this->product(['name' => 'Teh Botol Uji']);

        $this->getJson('/api/products?search='.urlencode("' OR 1=1 --"))->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/products?search=Botol')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_product_image_validation_and_storage(): void
    {
        Storage::fake('public');

        $base = ['name' => 'Berfoto', 'selling_price' => 1000, 'stock' => 1];
        $headers = ['Accept' => 'application/json'];

        $this->post('/api/products', $base + ['image' => UploadedFile::fake()->create('evil.jpg', 5)], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('image');

        $this->post('/api/products', $base + ['image' => UploadedFile::fake()->image('big.jpg', 100, 100)->size(3000)], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('image');

        $response = $this->post('/api/products', $base + ['image' => UploadedFile::fake()->image('ok.png', 200, 200)], $headers)->assertCreated();

        Storage::disk('public')->assertExists(Product::find($response->json('data.id'))->image_path);
    }

    public function test_admin_master_dashboard_counts_match_the_database(): void
    {
        $this->product(['stock' => 10]);
        $this->product(['stock' => 0]);
        $this->product(['stock' => 1, 'min_stock' => 2]);

        $this->getJson('/api/admin-master/dashboard')
            ->assertOk()
            ->assertJsonPath('totals.products', 3)
            ->assertJsonPath('stock.available', 1)
            ->assertJsonPath('stock.low', 1)
            ->assertJsonPath('stock.out', 1);
    }

    public function test_direct_stock_changes_and_history_edits_are_blocked(): void
    {
        $product = $this->product(['stock' => 5]);

        try {
            $product->update(['stock' => 99]);
            $this->fail('Perubahan stok langsung seharusnya ditolak.');
        } catch (LogicException) {
            // diharapkan
        }

        $this->assertSame(5, $this->stockOf($product));

        $movement = StockMovement::where('product_id', $product->id)->first();

        try {
            $movement->update(['note' => 'diubah']);
            $this->fail('Riwayat stok seharusnya tidak dapat diubah.');
        } catch (LogicException) {
            // diharapkan
        }

        try {
            $movement->delete();
            $this->fail('Riwayat stok seharusnya tidak dapat dihapus.');
        } catch (LogicException) {
            // diharapkan
        }

        $this->assertSame(1, StockMovement::where('product_id', $product->id)->count());
    }
}