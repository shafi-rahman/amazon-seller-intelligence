<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitor_benchmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
            $table->jsonb('benchmark_data');
            $table->timestamp('created_at')->useCurrent();

            $table->index('product_id');
            $table->unique(['product_id', 'competitor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_benchmarks');
    }
};
