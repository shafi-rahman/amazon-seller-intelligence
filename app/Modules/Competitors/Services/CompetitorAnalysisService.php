<?php

namespace App\Modules\Competitors\Services;

use App\Modules\Competitors\Models\Competitor;
use App\Modules\Competitors\Models\CompetitorBenchmark;
use App\Modules\Competitors\Models\CompetitorKeyword;
use App\Modules\Competitors\Models\KeywordGap;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductKeyword;
use App\Modules\Products\Services\KeywordExtractorService;

class CompetitorAnalysisService
{
    public function __construct(
        private readonly KeywordExtractorService $extractor,
        private readonly KeywordGapCalculator    $gapCalculator,
        private readonly BenchmarkCalculator     $benchmarkCalculator,
    ) {}

    /**
     * Full analysis pipeline for one competitor:
     * 1. Extract + store competitor keywords
     * 2. If linked product exists: calculate gaps + benchmark
     * 3. Update last_analyzed_at
     */
    public function analyze(Competitor $competitor): void
    {
        // Step 1: Extract keywords from competitor listing
        $keywords = $this->extractor->extract(
            $competitor->title ?? '',
            $competitor->bullets(),
            $competitor->description,
        );

        CompetitorKeyword::where('competitor_id', $competitor->id)->delete();

        if (!empty($keywords)) {
            CompetitorKeyword::insert(array_map(fn($k) => [
                'competitor_id' => $competitor->id,
                'keyword'       => $k['keyword'],
                'source'        => $k['source'],
                'frequency'     => $k['frequency'],
            ], $keywords));
        }

        // Step 2: If linked to a product, run gap analysis and benchmark
        if ($competitor->product_id !== null) {
            $product = Product::find($competitor->product_id);
            if ($product) {
                $this->calculateGaps($product, $competitor, $keywords);
                $this->calculateBenchmark($product, $competitor);
            }
        }

        $competitor->update(['last_analyzed_at' => now()]);
    }

    private function calculateGaps(Product $product, Competitor $competitor, array $theirKeywords): void
    {
        $ourKeywords = ProductKeyword::where('product_id', $product->id)
            ->select('keyword', 'source', 'frequency')
            ->get()
            ->toArray();

        $gaps = $this->gapCalculator->calculate(
            $ourKeywords,
            $theirKeywords,
            $product->id,
            $competitor->id,
            $competitor->title ?? '',
            $competitor->bullets(),
            array_filter([$competitor->brand, $product->brand]),
        );

        // Replace existing gaps for this pair
        KeywordGap::where('product_id', $product->id)
            ->where('competitor_id', $competitor->id)
            ->delete();

        if (!empty($gaps)) {
            KeywordGap::insert($gaps);
        }
    }

    private function calculateBenchmark(Product $product, Competitor $competitor): void
    {
        $data = $this->benchmarkCalculator->calculate($product, $competitor);

        CompetitorBenchmark::updateOrCreate(
            ['product_id' => $product->id, 'competitor_id' => $competitor->id],
            ['benchmark_data' => $data]
        );
    }

    /**
     * Aggregate keyword gaps across all competitors for a product.
     * Returns top missing keywords (appear in ≥50% of competitors).
     */
    public function aggregateGaps(Product $product): array
    {
        $competitorCount = $product->competitors()->count();
        if ($competitorCount === 0) {
            return [];
        }

        $threshold = max(1, (int) ceil($competitorCount * 0.5));

        return KeywordGap::where('product_id', $product->id)
            ->where('gap_type', 'missing')
            ->selectRaw('keyword, COUNT(*) as in_competitors, MAX(priority_score) as max_priority')
            ->groupBy('keyword')
            ->havingRaw('COUNT(*) >= ?', [$threshold])
            ->orderByDesc('in_competitors')
            ->orderByDesc('max_priority')
            ->limit(20)
            ->get()
            ->map(fn($r) => [
                'keyword'        => $r->keyword,
                'in_competitors' => (int) $r->in_competitors,
                'max_priority'   => (int) $r->max_priority,
            ])
            ->toArray();
    }
}
