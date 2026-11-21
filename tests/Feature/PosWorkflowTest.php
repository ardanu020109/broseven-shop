<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BroSevenHelpers;
use Tests\TestCase;

class PosWorkflowTest extends TestCase
{
    use BroSevenHelpers;
    use RefreshDatabase;

    private User $cashier;

    private Product $p1;

    private Product $p2;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = $this->signIn(Role::CASHIER);
        $this->p1 = $this->product(['stock' => 10, 'selling_price' => 10000]);
        $this->p2 = $this->product(['stock' => 3, 'selling_price' => 25000]);
        $this->order = $this->draftOrder([[$this->p1, 2], [$this->p2, 1]]);
    }

    private function url(string $action = ''): string
    {
        return "/api/kasir/orders/{$this->order->id}".($action !== '' ? "/{$action}" : '');
    }

    private function approve(): void
    {
        $this->postJson($this->url('approve'))->assertOk();
    }

    public function test_lookup_by_barcode_and_by_order_number(): void
    {
        $this->postJson('/api/kasir/orders/lookup', ['code' => strtolower($this->order->barcode_code)])
            ->assertOk()
            ->assertJsonPath('matched_by', 'barcode')
            ->assertJsonPath('barcode.valid', true)
            ->assertJsonPath('data.order_number', $this->order->order_number)
            ->assertJsonPath('data.can_approve', true);

        $this->postJson('/api/kasir/orders/lookup', ['code' => $this->order->order_number])
            ->assertOk()
            ->assertJsonPath('matched_by', 'order_number')
            ->assertJsonPath('barcode', null);
    }

    public function test_lookup_rejects_bad_formats_and_unknown_codes(): void
    {
        $this->postJson('/api/kasir/orders/lookup', ['code' => 'ABC123'])->assertUnprocessable();
        $this->postJson('/api/kasir/orders/lookup', ['code' => ''])->assertUnprocessable();
        $this->postJson('/api/kasir/orders/lookup', ['code' => 'ABCDEF012345ABCDEF012345'])->assertNotFound();
        $this->postJson('/api/kasir/orders/lookup', ['code' => 'BS-000000-AAAAA'])->assertNotFound();

        $this->assertDatabaseHas('audit_logs', ['action' => 'pos.scan_failed']);
    }

    public function test_approval_deducts_stock_once_and_burns_the_barcode(): void
    {
        $this->postJson($this->url('approve'), ['via' => 'scan'])
            ->assertOk()
            ->assertJsonPath('data.status', 'MENUNGGU')
            ->assertJsonPath('data.can_complete', true);

        $order = $this->order->fresh();

        $this->assertSame(OrderStatus::MENUNGGU, $order->status);
        $this->assertSame($this->cashier->id, (int) $order->cashier_id);
        $this->assertNotNull($order->barcode_used_at);
        $this->assertNotNull($order->stock_deducted_at);
        $this->assertSame(8, $this->stockOf($this->p1));
        $this->assertSame(2, $this->stockOf($this->p2));
        $this->assertSame(2, StockMovement::where('type', 'SALE')->where('reference_id', $order->id)->count());
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->p1->id, 'type' => 'SALE', 'quantity_change' => -2, 'stock_before' => 10, 'stock_after' => 8,
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id, 'from_status' => 'DRAFT', 'to_status' => 'MENUNGGU', 'user_id' => $this->cashier->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.approved']);

        $this->postJson($this->url('approve'))->assertStatus(409);
        $this->assertSame(8, $this->stockOf($this->p1));
        $this->assertSame(2, StockMovement::where('type', 'SALE')->where('reference_id', $order->id)->count());

        $this->postJson('/api/kasir/orders/lookup', ['code' => $order->barcode_code])
            ->assertOk()->assertJsonPath('barcode.valid', false);
    }

    public function test_approval_is_rejected_without_side_effects_when_stock_is_short(): void
    {
        app(StockService::class)->adjust($this->p1, StockMovementType::ADJUSTMENT, -9, 'Uji stok kurang');

        $this->postJson($this->url('approve'))
            ->assertStatus(422)
            ->assertJsonPath('stock_issues.0.product_id', $this->p1->id)
            ->assertJsonPath('stock_issues.0.requested', 2)
            ->assertJsonPath('stock_issues.0.available', 1);

        $order = $this->order->fresh();

        $this->assertSame(OrderStatus::DRAFT, $order->status);
        $this->assertNull($order->barcode_used_at);
        $this->assertNull($order->stock_deducted_at);
        $this->assertSame(0, StockMovement::where('type', 'SALE')->count());
        $this->assertSame(1, $this->stockOf($this->p1));
        $this->assertSame(3, $this->stockOf($this->p2));
    }

    public function test_completion_needs_approval_and_does_not_touch_stock(): void
    {
        $this->postJson($this->url('complete'))->assertStatus(409);

        $this->approve();

        $this->postJson($this->url('complete'))->assertOk()->assertJsonPath('data.status', 'SELESAI');

        $this->assertNotNull($this->order->fresh()->completed_at);
        $this->assertSame(8, $this->stockOf($this->p1));
        $this->assertSame(2, $this->stockOf($this->p2));

        $this->postJson($this->url('complete'))->assertStatus(409);
        $this->postJson($this->url('cancel'), ['reason' => 'Mencoba membatalkan'])->assertStatus(409);
        $this->postJson($this->url('approve'))->assertStatus(409);
    }

    public function test_cancelling_a_draft_needs_a_reason_and_keeps_stock(): void
    {
        $this->postJson($this->url('cancel'), [])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson($this->url('cancel'), ['reason' => 'abc'])->assertUnprocessable();

        $this->postJson($this->url('cancel'), ['reason' => 'Customer berubah pikiran'])
            ->assertOk()
            ->assertJsonPath('data.status', 'DIBATALKAN')
            ->assertJsonPath('data.cancel_reason', 'Customer berubah pikiran');

        $order = $this->order->fresh();

        $this->assertSame($this->cashier->id, (int) $order->cancelled_by);
        $this->assertSame(10, $this->stockOf($this->p1));
        $this->assertSame(3, $this->stockOf($this->p2));
        $this->assertSame(0, StockMovement::where('type', 'SALE')->count());
        $this->assertSame(0, StockMovement::where('type', 'SALE_CANCELLED')->count());

        $this->postJson($this->url('approve'))->assertStatus(409);
    }

    public function test_cancelling_an_approved_order_restores_stock_exactly_once(): void
    {
        $this->approve();

        $this->postJson($this->url('cancel'), ['reason' => 'Pembayaran tidak jadi'])
            ->assertOk()->assertJsonPath('data.status', 'DIBATALKAN');

        $order = $this->order->fresh();

        $this->assertNotNull($order->stock_restored_at);
        $this->assertSame(10, $this->stockOf($this->p1));
        $this->assertSame(3, $this->stockOf($this->p2));
        $this->assertSame(2, StockMovement::where('type', 'SALE_CANCELLED')->where('reference_id', $order->id)->count());

        $this->postJson($this->url('cancel'), ['reason' => 'Dibatalkan lagi'])->assertStatus(409);

        $this->assertSame(10, $this->stockOf($this->p1));
        $this->assertSame(2, StockMovement::where('type', 'SALE_CANCELLED')->where('reference_id', $order->id)->count());
        $this->assertSame(10, $this->ledgerSum($this->p1));
    }

    public function test_any_cashier_can_open_and_approve_a_draft(): void
    {
        $other = $this->signIn(Role::CASHIER);

        $this->getJson($this->url())->assertOk()->assertJsonPath('data.can_approve', true);
        $this->postJson($this->url('approve'))->assertOk();

        $this->assertSame($other->id, (int) $this->order->fresh()->cashier_id);
    }

    public function test_other_cashiers_cannot_see_or_finish_an_order_after_approval(): void
    {
        $this->approve();

        $this->signIn(Role::CASHIER);

        $this->getJson($this->url())->assertNotFound();
        $this->postJson($this->url('complete'))->assertNotFound();
        $this->postJson($this->url('cancel'), ['reason' => 'Percobaan batal'])->assertNotFound();
        $this->postJson('/api/kasir/orders/lookup', ['code' => $this->order->barcode_code])->assertStatus(409);
        $this->getJson('/api/kasir/orders?status=MENUNGGU')->assertOk()->assertJsonPath('total', 0);

        $this->assertSame(OrderStatus::MENUNGGU, $this->order->fresh()->status);
        $this->assertSame(8, $this->stockOf($this->p1));
    }

    public function test_only_cashiers_can_run_the_workflow(): void
    {
        $this->signIn(Role::SUPERADMIN);
        $this->postJson($this->url('approve'))->assertForbidden();
        $this->postJson('/api/kasir/orders/lookup', ['code' => $this->order->order_number])->assertForbidden();

        $this->signIn(Role::ADMIN_MASTER);
        $this->postJson($this->url('approve'))->assertForbidden();
        $this->postJson($this->url('complete'))->assertForbidden();

        $this->assertSame(OrderStatus::DRAFT, $this->order->fresh()->status);
    }

    public function test_counts_and_listing(): void
    {
        $this->getJson('/api/kasir/orders/counts')->assertOk()->assertJsonPath('draft', 1)->assertJsonPath('waiting', 0);

        $this->getJson('/api/kasir/orders?status=DRAFT&search='.urlencode('Customer Uji'))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.order_number', $this->order->order_number);

        $this->approve();

        $this->getJson('/api/kasir/orders/counts')->assertJsonPath('draft', 0)->assertJsonPath('waiting', 1);
    }
}