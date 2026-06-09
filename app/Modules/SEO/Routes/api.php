<?php

use App\Modules\SEO\Controllers\SeoCampaignController;
use Illuminate\Support\Facades\Route;

// Authenticated routes (user session)
Route::middleware('auth:sanctum')->group(function () {

    // Tag a product for SEO → creates campaign + dispatches job
    Route::post('workspaces/{workspaceId}/products/{productId}/seo/tag',
        [SeoCampaignController::class, 'tag']);

    // List all SEO campaigns for a workspace
    Route::get('workspaces/{workspaceId}/seo/campaigns',
        [SeoCampaignController::class, 'index']);

    // View a single campaign with all posts
    Route::get('workspaces/{workspaceId}/seo/campaigns/{id}',
        [SeoCampaignController::class, 'show']);

    // Post-level approval actions
    Route::post('seo/posts/{postId}/approve', [SeoCampaignController::class, 'approvePost']);
    Route::post('seo/posts/{postId}/reject',  [SeoCampaignController::class, 'rejectPost']);

    // Post-level content + image editing
    Route::put('seo/posts/{postId}',                 [SeoCampaignController::class, 'updatePost']);
    Route::post('seo/posts/{postId}/image/upload',   [SeoCampaignController::class, 'uploadPostImage']);
    Route::post('seo/posts/{postId}/image/generate', [SeoCampaignController::class, 'regeneratePostImage']);
});

// Token-secured endpoints for OpenClaw skill (no session needed)
Route::prefix('seo')->group(function () {
    Route::get('campaigns/{id}/product-data', [SeoCampaignController::class, 'productData']);
    Route::post('webhook/notify',             [SeoCampaignController::class, 'webhookNotify']);
});
