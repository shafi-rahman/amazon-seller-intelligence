<?php

namespace Tests\Feature\Competitors;

use App\Models\User;
use App\Modules\Competitors\Jobs\CompetitorAnalysisJob;
use App\Modules\Competitors\Models\Competitor;
use App\Modules\Competitors\Models\CompetitorKeyword;
use App\Modules\Competitors\Models\KeywordGap;
use App\Modules\Competitors\Services\CompetitorAnalysisService;
use App\Modules\Competitors\Services\KeywordGapCalculator;
use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductKeyword;
use App\Modules\Products\Services\ProductIntelligenceService;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CompetitorAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Workspace $workspace;
    private ImportBatch $batch;
    private Product $product;
    private Competitor $competitor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user      = User::factory()->create();
        $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
        $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);

        $this->batch = ImportBatch::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'type'         => 'products',
            'status'       => 'completed',
        ]);

        $this->product = Product::create([
            'workspace_id'    => $this->workspace->id,
            'import_batch_id' => $this->batch->id,
            'asin'            => 'B09OURPROD',
            'title'           => 'Ceramic Coffee Mug 350ml Dishwasher Safe',
            'brand'           => 'MugCo',
            'bullet_1'        => 'Premium ceramic material, BPA free and microwave safe.',
            'bullet_2'        => 'Perfect 350ml capacity for coffee and tea.',
            'description'     => 'A premium ceramic mug for everyday use.',
            'rating'          => 4.2,
            'review_count'    => 128,
        ]);

        $this->competitor = Competitor::create([
            'workspace_id'    => $this->workspace->id,
            'product_id'      => $this->product->id,
            'import_batch_id' => $this->batch->id,
            'asin'            => 'B09COMPETI',
            'title'           => 'Large Ceramic Coffee Mug 400ml Gift Box Dishwasher Safe Microwave Safe',
            'brand'           => 'CoffeeCo',
            'bullet_1'        => 'Extra large 400ml capacity perfect for morning coffee lovers.',
            'bullet_2'        => 'Premium ceramic microwave safe and dishwasher safe for easy cleaning.',
            'bullet_3'        => 'Beautiful gift box included ideal for birthday and Christmas gifts.',
            'description'     => 'A premium large ceramic mug perfect for coffee and tea drinkers.',
            'price'           => 649.00,
            'rating'          => 4.5,
            'review_count'    => 2341,
            'source_type'     => 'csv',
        ]);
    }

    // ─── KeywordGapCalculator ────────────────────────────────────────────

    public function test_missing_keyword_detected_correctly(): void
    {
        $calculator = new KeywordGapCalculator();

        $ourKeywords  = [
            ['keyword' => 'ceramic mug', 'source' => 'title', 'frequency' => 2],
            ['keyword' => 'coffee',       'source' => 'title', 'frequency' => 1],
        ];
        $theirKeywords = [
            ['keyword' => 'ceramic mug',   'source' => 'title',       'frequency' => 3],
            ['keyword' => 'gift box',      'source' => 'bullet',      'frequency' => 2],  // we don't have this
            ['keyword' => 'birthday gift', 'source' => 'description', 'frequency' => 1],  // we don't have this
        ];

        $gaps = $calculator->calculate(
            $ourKeywords, $theirKeywords, $this->product->id, $this->competitor->id,
            $this->competitor->title ?? '', $this->competitor->bullets()
        );

        $missingKeywords = collect($gaps)->where('gap_type', 'missing')->pluck('keyword')->toArray();
        $this->assertContains('gift box', $missingKeywords, '"gift box" should be detected as missing');
        $this->assertContains('birthday gift', $missingKeywords);
    }

    public function test_underused_keyword_detected_when_frequency_50_percent_higher(): void
    {
        $calculator = new KeywordGapCalculator();

        $ourKeywords  = [['keyword' => 'dishwasher safe', 'source' => 'bullet', 'frequency' => 1]];
        $theirKeywords = [['keyword' => 'dishwasher safe', 'source' => 'title',  'frequency' => 4]];

        $gaps = $calculator->calculate(
            $ourKeywords, $theirKeywords, $this->product->id, $this->competitor->id,
            'Dishwasher Safe Mug', []
        );

        $underused = collect($gaps)->where('gap_type', 'underused')->first();
        $this->assertNotNull($underused, 'Keyword with 4x their frequency vs 1x ours should be underused');
    }

    public function test_advantage_keyword_detected_when_we_have_they_dont(): void
    {
        $calculator = new KeywordGapCalculator();

        $ourKeywords  = [['keyword' => 'bpa free', 'source' => 'bullet', 'frequency' => 2]];
        $theirKeywords = [['keyword' => 'large mug', 'source' => 'title', 'frequency' => 3]];

        $gaps = $calculator->calculate(
            $ourKeywords, $theirKeywords, $this->product->id, $this->competitor->id, 'Large Mug', []
        );

        $advantage = collect($gaps)->where('gap_type', 'advantage')->pluck('keyword')->toArray();
        $this->assertContains('bpa free', $advantage, '"bpa free" should be our advantage');
    }

    public function test_title_keywords_get_higher_priority_than_description_keywords(): void
    {
        $calculator = new KeywordGapCalculator();

        $ourKeywords  = [];
        $theirKeywords = [
            ['keyword' => 'gift box',    'source' => 'title',       'frequency' => 2],
            ['keyword' => 'coffee lover', 'source' => 'description', 'frequency' => 2],
        ];

        $gaps = $calculator->calculate(
            $ourKeywords, $theirKeywords, $this->product->id, $this->competitor->id,
            'Gift Box Coffee Mug', []
        );

        $gapMap = collect($gaps)->keyBy('keyword');
        $titlePriority = $gapMap['gift box']['priority_score'] ?? 0;
        $descPriority  = $gapMap['coffee lover']['priority_score'] ?? 0;

        $this->assertGreaterThan(
            $descPriority,
            $titlePriority,
            'Keyword in competitor title should have higher priority than description keyword'
        );
    }

    public function test_singular_plural_normalization_prevents_false_gaps(): void
    {
        $calculator = new KeywordGapCalculator();

        $ourKeywords  = [['keyword' => 'mug', 'source' => 'title', 'frequency' => 2]];
        $theirKeywords = [['keyword' => 'mugs', 'source' => 'title', 'frequency' => 2]]; // plural variant

        $gaps = $calculator->calculate(
            $ourKeywords, $theirKeywords, $this->product->id, $this->competitor->id, '', []
        );

        $missing = collect($gaps)->where('gap_type', 'missing')->pluck('keyword')->toArray();
        $this->assertNotContains('mugs', $missing, '"mugs" should not be missing since we have "mug"');
    }

    public function test_priority_score_capped_at_95(): void
    {
        $calculator = new KeywordGapCalculator();

        // Max scenario: missing + high freq + in title
        $ourKeywords  = [];
        $theirKeywords = [['keyword' => 'gift set', 'source' => 'title', 'frequency' => 10]];

        $gaps = $calculator->calculate(
            $ourKeywords, $theirKeywords, $this->product->id, $this->competitor->id,
            'Best Gift Set Coffee Mug', []
        );

        $maxPriority = collect($gaps)->max('priority_score');
        $this->assertLessThanOrEqual(95, $maxPriority, 'Priority score must be capped at 95');
    }

    // ─── CompetitorAnalysisService ────────────────────────────────────────

    public function test_analysis_extracts_and_stores_competitor_keywords(): void
    {
        $service = app(CompetitorAnalysisService::class);
        $service->analyze($this->competitor);

        $count = CompetitorKeyword::where('competitor_id', $this->competitor->id)->count();
        $this->assertGreaterThan(0, $count, 'Competitor keywords should be extracted');
    }

    public function test_analysis_stores_keyword_gaps_when_product_is_linked(): void
    {
        // First: extract our product keywords
        $productService = app(ProductIntelligenceService::class);
        $productService->analyze($this->product);

        // Then: analyze competitor
        $service = app(CompetitorAnalysisService::class);
        $service->analyze($this->competitor);

        $gapCount = KeywordGap::where('product_id', $this->product->id)
            ->where('competitor_id', $this->competitor->id)
            ->count();

        $this->assertGreaterThan(0, $gapCount, 'Keyword gaps should be stored when product is linked');
    }

    // ─── API Endpoints ────────────────────────────────────────────────────

    public function test_can_list_competitors_for_product(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/products/{$this->product->id}/competitors");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data' => [['id', 'asin', 'low_confidence_fields']]]);
    }

    public function test_keyword_gaps_endpoint_supports_gap_type_filter(): void
    {
        // Insert some test gaps
        KeywordGap::insert([
            ['product_id' => $this->product->id, 'competitor_id' => $this->competitor->id,
             'keyword' => 'gift box', 'gap_type' => 'missing', 'our_frequency' => 0, 'their_frequency' => 2, 'priority_score' => 75],
            ['product_id' => $this->product->id, 'competitor_id' => $this->competitor->id,
             'keyword' => 'bpa free', 'gap_type' => 'advantage', 'our_frequency' => 2, 'their_frequency' => 0, 'priority_score' => 20],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/products/{$this->product->id}/keyword-gaps?gap_type=missing");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.gap_type', 'missing');
    }

    public function test_benchmark_endpoint_returns_product_and_competitor_data(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/products/{$this->product->id}/benchmark");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['product', 'competitors', 'consensus_gaps', 'competitor_count']]);
    }

    public function test_analyze_endpoint_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/workspaces/{$this->workspace->id}/products/{$this->product->id}/competitors/{$this->competitor->id}/analyze");

        $response->assertStatus(202);
        Queue::assertPushedOn('ai', CompetitorAnalysisJob::class);
    }

    public function test_html_parsed_competitor_flags_low_confidence_fields(): void
    {
        $htmlCompetitor = Competitor::create([
            'workspace_id'     => $this->workspace->id,
            'product_id'       => $this->product->id,
            'import_batch_id'  => $this->batch->id,
            'asin'             => 'B09HTMLCMP',
            'title'            => 'Test Product',
            'source_type'      => 'html',
            'parse_confidence' => [
                'title'        => 100,
                'price'        => 40,   // low confidence
                'rating'       => 25,   // low confidence
                'review_count' => 100,
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/products/{$this->product->id}/competitors");

        $response->assertStatus(200);
        $found = collect($response->json('data'))->firstWhere('asin', 'B09HTMLCMP');
        $this->assertNotNull($found);
        $this->assertContains('price',  $found['low_confidence_fields']);
        $this->assertContains('rating', $found['low_confidence_fields']);
        $this->assertNotContains('title', $found['low_confidence_fields']);
    }
}
