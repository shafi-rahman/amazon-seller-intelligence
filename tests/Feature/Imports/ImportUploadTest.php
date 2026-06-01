<?php

namespace Tests\Feature\Imports;

use App\Models\User;
use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
        Queue::fake();

        $this->user      = User::factory()->create();
        $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
        $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);
    }

    public function test_user_can_upload_orders_csv(): void
    {
        $csv = implode("\n", [
            'amazon-order-id,purchase-date,order-status,sku,asin,quantity,currency,item-price,item-tax',
            '403-1111111-1111111,2024-01-15 10:00:00,Shipped,SKU-001,B09XXXXXX,1,INR,599.00,107.82',
            '403-2222222-2222222,2024-01-16 11:00:00,Shipped,SKU-002,B09YYYYYY,2,INR,1199.00,215.82',
        ]);

        $file = UploadedFile::fake()->createWithContent('orders.csv', $csv);

        $response = $this->actingAs($this->user)->postJson('/api/v1/imports/upload', [
            'workspace_id' => $this->workspace->id,
            'type'         => 'orders',
            'file'         => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['import_batch_id', 'status', 'total_rows', 'detected_columns', 'suggested_mapping'],
            ])
            ->assertJsonPath('data.status', 'awaiting_mapping');

        Storage::disk('s3')->assertExists(
            ImportBatch::find($response->json('data.import_batch_id'))->storage_path
        );
    }

    public function test_upload_rejects_wrong_workspace(): void
    {
        $otherUser = User::factory()->create();
        $otherWs   = Workspace::factory()->create(['owner_id' => $otherUser->id]);

        $file = UploadedFile::fake()->createWithContent('orders.csv', 'amazon-order-id,purchase-date'."\n".'123,2024-01-01');

        $response = $this->actingAs($this->user)->postJson('/api/v1/imports/upload', [
            'workspace_id' => $otherWs->id,
            'type'         => 'orders',
            'file'         => $file,
        ]);

        $response->assertStatus(403);
    }

    public function test_upload_rejects_unknown_type(): void
    {
        $file = UploadedFile::fake()->createWithContent('test.csv', 'col1,col2'."\n".'a,b');

        $response = $this->actingAs($this->user)->postJson('/api/v1/imports/upload', [
            'workspace_id' => $this->workspace->id,
            'type'         => 'invalid_type',
            'file'         => $file,
        ]);

        $response->assertStatus(422);
    }

    public function test_confirm_mapping_dispatches_process_job(): void
    {
        $batch = ImportBatch::create([
            'workspace_id'    => $this->workspace->id,
            'user_id'         => $this->user->id,
            'type'            => 'orders',
            'original_filename' => 'orders.csv',
            'status'          => 'awaiting_mapping',
            'total_rows'      => 100,
            'column_mapping'  => ['amazon-order-id' => 'amazon_order_id'],
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/v1/imports/{$batch->id}/confirm-mapping", [
            'mapping' => ['amazon-order-id' => 'amazon_order_id', 'purchase-date' => 'purchase_date'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'pending');

        Queue::assertPushedOn('imports', \App\Modules\Imports\Jobs\ProcessImportJob::class);
    }

    public function test_status_polling_returns_progress(): void
    {
        $batch = ImportBatch::create([
            'workspace_id'    => $this->workspace->id,
            'user_id'         => $this->user->id,
            'type'            => 'orders',
            'original_filename' => 'orders.csv',
            'status'          => 'processing',
            'total_rows'      => 1000,
            'processed_rows'  => 450,
            'failed_rows'     => 5,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/imports/{$batch->id}/status");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.processed_rows', 450)
            ->assertJsonPath('data.percent', 45);
    }

    public function test_import_list_returns_workspace_batches(): void
    {
        ImportBatch::factory()->count(3)->create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'type'         => 'orders',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/imports");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_html_import_endpoint_accepts_html_content(): void
    {
        $html = str_repeat('<html><body><div id="productTitle">Test Product</div><input id="ASIN" value="B09XXXXXX" /></body></html>', 5);

        $response = $this->actingAs($this->user)->postJson('/api/v1/imports/competitors/html', [
            'workspace_id' => $this->workspace->id,
            'html_content' => $html,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['import_batch_id', 'status', 'type']]);
    }
}
