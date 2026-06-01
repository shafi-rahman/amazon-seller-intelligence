<?php

namespace App\Modules\AI\Listeners;

use App\Modules\AI\Jobs\EmbedDocumentJob;
use App\Modules\Competitors\Models\Competitor;
use App\Modules\Imports\Events\ImportCompleted;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductReview;
use Illuminate\Contracts\Queue\ShouldQueue;

class EmbedOnImport implements ShouldQueue
{
    public string $queue = 'embeddings';

    public function handle(ImportCompleted $event): void
    {
        $batch = $event->batch;

        match ($batch->type) {
            'products'         => $this->embedProducts($batch->id, $batch->workspace_id),
            'competitors_csv',
            'competitors_html' => $this->embedCompetitors($batch->id, $batch->workspace_id),
            default            => null,
        };
    }

    private function embedProducts(int $batchId, int $workspaceId): void
    {
        Product::where('import_batch_id', $batchId)
            ->select('id')
            ->chunkById(100, function ($products) use ($workspaceId) {
                foreach ($products as $p) {
                    EmbedDocumentJob::dispatch(Product::class, $p->id, $workspaceId)
                        ->onQueue('embeddings');
                }
            });
    }

    private function embedCompetitors(int $batchId, int $workspaceId): void
    {
        Competitor::where('import_batch_id', $batchId)
            ->select('id')
            ->chunkById(50, function ($comps) use ($workspaceId) {
                foreach ($comps as $c) {
                    EmbedDocumentJob::dispatch(Competitor::class, $c->id, $workspaceId)
                        ->onQueue('embeddings');
                }
            });
    }
}
