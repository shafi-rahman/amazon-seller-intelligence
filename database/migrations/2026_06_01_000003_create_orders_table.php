<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->string('amazon_order_id', 25);
            $table->string('merchant_order_id', 100)->nullable();
            $table->timestampTz('purchase_date');
            $table->timestampTz('last_updated_date')->nullable();
            $table->string('order_status', 50);
            $table->string('fulfillment_channel', 10)->nullable(); // AFN | MFN
            $table->string('sales_channel', 100)->nullable();
            $table->string('ship_service_level', 100)->nullable();
            $table->string('sku', 200)->nullable();
            $table->string('asin', 20)->nullable();
            $table->text('product_name')->nullable();
            $table->string('item_status', 50)->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('currency', 3)->default('INR');
            $table->decimal('item_price', 12, 2)->default(0);
            $table->decimal('item_tax', 12, 2)->default(0);
            $table->decimal('shipping_price', 12, 2)->default(0);
            $table->decimal('shipping_tax', 12, 2)->default(0);
            $table->decimal('gift_wrap_price', 12, 2)->default(0);
            $table->decimal('gift_wrap_tax', 12, 2)->default(0);
            $table->decimal('item_promotion_discount', 12, 2)->default(0);
            $table->decimal('ship_promotion_discount', 12, 2)->default(0);
            $table->string('ship_city', 200)->nullable();
            $table->string('ship_state', 100)->nullable();
            $table->string('ship_postal_code', 20)->nullable();
            $table->string('ship_country', 10)->nullable();
            $table->boolean('is_business_order')->default(false);
            $table->jsonb('raw_row')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['workspace_id', 'amazon_order_id', 'sku'], 'orders_workspace_order_sku_unique');
            $table->index(['workspace_id', 'purchase_date']);
            $table->index(['workspace_id', 'order_status']);
            $table->index('asin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
