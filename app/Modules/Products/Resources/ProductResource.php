<?php

namespace App\Modules\Products\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'asin'             => $this->asin,
            'sku'              => $this->sku,
            'title'            => $this->title,
            'brand'            => $this->brand,
            'category'         => $this->category,
            'price'            => $this->price !== null ? (float) $this->price : null,
            'currency'         => $this->currency,
            'rating'           => $this->rating !== null ? (float) $this->rating : null,
            'review_count'     => $this->review_count,
            'listing_score'    => $this->listing_score,
            'score_tier'       => $this->scoreTier(),
            'last_analyzed_at' => $this->last_analyzed_at?->toISOString(),
            'has_competitor_data' => $this->whenLoaded('competitors', fn() =>
                $this->competitors->isNotEmpty()
            ),
        ];
    }

    private function scoreTier(): ?string
    {
        if ($this->listing_score === null) {
            return null;
        }
        return match(true) {
            $this->listing_score >= 85 => 'excellent',
            $this->listing_score >= 70 => 'good',
            $this->listing_score >= 50 => 'needs_work',
            $this->listing_score >= 30 => 'poor',
            default                    => 'critical',
        };
    }
}
