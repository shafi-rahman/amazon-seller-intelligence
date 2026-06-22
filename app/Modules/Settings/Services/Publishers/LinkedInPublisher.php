<?php

namespace App\Modules\Settings\Services\Publishers;

use App\Modules\SEO\Models\SeoPost;
use App\Modules\Settings\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinkedInPublisher
{
    private const BASE_URL = 'https://api.linkedin.com/v2';

    public function publish(SeoPost $post, SocialAccount $account): array
    {
        $authorUrn = $account->linkedInAuthor();
        $token     = $account->access_token;

        abort_if(!$authorUrn || !$token, 422, 'LinkedIn Author URN and access token are required');

        $caption = ($post->edited_caption ?? $post->caption ?? '') .
                   ($post->hashtags ? "\n\n" . $post->hashtags : '');

        $payload = [
            'author'          => $authorUrn,
            'lifecycleState'  => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary'  => ['text' => $caption],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ];

        $response = Http::withHeaders([
            'Authorization'     => "Bearer {$token}",
            'Content-Type'      => 'application/json',
            'X-Restli-Protocol-Version' => '2.0.0',
        ])->timeout(30)->post(self::BASE_URL . '/ugcPosts', $payload);

        if (!$response->ok()) {
            Log::error('LinkedIn publish failed', ['status' => $response->status(), 'body' => substr($response->body(), 0, 500)]);
            throw new \RuntimeException('LinkedIn API error: ' . $response->json('message', $response->body()));
        }

        $postUrn = $response->header('X-RestLi-Id') ?? $response->json('id', '');

        return [
            'platform_post_id' => $postUrn,
            'url'              => 'https://www.linkedin.com/feed/',
        ];
    }

    public function testConnection(SocialAccount $account): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$account->access_token}",
            ])->timeout(10)->get(self::BASE_URL . '/me');
            return $response->ok();
        } catch (\Throwable) {
            return false;
        }
    }
}
