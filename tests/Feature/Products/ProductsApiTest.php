<?php

namespace Tests\Feature\Products;

use App\Models\User;
use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Products\Jobs\AnalyzeProductJob;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductAnalysis;
use App\Modules\Products\Models\ProductKeyword;
use App\Modules\Products\Services\ProductIntelligenceService;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProductsApiTest extends TestCase
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
        $this->batch     = ImportBatch::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'type'         => 'products',
            'status'       => 'completed',
        ]);
    }

    public function test_can_list_products_with_score_badges(): void
    {
        $this->createProduct('B09AAAAAA', score: 85);
        $this->createProduct('B09BBBBBBB', score: 45);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/products");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'asin', 'listing_score', 'score_tier']]]);
    }

    public function test_products_ordered_by_listing_score_desc(): void
    {
        $this->createProduct('B09LOW0000', score: 30);
        $this->createProduct('B09HIGH000', score: 90);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/products");

        $response->assertStatus(200);
        $scores = collect($response->json('data'))->pluck('listing_score')->toArray();
        $this->assertEquals([90, 30], $scores, 'Products should be ordered by score descending');
    }

    public function test_score_tier_is_correct_for_each_range(): void
    {
        $this->createProduct('B09EXC0000', score: 90);
        $this->createProduct('B09GOOD000', score: 75);
        $this->createProduct('B09WORK000', score: 55);
        $this->createProduct('B09POOR000', score: 35);
        $this->createProduct('B09CRIT000', score: 15);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/products?per_page=10");

        $response->assertStatus(200);
        $tiers = collect($response->json('data'))
            ->sortByDesc('listing_score')
            ->pluck('score_tier', 'asin')
            ->toArray();

        $this->assertEquals('excellent',  $tiers['B09EXC0000']);
        $this->assertEquals('good',       $tiers['B09GOOD000']);
        $this->assertEquals('needs_work', $tiers['B09WORK000']);
        $this->assertEquals('poor',       $tiers['B09POOR000']);
        $this->assertEquals('critical',   $tiers['B09CRIT000']);
    }

    public function test_product_detail_includes_score_breakdown(): void
    {
        $product = $this->createProduct('B09DETAIL1', score: 72);
        ProductAnalysis::create([
            'product_id'    => $product->id,
            'analysis_type' => 'listing_score',
            'analysis_data' => [
                'total' => 72,
                'dimensions' => [
                    'title' => ['score' => 20, 'max' => 25, 'issues' => [], 'passes' => []],
                ],
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/products/{$product->public_id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.listing_score', 72)
            ->assertJsonStructure(['data' => ['score_breakdown' => ['total', 'dimensions']]]);
    }

    public function test_product_detail_includes_top_keywords(): void
    {
        $product = $this->createProduct('B09KEYWORDS', score: 60);
        ProductKeyword::insert([
            ['product_id' => $product->id, 'keyword' => 'ceramic mug', 'source' => 'title', 'frequency' => 3],
            ['product_id' => $product->id, 'keyword' => 'coffee mug',  'source' => 'bullet', 'frequency' => 2],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/products/{$product->public_id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['top_keywords' => [['keyword', 'source', 'frequency']]]]);
    }

    public function test_analyze_endpoint_dispatches_job(): void
    {
        Queue::fake();
        $product = $this->createProduct('B09ANALYZE1', score: null);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/workspaces/{$this->workspace->id}/products/{$product->public_id}/analyze");

        $response->assertStatus(202);
        Queue::assertPushedOn('ai', AnalyzeProductJob::class);
    }

    public function test_product_intelligence_service_scores_product(): void
    {
        $product = $this->createProduct('B09SCORETEST', score: null);
        $product->title      = 'Premium Ceramic Coffee Mug 350ml Dishwasher Safe';
        $product->brand      = 'MugCo';
        $product->bullet_1   = 'Made from high-quality food-grade ceramic, 100% BPA free and non-toxic for safe daily use.';
        $product->bullet_2   = '350ml capacity — perfect for your morning coffee, tea, or hot drinks without spilling.';
        $product->bullet_3   = 'Dishwasher safe and microwave safe up to 800W for easy cleaning and reheating.';
        $product->bullet_4   = 'Ergonomic handle provides comfortable grip during use, suitable for adults.';
        $product->bullet_5   = 'Makes a perfect gift — comes in gift-ready packaging for any occasion.';
        $product->description= str_repeat('This is a premium ceramic mug suitable for coffee and tea. ', 30);
        $product->rating     = 4.2;
        $product->review_count = 128;
        $product->save();

        $service = app(ProductIntelligenceService::class);
        $analyzed = $service->analyze($product);

        $this->assertNotNull($analyzed->listing_score);
        $this->assertGreaterThan(0, $analyzed->listing_score);
        $this->assertLessThanOrEqual(100, $analyzed->listing_score);

        $analysis = ProductAnalysis::where('product_id', $product->id)
            ->where('analysis_type', 'listing_score')
            ->first();

        $this->assertNotNull($analysis);
        $this->assertArrayHasKey('total', $analysis->analysis_data);
        $this->assertArrayHasKey('dimensions', $analysis->analysis_data);

        $keywords = ProductKeyword::where('product_id', $product->id)->count();
        $this->assertGreaterThan(0, $keywords, 'Keywords should be extracted and stored');
    }

    public function test_cannot_access_another_workspaces_product(): void
    {
        $other   = User::factory()->create();
        $ws2     = Workspace::factory()->create(['owner_id' => $other->id]);
        $product = $this->createProduct('B09OTHERPRO', score: 50, workspaceId: $ws2->id);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$ws2->id}/products/{$product->public_id}");

        $response->assertStatus(403);
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function createProduct(string $asin, ?int $score, ?int $workspaceId = null): Product
    {
        return Product::create([
            'workspace_id'    => $workspaceId ?? $this->workspace->id,
            'import_batch_id' => $this->batch->id,
            'asin'            => $asin,
            'title'           => "Test Product {$asin}",
            'brand'           => 'TestBrand',
            'listing_score'   => $score,
        ]);
    }
}
