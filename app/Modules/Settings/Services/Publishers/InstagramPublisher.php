<?php

namespace App\Modules\Settings\Services\Publishers;

use App\Modules\SEO\Models\SeoPost;
use App\Modules\Settings\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InstagramPublisher
{
    private const API_VERSION = 'v19.0';
    private const BASE_URL    = 'https://graph.facebook.com';

    public function publish(SeoPost $post, SocialAccount $account): array
    {
        $igUserId = $account->igUserId();
        $token    = $account->access_token;

        abort_if(!$igUserId || !$token, 422, 'Instagram Business Account ID and access token are required');

        $caption = ($post->edited_caption ?? $post->caption ?? '') .
                   ($post->hashtags ? "\n\n" . $post->hashtags : '');

        // Instagram requires an image for feed posts.
        // If image not yet generated, fall back to a text-only REELS post or skip.
        if (!$post->image_path) {
            // Post as a text-only Story (requires image) — skip without image
            throw new \RuntimeException(
                'Instagram requires an image. Generate an image first or use the Reels format in Phase 3.'
            );
        }

        // Step 1: Create media container
        $imageUrl = Storage::disk('s3')->temporaryUrl($post->image_path, now()->addHour());

        $createResponse = Http::timeout(30)->post(
            self::BASE_URL . '/' . self::API_VERSION . '/' . $igUserId . '/media',
            [
                'image_url'    => $imageUrl,
                'caption'      => $caption,
                'access_token' => $token,
            ]
        );

        if (!$createResponse->ok()) {
            throw new \RuntimeException('Instagram media create failed: ' . $createResponse->json('error.message', ''));
        }

        $creationId = $createResponse->json('id');

        // Step 2: Publish the container
        $publishResponse = Http::timeout(30)->post(
            self::BASE_URL . '/' . self::API_VERSION . '/' . $igUserId . '/media_publish',
            [
                'creation_id'  => $creationId,
                'access_token' => $token,
            ]
        );

        if (!$publishResponse->ok()) {
            throw new \RuntimeException('Instagram publish failed: ' . $publishResponse->json('error.message', ''));
        }

        $postId = $publishResponse->json('id');

        return [
            'platform_post_id' => $postId,
            'url'              => "https://www.instagram.com/p/{$postId}/",
        ];
    }

    public function testConnection(SocialAccount $account): bool
    {
        try {
            $response = Http::timeout(10)->get(
                self::BASE_URL . '/' . self::API_VERSION . '/' . $account->igUserId(),
                [
                    'fields'       => 'id,username',
                    'access_token' => $account->access_token,
                ]
            );
            return $response->ok();
        } catch (\Throwable) {
            return false;
        }
    }
}
