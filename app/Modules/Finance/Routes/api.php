<?php

use App\Modules\Finance\Controllers\BankTransactionController;
use App\Modules\Finance\Controllers\FinanceDashboardController;
use App\Modules\Finance\Controllers\GstTransactionController;
use App\Modules\Finance\Controllers\OrderController;
use App\Modules\Finance\Controllers\SettlementController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // Financial dashboard
    Route::get('workspaces/{workspaceId}/finance/dashboard', [FinanceDashboardController::class, 'index']);

    // Orders
    Route::get('workspaces/{workspaceId}/orders',         [OrderController::class, 'index']);
    Route::get('workspaces/{workspaceId}/orders/summary', [OrderController::class, 'summary']);

    // Settlements
    Route::get('workspaces/{workspaceId}/settlements',         [SettlementController::class, 'index']);
    Route::get('workspaces/{workspaceId}/settlements/summary', [SettlementController::class, 'summary']);

    // Bank Transactions
    Route::get('workspaces/{workspaceId}/bank-transactions',         [BankTransactionController::class, 'index']);
    Route::get('workspaces/{workspaceId}/bank-transactions/summary', [BankTransactionController::class, 'summary']);

    // GST Transactions
    Route::get('workspaces/{workspaceId}/gst-transactions',         [GstTransactionController::class, 'index']);
    Route::get('workspaces/{workspaceId}/gst-transactions/summary', [GstTransactionController::class, 'summary']);
});
