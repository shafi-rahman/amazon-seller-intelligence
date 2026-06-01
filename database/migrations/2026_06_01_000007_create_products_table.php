<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('asin', 20);
            $table->string('sku', 200)->nullable();
            $table->text('title')->nullable();
            $table->string('brand', 500)->nullable();
            $table->string('category', 500)->nullable();
            $table->string('sub_category', 500)->nullable();
            $table->text('bullet_1')->nullable();
            $table->text('bullet_2')->nullable();
            $table->text('bullet_3')->nullable();
            $table->text('bullet_4')->nullable();
            $table->text('bullet_5')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 3)->default('INR');
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedSmallInteger('listing_score')->nullable(); // 0–100, computed in Sprint 5
            $table->string('source_type', 20)->default('csv'); // csv | html
            $table->timestamp('last_analyzed_at')->nullable();
            $table->jsonb('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'asin']);
            $table->index(['workspace_id', 'listing_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
