<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('keyword', 500);
            $table->string('source', 30);
            // title | bullet | description
            $table->unsignedSmallInteger('frequency')->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->index('product_id');
            $table->index('keyword');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_keywords');
    }
};
