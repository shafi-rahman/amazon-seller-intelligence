<?php

namespace App\Modules\SEO\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeoCampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // whenLoaded() returns a (truthy) MissingValue when the relation isn't
        // loaded — calling ->where() on it throws. Check relationLoaded() instead.
        $postsLoaded   = $this->resource->relationLoaded('posts');
        $posts         = $postsLoaded ? $this->posts : collect();
        $approvedCount = $posts->where('status', 'approved')->count();
        $totalPosts    = $posts->count();

        return [
            'id'            => $this->public_id, // UUID — used in browser URLs
            'uuid'          => $this->public_id,
            'product'       => $this->whenLoaded('product', fn() => [
                'id'    => $this->product->id,
                'asin'  => $this->product->asin,
                'title' => $this->product->title,
                'brand' => $this->product->brand,
                'price' => $this->product->price,
                'image' => null,
            ]),
            'status'        => $this->status,
            'trend_data'    => $this->trend_data,
            'ai_provider'   => $this->ai_provider,
            'generated_at'  => $this->generated_at?->toISOString(),
            'created_at'    => $this->created_at->toISOString(),
            'posts_count'   => $totalPosts,
            'approved_count'=> $approvedCount,
            'posts'         => $this->whenLoaded('posts', fn() =>
                $this->posts->map(fn($p) => [
                    'id'             => $p->id,
                    'platform'       => $p->platform,
                    'title'          => $p->title,
                    'caption'        => $p->caption,
                    'edited_caption' => $p->edited_caption,
                    'hashtags'       => $p->hashtags,
                    'image_prompt'   => $p->image_prompt,
                    'image_url'      => $p->imageUrl(),
                    'status'         => $p->status,
                    'created_at'     => $p->created_at?->toISOString(),
                ])
            ),
        ];
    }
}
