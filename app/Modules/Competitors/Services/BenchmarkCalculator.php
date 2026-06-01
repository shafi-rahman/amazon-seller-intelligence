<?php

namespace App\Modules\Competitors\Services;

use App\Modules\Competitors\Models\Competitor;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Services\KeywordExtractorService;
use App\Modules\Products\Services\ListingScorerService;

class BenchmarkCalculator
{
    public function __construct(
        private readonly ListingScorerService    $scorer,
        private readonly KeywordExtractorService $extractor,
    ) {}

    public function calculate(Product $product, Competitor $competitor): array
    {
        // Compute competitor listing score using the same algorithm
        $compProductLike         = $this->competitorAsProductLike($competitor);
        $competitorScore         = $this->scorer->score($compProductLike);
        $ourScore                = $product->listing_score ?? 0;

        // Keyword overlap
        $ourKeywords   = $product->keywords()->pluck('keyword')->map(fn($k) => mb_strtolower($k))->toArray();
        $theirKeywords = $competitor->keywords()->pluck('keyword')->map(fn($k) => mb_strtolower($k))->toArray();

        $overlap       = count(array_intersect($ourKeywords, $theirKeywords));
        $weLack        = count(array_diff($theirKeywords, $ourKeywords));
        $theyLack      = count(array_diff($ourKeywords, $theirKeywords));

        $ourPrice    = $product->price !== null ? (float) $product->price : null;
        $theirPrice  = $competitor->price !== null ? (float) $competitor->price : null;
        $ourRating   = $product->rating !== null ? (float) $product->rating : null;
        $theirRating = $competitor->rating !== null ? (float) $competitor->rating : null;

        return [
            'our_asin'              => $product->asin,
            'their_asin'            => $competitor->asin,
            'our_listing_score'     => $ourScore,
            'their_listing_score'   => $competitorScore['total'],
            'listing_score_delta'   => $ourScore - $competitorScore['total'],
            'our_price'             => $ourPrice,
            'their_price'           => $theirPrice,
            'price_delta'           => $ourPrice !== null && $theirPrice !== null
                ? round($ourPrice - $theirPrice, 2)
                : null,
            'our_rating'            => $ourRating,
            'their_rating'          => $theirRating,
            'rating_delta'          => $ourRating !== null && $theirRating !== null
                ? round($ourRating - $theirRating, 2)
                : null,
            'our_review_count'      => $product->review_count ?? 0,
            'their_review_count'    => $competitor->review_count ?? 0,
            'review_count_delta'    => ($product->review_count ?? 0) - ($competitor->review_count ?? 0),
            'keyword_overlap'       => $overlap,
            'keywords_we_lack'      => $weLack,
            'keywords_they_lack'    => $theyLack,
            'their_score_breakdown' => $competitorScore['dimensions'],
            'verdict' => [
                'price_position'    => $this->position($ourPrice, $theirPrice, invert: true),
                'listing_quality'   => $this->position($ourScore, $competitorScore['total']),
                'review_authority'  => $this->position(
                    $product->review_count ?? 0,
                    $competitor->review_count ?? 0
                ),
            ],
        ];
    }

    private function competitorAsProductLike(Competitor $competitor): Product
    {
        $mock = new Product([
            'title'        => $competitor->title,
            'brand'        => $competitor->brand,
            'bullet_1'     => $competitor->bullet_1,
            'bullet_2'     => $competitor->bullet_2,
            'bullet_3'     => $competitor->bullet_3,
            'bullet_4'     => $competitor->bullet_4,
            'bullet_5'     => $competitor->bullet_5,
            'description'  => $competitor->description,
            'price'        => $competitor->price,
            'rating'       => $competitor->rating,
            'review_count' => $competitor->review_count ?? 0,
        ]);
        $mock->id = 0; // avoid DB competitor check
        return $mock;
    }

    private function position(?float $ours, ?float $theirs, bool $invert = false): string
    {
        if ($ours === null || $theirs === null) {
            return 'unknown';
        }
        $diff = $ours - $theirs;
        if ($invert) {
            $diff = -$diff;
        }
        return match(true) {
            $diff > 0  => 'better',
            $diff < 0  => 'worse',
            default    => 'equal',
        };
    }
}
