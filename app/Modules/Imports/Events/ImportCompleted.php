<?php

namespace App\Modules\Imports\Events;

use App\Modules\Imports\Models\ImportBatch;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImportCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly ImportBatch $batch) {}
}
