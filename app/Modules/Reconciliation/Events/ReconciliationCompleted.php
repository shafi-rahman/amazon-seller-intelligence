<?php

namespace App\Modules\Reconciliation\Events;

use App\Modules\Reconciliation\Models\ReconciliationRun;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReconciliationCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly ReconciliationRun $run) {}
}
