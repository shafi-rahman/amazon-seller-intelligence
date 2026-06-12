<?php

namespace App\Modules\AI\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Describes an image using a NVIDIA vision-language model. Used to turn an
 * uploaded/existing reference image into a text description that can guide
 * text-to-image generation (reference-guided regenerate).
 */
class VisionService
{
    private const MODEL    = 'meta/llama-3.2-11b-vision-instruct';
    private const ENDPOINT = 'https://integrate.api.nvidia.com/v1/chat/completions';

    /**
     * Return a concise description of the image (object, colour, material,
     * background, composition) suitable for feeding to an image generator,
     * or null on failure.
     *
     * @param string $binary raw image bytes
     */
    public function describe(string $binary, string $mime = 'image/jpeg'): ?string
    {
        $apiKey = config('ai.providers.nvidia.api_key');
        if (empty($apiKey) || $binary === '') {
            return null;
        }

        $b64 = base64_encode($binary);

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ])
                ->timeout(60)
                ->post(self::ENDPOINT, [
                    'model'       => self::MODEL,
                    'temperature' => 0.2,
                    'max_tokens'  => 150,
                    'messages'    => [[
                        'role'    => 'user',
                        'content' => [
                            ['type' => 'text', 'text' =>
                                'Describe this image in one concise sentence for an image generator: '
                                . 'main object, colour, material, background, lighting and composition. '
                                . 'No preamble.'],
                            ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64,{$b64}"]],
                        ],
                    ]],
                ]);

            if (!$response->ok()) {
                Log::warning('Vision describe failed', ['status' => $response->status(), 'body' => substr($response->body(), 0, 200)]);
                return null;
            }

            $text = $response->json('choices.0.message.content');
            return is_string($text) && trim($text) !== '' ? trim($text) : null;
        } catch (\Throwable $e) {
            Log::warning('Vision describe threw', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
