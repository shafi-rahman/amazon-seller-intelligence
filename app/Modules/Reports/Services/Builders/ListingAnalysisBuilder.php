<?php

namespace App\Modules\Reports\Services\Builders;

use App\Modules\Products\Models\Product;
use App\Modules\Reports\Models\Report;

class ListingAnalysisBuilder extends BaseBuilder
{
    public function build(Report $report): string
    {
        $productId = $report->parameters['product_id']
            ?? throw new \InvalidArgumentException('product_id required');

        $product  = Product::with(['keywords', 'analyses'])->findOrFail($productId);
        $scored   = $product->latestAnalysis('listing_score');
        $aiHints  = $product->latestAnalysis('optimization_suggestions');

        return $this->buildPdf('reports.listing_analysis', [
            'product'   => $product,
            'score'     => $scored?->analysis_data ?? [],
            'ai_hints'  => $aiHints?->analysis_data ?? [],
            'keywords'  => $product->keywords()->orderByDesc('frequency')->limit(30)->get(),
            'generated' => now()->format('d M Y H:i'),
        ], $report);
    }
}
