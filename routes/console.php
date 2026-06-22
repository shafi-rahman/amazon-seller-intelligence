<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Scheduled maintenance (run by the `scheduler` container) ───────────────

// Expire stale Sanctum personal-access tokens.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Keep Horizon dashboard metrics fresh.
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Telescope is local-only; prune there so telescope_entries doesn't grow unbounded.
if (app()->environment('local') && class_exists(\Laravel\Telescope\Telescope::class)) {
    Schedule::command('telescope:prune --hours=48')->daily();
}
