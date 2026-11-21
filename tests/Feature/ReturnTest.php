<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\BroSevenHelpers;
use Tests\TestCase;

class ReturnTest extends TestCase
{
    use BroSevenHelpers;
    use RefreshDatabase;

    private User $cashier;

    private Product $p1;

    private Order $order;

    private OrderItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = $this->signIn(Role::CASHIER);
        $this->p1 = $this->product(['stock' => 20, 'selling_price' => 10000]);
        $this->order = $this->completedOrder([[$this->p1, 3]], $this->cashier);
        $this->item = $this->order->items()->first();
    }

    private function body(OrderItem $item, int $quantity, ?string $key = null, string $reason = 'Barang rusak'): array
    {
        return [
            'reason' => $reason,
            'idempotency_key' => $key ?? (string) Str::uuid(),
            'items' => [['order_item_id' => $item->id, 'quantity' => $quantity]],
        ];
    }

    private function url(?Order $order = null): string
    {
        return '/api/kasir/orders/'.($order ?? $this->order)->id.'/returns';
    }

    public function test_partial_returns_accumulate_and_cannot_exceed_the_purchased_quantity(): void
    {
        $this->assertSame(17, $this->stockOf($this->p1));

        $first = $this->postJson($this->url(), $this->body($this->item, 1))->assertCreated();

        $this->assertMatchesRegularExpression('/^RT-\d{6}-[A-Z2-9]{5}$/', $first->json('return.return_number'));
        $this->assertEquals(10000, $first->json('return.total_amount'));
        $first->assertJsonPath('data.status', 'SELESAI')
            ->assertJsonPath('data.items.0.returned_quantity', 1)
            ->assertJsonPath('data.items.0.returnable_quantity', 2)
            ->assertJsonPath('data.can_return', true);
        $this->assertSame(18, $this->stockOf($this->p1));

        $this->postJson($this->url(), $this->body($this->item, 2))
            ->assertCreated()->assertJsonPath('data.can_return', false);
        $this->assertSame(20, $this->stockOf($this->p1));

        $this->postJson($this->url(), $this->body($this->item, 1))
            ->assertStatus(422)
            ->assertJsonPath('return_issues.0.returnable', 0)
            ->assertJsonPath('return_issues.0.requested', 1);

        $this->assertSame(20, $this->stockOf($this->p1));
        $this->assertSame(3, (int) $this->item->fresh()->returned_quantity);
        $this->assertSame(20, $this->ledgerSum($this->p1));
        $this->assertSame(2, StockMovement::where('type', 'RETURN')->count());
        $this->assertSame(OrderReturn::class, StockMovement::where('type', 'RETURN')->first()->reference_type);
    }

    public function test_the_same_idempotency_key_returns_the_same_return_and_adds_stock_once(): void
    {
        $key = (string) Str::uuid();

        $a = $this->postJson($this->url(), $this->body($this->item, 1, $key))->assertCreated();
        $b = $this->postJson($this->url(), $this->body($this->item, 1, $key))->assertOk();

        $this->assertSame($a->json('return.return_number'), $b->json('return.return_number'));
        $this->assertSame(1, OrderReturn::count());
        $this->assertSame(18, $this->stockOf($this->p1));
    }

    public function test_only_finished_orders_can_be_returned(): void
    {
        $pending = $this->draftOrder([[$this->p1, 1]]);
        $item = $pending->items()->first();

        $this->postJson($this->url($pending), $this->body($item, 1))->assertStatus(409);

        app(OrderWorkflowService::class)->approve($pending, $this->cashier);

        $this->postJson($this->url($pending), $this->body($item, 1))->assertStatus(409);
        $this->assertSame(0, OrderReturn::count());
    }

    public function test_items_must_belong_to_the_order(): void
    {
        $other = $this->completedOrder([[$this->p1, 1]], $this->cashier);
        $foreign = $other->items()->first();

        $this->postJson($this->url(), $this->body($foreign, 1))->assertStatus(422);
        $this->assertSame(0, OrderReturn::count());
    }

    public function test_validation(): void
    {
        $this->postJson($this->url(), ['items' => []])->assertUnprocessable();
        $this->postJson($this->url(), $this->body($this->item, 0))->assertUnprocessable();
        $this->postJson($this->url(), $this->body($this->item, 1, null, 'abc'))->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson($this->url(), $this->body($this->item, 1, 'bukan-uuid'))->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
    }

    public function test_only_the_handling_cashier_can_return_and_other_roles_are_forbidden(): void
    {
        $this->signIn(Role::CASHIER);
        $this->postJson($this->url(), $this->body($this->item, 1))->assertNotFound();
        $this->getJson('/api/kasir/returns')->assertOk()->assertJsonPath('total', 0);

        $this->signIn(Role::SUPERADMIN);
        $this->postJson($this->url(), $this->body($this->item, 1))->assertForbidden();

        $this->signIn(Role::ADMIN_MASTER);
        $this->postJson($this->url(), $this->body($this->item, 1))->assertForbidden();

        $this->assertSame(0, OrderReturn::count());
        $this->assertSame(17, $this->stockOf($this->p1));
    }

    public function test_return_window_blocks_late_returns(): void
    {
        config(['broseven.return_window_days' => 1]);

        $late = $this->completedOrder([[$this->p1, 1]], $this->cashier, '2026-11-01 10:00:00');
        $item = $late->items()->first();

        \Carbon\Carbon::setTestNow('2026-11-10 10:00:00');

        $response = $this->postJson($this->url($late), $this->body($item, 1))->assertStatus(422);
        $this->assertStringContainsString('Batas waktu retur', $response->json('message'));

        $this->getJson('/api/kasir/orders/'.$late->id)->assertOk()->assertJsonPath('data.can_return', false);
    }

    public function test_return_history_is_scoped_to_the_cashier(): void
    {
        $created = $this->postJson($this->url(), $this->body($this->item, 1))->assertCreated();
        $id = OrderReturn::where('return_number', $created->json('return.return_number'))->value('id');

        $this->getJson('/api/kasir/returns')->assertOk()->assertJsonPath('total', 1);
        $this->getJson("/api/kasir/returns/{$id}")->assertOk()->assertJsonPath('data.items.0.quantity', 1);

        $this->signIn(Role::CASHIER);
        $this->getJson("/api/kasir/returns/{$id}")->assertNotFound();
    }
}