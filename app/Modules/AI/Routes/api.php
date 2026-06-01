<?php

use App\Modules\AI\Controllers\CopilotController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // AI status (is provider configured, active model)
    Route::get('workspaces/{workspaceId}/ai/status',
        [CopilotController::class, 'status']);

    // Conversation management
    Route::get('workspaces/{workspaceId}/ai/conversations',
        [CopilotController::class, 'index']);

    Route::post('workspaces/{workspaceId}/ai/conversations',
        [CopilotController::class, 'store']);

    Route::get('workspaces/{workspaceId}/ai/conversations/{conversationId}',
        [CopilotController::class, 'show']);

    Route::delete('workspaces/{workspaceId}/ai/conversations/{conversationId}',
        [CopilotController::class, 'destroy']);

    // Send a message (rate-limited to 30/min per workspace)
    Route::post('workspaces/{workspaceId}/ai/conversations/{conversationId}/messages',
        [CopilotController::class, 'sendMessage']);
});
