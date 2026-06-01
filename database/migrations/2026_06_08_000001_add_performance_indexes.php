<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = [
            // Orders: date-range queries in reconciliation and finance views
            'idx_orders_workspace_date'           => 'CREATE INDEX IF NOT EXISTS idx_orders_workspace_date ON orders (workspace_id, purchase_date DESC)',
            // Settlements: deposit-date range for bank matching Pass C/D
            'idx_settlements_workspace_deposit'   => 'CREATE INDEX IF NOT EXISTS idx_settlements_workspace_deposit ON settlements (workspace_id, deposit_date)',
            // Bank transactions: partial index for credit-only rows (reconciliation)
            'idx_bank_credit_amount'              => 'CREATE INDEX IF NOT EXISTS idx_bank_credit_amount ON bank_transactions (workspace_id, credit_amount) WHERE credit_amount > 0',
            // Reconciliation matches: fast order lookup within a run
            'idx_recon_matches_run_order'         => 'CREATE INDEX IF NOT EXISTS idx_recon_matches_run_order ON reconciliation_matches (reconciliation_run_id, order_id) WHERE order_id IS NOT NULL',
            // Reconciliation matches: settlement-bank match lookup
            'idx_recon_matches_run_settlement'    => 'CREATE INDEX IF NOT EXISTS idx_recon_matches_run_settlement ON reconciliation_matches (reconciliation_run_id, settlement_id) WHERE settlement_id IS NOT NULL',
            // AI conversations: newest-first listing per workspace/user
            'idx_ai_conversations_updated'        => 'CREATE INDEX IF NOT EXISTS idx_ai_conversations_updated ON ai_conversations (workspace_id, user_id, updated_at DESC)',
            // Reports: status filter for polling endpoint
            'idx_reports_workspace_status'        => 'CREATE INDEX IF NOT EXISTS idx_reports_workspace_status ON reports (workspace_id, status)',
            // Product analyses: latest-per-type lookup
            'idx_product_analyses_type_date'      => 'CREATE INDEX IF NOT EXISTS idx_product_analyses_type_date ON product_analyses (product_id, analysis_type, created_at DESC)',
        ];

        foreach ($indexes as $sql) {
            DB::statement($sql);
        }
    }

    public function down(): void
    {
        $drops = [
            'idx_orders_workspace_date',
            'idx_settlements_workspace_deposit',
            'idx_bank_credit_amount',
            'idx_recon_matches_run_order',
            'idx_recon_matches_run_settlement',
            'idx_ai_conversations_updated',
            'idx_reports_workspace_status',
            'idx_product_analyses_type_date',
        ];
        foreach ($drops as $index) {
            DB::statement("DROP INDEX IF EXISTS {$index}");
        }
    }
};
