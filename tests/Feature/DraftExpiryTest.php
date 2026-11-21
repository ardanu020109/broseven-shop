<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\CustomerAccessToken;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Services\DraftCleanupService;
use App\Services\OrderWorkflowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\BroSevenHelpers;
use Tests\TestCase;

class DraftExpiryTest extends TestCase
{
    use BroSevenHelpers;
    use RefreshDatabase;

    private function agedDraft(int $hoursOld): Order
    {
        Carbon::setTestNow(now()->subHours($hoursOld));

        $order = $this->draftOrder([[$this->product(), 2]]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => OrderStatus::DRAFT,
            'user_id' => null,
            'note' => 'Pesanan dibuat oleh customer.',
        ]);
        CustomerAccessToken::issueFor($order);

        Carbon::setTestNow();

        return $order;
    }

    public function test_the_default_limit_is_24_hours(): void
    {
        $this->assertSame(24, (int) CompanySetting::current()->draft_expiry_hours);
        $this->assertSame(24, app(DraftCleanupService::class)->configuredHours());
    }

    public function test_only_expired_drafts_are_deleted_together_with_their_attachments(): void
    {
        $expired = $this->agedDraft(25);
        $recent = $this->agedDraft(23);
        $product = Product::find($expired->items()->first()->product_id);
        $stock = $this->stockOf($product);

        $result = app(DraftCleanupService::class)->purge();

        $this->assertSame(1, $result['deleted']);
        $this->assertSame([$expired->order_number], $result['orders']);

        $this->assertDatabaseMissing('orders', ['id' => $expired->id]);
        $this->assertDatabaseMissing('order_items', ['order_id' => $expired->id]);
        $this->assertDatabaseMissing('order_status_histories', ['order_id' => $expired->id]);
        $this->assertDatabaseMissing('customer_access_tokens', ['order_id' => $expired->id]);
        $this->assertDatabaseHas('orders', ['id' => $recent->id]);

        $this->assertSame($stock, $this->stockOf($product));

        $log = AuditLog::where('action', 'order.draft_expired')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString($expired->order_number, (string) $log->description);
        $this->assertStringNotContainsString($expired->customer_phone, json_encode($log->old_values));

        $this->assertSame(0, Artisan::call('inventory:reconcile'), Artisan::output());
        $this->assertSame(0, app(DraftCleanupService::class)->purge()['deleted']);
    }

    public function test_orders_that_left_the_draft_status_are_never_deleted(): void
    {
        $cashier = $this->makeUser(Role::CASHIER);
        $workflow = app(OrderWorkflowService::class);

        $approved = $this->agedDraft(100);
        $workflow->approve($approved, $cashier);

        $finished = $this->agedDraft(100);
        $workflow->approve($finished, $cashier);
        $workflow->complete($finished->fresh(), $cashier);

        $cancelled = $this->agedDraft(100);
        $workflow->cancel($cancelled, $cashier, 'Dibatalkan untuk pengujian');

        $result = app(DraftCleanupService::class)->purge();

        $this->assertSame(0, $result['deleted']);

        foreach ([$approved, $finished, $cancelled] as $order) {
            $this->assertDatabaseHas('orders', ['id' => $order->id]);
        }

        $this->assertSame(0, Artisan::call('inventory:reconcile'), Artisan::output());
    }

    public function test_a_limit_of_zero_disables_the_cleanup(): void
    {
        $old = $this->agedDraft(500);

        CompanySetting::current()->update(['draft_expiry_hours' => 0]);

        $result = app(DraftCleanupService::class)->purge();

        $this->assertSame(0, $result['hours']);
        $this->assertSame(0, $result['deleted']);
        $this->assertDatabaseHas('orders', ['id' => $old->id]);
    }

    public function test_the_company_setting_and_the_hours_option_are_respected(): void
    {
        $thirty = $this->agedDraft(30);
        $fifty = $this->agedDraft(50);

        CompanySetting::current()->update(['draft_expiry_hours' => 48]);

        $this->assertSame(1, app(DraftCleanupService::class)->purge()['deleted']);
        $this->assertDatabaseHas('orders', ['id' => $thirty->id]);
        $this->assertDatabaseMissing('orders', ['id' => $fifty->id]);

        $this->assertSame(1, app(DraftCleanupService::class)->purge(1)['deleted']);
        $this->assertDatabaseMissing('orders', ['id' => $thirty->id]);
    }

    public function test_dry_run_deletes_nothing_and_writes_no_audit(): void
    {
        $old = $this->agedDraft(30);

        $result = app(DraftCleanupService::class)->purge(null, true);

        $this->assertSame(1, $result['found']);
        $this->assertSame(0, $result['deleted']);
        $this->assertSame([$old->order_number], $result['orders']);
        $this->assertDatabaseHas('orders', ['id' => $old->id]);
        $this->assertSame(0, AuditLog::where('action', 'order.draft_expired')->count());
    }

    public function test_the_artisan_command_and_the_schedule(): void
    {
        $old = $this->agedDraft(30);

        $this->artisan('orders:purge-drafts', ['--dry-run' => true])->assertExitCode(0);
        $this->assertDatabaseHas('orders', ['id' => $old->id]);

        $this->artisan('orders:purge-drafts', ['--hours' => 'abc'])->assertExitCode(1);

        $this->artisan('orders:purge-drafts')->assertExitCode(0);
        $this->assertDatabaseMissing('orders', ['id' => $old->id]);

        $this->artisan('schedule:list')->expectsOutputToContain('orders:purge-drafts')->assertExitCode(0);
    }

    public function test_superadmin_can_configure_the_limit_and_the_range_is_validated(): void
    {
        $this->signIn(Role::SUPERADMIN);
        $headers = ['Accept' => 'application/json'];
        $base = ['store_name' => 'BroSeven! Shop'];

        $this->getJson('/api/settings/company')->assertOk()->assertJsonPath('data.draft_expiry_hours', 24);

        $this->post('/api/settings/company', $base + ['draft_expiry_hours' => 48], $headers)
            ->assertOk()->assertJsonPath('data.draft_expiry_hours', 48);
        $this->getJson('/api/store/info')->assertJsonPath('company.draft_expiry_hours', 48);

        foreach ([-1, 721, 'abc', '1.5'] as $bad) {
            $this->post('/api/settings/company', $base + ['draft_expiry_hours' => $bad], $headers)
                ->assertUnprocessable()->assertJsonValidationErrors('draft_expiry_hours');
        }

        $this->post('/api/settings/company', $base + ['draft_expiry_hours' => 0], $headers)
            ->assertOk()->assertJsonPath('data.draft_expiry_hours', 0);

        $this->post('/api/settings/company', $base, $headers)
            ->assertOk()->assertJsonPath('data.draft_expiry_hours', 0);
    }
}