<?php

namespace App\Providers;

use App\Listeners\LogFailedJob;
use App\Modules\AI\Listeners\EmbedOnImport;
use Illuminate\Queue\Events\JobFailed;
use App\Modules\AI\Listeners\EmbedOnReconciliation;
use App\Modules\Competitors\Listeners\AnalyzeImportedCompetitors;
use App\Modules\Finance\Listeners\ExtractBankReferences;
use App\Modules\Imports\Events\ImportCompleted;
use App\Modules\Products\Listeners\AnalyzeImportedProducts;
use App\Modules\Reconciliation\Events\ReconciliationCompleted;
use App\Modules\Workspace\Models\Workspace;
use App\Observers\AuditObserver;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Telescope is a dev-only dependency (composer require-dev). Only register
        // it locally AND only if the package is actually installed, so a production
        // `--no-dev` build (where the class is absent) boots cleanly.
        if ($this->app->environment('local')
            && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
    }

    public function boot(): void
    {
        JsonResource::withoutWrapping();

        // Refuse to boot with debug mode on in production — it would leak stack
        // traces, env vars and provider API keys via the JSON exception handler.
        if ($this->app->environment('production') && config('app.debug')) {
            throw new \RuntimeException('APP_DEBUG must be false in production.');
        }

        // Behind a TLS terminator/proxy, force HTTPS so generated URLs and
        // secure session cookies are correct.
        if (! $this->app->environment('local')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        Workspace::observe(AuditObserver::class);

        Event::listen(ImportCompleted::class, ExtractBankReferences::class);
        Event::listen(ImportCompleted::class, AnalyzeImportedProducts::class);
        Event::listen(ImportCompleted::class, AnalyzeImportedCompetitors::class);
        Event::listen(ImportCompleted::class, EmbedOnImport::class);
        Event::listen(ReconciliationCompleted::class, EmbedOnReconciliation::class);

        // Log all failed queue jobs for debugging
        Event::listen(JobFailed::class, LogFailedJob::class);
    }
}
