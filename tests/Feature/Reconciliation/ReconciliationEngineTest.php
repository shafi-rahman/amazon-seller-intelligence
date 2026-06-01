<?php

namespace Tests\Feature\Reconciliation;

use App\Models\User;
use App\Modules\Finance\Models\BankTransaction;
use App\Modules\Finance\Models\GstTransaction;
use App\Modules\Finance\Models\Order;
use App\Modules\Finance\Models\Settlement;
use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Reconciliation\Jobs\ReconciliationJob;
use App\Modules\Reconciliation\Models\ReconciliationMatch;
use App\Modules\Reconciliation\Models\ReconciliationReport;
use App\Modules\Reconciliation\Models\ReconciliationRun;
use App\Modules\Reconciliation\Services\ReconciliationEngine;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReconciliationEngineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;
    private ImportBatch $batch;
    private ReconciliationEngine $engine;

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

        $this->engine = app(ReconciliationEngine::class);
    }

    // ─── Pass A: Exact matching ──────────────────────────────────────────────

    public function test_pass_a_exact_match_identifies_matched_orders(): void
    {
        $this->seedOrdersAndSettlements();

        $run = $this->createRun('2024-01-01', '2024-01-31');
        $this->engine->run($run);

        $run->refresh();
        $this->assertEquals('completed', $run->status);

        $matches = ReconciliationMatch::where('reconciliation_run_id', $run->id)
            ->where('match_type', 'exact')
            ->where('status', 'matched')
            ->count();

        $this->assertEquals(2, $matches, 'Pass A should match 2 exact orders');
    }

    public function test_pass_a_does_not_match_orders_without_settlements(): void
    {
        // Insert order with no corresponding settlement
        $this->insertOrder('403-ORPHAN-0000001', '2024-01-15', 'Shipped', 599.00);
        // Insert an order WITH a settlement
        $this->insertOrder('403-MATCHED-0000001', '2024-01-16', 'Shipped', 799.00);
        $this->insertSettlementRow('403-MATCHED-0000001', 799.00, 'Order');

        $run = $this->createRun('2024-01-01', '2024-01-31');
        $this->engine->run($run);

        $matched = ReconciliationMatch::where('reconciliation_run_id', $run->id)
            ->where('match_type', 'exact')
            ->where('status', 'matched')
            ->count();

        $this->assertEquals(1, $matched, 'Only 1 of 2 orders has a settlement');

        // Verify the orphan order is in missing_settlements report
        $report = ReconciliationReport::where('reconciliation_run_id', $run->id)
            ->where('report_type', 'missing_settlements')
            ->first();

        $this->assertNotNull($report);
        $orderIds = collect($report->report_data['rows'])->pluck('amazon_order_id')->toArray();
        $this->assertContains('403-ORPHAN-0000001', $orderIds);
    }

    public function test_pass_a_exact_match_100_percent_confidence(): void
    {
        $this->insertOrder('403-CONF-1111111', '2024-01-15', 'Shipped', 599.00);
        $this->insertSettlementRow('403-CONF-1111111', 599.00, 'Order');

        $run = $this->createRun('2024-01-01', '2024-01-31');
        $this->engine->run($run);

        $match = ReconciliationMatch::where('reconciliation_run_id', $run->id)
            ->where('match_type', 'exact')
            ->first();

        $this->assertNotNull($match);
        $this->assertEquals(100.00, (float) $match->match_confidence);
    }

    // ─── Pass B: Fuzzy refund matching ──────────────────────────────────────

    public function test_pass_b_matches_cancelled_order_to_refund_settlement(): void
    {
        $this->insertOrder('403-CANCEL-1111111', '2024-01-10', 'Cancelled', 599.00);
        $this->insertSettlementRow('403-CANCEL-1111111', -599.00, 'Refund');

        $run = $this->createRun('2024-01-01', '2024-01-31');
        $this->engine->run($run);

        $refundMatch = ReconciliationMatch::where('reconciliation_run_id', $run->id)
            ->where('match_type', 'fuzzy_refund')
            ->first();

        $this->assertNotNull($refundMatch, 'Cancelled order should match refund settlement');
        $this->assertEquals(85.00, (float) $refundMatch->match_confidence);
    }

    // ─── Pass C: Settlement-bank exact ──────────────────────────────────────

    public function test_pass_c_matches_settlement_cycle_to_bank_credit_exactly(): void
    {
        $this->insertSettlementCycle('SETT-001', '2024-01-16', 45000.00);
        $this->insertBankCredit('2024-01-16', 'Amazon Pay credit SETT-001 UTR123', 45000.00);

        $run = $this->createRun('2024-01-01', '2024-01-31');
        $this->engine->run($run);

        $bankMatch = ReconciliationMatch::where('reconciliation_run_id', $run->id)
            ->where('match_type', 'settlement_bank_exact')
            ->first();

        $this->assertNotNull($bankMatch, 'Settlement cycle should match bank credit exactly');
        $this->assertEquals(100.00, (float) $bankMatch->match_confidence);
    }

    // ─── Pass D: TDS tolerance ───────────────────────────────────────────────

    public function test_pass_d_matches_with_tds_deduction_within_2_percent(): void
    {
        $settled   = 45000.00;
        $afterTds  = $settled * 0.99; // 1% TDS deducted

        $this->insertSettlementCycle('SETT-TDS-001', '2024-01-16', $settled);
        $this->insertBankCredit('2024-01-17', 'Amazon credit', $afterTds);

        $run = $this->createRun('2024-01-01', '2024-01-31');
        $this->engine->run($run);

        $tdsMatch = ReconciliationMatch::where('reconciliation_run_id', $run->id)
            ->where('match_type', 'settlement_bank_tds')
            ->first();

        $this->assertNotNull($tdsMatch, 'Should match settlement with TDS deduction');
        $this->assertEquals('partial', $tdsMatch->status);
        $this->assertNotNull($tdsMatch->mismatch_amount);
    }

    // ─── Reports ────────────────────────────────────────────────────────────

    public function test_run_generates_all_six_report_types(): void
    {
        $run = $this->createRun('2024-01-01', '2024-01-31');
        $this->engine->run($run);

        $reportTypes = ReconciliationReport::where('reconciliation_run_id', $run->id)
            ->pluck('report_type')
            ->toArray();

        foreach (['summary', 'missing_settlements', 'missing_credits', 'refund_impact', 'return_impact', 'gst_mismatch'] as $type) {
            $this->assertContains($type, $reportTypes, "Report type '$type' should be generated");
        }
    }

    public function test_summary_report_has_correct_structure(): void
    {
        $this->seedOrdersAndSettlements();
        $run = $this->createRun('2024-01-01', '2024-01-31');
        $this->engine->run($run);

        $summary = ReconciliationReport::where('reconciliation_run_id', $run->id)
            ->where('report_type', 'summary')
            ->first();

        $this->assertNotNull($summary);
        $data = $summary->report_data;
        $this->assertArrayHasKey('total_orders', $data);
        $this->assertArrayHasKey('matched_orders', $data);
        $this->assertArrayHasKey('unmatched_orders', $data);
        $this->assertArrayHasKey('match_rate_pct', $data);
        $this->assertArrayHasKey('total_order_value', $data);
    }

    public function test_gst_mismatch_report_detects_tax_discrepancy(): void
    {
        $this->insertOrder('403-GST-111', '2024-01-15', 'Shipped', 509.32, 91.68);
        // Insert GST with different tax amount (20 instead of 91.68)
        GstTransaction::create([
            'workspace_id'    => $this->workspace->id,
            'import_batch_id' => $this->batch->id,
            'order_id'        => '403-GST-111',
            'invoice_date'    => '2024-01-15',
            'invoice_number'  => 'IN-GST-001',
            'igst_amount'     => 20.00,  // mismatch! should be 91.68
        ]);

        $run = $this->createRun('2024-01-01', '2024-01-31');
        $this->engine->run($run);

        $gstReport = ReconciliationReport::where('reconciliation_run_id', $run->id)
            ->where('report_type', 'gst_mismatch')
            ->first();

        $this->assertNotNull($gstReport);
        $this->assertGreaterThan(0, $gstReport->report_data['count']);
    }

    // ─── API endpoints ───────────────────────────────────────────────────────

    public function test_api_dispatches_reconciliation_job(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/workspaces/{$this->workspace->id}/reconciliation/run", [
                'period_start' => '2024-01-01',
                'period_end'   => '2024-01-31',
            ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'pending');

        Queue::assertPushedOn('reconciliation', ReconciliationJob::class);
    }

    public function test_api_can_poll_run_status(): void
    {
        $run = $this->createRun('2024-01-01', '2024-01-31');

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/reconciliation/{$run->id}/status");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure(['data' => ['id', 'status', 'started_at', 'completed_at']]);
    }

    public function test_api_returns_report_by_type(): void
    {
        $run = $this->createRun('2024-01-01', '2024-01-31');
        $this->engine->run($run);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/reconciliation/{$run->id}/reports/summary");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['report_type', 'data']]);
    }

    public function test_rerunning_creates_new_run_preserving_old(): void
    {
        $run1 = $this->createRun('2024-01-01', '2024-01-31');
        $this->engine->run($run1);

        $run2 = $this->createRun('2024-01-01', '2024-01-31');
        $this->engine->run($run2);

        $this->assertNotEquals($run1->id, $run2->id);
        $this->assertEquals('completed', $run1->fresh()->status);
        $this->assertEquals('completed', $run2->fresh()->status);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function createRun(string $from, string $to): ReconciliationRun
    {
        return ReconciliationRun::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'period_start' => $from,
            'period_end'   => $to,
            'status'       => 'pending',
        ]);
    }

    private function seedOrdersAndSettlements(): void
    {
        $this->insertOrder('403-MATCH-1111111', '2024-01-15', 'Shipped', 599.00);
        $this->insertOrder('403-MATCH-2222222', '2024-01-16', 'Shipped', 1199.00);
        $this->insertSettlementRow('403-MATCH-1111111', 599.00, 'Order');
        $this->insertSettlementRow('403-MATCH-2222222', 1199.00, 'Order');
    }

    private function insertOrder(string $orderId, string $date, string $status, float $price, float $tax = 0.0): Order
    {
        return Order::create([
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
        ]);
    }

    private function insertSettlementRow(string $orderId, float $amount, string $type): Settlement
    {
        return Settlement::create([
            'workspace_id'          => $this->workspace->id,
            'import_batch_id'       => $this->batch->id,
            'settlement_id'         => 'SETT-'.rand(10000, 99999),
            'settlement_start_date' => '2024-01-01',
            'settlement_end_date'   => '2024-01-14',
            'deposit_date'          => '2024-01-16',
            'deposited_amount'      => 45000.00,
            'currency'              => 'INR',
            'transaction_type'      => $type,
            'order_id'              => $orderId,
            'amount'                => $amount,
        ]);
    }

    private function insertSettlementCycle(string $cycleId, string $depositDate, float $amount): Settlement
    {
        return Settlement::create([
            'workspace_id'          => $this->workspace->id,
            'import_batch_id'       => $this->batch->id,
            'settlement_id'         => $cycleId,
            'settlement_start_date' => '2024-01-01',
            'settlement_end_date'   => '2024-01-14',
            'deposit_date'          => $depositDate,
            'deposited_amount'      => $amount,
            'currency'              => 'INR',
            'transaction_type'      => 'Order',
            'amount'                => $amount,
        ]);
    }

    private function insertBankCredit(string $date, string $description, float $amount): BankTransaction
    {
        return BankTransaction::create([
            'workspace_id'     => $this->workspace->id,
            'import_batch_id'  => $this->batch->id,
            'transaction_date' => $date,
            'description'      => $description,
            'debit_amount'     => 0,
            'credit_amount'    => $amount,
        ]);
    }
}
