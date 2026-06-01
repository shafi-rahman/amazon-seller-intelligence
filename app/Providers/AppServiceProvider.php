<?php

namespace App\Providers;

use App\Modules\Workspace\Models\Workspace;
use App\Observers\AuditObserver;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        JsonResource::withoutWrapping();

        Workspace::observe(AuditObserver::class);
    }
}
