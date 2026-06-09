<?php

namespace App\Modules\Products\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $scoredAnalysis = $this->latestAnalysis('listing_score');
        $aiAnalysis     = $this->latestAnalysis('optimization_suggestions');

        return [
            'id'               => $this->public_id,
            'asin'             => $this->asin,
            'sku'              => $this->sku,
            'title'            => $this->title,
            'brand'            => $this->brand,
            'category'         => $this->category,
            'sub_category'     => $this->sub_category,
            'bullet_1'         => $this->bullet_1,
            'bullet_2'         => $this->bullet_2,
            'bullet_3'         => $this->bullet_3,
            'bullet_4'         => $this->bullet_4,
            'bullet_5'         => $this->bullet_5,
            'description'      => $this->description,
            'price'            => $this->price !== null ? (float) $this->price : null,
            'currency'         => $this->currency,
            'rating'           => $this->rating !== null ? (float) $this->rating : null,
            'review_count'     => $this->review_count,
            'listing_score'    => $this->listing_score,
            'last_analyzed_at' => $this->last_analyzed_at?->toISOString(),
            // Image gallery
            'images'     => $this->whenLoaded('images', fn() =>
                $this->images->map(fn($img) => [
                    'id'            => $img->public_id,
                    'url'           => $img->url(),
                    'file_name'     => $img->file_name,
                    'display_order' => $img->display_order,
                    'is_primary'    => $img->is_primary,
                ])
            ),
            // Primary image URL (for backward compat / quick access)
            'image_url'  => $this->whenLoaded('images', fn() =>
                $this->images->firstWhere('is_primary', true)?->url() ??
                $this->images->first()?->url()
            ),

            // Score breakdown from latest analysis
            'score_breakdown' => $scoredAnalysis?->analysis_data,

            // AI suggestions if available
            'ai_suggestions'  => $aiAnalysis?->analysis_data,

            // Top keywords
            'top_keywords' => $this->whenLoaded('keywords', fn() =>
                $this->keywords->take(20)->map(fn($k) => [
                    'keyword'   => $k->keyword,
                    'source'    => $k->source,
                    'frequency' => $k->frequency,
                ])
            ),
        ];
    }
}
