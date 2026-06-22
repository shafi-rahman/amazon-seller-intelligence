<?php

namespace App\Modules\Settings\Services\Publishers;

use App\Modules\SEO\Models\SeoPost;
use App\Modules\Settings\Models\SocialAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleBusinessPublisher
{
    private const BASE_URL = 'https://mybusiness.googleapis.com/v4';

    public function publish(SeoPost $post, SocialAccount $account): array
    {
        $locationName = $account->locationName(); // e.g. "accounts/xxx/locations/xxx"
        $token        = $account->access_token;

        abort_if(!$locationName || !$token, 422, 'Google Business Location Name and access token are required');

        $text     = $post->edited_caption ?? $post->caption ?? '';
        // Google Business posts are short — use just the main caption
        $postText = mb_substr($text, 0, 1500);

        $payload = [
            'languageCode' => 'en-IN',
            'summary'      => $postText,
            'callToAction' => [
                'actionType' => 'ORDER',
                'url'        => 'https://www.amazon.in/s?k=' . urlencode($post->campaign->product->asin ?? ''),
            ],
            'topicType' => 'STANDARD',
        ];

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Content-Type'  => 'application/json',
        ])->timeout(30)->post(self::BASE_URL . "/{$locationName}/localPosts", $payload);

        if (!$response->ok()) {
            Log::error('Google Business publish failed', ['status' => $response->status(), 'body' => substr($response->body(), 0, 500)]);
            throw new \RuntimeException('Google Business API error: ' . $response->json('error.message', $response->body()));
        }

        $postName = $response->json('name', '');

        return [
            'platform_post_id' => $postName,
            'url'              => 'https://business.google.com/',
        ];
    }

    public function testConnection(SocialAccount $account): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$account->access_token}",
            ])->timeout(10)->get('https://mybusinessaccountmanagement.googleapis.com/v1/accounts');
            return $response->ok();
        } catch (\Throwable) {
            return false;
        }
    }
}
