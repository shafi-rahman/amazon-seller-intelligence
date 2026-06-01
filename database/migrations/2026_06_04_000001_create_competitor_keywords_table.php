<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitor_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competitor_id')->constrained()->cascadeOnDelete();
            $table->string('keyword', 500);
            $table->string('source', 30); // title | bullet | description
            $table->unsignedSmallInteger('frequency')->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->index('competitor_id');
            $table->index('keyword');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_keywords');
    }
};
