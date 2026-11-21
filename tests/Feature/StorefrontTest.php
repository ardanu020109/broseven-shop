<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\CustomerAccessToken;
use App\Models\Order;
use App\Services\OrderWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\BroSevenHelpers;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use BroSevenHelpers;
    use RefreshDatabase;

    private function payload(array $lines, array $override = []): array
    {
        return array_merge([
            'customer_name' => 'Budi Santoso',
            'customer_phone' => '081234567890',
            'notes' => 'Tanpa kantong plastik',
            'idempotency_key' => (string) Str::uuid(),
            'items' => array_map(fn ($line) => ['product_id' => $line[0]->id, 'quantity' => $line[1]], $lines),
        ], $override);
    }

    public function test_catalog_hides_inactive_and_deleted_products_and_sensitive_fields(): void
    {
        $this->product(['name' => 'Roti Tawar Uji']);
        $this->product(['name' => 'Roti Habis Uji', 'stock' => 0]);
        $this->product(['name' => 'Roti Nonaktif Uji', 'is_active' => false]);
        $this->product(['name' => 'Roti Terhapus Uji'])->delete();

        $response = $this->getJson('/api/store/products?per_page=48')->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();

        $this->assertContains('Roti Tawar Uji', $names);
        $this->assertContains('Roti Habis Uji', $names);
        $this->assertNotContains('Roti Nonaktif Uji', $names);
        $this->assertNotContains('Roti Terhapus Uji', $names);

        $habis = collect($response->json('data'))->firstWhere('name', 'Roti Habis Uji');
        $this->assertFalse($habis['in_stock']);

        $response->assertJsonMissingPath('data.0.purchase_price')
            ->assertJsonMissingPath('data.0.code')
            ->assertJsonMissingPath('data.0.min_stock');
    }

    public function test_product_detail_of_an_inactive_product_is_not_found(): void
    {
        $inactive = $this->product(['is_active' => false]);

        $this->getJson("/api/store/products/{$inactive->id}")->assertNotFound();
        $this->getJson('/api/store/products/'.$this->product()->id)->assertOk();
    }

    public function test_cart_validation_adjusts_quantities_and_removes_unavailable_items(): void
    {
        $limited = $this->product(['stock' => 5, 'selling_price' => 7000]);
        $empty = $this->product(['stock' => 0]);
        $inactive = $this->product(['is_active' => false]);

        $response = $this->postJson('/api/store/cart/validate', ['items' => [
            ['product_id' => $limited->id, 'quantity' => 99],
            ['product_id' => $empty->id, 'quantity' => 1],
            ['product_id' => $inactive->id, 'quantity' => 1],
        ]])->assertOk();

        $this->assertSame(5, $response->json('lines.0.quantity'));
        $this->assertTrue($response->json('lines.0.adjusted'));
        $this->assertEquals(35000, $response->json('total'));
        $this->assertSame(['out_of_stock', 'unavailable'], collect($response->json('removed'))->pluck('reason')->sort()->values()->all());
    }

    public function test_order_uses_database_prices_and_does_not_touch_stock(): void
    {
        $product = $this->product(['stock' => 10, 'selling_price' => 12500]);

        $payload = $this->payload([[$product, 2]]);
        $payload['total_amount'] = 1;
        $payload['items'][0]['unit_price'] = 1;
        $payload['items'][0]['price'] = 1;

        $response = $this->postJson('/api/store/orders', $payload)->assertCreated();

        $this->assertEquals(25000, $response->json('data.total_amount'));
        $this->assertSame('DRAFT', $response->json('data.status'));
        $this->assertMatchesRegularExpression('/^BS-\d{6}-[A-Z2-9]{5}$/', $response->json('data.order_number'));
        $this->assertMatchesRegularExpression('/^[0-9A-F]{24}$/', $response->json('data.barcode_code'));
        $this->assertSame(10, $this->stockOf($product));
        $this->assertDatabaseCount('stock_movements', 1);

        $order = Order::where('order_number', $response->json('data.order_number'))->first();
        $this->assertSame(1, $order->statusHistories()->count());
        $this->assertEquals(12500, $order->items()->first()->unit_price);

        $token = $response->json('token');
        $stored = CustomerAccessToken::where('order_id', $order->id)->first();
        $this->assertSame(hash('sha256', $token), $stored->token_hash);
        $this->assertNotSame($token, $stored->token_hash);
    }

    public function test_the_same_idempotency_key_never_creates_two_orders(): void
    {
        $product = $this->product();
        $payload = $this->payload([[$product, 1]]);

        $first = $this->postJson('/api/store/orders', $payload)->assertCreated();
        $second = $this->postJson('/api/store/orders', $payload)->assertOk();

        $this->assertSame($first->json('data.order_number'), $second->json('data.order_number'));
        $this->assertSame(1, Order::count());
    }

    public function test_order_is_rejected_when_stock_is_not_enough(): void
    {
        $limited = $this->product(['stock' => 2]);
        $empty = $this->product(['stock' => 0]);

        $this->postJson('/api/store/orders', $this->payload([[$limited, 3]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->postJson('/api/store/orders', $this->payload([[$empty, 1]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertSame(0, Order::count());
    }

    public function test_checkout_validation_and_xss_sanitising(): void
    {
        $product = $this->product();

        $this->postJson('/api/store/orders', $this->payload([[$product, 1]], ['customer_phone' => 'abc', 'customer_name' => 'A']))
            ->assertUnprocessable()->assertJsonValidationErrors(['customer_phone', 'customer_name']);

        $this->postJson('/api/store/orders', $this->payload([], []))->assertUnprocessable();

        $response = $this->postJson('/api/store/orders', $this->payload([[$product, 1]], [
            'customer_name' => '<script>alert(1)</script>Budi',
            'notes' => '<b>Catatan</b>',
        ]))->assertCreated();

        $this->assertSame('alert(1)Budi', $response->json('data.customer_name'));
        $this->assertSame('Catatan', $response->json('data.notes'));
    }

    public function test_order_tracking_requires_the_matching_number_and_token(): void
    {
        $product = $this->product();

        $a = $this->postJson('/api/store/orders', $this->payload([[$product, 1]]))->assertCreated();
        $b = $this->postJson('/api/store/orders', $this->payload([[$product, 1]]))->assertCreated();

        $numberA = $a->json('data.order_number');
        $tokenA = $a->json('token');
        $tokenB = $b->json('token');

        $this->getJson("/api/store/orders/{$numberA}", ['X-Order-Token' => $tokenA])
            ->assertOk()->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.barcode_code', $a->json('data.barcode_code'));

        $this->getJson("/api/store/orders/{$numberA}")->assertNotFound();
        $this->getJson("/api/store/orders/{$numberA}", ['X-Order-Token' => 'salah'])->assertNotFound();
        $this->getJson("/api/store/orders/{$numberA}", ['X-Order-Token' => $tokenB])->assertNotFound();
        $this->getJson('/api/store/orders/BS-000000-AAAAA', ['X-Order-Token' => $tokenA])->assertNotFound();
    }

    public function test_barcode_is_hidden_after_approval_and_cancellation_reason_is_visible(): void
    {
        $product = $this->product();
        $cashier = $this->makeUser(Role::CASHIER);
        $workflow = app(OrderWorkflowService::class);

        $a = $this->postJson('/api/store/orders', $this->payload([[$product, 1]]))->assertCreated();
        $b = $this->postJson('/api/store/orders', $this->payload([[$product, 1]]))->assertCreated();

        $orderA = Order::where('order_number', $a->json('data.order_number'))->first();
        $workflow->approve($orderA, $cashier);

        $this->getJson('/api/store/orders/'.$orderA->order_number, ['X-Order-Token' => $a->json('token')])
            ->assertOk()->assertJsonPath('data.status', 'MENUNGGU')->assertJsonPath('data.barcode_code', null);

        $orderB = Order::where('order_number', $b->json('data.order_number'))->first();
        $workflow->cancel($orderB, $cashier, 'Customer tidak jadi membeli');

        $this->getJson('/api/store/orders/'.$orderB->order_number, ['X-Order-Token' => $b->json('token')])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::DIBATALKAN->value)
            ->assertJsonPath('data.cancel_reason', 'Customer tidak jadi membeli');
    }

}
