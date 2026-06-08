<?php

use App\Modules\Settings\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // Social accounts
    Route::get('workspaces/{workspaceId}/settings/social-accounts',
        [SettingsController::class, 'socialAccounts']);

    Route::put('workspaces/{workspaceId}/settings/social-accounts/{platform}',
        [SettingsController::class, 'updateSocialAccount']);

    Route::post('workspaces/{workspaceId}/settings/social-accounts/{platform}/test',
        [SettingsController::class, 'testSocialAccount']);

    Route::delete('workspaces/{workspaceId}/settings/social-accounts/{platform}',
        [SettingsController::class, 'disconnectSocialAccount']);

    // AI keys
    Route::get('workspaces/{workspaceId}/settings/ai-keys',
        [SettingsController::class, 'aiKeys']);

    Route::put('workspaces/{workspaceId}/settings/ai-keys',
        [SettingsController::class, 'updateAiKeys']);

    // Notifications
    Route::get('workspaces/{workspaceId}/settings/notifications',
        [SettingsController::class, 'notifications']);

    Route::post('workspaces/{workspaceId}/settings/notifications/regenerate-token',
        [SettingsController::class, 'regenerateToken']);

    // Publish SEO post to social media
    Route::post('seo/posts/{postId}/publish',
        [SettingsController::class, 'publishPost']);
});
