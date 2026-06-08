<?php

namespace App\Modules\Settings\Services;

use App\Modules\SEO\Models\SeoPost;
use App\Modules\Settings\Models\SocialAccount;
use App\Modules\Settings\Services\Publishers\FacebookPublisher;
use App\Modules\Settings\Services\Publishers\GoogleBusinessPublisher;
use App\Modules\Settings\Services\Publishers\InstagramPublisher;
use App\Modules\Settings\Services\Publishers\LinkedInPublisher;
use Illuminate\Support\Facades\Log;

class SocialPublisherService
{
    public function __construct(
        private readonly FacebookPublisher       $facebook,
        private readonly InstagramPublisher      $instagram,
        private readonly LinkedInPublisher       $linkedin,
        private readonly GoogleBusinessPublisher $google,
    ) {}

    /**
     * Publish a single SeoPost to its platform.
     * Updates the post status and stores the platform post ID.
     */
    public function publish(SeoPost $post): void
    {
        $account = SocialAccount::where('workspace_id', $post->campaign->workspace_id)
            ->where('platform', $post->platform)
            ->where('is_connected', true)
            ->where('is_active', true)
            ->first();

        if (!$account) {
            $post->update([
                'status' => 'failed',
            ]);
            throw new \RuntimeException("No connected {$post->platform} account. Configure it in Settings → Social Accounts.");
        }

        try {
            $result = match ($post->platform) {
                'facebook'        => $this->facebook->publish($post, $account),
                'instagram'       => $this->instagram->publish($post, $account),
                'linkedin'        => $this->linkedin->publish($post, $account),
                'google_business' => $this->google->publish($post, $account),
                default           => throw new \InvalidArgumentException("Unknown platform: {$post->platform}"),
            };

            $post->update([
                'status'           => 'published',
                'platform_post_id' => $result['platform_post_id'] ?? null,
                'published_at'     => now(),
            ]);

            Log::info("SEO post published to {$post->platform}", [
                'post_id'          => $post->id,
                'platform_post_id' => $result['platform_post_id'],
            ]);

        } catch (\Throwable $e) {
            $post->update(['status' => 'failed']);
            throw $e;
        }
    }

    public function testConnection(SocialAccount $account): bool
    {
        return match ($account->platform) {
            'facebook'        => $this->facebook->testConnection($account),
            'instagram'       => $this->instagram->testConnection($account),
            'linkedin'        => $this->linkedin->testConnection($account),
            'google_business' => $this->google->testConnection($account),
            default           => false,
        };
    }
}
