<?php

namespace App\Providers;

use App\Modules\AI\Listeners\EmbedOnImport;
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
    public function register(): void {}

    public function boot(): void
    {
        JsonResource::withoutWrapping();

        Workspace::observe(AuditObserver::class);

        Event::listen(ImportCompleted::class, ExtractBankReferences::class);
        Event::listen(ImportCompleted::class, AnalyzeImportedProducts::class);
        Event::listen(ImportCompleted::class, AnalyzeImportedCompetitors::class);
        Event::listen(ImportCompleted::class, EmbedOnImport::class);
        Event::listen(ReconciliationCompleted::class, EmbedOnReconciliation::class);
    }
}
