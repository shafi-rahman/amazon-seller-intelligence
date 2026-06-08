<?php

namespace App\Modules\Products\Services;

use App\Modules\AI\Jobs\EmbedDocumentJob;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductAnalysis;
use App\Modules\Products\Models\ProductKeyword;
use Illuminate\Support\Facades\DB;

class ProductIntelligenceService
{
    public function isAiConfigured(): bool
    {
        return $this->ai->isConfigured();
    }

    public function __construct(
        private readonly KeywordExtractorService $extractor,
        private readonly ListingScorerService    $scorer,
        private readonly AiAnalysisService       $ai,
    ) {}

    /**
     * Run the full analysis pipeline for one product.
     * Step 1: Extract keywords → store in product_keywords
     * Step 2: Compute listing score → store in product_analyses
     * Step 3: Call AI (if configured) → store in product_analyses
     * Step 4: Update product.listing_score and last_analyzed_at
     */
    public function analyze(Product $product): Product
    {
        // Step 1: Keyword extraction
        $keywords = $this->extractor->extract(
            $product->title ?? '',
            $product->bullets(),
            $product->description,
        );

        // Persist keywords (replace previous extraction)
        ProductKeyword::where('product_id', $product->id)->delete();
        if (!empty($keywords)) {
            $rows = array_map(fn($k) => [
                'product_id' => $product->id,
                'keyword'    => $k['keyword'],
                'source'     => $k['source'],
                'frequency'  => $k['frequency'],
            ], $keywords);
            ProductKeyword::insert($rows);
        }

        // Step 2: Rule-based listing score
        $scored = $this->scorer->score($product);

        ProductAnalysis::create([
            'product_id'    => $product->id,
            'analysis_type' => 'listing_score',
            'analysis_data' => $scored,
        ]);

        // Step 3: AI analysis (optional, skipped if no API key)
        if ($this->ai->isConfigured()) {
            $aiResult = $this->ai->analyzeListingWithClaude($product, $scored);

            if ($aiResult !== null) {
                ProductAnalysis::create([
                    'product_id'        => $product->id,
                    'analysis_type'     => 'optimization_suggestions',
                    'ai_provider'       => $aiResult['provider'] ?? config('ai.default_provider'),
                    'ai_model'          => $aiResult['model'] ?? null,
                    'prompt_tokens'     => $aiResult['prompt_tokens'] ?? null,
                    'completion_tokens' => $aiResult['completion_tokens'] ?? null,
                    'analysis_data'     => $aiResult['analysis_data'] ?? [],
                ]);
            }
        }

        // Step 4: Update product
        $product->update([
            'listing_score'   => $scored['total'],
            'last_analyzed_at'=> now(),
        ]);

        return $product->fresh();
    }

    /**
     * Generate and store an AI rewrite for a product.
     * Requires AI to be configured.
     */
    public function generateRewrite(Product $product): ?array
    {
        if (!$this->ai->isConfigured()) {
            return null;
        }

        // Get missing keywords from stored analysis
        $scoredAnalysis = $product->latestAnalysis('listing_score');
        $missingKeywords = [];

        if ($scoredAnalysis) {
            $kw = $scoredAnalysis->analysis_data['dimensions']['keywords'] ?? [];
            $missingKeywords = collect($kw['issues'] ?? [])->toArray();
        }

        $rewrite = $this->ai->generateRewrite($product, $missingKeywords);

        if ($rewrite !== null) {
            ProductAnalysis::create([
                'product_id'    => $product->id,
                'analysis_type' => 'ai_rewrite',
                'ai_provider'   => 'anthropic',
                'ai_model'      => config('ai.providers.anthropic.model'),
                'analysis_data' => $rewrite,
            ]);
        }

        return $rewrite;
    }

    /**
     * Apply an accepted rewrite back to the product fields.
     */
    public function applyRewrite(Product $product, array $rewrite): Product
    {
        $allowed = ['title', 'bullet_1', 'bullet_2', 'bullet_3', 'bullet_4', 'bullet_5', 'description'];
        $updates = array_intersect_key($rewrite, array_flip($allowed));

        $product->update($updates);

        // Re-analyze with the updated content
        $analyzed = $this->analyze($product->fresh());

        // Re-embed the updated listing (async)
        EmbedDocumentJob::dispatch(Product::class, $analyzed->id, $analyzed->workspace_id)
            ->onQueue('embeddings');

        return $analyzed;
    }
}
