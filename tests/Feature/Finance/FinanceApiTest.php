<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use App\Modules\Finance\Models\BankTransaction;
use App\Modules\Finance\Models\GstTransaction;
use App\Modules\Finance\Models\Order;
use App\Modules\Finance\Models\Settlement;
use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;
    private ImportBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user      = User::factory()->create();
        $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
        $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);

        $this->batch = ImportBatch::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'type'         => 'orders',
            'status'       => 'completed',
        ]);
    }

    // ── Orders ─────────────────────────────────────────────────

    public function test_can_list_orders_with_pagination(): void
    {
        Order::insert([
            $this->makeOrder('403-1111111-1111111', '2024-01-15', 'Shipped', 599.00),
            $this->makeOrder('403-2222222-2222222', '2024-01-16', 'Shipped', 1199.00),
            $this->makeOrder('403-3333333-3333333', '2024-01-17', 'Cancelled', 0.00),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/orders?per_page=2");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'amazon_order_id', 'order_status', 'item_price']], 'meta']);
    }

    public function test_orders_filter_by_status(): void
    {
        Order::insert([
            $this->makeOrder('403-1111111-1111111', '2024-01-15', 'Shipped', 599.00),
            $this->makeOrder('403-2222222-2222222', '2024-01-16', 'Cancelled', 0.00),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/orders?status=Cancelled");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_status', 'Cancelled');
    }

    public function test_orders_filter_by_date_range(): void
    {
        Order::insert([
            $this->makeOrder('403-1111111-1111111', '2024-01-10', 'Shipped', 500.00),
            $this->makeOrder('403-2222222-2222222', '2024-01-20', 'Shipped', 600.00),
            $this->makeOrder('403-3333333-3333333', '2024-02-01', 'Shipped', 700.00),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/orders?date_from=2024-01-01&date_to=2024-01-31");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_orders_summary_returns_correct_totals(): void
    {
        Order::insert([
            $this->makeOrder('403-1111111-1111111', '2024-01-15', 'Shipped', 599.00, 107.82),
            $this->makeOrder('403-2222222-2222222', '2024-01-16', 'Shipped', 1199.00, 215.82),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/orders/summary?date_from=2024-01-01&date_to=2024-01-31");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_orders', 2)
            ->assertJsonPath('data.total_tax', 323.64);

        // total_revenue is a whole number (1798.00) which json_encode emits as
        // the integer 1798; assertJsonPath uses strict comparison, so compare
        // numerically against the decoded value instead.
        $this->assertEqualsWithDelta(1798.00, (float) $response->json('data.total_revenue'), 0.001);
    }

    public function test_orders_blocks_unauthorized_workspace(): void
    {
        $other = User::factory()->create();
        $response = $this->actingAs($other)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/orders");
        $response->assertStatus(403);
    }

    // ── Settlements ────────────────────────────────────────────

    public function test_can_list_settlements(): void
    {
        Settlement::insert($this->makeSettlements());

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/settlements");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'settlement_id', 'amount']]]);
    }

    public function test_settlements_filter_by_settlement_id(): void
    {
        Settlement::insert($this->makeSettlements());

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/settlements?settlement_id=SETT-001");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    // ── Bank Transactions ───────────────────────────────────────

    public function test_can_list_bank_transactions(): void
    {
        BankTransaction::insert([
            $this->makeBankTx('2024-01-15', 'Amazon Pay credit', 0, 50000.00),
            $this->makeBankTx('2024-01-16', 'Bill payment', 500.00, 0),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/bank-transactions");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_bank_transactions_filter_credits_only(): void
    {
        BankTransaction::insert([
            $this->makeBankTx('2024-01-15', 'Amazon credit', 0, 50000.00),
            $this->makeBankTx('2024-01-16', 'Debit', 500.00, 0),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/bank-transactions?type=credit");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    // ── GST Transactions ───────────────────────────────────────

    public function test_can_list_gst_transactions(): void
    {
        GstTransaction::insert($this->makeGstRows());

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/gst-transactions");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'invoice_number', 'total_tax']]]);
    }

    public function test_gst_filter_by_order_id(): void
    {
        GstTransaction::insert($this->makeGstRows());

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/gst-transactions?order_id=403-1111111-1111111");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    // ── Dashboard ──────────────────────────────────────────────

    public function test_dashboard_returns_all_summary_sections(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/finance/dashboard?date_from=2024-01-01&date_to=2024-01-31");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [
                'period', 'data_availability', 'orders_summary',
                'settlements_summary', 'bank_summary', 'gst_summary', 'top_products',
            ]]);
    }

    // ── Helpers ────────────────────────────────────────────────

    private function makeOrder(string $orderId, string $date, string $status, float $price, float $tax = 0): array
    {
        return [
            'workspace_id'    => $this->workspace->id,
            'import_batch_id' => $this->batch->id,
            'amazon_order_id' => $orderId,
            'purchase_date'   => $date.' 10:00:00',
            'order_status'    => $status,
            'sku'             => 'SKU-TEST',
            'quantity'        => 1,
            'currency'        => 'INR',
            'item_price'      => $price,
            'item_tax'        => $tax,
            'shipping_price'  => 0,
            'shipping_tax'    => 0,
            'gift_wrap_price' => 0,
            'gift_wrap_tax'   => 0,
            'item_promotion_discount' => 0,
            'ship_promotion_discount' => 0,
            'is_business_order' => false,
            'created_at'      => now()->toDateTimeString(),
        ];
    }

    private function makeSettlements(): array
    {
        $base = [
            'workspace_id'          => $this->workspace->id,
            'import_batch_id'       => $this->batch->id,
            'settlement_start_date' => '2024-01-01',
            'settlement_end_date'   => '2024-01-14',
            'deposit_date'          => '2024-01-16',
            'deposited_amount'      => 45000.00,
            'currency'              => 'INR',
            'amount'                => 0,
            'created_at'            => now()->toDateTimeString(),
        ];
        return [
            array_merge($base, ['settlement_id' => 'SETT-001', 'transaction_type' => 'Order', 'amount' => 599.00]),
            array_merge($base, ['settlement_id' => 'SETT-002', 'transaction_type' => 'ItemFees', 'amount' => -89.85]),
        ];
    }

    private function makeBankTx(string $date, string $desc, float $debit, float $credit): array
    {
        return [
            'workspace_id'     => $this->workspace->id,
            'import_batch_id'  => $this->batch->id,
            'transaction_date' => $date,
            'description'      => $desc,
            'debit_amount'     => $debit,
            'credit_amount'    => $credit,
            'balance'          => null,
            'created_at'       => now()->toDateTimeString(),
        ];
    }

    private function makeGstRows(): array
    {
        $base = [
            'workspace_id'    => $this->workspace->id,
            'import_batch_id' => $this->batch->id,
            'transaction_type'=> 'SALE',
            'invoice_date'    => '2024-01-15',
            'taxable_value'   => 509.32,
            'igst_rate'       => 18.00,
            'igst_amount'     => 91.68,
            'cgst_rate'       => null,
            'cgst_amount'     => null,
            'sgst_rate'       => null,
            'sgst_amount'     => null,
            'invoice_amount'  => 601.00,
            'created_at'      => now()->toDateTimeString(),
        ];
        return [
            array_merge($base, ['invoice_number' => 'IN-2024-001', 'order_id' => '403-1111111-1111111']),
            array_merge($base, ['invoice_number' => 'IN-2024-002', 'order_id' => '403-2222222-2222222']),
        ];
    }
}
