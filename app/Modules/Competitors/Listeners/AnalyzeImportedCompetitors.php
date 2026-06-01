<?php

namespace App\Modules\Competitors\Listeners;

use App\Modules\Competitors\Jobs\CompetitorAnalysisJob;
use App\Modules\Competitors\Models\Competitor;
use App\Modules\Imports\Events\ImportCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;

class AnalyzeImportedCompetitors implements ShouldQueue
{
    public string $queue = 'ai';

    public function handle(ImportCompleted $event): void
    {
        if (!in_array($event->batch->type, ['competitors_csv', 'competitors_html'])) {
            return;
        }

        Competitor::where('import_batch_id', $event->batch->id)
            ->select('id')
            ->chunkById(50, function ($competitors) {
                foreach ($competitors as $c) {
                    CompetitorAnalysisJob::dispatch($c->id)->onQueue('ai');
                }
            });
    }
}
