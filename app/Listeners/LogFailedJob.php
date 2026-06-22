<?php

namespace App\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;

class LogFailedJob
{
    public function handle(JobFailed $event): void
    {
        // Log only safe identifiers — NOT $job->payload(), which serializes the
        // whole job (imported rows, AI prompts, tokens) into the log.
        $payload = $event->job->payload();
        Log::error('Queue job failed', [
            'connection'  => $event->connectionName,
            'queue'       => $event->job->getQueue(),
            'job_class'   => $event->job->resolveName(), // already the class name string
            'job_uuid'    => $payload['uuid'] ?? null,
            'attempts'    => $event->job->attempts(),
            'exception'   => $event->exception->getMessage(),
            'file'        => $event->exception->getFile(),
            'line'        => $event->exception->getLine(),
        ]);
    }
}
