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
        'hashtags', 'image_prompt', 'image_path', 'previous_image_path',
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
        return $this->signedUrl($this->image_path, $ttlHours);
    }

    // Presigned URL for the image this post had before the last change.
    public function previousImageUrl(int $ttlHours = 24): ?string
    {
        return $this->signedUrl($this->previous_image_path, $ttlHours);
    }

    private function signedUrl(?string $path, int $ttlHours): ?string
    {
        if (empty($path)) return null;
        try {
            return Storage::disk('s3_public')->temporaryUrl($path, now()->addHours($ttlHours));
        } catch (\Throwable) {
            return null;
        }
    }

    // Apply a new image, remembering the prior one so it can be restored.
    public function applyNewImage(string $newPath, ?string $prompt = null): void
    {
        if ($this->image_path && $this->image_path !== $newPath) {
            $this->previous_image_path = $this->image_path;
        }
        $this->image_path = $newPath;
        if ($prompt !== null) {
            $this->image_prompt = $prompt;
        }
        $this->save();
    }
}
