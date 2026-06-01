<?php

namespace App\Modules\Competitors\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompetitorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $conf = $this->parse_confidence ?? [];

        return [
            'id'               => $this->id,
            'asin'             => $this->asin,
            'title'            => $this->title,
            'brand'            => $this->brand,
            'price'            => $this->price !== null ? (float) $this->price : null,
            'currency'         => $this->currency,
            'rating'           => $this->rating !== null ? (float) $this->rating : null,
            'review_count'     => $this->review_count,
            'source_type'      => $this->source_type,
            'last_analyzed_at' => $this->last_analyzed_at?->toISOString(),
            // Flag fields parsed from HTML with <60% confidence
            'low_confidence_fields' => $this->source_type === 'html'
                ? collect($conf)->filter(fn($v) => $v < 60)->keys()->values()
                : [],
            'parse_confidence' => $this->when($this->source_type === 'html', $conf),
        ];
    }
}
