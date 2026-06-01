<?php

namespace App\Modules\Competitors\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompetitorDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $conf = $this->parse_confidence ?? [];

        return [
            'id'               => $this->id,
            'asin'             => $this->asin,
            'title'            => $this->title,
            'brand'            => $this->brand,
            'category'         => $this->category,
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
            'source_type'      => $this->source_type,
            'last_analyzed_at' => $this->last_analyzed_at?->toISOString(),
            // HTML confidence review
            'parse_confidence'      => $this->when($this->source_type === 'html', $conf),
            'low_confidence_fields' => $this->source_type === 'html'
                ? collect($conf)->filter(fn($v) => $v < 60)->keys()->values()
                : [],
            // Keyword data
            'top_keywords' => $this->whenLoaded('keywords', fn() =>
                $this->keywords->take(20)->map(fn($k) => [
                    'keyword'   => $k->keyword,
                    'source'    => $k->source,
                    'frequency' => $k->frequency,
                ])
            ),
            // Benchmark if available
            'benchmark' => $this->whenLoaded('benchmark', fn() =>
                $this->benchmark?->benchmark_data
            ),
        ];
    }
}
