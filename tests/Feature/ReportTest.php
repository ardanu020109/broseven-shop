<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderWorkflowService;
use App\Services\ReturnService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\BroSevenHelpers;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use BroSevenHelpers;
    use RefreshDatabase;

    private User $admin;

    private User $c1;

    private User $c2;

    private Order $o1;

    private Order $o2;

    private Order $draft;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->signIn(Role::SUPERADMIN);
        $this->c1 = $this->makeUser(Role::CASHIER, ['name' => 'Kasir Satu']);
        $this->c2 = $this->makeUser(Role::CASHIER, ['name' => 'Kasir Dua']);

        $p1 = $this->product(['name' => 'Barang Satu', 'selling_price' => 10000, 'stock' => 200]);
        $p2 = $this->product(['name' => 'Barang Dua', 'selling_price' => 25000, 'stock' => 200]);
        $this->product(['name' => 'Produk Habis Uji', 'selling_price' => 1000, 'stock' => 0]);

        $this->o1 = $this->completedOrder([[$p1, 2], [$p2, 1]], $this->c1, '2026-11-10 09:00:00', ['customer_name' => 'Pelanggan A', 'customer_phone' => '0811111111']);
        $this->o2 = $this->completedOrder([[$p1, 5]], $this->c2, '2026-11-12 14:00:00', ['customer_name' => 'Pelanggan B', 'customer_phone' => '0822222222']);

        Carbon::setTestNow('2026-11-13 08:00:00');
        $workflow = app(OrderWorkflowService::class);

        $o3 = $this->draftOrder([[$p2, 2]], ['customer_name' => 'Pelanggan C', 'customer_phone' => '0833333333']);
        $workflow->approve($o3, $this->c1, 'manual');

        $o4 = $this->draftOrder([[$p1, 1]], ['customer_name' => 'Pelanggan D', 'customer_phone' => '0844444444']);
        $workflow->cancel($o4, $this->c2, 'Customer batal');

        $this->draft = $this->draftOrder([[$p1, 1]], ['customer_name' => 'Pelanggan E', 'customer_phone' => '0855555555']);

        $this->completedOrder([[$p1, 1]], $this->c1, '2026-10-20 10:00:00', ['customer_name' => 'Pelanggan F', 'customer_phone' => '0866666666']);

        Carbon::setTestNow('2026-11-14 10:00:00');
        $item = $this->o1->items()->where('product_id', $p1->id)->first();

        app(ReturnService::class)->create($this->o1, $this->c1, [
            'reason' => 'Barang rusak',
            'idempotency_key' => (string) Str::uuid(),
            'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
        ]);

        Carbon::setTestNow('2026-11-20 12:00:00');
    }

    public function test_revenue_counts_only_finished_orders_and_shows_returns_separately(): void
    {
        $response = $this->getJson('/api/reports/revenue?period=month&month=2026-11')->assertOk();

        $this->assertEquals(95000, $response->json('summary.gross_revenue'));
        $this->assertEquals(10000, $response->json('summary.returns_total'));
        $this->assertEquals(85000, $response->json('summary.net_revenue'));
        $this->assertSame(2, $response->json('summary.transactions'));
        $this->assertEquals(47500, $response->json('summary.average'));
        $this->assertSame(8, $response->json('summary.units_sold'));
        $this->assertEquals(95000, collect($response->json('series'))->sum('revenue'));
    }

    public function test_revenue_period_filters_and_cashier_filter(): void
    {
        $this->assertEquals(45000, $this->getJson('/api/reports/revenue?period=day&date=2026-11-10')->json('summary.gross_revenue'));
        $this->assertEquals(10000, $this->getJson('/api/reports/revenue?period=month&month=2026-10')->json('summary.gross_revenue'));
        $this->assertEquals(105000, $this->getJson('/api/reports/revenue?period=year&year=2026')->json('summary.gross_revenue'));
        $this->assertEquals(50000, $this->getJson('/api/reports/revenue?period=range&from=2026-11-11&to=2026-11-30')->json('summary.gross_revenue'));
        $this->assertEquals(45000, $this->getJson("/api/reports/revenue?period=month&month=2026-11&cashier_id={$this->c1->id}")->json('summary.gross_revenue'));

        $day = $this->getJson('/api/reports/revenue?period=day&date=2026-11-10')->json('series');
        $this->assertCount(24, $day);
        $this->assertEquals(45000, collect($day)->firstWhere('key', '9')['revenue']);
    }

    public function test_revenue_period_validation(): void
    {
        $this->getJson('/api/reports/revenue?period=range')->assertUnprocessable();
        $this->getJson('/api/reports/revenue?period=range&from=2026-11-10&to=2026-11-01')->assertUnprocessable();
        $this->getJson('/api/reports/revenue?period=range&from=2020-01-01&to=2026-01-01')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson('/api/reports/revenue?period=weekly')->assertUnprocessable();
    }

    public function test_revenue_transactions_list_only_finished_orders_newest_first(): void
    {
        $this->getJson('/api/reports/revenue/transactions?period=month&month=2026-11')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.customer_name', 'Pelanggan B')
            ->assertJsonPath('data.1.customer_name', 'Pelanggan A');
    }

    public function test_cashier_performance_report(): void
    {
        $rows = collect($this->getJson('/api/reports/cashiers?period=month&month=2026-11')->assertOk()->json('rows'));

        $one = $rows->firstWhere('name', 'Kasir Satu');
        $two = $rows->firstWhere('name', 'Kasir Dua');

        $this->assertSame(2, $one['handled']);
        $this->assertSame(1, $one['completed']);
        $this->assertEquals(45000, $one['revenue']);
        $this->assertSame(3, $one['units_sold']);
        $this->assertSame(0, $one['cancelled']);
        $this->assertSame(1, $one['returns_count']);
        $this->assertEquals(35000, $one['net_revenue']);

        $this->assertSame(1, $two['handled']);
        $this->assertSame(1, $two['completed']);
        $this->assertEquals(50000, $two['revenue']);
        $this->assertSame(1, $two['cancelled']);
    }

    public function test_superadmin_dashboard_totals(): void
    {
        $response = $this->getJson('/api/superadmin/dashboard?period=month&month=2026-11')->assertOk();

        $this->assertEquals(0, $response->json('totals.revenue_today'));
        $this->assertEquals(95000, $response->json('totals.revenue_month'));
        $this->assertEquals(105000, $response->json('totals.revenue_year'));
        $this->assertSame(6, $response->json('totals.customers'));
        $this->assertSame(2, $response->json('totals.cashiers'));
        $this->assertSame(3, $response->json('products.total'));
        $this->assertSame(1, $response->json('products.out'));
        $this->assertEquals(85000, $response->json('period_summary.net_revenue'));
        $this->assertSame('Barang Satu', $response->json('top_products.0.name'));
        $this->assertSame(7, $response->json('top_products.0.quantity'));
    }

    public function test_monitor_counts_and_barcode_visibility(): void
    {
        $response = $this->getJson('/api/monitor/orders')->assertOk();

        $this->assertSame(1, $response->json('counts.DRAFT'));
        $this->assertSame(1, $response->json('counts.MENUNGGU'));
        $this->assertSame(3, $response->json('counts.SELESAI'));
        $this->assertSame(1, $response->json('counts.DIBATALKAN'));

        $this->getJson("/api/monitor/orders/{$this->draft->id}")->assertOk()->assertJsonPath('data.barcode_code', $this->draft->barcode_code);
        $this->getJson("/api/monitor/orders/{$this->o1->id}")->assertOk()->assertJsonPath('data.barcode_code', null);
        $this->postJson("/api/kasir/orders/{$this->draft->id}/approve")->assertForbidden();
    }

    public function test_cashier_self_report_is_scoped_to_the_cashier(): void
    {
        $this->actingAs($this->c2);

        $summary = $this->getJson('/api/kasir/reports/summary?period=month&month=2026-11')->assertOk();
        $this->assertSame(1, $summary->json('summary.completed'));
        $this->assertSame(1, $summary->json('summary.cancelled'));
        $this->assertEquals(50000, $summary->json('summary.revenue'));

        $this->getJson('/api/kasir/reports/transactions?period=month&month=2026-11')->assertOk()->assertJsonPath('total', 2);
        $this->getJson('/api/kasir/reports/transactions?period=month&month=2026-11&status=SELESAI')->assertJsonPath('total', 1);
        $this->getJson('/api/kasir/reports/transactions?period=month&month=2026-11&status=DIBATALKAN')->assertJsonPath('total', 1);
        $this->getJson("/api/kasir/reports/transactions?period=month&month=2026-11&cashier_id={$this->c1->id}")->assertJsonPath('total', 2);

        $this->actingAs($this->c1);
        $this->getJson('/api/kasir/reports/transactions?period=month&month=2026-11')->assertJsonPath('total', 1);
    }

    public function test_inventory_report_summary_and_filters(): void
    {
        $this->getJson('/api/reports/inventory?per_page=50')
            ->assertOk()
            ->assertJsonPath('summary.total', 3)
            ->assertJsonPath('summary.tersedia', 2)
            ->assertJsonPath('summary.habis', 1);

        $this->getJson('/api/reports/inventory?status=habis')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.name', 'Produk Habis Uji');
    }

    public function test_pdf_export_is_a_real_pdf_and_is_audited(): void
    {
        $response = $this->get('/api/reports/revenue/export?format=pdf&period=month&month=2026-11')->assertOk();

        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertGreaterThan(1500, strlen($response->getContent()));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.exported']);
    }

    public function test_excel_export_contains_the_right_rows_as_text_and_numbers(): void
    {
        $response = $this->get('/api/reports/revenue/export?format=xlsx&period=month&month=2026-11')->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('Content-Type'));

        $binary = $this->binaryBody($response);
        $this->assertStringStartsWith('PK', $binary);

        $xlsx = $this->xlsxContents($binary);

        $this->assertStringContainsString('Detail Transaksi', $xlsx['all']);
        $this->assertStringContainsString('Pelanggan A', $xlsx['all']);
        $this->assertStringContainsString('Pelanggan B', $xlsx['all']);

        foreach (['Pelanggan C', 'Pelanggan D', 'Pelanggan E', 'Pelanggan F'] as $excluded) {
            $this->assertStringNotContainsString($excluded, $xlsx['all'], "{$excluded} tidak boleh ada di laporan pendapatan November");
        }

        $this->assertStringContainsString('95000', $xlsx['all']);
    }

    public function test_excel_export_never_writes_formulas_from_user_text(): void
    {
        $product = $this->product(['stock' => 50]);
        $this->completedOrder([[$product, 1]], $this->c1, '2026-11-15 10:00:00', [
            'customer_name' => '=HYPERLINK("http://contoh.invalid","klik")',
            'customer_phone' => '0877777777',
        ]);
        Carbon::setTestNow('2026-11-20 12:00:00');

        $xlsx = $this->xlsxContents($this->binaryBody(
            $this->get('/api/reports/revenue/export?format=xlsx&period=month&month=2026-11')->assertOk()
        ));

        $this->assertStringContainsString('HYPERLINK', $xlsx['all']);

        foreach ($xlsx['sheets'] as $name => $xml) {
            $this->assertStringNotContainsString('<f>', $xml, "Rumus ditemukan di {$name}");
            $this->assertStringNotContainsString('<f ', $xml, "Rumus ditemukan di {$name}");
        }
    }

    public function test_inventory_export_follows_the_screen_filters(): void
    {
        $this->get('/api/reports/inventory/export?format=print&status=habis')
            ->assertOk()
            ->assertSee('Produk Habis Uji')
            ->assertDontSee('Barang Satu');
    }

    public function test_cashier_export_only_contains_the_cashiers_own_orders(): void
    {
        $this->actingAs($this->c2);

        $this->get('/api/kasir/reports/export?format=print&period=month&month=2026-11')
            ->assertOk()
            ->assertSee('Pelanggan B')
            ->assertSee('Pelanggan D')
            ->assertDontSee('Pelanggan A');

        $this->get("/api/kasir/reports/export?format=print&period=month&month=2026-11&cashier_id={$this->c1->id}")
            ->assertOk()
            ->assertDontSee('Pelanggan A');
    }

    public function test_cashier_performance_export_has_both_sheets(): void
    {
        $xlsx = $this->xlsxContents($this->binaryBody(
            $this->get('/api/reports/cashiers/export?format=xlsx&period=month&month=2026-11')->assertOk()
        ));

        $this->assertStringContainsString('Kinerja Kasir', $xlsx['all']);
        $this->assertStringContainsString('Riwayat Aktivitas', $xlsx['all']);
        $this->assertStringContainsString('Kasir Satu', $xlsx['all']);
    }

    public function test_oversized_reports_are_rejected_instead_of_truncated(): void
    {
        config(['broseven.reports.pdf_max_rows' => 1]);

        $response = $this->get('/api/reports/revenue/export?format=pdf&period=month&month=2026-11')->assertStatus(422);
        $this->assertStringContainsString('terlalu besar', $response->json('message'));

        $this->get('/api/reports/revenue/export?format=print&period=month&month=2026-11')->assertStatus(422);
        $this->get('/api/reports/revenue/export?format=xlsx&period=month&month=2026-11')->assertOk();
    }

    public function test_export_input_validation_and_role_limits(): void
    {
        $this->get('/api/reports/revenue/export?format=docx&period=month')->assertStatus(422);
        $this->get('/api/reports/revenue/export?period=month')->assertStatus(422);

        $this->actingAs($this->c1);
        $this->get('/api/reports/revenue/export?format=print')->assertForbidden();
        $this->get('/api/reports/cashiers/export?format=print')->assertForbidden();
        $this->get('/api/reports/inventory/export?format=print')->assertForbidden();

        $this->signIn(Role::ADMIN_MASTER);
        $this->get('/api/reports/revenue/export?format=print')->assertForbidden();
        $this->get('/api/reports/inventory/export?format=print')->assertOk();
        $this->get('/api/kasir/reports/export?format=print')->assertForbidden();

        $this->actingAs($this->admin);
        $this->get('/api/kasir/reports/export?format=print')->assertForbidden();
    }
}
