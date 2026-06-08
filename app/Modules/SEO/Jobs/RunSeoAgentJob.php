<?php

namespace App\Modules\SEO\Jobs;

use App\Modules\SEO\Models\SeoCampaign;
use App\Modules\SEO\Services\SeoContentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunSeoAgentJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;
    public int $tries   = 2;

    public function backoff(): array
    {
        return [30, 90];
    }

    public function __construct(private readonly int $campaignId) {}

    public function handle(SeoContentService $service): void
    {
        $campaign = SeoCampaign::with('product')->find($this->campaignId);

        if (!$campaign || $campaign->status === 'awaiting_approval') {
            return;
        }

        try {
            $service->generate($campaign);
        } catch (\Throwable $e) {
            Log::error('SEO agent job failed', [
                'campaign_id' => $this->campaignId,
                'error'       => $e->getMessage(),
            ]);
            $campaign->update(['status' => 'failed']);
            throw $e;
        }
    }
}
