<?php

namespace App\Modules\AI\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Text-to-image generation via NVIDIA's hosted FLUX.1-schnell model
 * (free, fast — ~4 inference steps). Returns raw JPEG bytes; callers decide
 * where to store them. Shared by the SEO and Products modules.
 */
class ImageGenerationService
{
    private const ENDPOINT = 'https://ai.api.nvidia.com/v1/genai/black-forest-labs/flux.1-schnell';

    /**
     * Generate one image and return the decoded JPEG bytes, or null on failure.
     * Vary $seed to get different images from the same prompt.
     */
    public function generate(string $prompt, int $seed = 0): ?string
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
                    'seed'   => $seed,
                ]);

            if (!$response->ok()) {
                Log::warning('NVIDIA image generation failed', [
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 300),
                ]);
                return null;
            }

            $data = $response->json();
            $b64  = $data['artifacts'][0]['base64'] ?? ($data['image'] ?? null);
            if (empty($b64)) {
                Log::warning('NVIDIA image response had no base64 payload', ['keys' => array_keys($data ?? [])]);
                return null;
            }

            $binary = base64_decode($b64, true);
            return $binary === false ? null : $binary;
        } catch (\Throwable $e) {
            Log::warning('NVIDIA image generation threw', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
