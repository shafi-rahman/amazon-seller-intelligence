<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->jsonb('raw_data')->nullable();
            $table->string('error_type', 100);
            // missing_required | invalid_format | duplicate | parse_error
            $table->text('error_message');
            $table->timestamp('created_at')->useCurrent();

            $table->index('import_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_errors');
    }
};
