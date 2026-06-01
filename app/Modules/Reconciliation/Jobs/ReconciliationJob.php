<?php

namespace App\Modules\Reconciliation\Jobs;

use App\Modules\Reconciliation\Models\ReconciliationRun;
use App\Modules\Reconciliation\Services\ReconciliationEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReconciliationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;
    public int $tries   = 2;

    public function backoff(): array
    {
        return [60, 120]; // 1m, 2m between retries
    }

    public function __construct(private readonly int $runId) {}

    public function handle(ReconciliationEngine $engine): void
    {
        $run = ReconciliationRun::findOrFail($this->runId);

        if ($run->status !== 'pending') {
            return;
        }

        $engine->run($run);
    }
}
