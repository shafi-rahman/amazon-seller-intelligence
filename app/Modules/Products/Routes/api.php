<?php

use App\Modules\Products\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    Route::get('workspaces/{workspaceId}/products',                          [ProductController::class, 'index']);
    Route::get('workspaces/{workspaceId}/products/{product}',                [ProductController::class, 'show']);
    Route::post('workspaces/{workspaceId}/products/{product}/analyze',       [ProductController::class, 'analyze']);
    Route::post('workspaces/{workspaceId}/products/{product}/rewrite',       [ProductController::class, 'rewrite']);
    Route::post('workspaces/{workspaceId}/products/{product}/apply-rewrite', [ProductController::class, 'applyRewrite']);

    // Product image management
    Route::post('workspaces/{workspaceId}/products/{product}/image',     [ProductController::class, 'uploadImage']);
    Route::delete('workspaces/{workspaceId}/products/{product}/image',   [ProductController::class, 'deleteImage']);
    Route::get('workspaces/{workspaceId}/products/{product}/image-url',  [ProductController::class, 'imageUrl']);
});
