<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keyword_gaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
            $table->string('keyword', 500);
            $table->string('gap_type', 20);
            // missing | underused | advantage
            $table->unsignedSmallInteger('our_frequency')->default(0);
            $table->unsignedSmallInteger('their_frequency')->default(0);
            $table->unsignedSmallInteger('priority_score')->default(0); // 0–95
            $table->timestamp('created_at')->useCurrent();

            $table->index('product_id');
            $table->index(['product_id', 'gap_type', 'priority_score']);
            $table->index('competitor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_gaps');
    }
};
