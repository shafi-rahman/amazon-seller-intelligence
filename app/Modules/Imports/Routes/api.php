<?php

use App\Modules\Imports\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // File CSV upload
    Route::post('imports/upload',                        [ImportController::class, 'upload']);

    // Competitor HTML paste
    Route::post('imports/competitors/html',              [ImportController::class, 'uploadHtml']);

    // Confirm column mapping → dispatches ProcessImportJob
    Route::post('imports/{importBatch}/confirm-mapping', [ImportController::class, 'confirmMapping']);

    // Status polling (frontend polls every 3s while status=processing)
    Route::get('imports/{importBatch}/status',           [ImportController::class, 'status']);

    // Error log for a completed/partial batch
    Route::get('imports/{importBatch}/errors',           [ImportController::class, 'errors']);

    // Import history per workspace
    Route::get('workspaces/{workspaceId}/imports',       [ImportController::class, 'index']);
});
