<?php

namespace App\Modules\SEO\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SeoPost extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'campaign_id', 'platform', 'title', 'caption', 'edited_caption',
        'hashtags', 'image_prompt', 'image_path',
        'status', 'platform_post_id', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'created_at'   => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(SeoCampaign::class, 'campaign_id');
    }

    // The caption to actually use — edited version takes priority
    public function activeCaptionAttribute(): string
    {
        return $this->edited_caption ?? $this->caption ?? '';
    }

    // Browser-reachable presigned URL for the generated post image (24h).
    public function imageUrl(int $ttlHours = 24): ?string
    {
        if (empty($this->image_path)) return null;
        try {
            return Storage::disk('s3_public')->temporaryUrl($this->image_path, now()->addHours($ttlHours));
        } catch (\Throwable) {
            return null;
        }
    }
}
