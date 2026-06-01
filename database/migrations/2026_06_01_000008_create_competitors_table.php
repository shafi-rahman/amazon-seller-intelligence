<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('asin', 20);
            $table->text('title')->nullable();
            $table->string('brand', 500)->nullable();
            $table->string('category', 500)->nullable();
            $table->text('bullet_1')->nullable();
            $table->text('bullet_2')->nullable();
            $table->text('bullet_3')->nullable();
            $table->text('bullet_4')->nullable();
            $table->text('bullet_5')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 3)->default('INR');
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('review_count')->nullable();
            $table->string('source_type', 20)->default('html'); // csv | html
            $table->text('raw_html')->nullable();
            $table->jsonb('parse_confidence')->nullable(); // {field: score} per extracted field
            $table->timestamp('last_analyzed_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'product_id', 'asin'], 'competitors_workspace_product_asin_unique');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitors');
    }
};
