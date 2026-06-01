<?php

use App\Modules\Reports\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // Available report types (for the UI to enumerate)
    Route::get('workspaces/{workspaceId}/reports/types',
        [ReportController::class, 'types']);

    // Report list + create
    Route::get('workspaces/{workspaceId}/reports',
        [ReportController::class, 'index']);

    Route::post('workspaces/{workspaceId}/reports',
        [ReportController::class, 'store']);

    // Show single report (for status polling)
    Route::get('workspaces/{workspaceId}/reports/{reportId}',
        [ReportController::class, 'show']);

    // Download presigned URL (only when status=completed)
    Route::get('workspaces/{workspaceId}/reports/{reportId}/download',
        [ReportController::class, 'download']);
});
