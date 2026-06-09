<?php

namespace App\Modules\SEO\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates social-media post images with NVIDIA's hosted FLUX.1-schnell model
 * (free, fast — ~4 inference steps). Returns a MinIO storage path or null on failure.
 *
 * The image is stored on the 's3' disk (written via minio:9000); presigned URLs
 * for the browser are produced from the 's3_public' disk (localhost:9000).
 */
class SeoImageService
{
    private const ENDPOINT = 'https://ai.api.nvidia.com/v1/genai/black-forest-labs/flux.1-schnell';

    /**
     * Generate an image from a text prompt and store it in MinIO.
     *
     * @return string|null storage path (e.g. seo/1/{uuid}/post.jpg) or null on failure
     */
    public function generate(string $prompt, int $workspaceId, string $campaignPublicId): ?string
    {
        $apiKey = config('ai.providers.nvidia.api_key');
        if (empty($apiKey) || trim($prompt) === '') {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ])
                ->timeout(90)
                ->post(self::ENDPOINT, [
                    'prompt' => Str::limit($prompt, 900, ''),
                    'width'  => 1024,
                    'height' => 1024,
                    'steps'  => 4,
                    'seed'   => 0,
                ]);

            if (!$response->ok()) {
                Log::warning('NVIDIA image generation failed', [
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 300),
                ]);
                return null;
            }

            $data = $response->json();

            // FLUX returns { "artifacts": [ { "base64": "...", "finishReason": "SUCCESS" } ] }
            $b64 = $data['artifacts'][0]['base64'] ?? ($data['image'] ?? null);
            if (empty($b64)) {
                Log::warning('NVIDIA image response had no base64 payload', ['keys' => array_keys($data ?? [])]);
                return null;
            }

            $binary = base64_decode($b64, true);
            if ($binary === false) {
                return null;
            }

            $path = "seo/{$workspaceId}/{$campaignPublicId}/" . Str::uuid() . '.jpg';
            Storage::disk('s3')->put($path, $binary);

            return $path;
        } catch (\Throwable $e) {
            Log::warning('NVIDIA image generation threw', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
