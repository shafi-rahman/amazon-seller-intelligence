<?php

namespace App\Modules\Finance\Listeners;

use App\Modules\Finance\Services\UtrExtractorService;
use App\Modules\Imports\Events\ImportCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;

class ExtractBankReferences implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private readonly UtrExtractorService $extractor) {}

    public function handle(ImportCompleted $event): void
    {
        if ($event->batch->type !== 'bank_statement') {
            return;
        }

        $this->extractor->processImportBatch($event->batch);
    }
}
