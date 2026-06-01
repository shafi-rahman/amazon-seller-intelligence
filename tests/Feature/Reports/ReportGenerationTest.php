<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductAnalysis;
use App\Modules\Reconciliation\Models\ReconciliationReport;
use App\Modules\Reconciliation\Models\ReconciliationRun;
use App\Modules\Reports\Jobs\GenerateReportJob;
use App\Modules\Reports\Models\Report;
use App\Modules\Reports\Services\ReportGeneratorService;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;
    private ImportBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');

        $this->user      = User::factory()->create();
        $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
        $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);
        $this->batch     = ImportBatch::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'type'         => 'products',
            'status'       => 'completed',
        ]);
    }

    // ─── API endpoints ────────────────────────────────────────────────────

    public function test_can_get_report_types(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/reports/types");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [
                'reconciliation_summary' => ['title', 'formats'],
                'listing_analysis'       => ['title', 'formats'],
                'keyword_gap'            => ['title', 'formats'],
            ]]);
    }

    public function test_can_request_report_and_gets_202(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/workspaces/{$this->workspace->id}/reports", [
                'type'       => 'keyword_gap',
                'format'     => 'csv',
                'parameters' => ['product_id' => 1],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.type', 'keyword_gap');

        Queue::assertPushedOn('reports', GenerateReportJob::class);
    }

    public function test_report_rejects_unknown_type(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/workspaces/{$this->workspace->id}/reports", [
                'type'   => 'invalid_report_type',
                'format' => 'csv',
            ]);

        $response->assertStatus(422);
    }

    public function test_can_list_reports_for_workspace(): void
    {
        Report::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'type'         => 'keyword_gap',
            'title'        => 'Keyword Gap Analysis',
            'parameters'   => [],
            'status'       => 'completed',
            'file_format'  => 'csv',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/reports");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data' => [['id', 'type', 'title', 'status', 'has_file']]]);
    }

    public function test_can_filter_reports_by_type(): void
    {
        Report::insert([
            ['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id, 'type' => 'keyword_gap',  'title' => 'A', 'parameters' => '{}', 'status' => 'completed', 'file_format' => 'csv'],
            ['workspace_id' => $this->workspace->id, 'user_id' => $this->user->id, 'type' => 'listing_analysis', 'title' => 'B', 'parameters' => '{}', 'status' => 'pending', 'file_format' => 'pdf'],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/reports?type=keyword_gap");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'keyword_gap');
    }

    public function test_download_returns_presigned_url_for_completed_report(): void
    {
        // Store a fake file in MinIO (faked via Storage::fake)
        Storage::disk('s3')->put('asip-reports/1/test.csv', 'col1,col2');

        $report = Report::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'type'         => 'keyword_gap',
            'title'        => 'KW Gap',
            'parameters'   => [],
            'status'       => 'completed',
            'file_format'  => 'csv',
            'file_path'    => 'asip-reports/1/test.csv',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/reports/{$report->id}/download");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['url', 'format', 'expires_in']]);
    }

    public function test_download_returns_404_when_file_not_ready(): void
    {
        $report = Report::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'type'         => 'keyword_gap',
            'title'        => 'KW Gap',
            'parameters'   => [],
            'status'       => 'pending',
            'file_format'  => 'csv',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/reports/{$report->id}/download");

        $response->assertStatus(404);
    }

    // ─── Report builders ──────────────────────────────────────────────────

    public function test_keyword_gap_csv_builder_generates_file(): void
    {
        $product = Product::create([
            'workspace_id'    => $this->workspace->id,
            'import_batch_id' => $this->batch->id,
            'asin'            => 'B09RPTTEST',
            'title'           => 'Test Product',
        ]);

        \App\Modules\Competitors\Models\KeywordGap::insert([
            ['product_id' => $product->id, 'competitor_id' => 1, 'keyword' => 'ceramic mug', 'gap_type' => 'missing',   'our_frequency' => 0, 'their_frequency' => 3, 'priority_score' => 75],
            ['product_id' => $product->id, 'competitor_id' => 1, 'keyword' => 'bpa free',    'gap_type' => 'advantage', 'our_frequency' => 2, 'their_frequency' => 0, 'priority_score' => 20],
        ]);

        $report = Report::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'type'         => 'keyword_gap',
            'title'        => 'Keyword Gap',
            'parameters'   => ['product_id' => $product->id],
            'status'       => 'pending',
            'file_format'  => 'csv',
        ]);

        $service = app(ReportGeneratorService::class);
        $service->generate($report);

        $report->refresh();
        $this->assertEquals('completed', $report->status);
        $this->assertNotEmpty($report->file_path);
        Storage::disk('s3')->assertExists($report->file_path);
    }

    public function test_listing_analysis_pdf_builder_generates_file(): void
    {
        $product = Product::create([
            'workspace_id'    => $this->workspace->id,
            'import_batch_id' => $this->batch->id,
            'asin'            => 'B09LISTPDF',
            'title'           => 'Ceramic Coffee Mug',
            'listing_score'   => 72,
        ]);

        ProductAnalysis::create([
            'product_id'    => $product->id,
            'analysis_type' => 'listing_score',
            'analysis_data' => ['total' => 72, 'dimensions' => ['title' => ['score' => 20, 'max' => 25, 'issues' => [], 'passes' => ['Title present']]]],
        ]);

        $report = Report::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'type'         => 'listing_analysis',
            'title'        => 'Listing Analysis',
            'parameters'   => ['product_id' => $product->id],
            'status'       => 'pending',
            'file_format'  => 'pdf',
        ]);

        $service = app(ReportGeneratorService::class);
        $service->generate($report);

        $report->refresh();
        $this->assertEquals('completed', $report->status);
        $this->assertNotEmpty($report->file_path);
    }

    public function test_reconciliation_report_builder_generates_csv(): void
    {
        $run = ReconciliationRun::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'period_start' => '2024-01-01',
            'period_end'   => '2024-01-31',
            'status'       => 'completed',
        ]);

        ReconciliationReport::create([
            'reconciliation_run_id' => $run->id,
            'workspace_id'          => $this->workspace->id,
            'report_type'           => 'missing_settlements',
            'report_data'           => ['count' => 2, 'total_value' => 1200.00, 'by_reason' => [], 'rows' => [
                ['amazon_order_id' => '403-111', 'purchase_date' => '2024-01-05', 'order_status' => 'Shipped', 'sku' => 'SKU-001', 'item_price' => 599, 'days_since_order' => 26, 'reason' => 'settlement_missing'],
            ]],
        ]);

        $report = Report::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'type'         => 'missing_settlements',
            'title'        => 'Missing Settlements',
            'parameters'   => ['reconciliation_run_id' => $run->id],
            'status'       => 'pending',
            'file_format'  => 'csv',
        ]);

        $service = app(ReportGeneratorService::class);
        $service->generate($report);

        $report->refresh();
        $this->assertEquals('completed', $report->status);
        Storage::disk('s3')->assertExists($report->file_path);

        // Verify CSV content
        $content = Storage::disk('s3')->get($report->file_path);
        $this->assertStringContainsString('Order ID', $content);
        $this->assertStringContainsString('403-111', $content);
    }
}
