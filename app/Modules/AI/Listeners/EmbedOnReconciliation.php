<?php

namespace App\Modules\AI\Listeners;

use App\Modules\AI\Jobs\EmbedDocumentJob;
use App\Modules\Reconciliation\Events\ReconciliationCompleted;
use App\Modules\Reconciliation\Models\ReconciliationReport;
use Illuminate\Contracts\Queue\ShouldQueue;

class EmbedOnReconciliation implements ShouldQueue
{
    public string $queue = 'embeddings';

    public function handle(ReconciliationCompleted $event): void
    {
        $run = $event->run;

        // Embed the summary report for AI financial context queries
        ReconciliationReport::where('reconciliation_run_id', $run->id)
            ->whereIn('report_type', ['summary', 'missing_settlements', 'missing_credits'])
            ->select('id')
            ->each(function ($report) use ($run) {
                EmbedDocumentJob::dispatch(ReconciliationReport::class, $report->id, $run->workspace_id)
                    ->onQueue('embeddings');
            });
    }
}
