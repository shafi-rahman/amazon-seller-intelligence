<?php

namespace App\Modules\SEO\Services;

use App\Modules\AI\Services\ImageGenerationService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates social-media post images with NVIDIA FLUX (via the shared
 * ImageGenerationService) and stores them in MinIO. Returns a storage path.
 *
 * Images are written to the 's3' disk (minio:9000); presigned URLs for the
 * browser are produced from the 's3_public' disk (localhost:9000).
 */
class SeoImageService
{
    public function __construct(private readonly ImageGenerationService $images) {}

    /**
     * Generate an image from a text prompt and store it in MinIO.
     *
     * @return string|null storage path (e.g. seo/1/{uuid}/post.jpg) or null on failure
     */
    public function generate(string $prompt, int $workspaceId, string $campaignPublicId): ?string
    {
        $binary = $this->images->generate($prompt);
        if ($binary === null) {
            return null;
        }

        $path = "seo/{$workspaceId}/{$campaignPublicId}/" . Str::uuid() . '.jpg';
        Storage::disk('s3')->put($path, $binary);

        return $path;
    }
}
