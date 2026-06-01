<?php

use App\Modules\Reconciliation\Controllers\ReconciliationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // Run new reconciliation
    Route::post('workspaces/{workspaceId}/reconciliation/run',   [ReconciliationController::class, 'run']);

    // List past runs
    Route::get('workspaces/{workspaceId}/reconciliation',        [ReconciliationController::class, 'index']);

    // Run detail (with all report type summaries)
    Route::get('workspaces/{workspaceId}/reconciliation/{runId}',[ReconciliationController::class, 'show']);

    // Status polling (every 3s while running)
    Route::get('workspaces/{workspaceId}/reconciliation/{runId}/status', [ReconciliationController::class, 'status']);

    // View a specific report type (paginated rows)
    Route::get('workspaces/{workspaceId}/reconciliation/{runId}/reports/{type}', [ReconciliationController::class, 'report']);

    // Request export (async, queued)
    Route::post('workspaces/{workspaceId}/reconciliation/{runId}/reports/{type}/export', [ReconciliationController::class, 'export']);

    // Download presigned URL (after export is ready)
    Route::get('workspaces/{workspaceId}/reconciliation/reports/{reportId}/download', [ReconciliationController::class, 'download']);
});
