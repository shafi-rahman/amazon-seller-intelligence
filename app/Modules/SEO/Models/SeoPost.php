<?php

namespace App\Modules\SEO\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoPost extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'campaign_id', 'platform', 'caption', 'edited_caption',
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
}
