<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id', 200)->nullable();
            $table->string('reviewer_name', 200)->nullable();
            $table->unsignedTinyInteger('rating'); // 1–5
            $table->text('title')->nullable();
            $table->text('body')->nullable();
            $table->boolean('verified_purchase')->default(false);
            $table->date('review_date')->nullable();
            $table->unsignedInteger('helpful_votes')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index('product_id');
            $table->index(['product_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
