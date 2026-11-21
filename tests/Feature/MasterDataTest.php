<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BroSevenHelpers;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use BroSevenHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signIn(Role::ADMIN_MASTER);
    }

    public static function entities(): array
    {
        return [
            'kategori' => ['categories'],
            'tipe barang' => ['product-types'],
            'merek' => ['brands'],
            'satuan' => ['units'],
        ];
    }

    #[DataProvider('entities')]
    public function test_crud_cycle(string $uri): void
    {
        $created = $this->postJson("/api/{$uri}", ['name' => 'Contoh Data'])
            ->assertCreated()
            ->assertJsonPath('data.is_active', true);

        $id = $created->json('data.id');

        $this->postJson("/api/{$uri}", ['name' => 'contoh data'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->postJson("/api/{$uri}", ['name' => ''])->assertUnprocessable()->assertJsonValidationErrors('name');

        $this->putJson("/api/{$uri}/{$id}", ['name' => 'Data Baru', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Data Baru');

        $this->getJson("/api/{$uri}?status=inactive")->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson("/api/{$uri}?search=Baru")->assertOk()->assertJsonPath('total', 1);

        $this->deleteJson("/api/{$uri}/{$id}")->assertOk();
        $this->assertDatabaseMissing(Str::replace('-', '_', $uri), ['id' => $id]);
    }

    public function test_master_used_by_products_cannot_be_deleted_even_by_archived_products(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $response = $this->deleteJson("/api/categories/{$category->id}")->assertUnprocessable();
        $this->assertStringContainsString('masih digunakan', $response->json('message'));

        $product->delete();

        $this->deleteJson("/api/categories/{$category->id}")->assertUnprocessable();
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_units_accept_an_abbreviation_and_master_changes_are_audited(): void
    {
        $this->postJson('/api/units', ['name' => 'Kilogram', 'abbreviation' => 'kg'])
            ->assertCreated()
            ->assertJsonPath('data.abbreviation', 'kg');

        $this->assertDatabaseHas('audit_logs', ['action' => 'master.units.created']);
    }
}