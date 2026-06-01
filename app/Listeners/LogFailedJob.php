<?php

namespace App\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;

class LogFailedJob
{
    public function handle(JobFailed $event): void
    {
        Log::error('Queue job failed', [
            'connection'  => $event->connectionName,
            'queue'       => $event->job->getQueue(),
            'job_class'   => get_class($event->job->resolveName()),
            'payload'     => $event->job->payload(),
            'exception'   => $event->exception->getMessage(),
            'file'        => $event->exception->getFile(),
            'line'        => $event->exception->getLine(),
        ]);
    }
}
