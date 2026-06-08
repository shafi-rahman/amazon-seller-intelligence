<?php

namespace App\Modules\SEO\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeoCampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $posts = $this->whenLoaded('posts');
        $approvedCount = $posts ? $posts->where('status', 'approved')->count() : 0;
        $totalPosts    = $posts ? $posts->count() : 0;

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
                    'caption'        => $p->caption,
                    'edited_caption' => $p->edited_caption,
                    'hashtags'       => $p->hashtags,
                    'image_prompt'   => $p->image_prompt,
                    'status'         => $p->status,
                    'created_at'     => $p->created_at?->toISOString(),
                ])
            ),
        ];
    }
}
