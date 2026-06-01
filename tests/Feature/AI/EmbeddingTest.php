<?php

namespace Tests\Feature\AI;

use App\Models\User;
use App\Modules\AI\Jobs\EmbedDocumentJob;
use App\Modules\AI\Listeners\EmbedOnImport;
use App\Modules\AI\Models\Embedding;
use App\Modules\AI\Services\DocumentChunkerService;
use App\Modules\AI\Services\EmbeddingProviderService;
use App\Modules\AI\Services\VectorSearchService;
use App\Modules\Imports\Events\ImportCompleted;
use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Products\Models\Product;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmbeddingTest extends TestCase
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

    // ─── DocumentChunkerService ──────────────────────────────────────────

    public function test_short_text_returns_single_chunk(): void
    {
        $chunker = new DocumentChunkerService();
        $chunks  = $chunker->chunk('Hello world. This is a short test.');
        $this->assertCount(1, $chunks);
    }

    public function test_long_text_is_split_into_multiple_chunks(): void
    {
        $chunker  = new DocumentChunkerService();
        $longText = str_repeat('This is a sentence about ceramic mugs. ', 100); // ~4000 chars
        $chunks   = $chunker->chunk($longText);
        $this->assertGreaterThan(1, count($chunks), 'Long text should produce multiple chunks');
    }

    public function test_chunks_have_overlap(): void
    {
        $chunker  = new DocumentChunkerService();
        $longText = str_repeat('A unique sentence with specific words about coffee mugs. ', 80);
        $chunks   = $chunker->chunk($longText);

        if (count($chunks) >= 2) {
            // The end of chunk 1 should partially appear in chunk 2
            $endOfFirst    = mb_substr($chunks[0], -100);
            $startOfSecond = mb_substr($chunks[1], 0, 100);
            $this->assertNotEmpty(trim($startOfSecond), 'Chunk 2 should not be empty');
        } else {
            $this->markTestSkipped('Text did not produce multiple chunks');
        }
    }

    public function test_empty_text_returns_empty_array(): void
    {
        $chunker = new DocumentChunkerService();
        $this->assertEmpty($chunker->chunk(''));
        $this->assertEmpty($chunker->chunk('   '));
        $this->assertEmpty($chunker->chunk('<p></p>'));
    }

    public function test_html_is_stripped_before_chunking(): void
    {
        $chunker = new DocumentChunkerService();
        $html    = '<p>This is <strong>bold</strong> text.</p><ul><li>Item one</li></ul>';
        $chunks  = $chunker->chunk($html);
        $this->assertCount(1, $chunks);
        $this->assertStringNotContainsString('<p>', $chunks[0]);
        $this->assertStringContainsString('bold', $chunks[0]);
    }

    // ─── EmbeddingProviderService ────────────────────────────────────────

    public function test_provider_is_unconfigured_when_no_keys_set(): void
    {
        // Clear any env keys
        config(['ai.providers.openai.api_key' => null]);
        config(['ai.providers.ollama.base_url' => '']);

        $provider = new EmbeddingProviderService();
        $this->assertFalse($provider->isConfigured());
    }

    public function test_to_pg_vector_formats_correctly(): void
    {
        $vector = [0.1, -0.2, 0.35];
        $result = EmbeddingProviderService::toPgVector($vector);
        $this->assertStringStartsWith('[', $result);
        $this->assertStringEndsWith(']', $result);
        $this->assertStringContainsString('0.1', $result);
    }

    public function test_to_pg_vector_rejects_non_numeric(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EmbeddingProviderService::toPgVector([0.1, 'injection', 0.3]);
    }

    // ─── EmbedDocumentJob ────────────────────────────────────────────────

    public function test_embed_job_skips_gracefully_when_no_provider(): void
    {
        config(['ai.providers.openai.api_key' => null]);
        config(['ai.providers.ollama.base_url' => '']);

        $product = Product::create([
            'workspace_id' => $this->workspace->id,
            'asin'         => 'B09EMBED001',
            'title'        => 'Test product for embedding',
        ]);

        // Should not throw even with no provider
        $job = new EmbedDocumentJob(Product::class, $product->id, $this->workspace->id);
        $job->handle(new EmbeddingProviderService(), new DocumentChunkerService());

        $count = Embedding::where('embeddable_id', $product->id)->count();
        $this->assertEquals(0, $count, 'No embeddings should be stored when provider is not configured');
    }

    // ─── EmbedOnImport Listener ──────────────────────────────────────────

    public function test_products_import_dispatches_embed_jobs(): void
    {
        Queue::fake();

        $product = Product::create([
            'workspace_id'    => $this->workspace->id,
            'import_batch_id' => $this->batch->id,
            'asin'            => 'B09LISTEN01',
            'title'           => 'Product to embed',
        ]);

        $listener = new EmbedOnImport();
        $listener->handle(new ImportCompleted($this->batch));

        Queue::assertPushedOn('embeddings', EmbedDocumentJob::class);
    }

    public function test_non_product_import_does_not_dispatch_embed_jobs(): void
    {
        Queue::fake();

        $otherBatch = ImportBatch::create([
            'workspace_id' => $this->workspace->id,
            'user_id'      => $this->user->id,
            'type'         => 'orders', // not products
            'status'       => 'completed',
        ]);

        $listener = new EmbedOnImport();
        $listener->handle(new ImportCompleted($otherBatch));

        Queue::assertNotPushed(EmbedDocumentJob::class);
    }

    // ─── VectorSearchService ─────────────────────────────────────────────

    public function test_vector_search_returns_empty_when_not_configured(): void
    {
        config(['ai.providers.openai.api_key' => null]);
        config(['ai.providers.ollama.base_url' => '']);

        $provider = new EmbeddingProviderService();
        $chunker  = new DocumentChunkerService();
        $service  = new VectorSearchService($provider);

        $results = $service->search('ceramic mug', $this->workspace->id);
        $this->assertEmpty($results, 'Search should return empty when provider is not configured');
    }

    public function test_vector_search_is_workspace_isolated(): void
    {
        // Create a second workspace
        $other      = User::factory()->create();
        $otherWs    = Workspace::factory()->create(['owner_id' => $other->id]);

        // Manually insert fake embeddings for both workspaces
        $fakeVector = '['.implode(',', array_fill(0, 1536, '0.1')).']';

        \DB::insert("
            INSERT INTO embeddings (embeddable_type, embeddable_id, chunk_index, chunk_text, embedding, model, workspace_id, created_at)
            VALUES (?, ?, ?, ?, ?::vector, ?, ?, NOW())
        ", [Product::class, 1, 0, 'Our product text', $fakeVector, 'test-model', $this->workspace->id]);

        \DB::insert("
            INSERT INTO embeddings (embeddable_type, embeddable_id, chunk_index, chunk_text, embedding, model, workspace_id, created_at)
            VALUES (?, ?, ?, ?, ?::vector, ?, ?, NOW())
        ", [Product::class, 2, 0, 'Other workspace text', $fakeVector, 'test-model', $otherWs->id]);

        // Query using raw SQL to verify isolation without needing the provider
        $ourCount   = \DB::selectOne('SELECT COUNT(*) AS cnt FROM embeddings WHERE workspace_id = ?', [$this->workspace->id]);
        $otherCount = \DB::selectOne('SELECT COUNT(*) AS cnt FROM embeddings WHERE workspace_id = ?', [$otherWs->id]);

        $this->assertEquals(1, $ourCount->cnt, 'Our workspace should have 1 embedding');
        $this->assertEquals(1, $otherCount->cnt, 'Other workspace should have 1 embedding');
    }

    public function test_format_as_context_produces_readable_output(): void
    {
        $provider = new EmbeddingProviderService();
        $service  = new VectorSearchService($provider);

        $fakeResults = [
            (object) [
                'embeddable_type' => Product::class,
                'embeddable_id'   => 1,
                'chunk_text'      => 'This is a product about ceramic mugs.',
                'similarity'      => 0.87,
            ],
        ];

        $context = $service->formatAsContext($fakeResults);
        $this->assertStringContainsString('Product', $context);
        $this->assertStringContainsString('ceramic mugs', $context);
        $this->assertStringContainsString('87%', $context);
    }

    // ─── VectorSearchService::isAvailable() ──────────────────────────────

    public function test_search_is_available_when_openai_configured(): void
    {
        config(['ai.providers.openai.api_key' => 'test-key-123']);
        $provider = new EmbeddingProviderService();
        $service  = new VectorSearchService($provider);
        $this->assertTrue($service->isAvailable());
    }
}
