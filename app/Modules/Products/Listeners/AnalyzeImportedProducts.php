<?php

namespace App\Modules\Products\Listeners;

use App\Modules\Imports\Events\ImportCompleted;
use App\Modules\Products\Jobs\AnalyzeProductJob;
use App\Modules\Products\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;

class AnalyzeImportedProducts implements ShouldQueue
{
    public string $queue = 'ai';

    public function handle(ImportCompleted $event): void
    {
        if ($event->batch->type !== 'products') {
            return;
        }

        // Dispatch one analysis job per product in this import batch
        Product::where('import_batch_id', $event->batch->id)
            ->select('id')
            ->chunkById(100, function ($products) {
                foreach ($products as $product) {
                    AnalyzeProductJob::dispatch($product->id)->onQueue('ai');
                }
            });
    }
}
