<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            // orders | settlements | bank_statement | gst_report
            // products | competitors_csv | competitors_html
            $table->string('original_filename', 500)->nullable();
            $table->string('storage_path', 1000)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('status', 20)->default('pending');
            // pending | detecting | awaiting_mapping | processing | completed | failed | partial
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->jsonb('column_mapping')->nullable();
            $table->jsonb('meta')->default('{}');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
