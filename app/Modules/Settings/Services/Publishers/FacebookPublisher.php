<?php

namespace App\Modules\Settings\Services\Publishers;

use App\Modules\SEO\Models\SeoPost;
use App\Modules\Settings\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookPublisher
{
    private const API_VERSION = 'v19.0';
    private const BASE_URL    = 'https://graph.facebook.com';

    public function publish(SeoPost $post, SocialAccount $account): array
    {
        $pageId    = $account->pageId();
        $token     = $account->access_token;

        abort_if(!$pageId || !$token, 422, 'Facebook Page ID and access token are required');

        $caption  = $post->edited_caption ?? $post->caption ?? '';
        $message  = $caption . ($post->hashtags ? "\n\n" . $post->hashtags : '');

        $payload  = [
            'message'      => $message,
            'access_token' => $token,
        ];

        // If an image is stored in MinIO (Phase 2+), add the link
        if ($post->image_path) {
            $payload['link'] = config('app.url') . '/storage/' . $post->image_path;
        }

        $response = Http::timeout(30)->post(
            self::BASE_URL . '/' . self::API_VERSION . '/' . $pageId . '/feed',
            $payload
        );

        if (!$response->ok()) {
            Log::error('Facebook publish failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('Facebook API error: ' . $response->json('error.message', $response->body()));
        }

        $postId = $response->json('id');

        return [
            'platform_post_id' => $postId,
            'url'              => "https://www.facebook.com/{$postId}",
        ];
    }

    public function testConnection(SocialAccount $account): bool
    {
        try {
            $response = Http::timeout(10)->get(
                self::BASE_URL . '/' . self::API_VERSION . '/me',
                ['access_token' => $account->access_token]
            );
            return $response->ok();
        } catch (\Throwable) {
            return false;
        }
    }
}
