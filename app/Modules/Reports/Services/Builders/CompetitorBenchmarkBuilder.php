<?php

namespace App\Modules\Reports\Services\Builders;

use App\Modules\Competitors\Models\Competitor;
use App\Modules\Competitors\Models\CompetitorBenchmark;
use App\Modules\Products\Models\Product;
use App\Modules\Reports\Models\Report;

class CompetitorBenchmarkBuilder extends BaseBuilder
{
    public function build(Report $report): string
    {
        $productId = $report->parameters['product_id']
            ?? throw new \InvalidArgumentException('product_id required');

        $product    = Product::findOrFail($productId);
        $benchmarks = CompetitorBenchmark::where('product_id', $productId)
            ->with('competitor')
            ->get();

        if ($report->file_format === 'csv') {
            $headers = [
                'Our ASIN', 'Competitor ASIN', 'Our Score', 'Their Score', 'Score Delta',
                'Our Price (₹)', 'Their Price (₹)', 'Price Delta',
                'Our Rating', 'Their Rating', 'Our Reviews', 'Their Reviews',
                'Keyword Overlap', 'Keywords We Lack', 'Keywords They Lack',
            ];

            $formatted = $benchmarks->map(fn($b) => [
                $b->benchmark_data['our_asin']            ?? '',
                $b->benchmark_data['their_asin']          ?? $b->competitor->asin ?? '',
                $b->benchmark_data['our_listing_score']   ?? '',
                $b->benchmark_data['their_listing_score'] ?? '',
                $b->benchmark_data['listing_score_delta'] ?? '',
                number_format((float)($b->benchmark_data['our_price']   ?? 0), 2),
                number_format((float)($b->benchmark_data['their_price'] ?? 0), 2),
                $b->benchmark_data['price_delta']         ?? '',
                $b->benchmark_data['our_rating']          ?? '',
                $b->benchmark_data['their_rating']        ?? '',
                $b->benchmark_data['our_review_count']    ?? '',
                $b->benchmark_data['their_review_count']  ?? '',
                $b->benchmark_data['keyword_overlap']     ?? '',
                $b->benchmark_data['keywords_we_lack']    ?? '',
                $b->benchmark_data['keywords_they_lack']  ?? '',
            ])->toArray();

            return $this->buildCsv($headers, $formatted, $report);
        }

        return $this->buildPdf('reports.competitor_benchmark', [
            'product'    => $product,
            'benchmarks' => $benchmarks,
            'generated'  => now()->format('d M Y H:i'),
        ], $report);
    }
}
