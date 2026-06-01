<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->string('settlement_id', 100);
            $table->date('settlement_start_date');
            $table->date('settlement_end_date');
            $table->date('deposit_date')->nullable();
            $table->decimal('deposited_amount', 14, 2)->nullable();
            $table->string('currency', 3)->default('INR');
            $table->string('transaction_type', 100)->nullable();
            $table->string('order_id', 25)->nullable();
            $table->string('merchant_order_id', 100)->nullable();
            $table->string('adjustment_id', 100)->nullable();
            $table->string('shipment_id', 100)->nullable();
            $table->string('marketplace_name', 200)->nullable();
            $table->string('amount_type', 100)->nullable();
            $table->string('amount_description', 200)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('fulfillment_id', 100)->nullable();
            $table->date('posted_date')->nullable();
            $table->timestampTz('posted_datetime')->nullable();
            $table->string('sku', 200)->nullable();
            $table->unsignedSmallInteger('quantity_purchased')->nullable();
            $table->jsonb('raw_row')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workspace_id', 'settlement_id']);
            $table->index(['workspace_id', 'order_id']);
            $table->index(['workspace_id', 'deposit_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};
