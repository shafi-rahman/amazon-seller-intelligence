<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reconciliation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('settlement_id')->nullable()->constrained('settlements')->nullOnDelete();
            $table->foreignId('bank_transaction_id')->nullable()->constrained('bank_transactions')->nullOnDelete();
            $table->string('match_type', 30);
            // exact | fuzzy_refund | settlement_bank_exact | settlement_bank_tds | unmatched_order | unmatched_settlement
            $table->decimal('match_confidence', 5, 2)->nullable();
            $table->string('status', 20);
            // matched | partial | unmatched
            $table->decimal('mismatch_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('reconciliation_run_id');
            $table->index(['reconciliation_run_id', 'match_type']);
            $table->index('order_id');
            $table->index('settlement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_matches');
    }
};
