<?php

use App\Modules\Competitors\Controllers\CompetitorController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // Competitor list and detail for a product ({product} resolves by UUID)
    Route::get('workspaces/{workspaceId}/products/{product}/competitors',
        [CompetitorController::class, 'index']);

    Route::get('workspaces/{workspaceId}/products/{product}/competitors/{competitorId}',
        [CompetitorController::class, 'show']);

    // Add competitors to a product (paste HTML / upload CSV)
    Route::post('workspaces/{workspaceId}/products/{product}/competitors/html',
        [CompetitorController::class, 'addHtml']);
    Route::post('workspaces/{workspaceId}/products/{product}/competitors/csv',
        [CompetitorController::class, 'addCsv']);

    // Trigger analysis for / delete one competitor
    Route::post('workspaces/{workspaceId}/products/{product}/competitors/{competitorId}/analyze',
        [CompetitorController::class, 'analyze']);
    Route::delete('workspaces/{workspaceId}/products/{product}/competitors/{competitorId}',
        [CompetitorController::class, 'destroy']);

    // Keyword gaps for a product (across all competitors)
    Route::get('workspaces/{workspaceId}/products/{product}/keyword-gaps',
        [CompetitorController::class, 'keywordGaps']);

    // Benchmark comparison for a product vs all competitors
    Route::get('workspaces/{workspaceId}/products/{product}/benchmark',
        [CompetitorController::class, 'benchmark']);
});
