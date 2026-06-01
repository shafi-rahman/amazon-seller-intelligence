<?php

use App\Modules\Workspace\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('workspaces', WorkspaceController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('workspaces/{workspace}/members',         [WorkspaceController::class, 'inviteMember']);
    Route::delete('workspaces/{workspace}/members/{user}',[WorkspaceController::class, 'removeMember']);
});
