<?php

namespace App\Modules\Reports\Services\Builders;

use App\Modules\Competitors\Models\KeywordGap;
use App\Modules\Products\Models\Product;
use App\Modules\Reports\Models\Report;

class KeywordGapBuilder extends BaseBuilder
{
    public function build(Report $report): string
    {
        $productId = $report->parameters['product_id']
            ?? throw new \InvalidArgumentException('product_id required');

        $product = Product::findOrFail($productId);

        $gaps = KeywordGap::where('product_id', $productId)
            ->orderByDesc('priority_score')
            ->get();

        $headers = ['Keyword', 'Gap Type', 'Our Frequency', 'Competitor Frequency', 'Priority Score'];

        $formatted = $gaps->map(fn($g) => [
            $g->keyword,
            ucfirst($g->gap_type),
            $g->our_frequency,
            $g->their_frequency,
            $g->priority_score,
        ])->toArray();

        return $this->buildCsv($headers, $formatted, $report);
    }
}
