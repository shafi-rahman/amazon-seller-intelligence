<?php

namespace App\Modules\SEO\Jobs;

use App\Modules\SEO\Models\SeoPost;
use App\Modules\Settings\Services\SocialPublisherService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PublishSeoPostJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;
    public int $tries   = 2;

    public function backoff(): array
    {
        return [30, 60];
    }

    public function __construct(private readonly int $postId) {}

    public function handle(SocialPublisherService $publisher): void
    {
        $post = SeoPost::with(['campaign.product'])->find($this->postId);

        if (!$post || $post->status === 'published') {
            return;
        }

        try {
            $publisher->publish($post);
        } catch (\Throwable $e) {
            Log::error('PublishSeoPostJob failed', [
                'post_id'  => $this->postId,
                'platform' => $post->platform,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
