<?php

use App\Modules\Competitors\Controllers\CompetitorController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // Competitor list and detail for a product
    Route::get('workspaces/{workspaceId}/products/{productId}/competitors',
        [CompetitorController::class, 'index']);

    Route::get('workspaces/{workspaceId}/products/{productId}/competitors/{competitorId}',
        [CompetitorController::class, 'show']);

    // Trigger analysis for one competitor
    Route::post('workspaces/{workspaceId}/products/{productId}/competitors/{competitorId}/analyze',
        [CompetitorController::class, 'analyze']);

    // Keyword gaps for a product (across all competitors)
    Route::get('workspaces/{workspaceId}/products/{productId}/keyword-gaps',
        [CompetitorController::class, 'keywordGaps']);

    // Benchmark comparison for a product vs all competitors
    Route::get('workspaces/{workspaceId}/products/{productId}/benchmark',
        [CompetitorController::class, 'benchmark']);
});
