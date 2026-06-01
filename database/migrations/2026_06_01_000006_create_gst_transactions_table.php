<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gst_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_type', 100)->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->string('order_id', 25)->nullable();
            $table->string('transaction_id', 100)->nullable();
            $table->string('asin', 20)->nullable();
            $table->string('sku', 200)->nullable();
            $table->text('product_name')->nullable();
            $table->unsignedSmallInteger('quantity')->nullable();
            $table->string('ship_from_state', 100)->nullable();
            $table->string('ship_to_state', 100)->nullable();
            $table->decimal('taxable_value', 12, 2)->nullable();
            $table->decimal('igst_rate', 5, 2)->nullable();
            $table->decimal('igst_amount', 12, 2)->nullable();
            $table->decimal('cgst_rate', 5, 2)->nullable();
            $table->decimal('cgst_amount', 12, 2)->nullable();
            $table->decimal('sgst_rate', 5, 2)->nullable();
            $table->decimal('sgst_amount', 12, 2)->nullable();
            $table->decimal('cess_rate', 5, 2)->nullable();
            $table->decimal('cess_amount', 12, 2)->nullable();
            $table->decimal('invoice_amount', 12, 2)->nullable();
            $table->string('irn', 200)->nullable();
            $table->string('hsn_sac', 20)->nullable();
            $table->jsonb('raw_row')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workspace_id', 'invoice_date']);
            $table->index(['workspace_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gst_transactions');
    }
};
