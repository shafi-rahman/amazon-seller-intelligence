<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->date('transaction_date');
            $table->date('value_date')->nullable();
            $table->text('description')->nullable();
            $table->decimal('debit_amount', 14, 2)->default(0);
            $table->decimal('credit_amount', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->nullable();
            $table->string('reference', 500)->nullable();
            $table->string('bank_name', 200)->nullable();
            $table->jsonb('raw_row')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workspace_id', 'transaction_date']);
            $table->index(['workspace_id', 'credit_amount']);
        });

        // GIN index for fuzzy description search (used in reconciliation)
        \DB::statement('CREATE INDEX bank_transactions_desc_gin ON bank_transactions USING GIN (description gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
