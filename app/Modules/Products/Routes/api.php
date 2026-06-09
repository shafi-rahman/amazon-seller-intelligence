<?php

use App\Modules\Products\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    Route::get('workspaces/{workspaceId}/products',                          [ProductController::class, 'index']);
    Route::get('workspaces/{workspaceId}/products/{product}',                [ProductController::class, 'show']);
    Route::post('workspaces/{workspaceId}/products/{product}/analyze',       [ProductController::class, 'analyze']);
    Route::post('workspaces/{workspaceId}/products/{product}/rewrite',       [ProductController::class, 'rewrite']);
    Route::post('workspaces/{workspaceId}/products/{product}/apply-rewrite', [ProductController::class, 'applyRewrite']);

    // Product image gallery — multiple images
    Route::get('workspaces/{workspaceId}/products/{product}/images',                   [ProductController::class, 'listImages']);
    Route::post('workspaces/{workspaceId}/products/{product}/images',                  [ProductController::class, 'uploadImages']);
    Route::delete('workspaces/{workspaceId}/products/{product}/images/{imageId}',      [ProductController::class, 'deleteProductImage']);
    Route::put('workspaces/{workspaceId}/products/{product}/images/{imageId}/primary', [ProductController::class, 'setPrimaryImage']);
    Route::put('workspaces/{workspaceId}/products/{product}/images/reorder',           [ProductController::class, 'reorderImages']);
});
