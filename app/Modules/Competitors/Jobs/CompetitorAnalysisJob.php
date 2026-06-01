<?php

namespace App\Modules\Competitors\Jobs;

use App\Modules\Competitors\Models\Competitor;
use App\Modules\Competitors\Services\CompetitorAnalysisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CompetitorAnalysisJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;
    public int $tries   = 2;

    public function __construct(private readonly int $competitorId) {}

    public function handle(CompetitorAnalysisService $service): void
    {
        $competitor = Competitor::find($this->competitorId);

        if (!$competitor) {
            return;
        }

        $service->analyze($competitor);
    }
}
